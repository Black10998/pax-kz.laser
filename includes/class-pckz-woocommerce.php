<?php
/**
 * WooCommerce integration — customer order + production handoff.
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PCKZ_WooCommerce
 */
class PCKZ_WooCommerce {

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( ! PCKZ_Settings::get( 'enable_woocommerce', true ) ) {
			return;
		}

		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'preserve_cart_item_data' ), 10, 3 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'display_cart_item_data' ), 10, 2 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply_admin_pricing_to_cart' ), 20, 1 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_order_item_meta' ), 10, 4 );
		add_action( 'add_meta_boxes', array( $this, 'order_design_metabox' ) );
		add_action( 'woocommerce_email_after_order_table', array( $this, 'email_production_summary' ), 10, 4 );
	}

	/**
	 * Preserve custom cart data.
	 *
	 * @param array $cart_item_data Cart item data.
	 * @param int   $product_id     Product ID.
	 * @param int   $variation_id   Variation ID.
	 * @return array
	 */
	public function preserve_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
		$key = PCKZ_Settings::get( 'cart_meta_key', '_pckz_design' );
		if ( ! empty( $_POST['pckz_design_id'] ) ) {
			$design_id = absint( $_POST['pckz_design_id'] );
			$cart_item_data[ $key ] = $this->pack_design_for_cart( $design_id );
		}
		return $cart_item_data;
	}

	/**
	 * Build cart payload from saved design.
	 *
	 * @param int $design_id Design ID.
	 * @return array
	 */
	private function pack_design_for_cart( $design_id ) {
		$design = PCKZ_Design_Storage::get_design( $design_id );
		if ( ! $design ) {
			return array();
		}
		$meta = array();
		if ( ! empty( $design['meta_json'] ) ) {
			$meta = json_decode( $design['meta_json'], true );
		}
		return array(
			'design_id'   => $design_id,
			'preview_url' => $design['preview_url'] ?? '',
			'export_url'  => $design['export_url'] ?? '',
			'product_id'  => $design['product_id'] ?? 0,
			'selections'  => $meta['selections'] ?? array(),
			'production'  => $meta['production'] ?? array(),
		);
	}

	/**
	 * Override WooCommerce line price with admin configurator pricing.
	 *
	 * @param WC_Cart $cart Cart.
	 */
	public function apply_admin_pricing_to_cart( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}
		if ( ! class_exists( 'PCKZ_Commerce' ) ) {
			return;
		}
		$key = PCKZ_Settings::get( 'cart_meta_key', '_pckz_design' );
		foreach ( $cart->get_cart() as $cart_item ) {
			if ( empty( $cart_item[ $key ]['product_id'] ) ) {
				continue;
			}
			$creator_id = (int) $cart_item[ $key ]['product_id'];
			$currency   = ! empty( $cart_item['pckz_currency'] )
				? PCKZ_Commerce::sanitize_currency_code( $cart_item['pckz_currency'] )
				: PCKZ_Commerce::get_default_currency_code();
			$unit       = PCKZ_Commerce::get_unit_price( $currency );
			if ( $unit > 0 && isset( $cart_item['data'] ) && is_object( $cart_item['data'] ) ) {
				$cart_item['data']->set_price( $unit );
			}
			unset( $creator_id );
		}
	}

	/**
	 * Display customer selections in cart / checkout.
	 *
	 * @param array $item_data Item data rows.
	 * @param array $cart_item Cart item.
	 * @return array
	 */
	public function display_cart_item_data( $item_data, $cart_item ) {
		$key = PCKZ_Settings::get( 'cart_meta_key', '_pckz_design' );
		if ( empty( $cart_item[ $key ] ) ) {
			return $item_data;
		}

		$design = $cart_item[ $key ];
		$config = PCKZ_Post_Type::get_product_config( (int) ( $design['product_id'] ?? 0 ) );

		if ( ! empty( $design['selections'] ) && is_array( $design['selections'] ) ) {
			foreach ( $design['selections'] as $id => $value ) {
				$item_data[] = array(
					'key'   => PCKZ_Production::label_for_option_public( $id, $config ),
					'value' => esc_html( is_array( $value ) ? wp_json_encode( $value ) : (string) $value ),
				);
			}
		}

		$item_data[] = array(
			'key'   => __( 'Design preview', 'pckz-canonical-engine' ),
			'value' => ! empty( $design['preview_url'] )
				? esc_url( $design['preview_url'] )
				: __( 'Saved with order', 'pckz-canonical-engine' ),
		);

		return $item_data;
	}

	/**
	 * Save design meta on order line item.
	 *
	 * @param WC_Order_Item_Product $item          Order item.
	 * @param string                $cart_item_key Cart item key.
	 * @param array                 $values        Cart values.
	 * @param WC_Order              $order         Order.
	 */
	public function add_order_item_meta( $item, $cart_item_key, $values, $order ) {
		$key = PCKZ_Settings::get( 'cart_meta_key', '_pckz_design' );
		if ( empty( $values[ $key ] ) ) {
			return;
		}

		$design = $values[ $key ];
		$item->add_meta_data( '_pckz_design_id', (int) ( $design['design_id'] ?? 0 ), true );
		if ( ! empty( $design['preview_url'] ) ) {
			$item->add_meta_data( '_pckz_preview_url', esc_url_raw( $design['preview_url'] ), true );
		}
		if ( ! empty( $design['export_url'] ) ) {
			$item->add_meta_data( '_pckz_export_url', esc_url_raw( $design['export_url'] ), true );
		}
		if ( ! empty( $design['product_id'] ) ) {
			$item->add_meta_data( '_pckz_creator_product_id', (int) $design['product_id'], true );
		}
		if ( ! empty( $design['selections'] ) ) {
			$item->add_meta_data( '_pckz_selections', wp_json_encode( $design['selections'] ), true );
		}
		if ( ! empty( $design['production'] ) ) {
			$item->add_meta_data( '_pckz_production', wp_json_encode( $design['production'] ), true );
		}
		if ( ! empty( $design['production']['lightburn_json_url'] ) ) {
			$item->add_meta_data( '_pckz_lightburn_json_url', esc_url_raw( $design['production']['lightburn_json_url'] ), true );
		}
		if ( ! empty( $design['production']['canvas_json_url'] ) ) {
			$item->add_meta_data( '_pckz_canvas_json_url', esc_url_raw( $design['production']['canvas_json_url'] ), true );
		}
		if ( ! empty( $design['production']['production_svg_url'] ) ) {
			$item->add_meta_data( '_pckz_production_svg_url', esc_url_raw( $design['production']['production_svg_url'] ), true );
		}
		if ( ! empty( $design['production']['production_lbrn2_url'] ) ) {
			$item->add_meta_data( '_pckz_production_lbrn2_url', esc_url_raw( $design['production']['production_lbrn2_url'] ), true );
		}
		if ( ! empty( $design['production']['production_dxf_url'] ) ) {
			$item->add_meta_data( '_pckz_production_dxf_url', esc_url_raw( $design['production']['production_dxf_url'] ), true );
		}
		if ( ! empty( $design['customer_email'] ) ) {
			$item->add_meta_data( '_pckz_customer_email', sanitize_email( $design['customer_email'] ), true );
		}
		if ( ! empty( $design['customer_wishes'] ) ) {
			$item->add_meta_data( '_pckz_customer_wishes', sanitize_textarea_field( $design['customer_wishes'] ), true );
		}
	}

	/**
	 * Admin order metabox — production package for manual engraving.
	 */
	public function order_design_metabox() {
		$screens = array( 'shop_order' );
		if ( function_exists( 'wc_get_page_screen_id' ) ) {
			$screens[] = wc_get_page_screen_id( 'shop-order' );
		}
		foreach ( $screens as $screen ) {
			add_meta_box(
				'pckz_order_designs',
				__( 'Customer customization (for production)', 'pckz-canonical-engine' ),
				array( $this, 'render_order_metabox' ),
				$screen,
				'normal',
				'high'
			);
		}
	}

	/**
	 * Render order production metabox.
	 *
	 * @param WP_Post|WC_Order $post_or_order Post or order object.
	 */
	public function render_order_metabox( $post_or_order ) {
		$order = ( $post_or_order instanceof WP_Post ) ? wc_get_order( $post_or_order->ID ) : $post_or_order;
		if ( ! $order ) {
			echo '<p>' . esc_html__( 'No order data.', 'pckz-canonical-engine' ) . '</p>';
			return;
		}

		$found = false;
		foreach ( $order->get_items() as $item ) {
			$design_id = $item->get_meta( '_pckz_design_id' );
			if ( ! $design_id ) {
				continue;
			}
			$found = true;
			$production = $item->get_meta( '_pckz_production' );
			$package    = $production ? json_decode( $production, true ) : array();

			if ( empty( $package ) || empty( $package['layout'] ) ) {
				$design_row = PCKZ_Design_Storage::get_design( (int) $design_id );
				$config     = PCKZ_Post_Type::get_product_config( (int) $item->get_meta( '_pckz_creator_product_id' ) );
				$meta       = array();
				if ( $design_row && ! empty( $design_row['meta_json'] ) ) {
					$meta = json_decode( $design_row['meta_json'], true );
				}
				if ( ! empty( $meta['production'] ) && is_array( $meta['production'] ) ) {
					$package = $meta['production'];
					if ( class_exists( 'PCKZ_Production_Scene' ) ) {
						$fragment = PCKZ_Production_Scene::resolve_text_plate_paths_from_package(
							array_merge(
								is_array( $package ) ? $package : array(),
								array( 'meta' => is_array( $meta ) ? $meta : array() )
							)
						);
						if ( '' !== $fragment ) {
							$package['text_plate_paths'] = $fragment;
							if ( empty( $package['layout'] ) || ! is_array( $package['layout'] ) ) {
								$package['layout'] = array();
							}
							$package['layout']['text_plate_paths'] = $fragment;
						}
					}
				} else {
					$package = PCKZ_Production::build_package(
						array(
							'selections'  => $meta['selections'] ?? array(),
							'canvas_json' => $design_row['canvas_json'] ?? '',
							'config'      => $config,
							'preview_url' => $item->get_meta( '_pckz_preview_url' ) ?: ( $design_row['preview_url'] ?? '' ),
							'export_url'  => $item->get_meta( '_pckz_export_url' ) ?: ( $design_row['export_url'] ?? '' ),
							'std_spec'    => $meta['std_spec'] ?? array(),
							'design_id'   => (int) $design_id,
						)
					);
					$package = PCKZ_Production::persist_export_files( $package, (int) $design_id );
				}
			}

			$lb_url = $item->get_meta( '_pckz_lightburn_json_url' );
			if ( $lb_url && empty( $package['lightburn_json_url'] ) ) {
				$package['lightburn_json_url'] = $lb_url;
			}
			$canvas_url = $item->get_meta( '_pckz_canvas_json_url' );
			if ( $canvas_url && empty( $package['canvas_json_url'] ) ) {
				$package['canvas_json_url'] = $canvas_url;
			}
			foreach ( array(
				'_pckz_production_svg_url'   => 'production_svg_url',
				'_pckz_production_lbrn2_url' => 'production_lbrn2_url',
				'_pckz_production_dxf_url'     => 'production_dxf_url',
			) as $meta_key => $pkg_key ) {
				$url = $item->get_meta( $meta_key );
				if ( $url && empty( $package[ $pkg_key ] ) ) {
					$package[ $pkg_key ] = $url;
				}
			}
			if ( empty( $package['production_lbrn2_url'] ) && ! empty( $package['layout']['objects'] ) ) {
				$package = PCKZ_Production::persist_export_files( $package, (int) $design_id );
			}

			$detail_url = admin_url( 'admin.php?page=pckz-designs&design_id=' . (int) $design_id );

			$customer_email  = $item->get_meta( '_pckz_customer_email' );
			$customer_wishes = $item->get_meta( '_pckz_customer_wishes' );
			$details_raw     = $item->get_meta( '_pckz_customer_details' );
			if ( ! $details_raw ) {
				$details_raw = $order->get_meta( '_pckz_customer_details' );
			}
			$payment_status  = $order->get_meta( '_pckz_payment_status' );

			echo '<div class="pckz-order-production-block">';
			echo '<h4>' . esc_html( $item->get_name() ) . ' — ' . esc_html__( 'Design #', 'pckz-canonical-engine' ) . esc_html( $design_id ) . '</h4>';
			self::render_customer_details_admin( $details_raw, $customer_email, $customer_wishes );
			if ( $payment_status ) {
				echo '<p><strong>' . esc_html__( 'Zahlungsstatus:', 'pckz-canonical-engine' ) . '</strong> ' . esc_html( $payment_status ) . '</p>';
			}
			echo '<p><a class="button" href="' . esc_url( $detail_url ) . '">' . esc_html__( 'Open full production package', 'pckz-canonical-engine' ) . '</a></p>';
			echo PCKZ_Production::render_admin_production_panel( $package, array( 'design_id' => (int) $design_id ) );
			echo '</div>';
		}

		if ( ! $found ) {
			echo '<p>' . esc_html__( 'No customized products in this order.', 'pckz-canonical-engine' ) . '</p>';
		}
	}

	/**
	 * Output customer checkout fields in admin order view.
	 *
	 * @param string $details_raw     JSON details.
	 * @param string $customer_email  Fallback email.
	 * @param string $customer_wishes Wishes text.
	 */
	public static function render_customer_details_admin( $details_raw, $customer_email = '', $customer_wishes = '' ) {
		$details = class_exists( 'PCKZ_Commerce' )
			? PCKZ_Commerce::decode_customer_details( $details_raw )
			: array();
		if ( empty( $details ) && $customer_email ) {
			echo '<p><strong>' . esc_html__( 'Kunden-E-Mail:', 'pckz-canonical-engine' ) . '</strong> ' . esc_html( $customer_email ) . '</p>';
			if ( $customer_wishes ) {
				echo '<p><strong>' . esc_html__( 'Wünsche / Hinweise:', 'pckz-canonical-engine' ) . '</strong><br>' . esc_html( $customer_wishes ) . '</p>';
			}
			return;
		}
		if ( empty( $details ) ) {
			return;
		}
		$countries = class_exists( 'PCKZ_Commerce' ) ? PCKZ_Commerce::checkout_countries() : array();
		echo '<div class="pckz-admin-customer-details">';
		echo '<p><strong>' . esc_html( trim( ( $details['first_name'] ?? '' ) . ' ' . ( $details['last_name'] ?? '' ) ) ) . '</strong></p>';
		echo '<p>' . esc_html( $details['email'] ?? $customer_email ) . '<br>' . esc_html( $details['phone'] ?? '' ) . '</p>';
		$street = trim( ( $details['street'] ?? '' ) . ' ' . ( $details['house_number'] ?? '' ) );
		echo '<p>' . esc_html( $street ) . '<br>' . esc_html( ( $details['postal_code'] ?? '' ) . ' ' . ( $details['city'] ?? '' ) ) . '</p>';
		if ( ! empty( $details['country'] ) ) {
			echo '<p>' . esc_html( $countries[ $details['country'] ] ?? $details['country'] ) . '</p>';
		}
		if ( ! empty( $details['wishes'] ) || $customer_wishes ) {
			echo '<p><strong>' . esc_html__( 'Wünsche / Hinweise:', 'pckz-canonical-engine' ) . '</strong><br>' . esc_html( $details['wishes'] ?: $customer_wishes ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Attach production summary to admin new-order email.
	 *
	 * @param WC_Order $order         Order.
	 * @param bool     $sent_to_admin Sent to admin.
	 * @param bool     $plain_text    Plain text email.
	 * @param WC_Email $email         Email object.
	 */
	public function email_production_summary( $order, $sent_to_admin, $plain_text, $email ) {
		if ( ! $sent_to_admin || ! PCKZ_Settings::get( 'admin_email_notify', false ) ) {
			return;
		}

		foreach ( $order->get_items() as $item ) {
			if ( ! $item->get_meta( '_pckz_design_id' ) ) {
				continue;
			}
			$production = $item->get_meta( '_pckz_production' );
			$package    = $production ? json_decode( $production, true ) : array();
			if ( empty( $package ) ) {
				continue;
			}
			if ( $plain_text ) {
				echo "\n" . esc_html__( 'Customization:', 'pckz-canonical-engine' ) . "\n";
				foreach ( $package['lines'] as $row ) {
					echo esc_html( $row['label'] ) . ': ' . esc_html( $row['value'] ) . "\n";
				}
			} else {
				echo '<h3>' . esc_html__( 'Customer customization', 'pckz-canonical-engine' ) . '</h3>';
				echo PCKZ_Production::render_html_table( $package );
			}
		}
	}
}
