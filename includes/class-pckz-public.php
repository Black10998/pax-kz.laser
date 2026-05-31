<?php
/**
 * Frontend assets and rendering.
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PCKZ_Public
 */
class PCKZ_Public {

	/**
	 * Track if creator assets were enqueued.
	 *
	 * @var bool
	 */
	private static $assets_enqueued = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'template_redirect', array( $this, 'handle_paypal_return' ) );
	}

	/**
	 * Register (not enqueue) frontend assets.
	 */
	public function register_assets() {
		// Assets are enqueued on demand via PCKZ_Assets when the shortcode renders.
	}

	/**
	 * Enqueue creator assets for a product.
	 *
	 * @param int   $product_id Creator product post ID.
	 * @param array $config     Product configuration.
	 */
	public static function enqueue_creator( $product_id, $config ) {
		if ( self::$assets_enqueued ) {
			return;
		}
		PCKZ_Assets::enqueue_creator( $product_id, $config );
		self::$assets_enqueued = true;
	}

	/**
	 * Render creator markup.
	 *
	 * @param int $product_id Creator product ID.
	 * @return string
	 */
	/**
	 * Capture payment after provider approval (return URL).
	 */
	public function handle_paypal_return() {
		if ( ! class_exists( 'PCKZ_Commerce' ) ) {
			return;
		}
		if ( isset( $_GET['pckz_payment'] ) ) {
			$payment_mode = sanitize_key( wp_unslash( $_GET['pckz_payment'] ) );
			$internal_id  = isset( $_GET['pckz_order'] ) ? absint( $_GET['pckz_order'] ) : 0;
			if ( 'stripe_cancel' === $payment_mode ) {
				if ( $internal_id ) {
					$row = PCKZ_Commerce::get_order( $internal_id );
					if ( $row && in_array( sanitize_key( (string) ( $row['status'] ?? '' ) ), array( 'pending', 'stripe_created', 'failed' ), true ) ) {
						PCKZ_Commerce::update_order( $internal_id, array( 'status' => 'cancelled' ) );
					}
				}
				$redirect = wp_get_referer() ?: home_url( '/' );
				if ( $internal_id ) {
					$row = PCKZ_Commerce::get_order( $internal_id );
					if ( ! empty( $row['return_url'] ) ) {
						$redirect = (string) $row['return_url'];
					}
				}
				wp_safe_redirect( $redirect );
				exit;
			}
			if ( 'stripe_return' === $payment_mode && class_exists( 'PCKZ_Payments' ) ) {
				$session_id = isset( $_GET['session_id'] ) ? sanitize_text_field( wp_unslash( $_GET['session_id'] ) ) : '';
				if ( '' !== $session_id ) {
					$order = null;
					$confirmed = array();
					if ( $internal_id ) {
						$order = PCKZ_Commerce::get_order( $internal_id );
					}
					if ( ! $order ) {
						$order = PCKZ_Commerce::get_order_by_payment_reference( $session_id, 'stripe' );
					}
					if ( $order ) {
						if ( 'paid' !== sanitize_key( (string) ( $order['status'] ?? '' ) ) ) {
							$confirmed = PCKZ_Payments::confirm_stripe_checkout( $session_id );
							if ( is_wp_error( $confirmed ) ) {
								wp_die( esc_html( $confirmed->get_error_message() ), esc_html__( 'Zahlung fehlgeschlagen', 'pckz-canonical-engine' ), array( 'response' => 403 ) );
							}
							$order = PCKZ_Commerce::get_order( (int) ( $confirmed['commerce_id'] ?? $order['id'] ) );
						}
						if ( $order ) {
							$redirect = PCKZ_Commerce::resolve_post_payment_redirect( $order );
							$wc_id = isset( $order['wc_order_id'] ) ? (int) $order['wc_order_id'] : 0;
							if ( ! $wc_id && ! empty( $confirmed['wc_order_id'] ) ) {
								$wc_id = (int) $confirmed['wc_order_id'];
							}
							if ( $wc_id > 0 ) {
								$redirect = add_query_arg( 'wc_order', $wc_id, $redirect );
							}
							wp_safe_redirect( $redirect );
							exit;
						}
					}
				}
			}
		}
		if ( ! isset( $_GET['pckz_paypal'] ) ) {
			return;
		}

		$mode = sanitize_key( wp_unslash( $_GET['pckz_paypal'] ) );
		$internal_id = isset( $_GET['pckz_order'] ) ? absint( $_GET['pckz_order'] ) : 0;

		if ( 'cancel' === $mode ) {
			if ( $internal_id ) {
				PCKZ_Commerce::update_order( $internal_id, array( 'status' => 'cancelled' ) );
			}
			$cancel   = (string) PCKZ_Settings::get( 'paypal_cancel_url', '' );
			$redirect = $cancel ? $cancel : ( wp_get_referer() ?: home_url( '/' ) );
			wp_safe_redirect( $redirect );
			exit;
		}

		if ( 'return' !== $mode ) {
			return;
		}

		$paypal_token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
		if ( ! $paypal_token ) {
			return;
		}

		$commerce = PCKZ_Commerce::get_order_by_paypal_id( $paypal_token );
		if ( ! $commerce && $internal_id ) {
			$commerce = PCKZ_Commerce::get_order( $internal_id );
		}
		if ( ! $commerce || 'paid' === ( $commerce['status'] ?? '' ) ) {
			return;
		}

		$capture = PCKZ_Commerce::capture_paypal_order( $paypal_token );
		if ( is_wp_error( $capture ) ) {
			wp_die( esc_html( $capture->get_error_message() ), esc_html__( 'Zahlung fehlgeschlagen', 'pckz-canonical-engine' ), array( 'response' => 403 ) );
		}

		PCKZ_Commerce::update_order(
			(int) $commerce['id'],
			array(
				'paypal_capture_id' => $capture['capture_id'] ?? '',
				'status'            => 'paid',
			)
		);
		$commerce['paypal_capture_id'] = $capture['capture_id'] ?? '';
		$result = PCKZ_Commerce::finalize_paid_order( $commerce );

		$commerce['id'] = (int) ( $result['commerce_id'] ?? $commerce['id'] );
		$redirect       = PCKZ_Commerce::resolve_post_payment_redirect( $commerce );
		if ( ! empty( $result['wc_order_id'] ) ) {
			$redirect = add_query_arg( 'wc_order', (int) $result['wc_order_id'], $redirect );
		}
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Render creator markup.
	 *
	 * @param int $product_id Creator product ID.
	 * @return string
	 */
	public static function render_creator( $product_id ) {
		$post = get_post( $product_id );
		if ( ! $post || PCKZ_Post_Type::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			return '<p class="pckz-error">' . esc_html__( 'Creator product not found.', 'pckz-canonical-engine' ) . '</p>';
		}

		$config = PCKZ_Post_Type::get_product_config( $product_id );
		self::enqueue_creator( $product_id, $config );

		ob_start();
		include PCKZCE_PLUGIN_DIR . 'public/templates/creator.php';
		return ob_get_clean();
	}
}
