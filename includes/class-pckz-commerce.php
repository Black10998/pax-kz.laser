<?php
/**
 * Pricing, PayPal checkout, and customer order metadata (isolated from production export).
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PCKZ_Commerce
 */
class PCKZ_Commerce {

	const TABLE_ORDERS = 'pckz_commerce_orders';

	/**
	 * Create commerce orders table.
	 */
	public static function create_table() {
		global $wpdb;

		$table   = $wpdb->prefix . self::TABLE_ORDERS;
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			design_id bigint(20) unsigned NOT NULL DEFAULT 0,
			product_id bigint(20) unsigned NOT NULL DEFAULT 0,
			customer_email varchar(190) NOT NULL DEFAULT '',
			customer_note longtext,
			quantity int unsigned NOT NULL DEFAULT 1,
			amount decimal(12,2) NOT NULL DEFAULT 0,
			currency varchar(10) NOT NULL DEFAULT 'EUR',
			paypal_order_id varchar(64) NOT NULL DEFAULT '',
			paypal_capture_id varchar(64) NOT NULL DEFAULT '',
			status varchar(32) NOT NULL DEFAULT 'pending',
			wc_order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY design_id (design_id),
			KEY paypal_order_id (paypal_order_id),
			KEY status (status)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Whether PayPal checkout is configured and enabled.
	 *
	 * @return bool
	 */
	public static function paypal_enabled() {
		if ( ! PCKZ_Settings::get( 'paypal_enabled', false ) ) {
			return false;
		}
		$creds = self::paypal_credentials();
		return ! empty( $creds['client_id'] ) && ! empty( $creds['secret'] );
	}

	/**
	 * PayPal REST credentials for current mode.
	 *
	 * @return array{client_id:string,secret:string,api_base:string,mode:string}
	 */
	public static function paypal_credentials() {
		$test = (bool) PCKZ_Settings::get( 'paypal_test_mode', true );
		return array(
			'client_id' => $test
				? (string) PCKZ_Settings::get( 'paypal_sandbox_client_id', '' )
				: (string) PCKZ_Settings::get( 'paypal_live_client_id', '' ),
			'secret'    => $test
				? (string) PCKZ_Settings::get( 'paypal_sandbox_secret', '' )
				: (string) PCKZ_Settings::get( 'paypal_live_secret', '' ),
			'api_base'  => $test ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com',
			'mode'      => $test ? 'sandbox' : 'live',
		);
	}

	/**
	 * Supported checkout currencies (PayPal-compatible).
	 *
	 * @return array<string, array{symbol:string,label:string}>
	 */
	public static function currency_catalog() {
		return array(
			'EUR' => array(
				'symbol' => '€',
				'label'  => 'Euro (EUR)',
			),
			'USD' => array(
				'symbol' => '$',
				'label'  => 'US-Dollar (USD)',
			),
			'CHF' => array(
				'symbol' => 'CHF',
				'label'  => 'Schweizer Franken (CHF)',
			),
			'GBP' => array(
				'symbol' => '£',
				'label'  => 'Britisches Pfund (GBP)',
			),
		);
	}

	/**
	 * Sanitize and validate a currency code against the catalog.
	 *
	 * @param string $code Currency code.
	 * @return string
	 */
	public static function sanitize_currency_code( $code ) {
		$code = strtoupper( sanitize_text_field( (string) $code ) );
		$catalog = self::currency_catalog();
		return isset( $catalog[ $code ] ) ? $code : self::get_default_currency_code();
	}

	/**
	 * Default checkout currency from admin.
	 *
	 * @return string
	 */
	public static function get_default_currency_code() {
		$default = strtoupper( (string) PCKZ_Settings::get( 'price_default_currency', '' ) );
		if ( $default && isset( self::currency_catalog()[ $default ] ) ) {
			return $default;
		}
		$legacy = strtoupper( (string) PCKZ_Settings::get( 'price_currency_code', 'EUR' ) );
		return isset( self::currency_catalog()[ $legacy ] ) ? $legacy : 'EUR';
	}

	/**
	 * Currencies the admin allows for checkout / PayPal.
	 *
	 * @return string[]
	 */
	public static function get_allowed_currency_codes() {
		$enabled = PCKZ_Settings::get( 'price_currencies_enabled', array() );
		if ( ! is_array( $enabled ) ) {
			$enabled = array_filter( array_map( 'trim', explode( ',', (string) $enabled ) ) );
		}
		$enabled = array_values(
			array_filter(
				array_map(
					function ( $code ) {
						return self::sanitize_currency_code( $code );
					},
					$enabled
				)
			)
		);
		if ( empty( $enabled ) ) {
			$enabled = array( self::get_default_currency_code() );
		}
		$default = self::get_default_currency_code();
		if ( ! in_array( $default, $enabled, true ) ) {
			array_unshift( $enabled, $default );
		}
		return array_values( array_unique( $enabled ) );
	}

	/**
	 * Whether customers may switch currency on the frontend.
	 *
	 * @return bool
	 */
	public static function currency_switch_enabled() {
		if ( ! PCKZ_Settings::get( 'price_allow_currency_switch', false ) ) {
			return false;
		}
		return count( self::get_allowed_currency_codes() ) > 1;
	}

	/**
	 * Symbol for a currency (admin override for default, else catalog).
	 *
	 * @param string $code Currency code.
	 * @return string
	 */
	public static function get_currency_symbol( $code ) {
		$code = self::sanitize_currency_code( $code );
		if ( $code === self::get_default_currency_code() ) {
			$custom = (string) PCKZ_Settings::get( 'price_currency_symbol', '' );
			if ( '' !== $custom ) {
				return $custom;
			}
		}
		$catalog = self::currency_catalog();
		return $catalog[ $code ]['symbol'] ?? '€';
	}

	/**
	 * Base price for a currency (per-currency override or global admin base).
	 *
	 * @param string $currency_code Currency code.
	 * @return float
	 */
	public static function get_base_price_for_currency( $currency_code ) {
		$currency_code = self::sanitize_currency_code( $currency_code );
		$by_currency   = PCKZ_Settings::get( 'price_by_currency', array() );
		if ( is_array( $by_currency ) && isset( $by_currency[ $currency_code ] ) && '' !== $by_currency[ $currency_code ] ) {
			return max( 0, (float) $by_currency[ $currency_code ] );
		}
		return max( 0, (float) PCKZ_Settings::get( 'price_base', 0 ) );
	}

	/**
	 * Frontend pricing payload (admin settings only — no product metabox fallback).
	 *
	 * @param int    $product_id    Creator product ID (reserved for future use).
	 * @param string $currency_code Active currency code.
	 * @return array
	 */
	public static function get_frontend_pricing( $product_id = 0, $currency_code = '' ) {
		unset( $product_id );
		$show   = (bool) PCKZ_Settings::get( 'price_show_enabled', true );
		$setup  = max( 0, (float) PCKZ_Settings::get( 'price_setup_fee', 0 ) );
		$code   = $currency_code ? self::sanitize_currency_code( $currency_code ) : self::get_default_currency_code();
		if ( ! in_array( $code, self::get_allowed_currency_codes(), true ) ) {
			$code = self::get_default_currency_code();
		}
		$base   = self::get_base_price_for_currency( $code );
		$symbol = self::get_currency_symbol( $code );
		$unit   = round( $base + $setup, 2 );

		return array(
			'show'             => $show,
			'base'             => round( $base, 2 ),
			'setup_fee'        => round( $setup, 2 ),
			'unit_price'       => $unit,
			'currency_code'    => $code,
			'currency_symbol'  => $symbol,
			'formatted_base'   => self::format_money( $base, $symbol, $code ),
			'formatted_setup'  => $setup > 0 ? self::format_money( $setup, $symbol, $code ) : '',
			'formatted_unit'   => self::format_money( $unit, $symbol, $code ),
		);
	}

	/**
	 * Calculate order total for quantity and currency.
	 *
	 * @param int    $quantity      Quantity.
	 * @param int    $product_id    Creator product ID (unused; admin pricing only).
	 * @param string $currency_code Currency code.
	 * @return float
	 */
	public static function calculate_total( $quantity, $product_id = 0, $currency_code = '' ) {
		unset( $product_id );
		$pricing = self::get_frontend_pricing( 0, $currency_code );
		$qty     = max( 1, (int) $quantity );
		return round( ( $pricing['base'] + $pricing['setup_fee'] ) * $qty, 2 );
	}

	/**
	 * Unit price (base + setup) for WooCommerce cart overrides.
	 *
	 * @param string $currency_code Currency code.
	 * @return float
	 */
	public static function get_unit_price( $currency_code = '' ) {
		$pricing = self::get_frontend_pricing( 0, $currency_code );
		return (float) $pricing['unit_price'];
	}

	/**
	 * Customer reassurance notice HTML (admin-controlled).
	 *
	 * @return string
	 */
	public static function get_checkout_notice_html() {
		if ( ! PCKZ_Settings::get( 'checkout_notice_enabled', true ) ) {
			return '';
		}
		$message = (string) PCKZ_Settings::get( 'checkout_notice_message', '' );
		if ( '' === trim( wp_strip_all_tags( $message ) ) ) {
			$message = PCKZ_Settings::default_checkout_notice_message();
		}
		return wp_kses_post( $message );
	}

	/**
	 * Format money for display.
	 *
	 * @param float  $amount Amount.
	 * @param string $symbol Symbol.
	 * @param string $code   Currency code.
	 * @return string
	 */
	public static function format_money( $amount, $symbol = '€', $code = 'EUR' ) {
		$amount = number_format_i18n( (float) $amount, 2 );
		if ( 'EUR' === strtoupper( $code ) ) {
			return $amount . ' ' . $symbol;
		}
		return $symbol . $amount . ' ' . $code;
	}

	/**
	 * Validate customer email.
	 *
	 * @param string $email Email.
	 * @return string|WP_Error Sanitized email or error.
	 */
	public static function validate_email( $email ) {
		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', __( 'Bitte geben Sie eine gültige E-Mail-Adresse ein.', 'pckz-canonical-engine' ) );
		}
		return $email;
	}

	/**
	 * Insert pending commerce order.
	 *
	 * @param array $data Order row.
	 * @return int
	 */
	public static function insert_order( $data ) {
		global $wpdb;
		self::create_table();
		$wpdb->insert(
			$wpdb->prefix . self::TABLE_ORDERS,
			array(
				'design_id'       => absint( $data['design_id'] ?? 0 ),
				'product_id'      => absint( $data['product_id'] ?? 0 ),
				'customer_email'  => sanitize_email( $data['customer_email'] ?? '' ),
				'customer_note'   => sanitize_textarea_field( $data['customer_note'] ?? '' ),
				'quantity'        => max( 1, absint( $data['quantity'] ?? 1 ) ),
				'amount'          => (float) ( $data['amount'] ?? 0 ),
				'currency'        => sanitize_text_field( $data['currency'] ?? 'EUR' ),
				'paypal_order_id' => sanitize_text_field( $data['paypal_order_id'] ?? '' ),
				'status'          => sanitize_key( $data['status'] ?? 'pending' ),
			),
			array( '%d', '%d', '%s', '%s', '%d', '%f', '%s', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * Update commerce order row.
	 *
	 * @param int   $id   Row ID.
	 * @param array $data Fields.
	 */
	public static function update_order( $id, $data ) {
		global $wpdb;
		$data['updated_at'] = current_time( 'mysql' );
		$wpdb->update(
			$wpdb->prefix . self::TABLE_ORDERS,
			$data,
			array( 'id' => absint( $id ) ),
			null,
			array( '%d' )
		);
	}

	/**
	 * Get order by PayPal order ID.
	 *
	 * @param string $paypal_order_id PayPal order ID.
	 * @return array|null
	 */
	public static function get_order_by_paypal_id( $paypal_order_id ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_ORDERS;
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE paypal_order_id = %s LIMIT 1",
				sanitize_text_field( $paypal_order_id )
			),
			ARRAY_A
		);
		return $row ?: null;
	}

	/**
	 * Get order by internal ID.
	 *
	 * @param int $id Row ID.
	 * @return array|null
	 */
	public static function get_order( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_ORDERS;
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", absint( $id ) ),
			ARRAY_A
		);
		return $row ?: null;
	}

	/**
	 * Persist customer email/note on design meta.
	 *
	 * @param int    $design_id Design ID.
	 * @param string $email     Email.
	 * @param string $note      Customer note.
	 */
	public static function attach_customer_meta_to_design( $design_id, $email, $note = '' ) {
		$design = PCKZ_Design_Storage::get_design( $design_id );
		if ( ! $design ) {
			return;
		}
		$meta = array();
		if ( ! empty( $design['meta_json'] ) ) {
			$meta = json_decode( $design['meta_json'], true );
		}
		if ( ! is_array( $meta ) ) {
			$meta = array();
		}
		$meta['customer_email'] = sanitize_email( $email );
		$meta['customer_note']  = sanitize_textarea_field( $note );
		if ( empty( $meta['selections'] ) || ! is_array( $meta['selections'] ) ) {
			$meta['selections'] = array();
		}
		$meta['selections']['customer_email'] = $meta['customer_email'];
		$meta['selections']['customer_wishes'] = $meta['customer_note'];
		PCKZ_Design_Storage::update_meta( $design_id, $meta );
	}

	/**
	 * PayPal OAuth access token.
	 *
	 * @return string|WP_Error
	 */
	public static function paypal_access_token() {
		$creds = self::paypal_credentials();
		$response = wp_remote_post(
			$creds['api_base'] . '/v1/oauth2/token',
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $creds['client_id'] . ':' . $creds['secret'] ),
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => 'grant_type=client_credentials',
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 || empty( $body['access_token'] ) ) {
			return new WP_Error(
				'paypal_auth',
				__( 'PayPal-Verbindung fehlgeschlagen. Bitte prüfen Sie die Zahlungseinstellungen.', 'pckz-canonical-engine' ),
				array( 'status' => $code, 'body' => $body )
			);
		}
		return (string) $body['access_token'];
	}

	/**
	 * Create PayPal checkout order.
	 *
	 * @param array $args Checkout args.
	 * @return array|WP_Error Approval URL + IDs.
	 */
	public static function create_paypal_checkout( $args ) {
		$token = self::paypal_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$amount   = (float) ( $args['amount'] ?? 0 );
		$currency = strtoupper( sanitize_text_field( $args['currency'] ?? 'EUR' ) );
		$return   = esc_url_raw( $args['return_url'] ?? '' );
		$cancel   = esc_url_raw( $args['cancel_url'] ?? '' );

		$payload = array(
			'intent'              => 'CAPTURE',
			'purchase_units'      => array(
				array(
					'amount' => array(
						'currency_code' => $currency,
						'value'         => number_format( $amount, 2, '.', '' ),
					),
					'description' => sanitize_text_field( $args['description'] ?? 'PCKZ Personalisierung' ),
				),
			),
			'application_context' => array(
				'return_url'          => $return,
				'cancel_url'          => $cancel,
				'brand_name'          => get_bloginfo( 'name' ),
				'user_action'         => 'PAY_NOW',
				'landing_page'        => 'BILLING',
				'shipping_preference' => 'NO_SHIPPING',
			),
		);

		$response = wp_remote_post(
			self::paypal_credentials()['api_base'] . '/v2/checkout/orders',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
				'timeout' => 45,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 || empty( $body['id'] ) ) {
			return new WP_Error(
				'paypal_create',
				__( 'PayPal-Checkout konnte nicht gestartet werden.', 'pckz-canonical-engine' ),
				array( 'status' => $code, 'body' => $body )
			);
		}

		$approve = '';
		if ( ! empty( $body['links'] ) ) {
			foreach ( $body['links'] as $link ) {
				if ( ( $link['rel'] ?? '' ) === 'approve' && ! empty( $link['href'] ) ) {
					$approve = $link['href'];
					break;
				}
			}
		}

		if ( ! $approve ) {
			return new WP_Error( 'paypal_approve', __( 'PayPal-Zahlungslink fehlt.', 'pckz-canonical-engine' ) );
		}

		return array(
			'paypal_order_id' => (string) $body['id'],
			'approve_url'     => $approve,
			'status'          => (string) ( $body['status'] ?? '' ),
		);
	}

	/**
	 * Capture approved PayPal order.
	 *
	 * @param string $paypal_order_id PayPal order ID.
	 * @return array|WP_Error Capture data.
	 */
	public static function capture_paypal_order( $paypal_order_id ) {
		$token = self::paypal_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$paypal_order_id = sanitize_text_field( $paypal_order_id );
		$response        = wp_remote_post(
			self::paypal_credentials()['api_base'] . '/v2/checkout/orders/' . rawurlencode( $paypal_order_id ) . '/capture',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => '{}',
				'timeout' => 45,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'paypal_capture',
				__( 'PayPal-Zahlung konnte nicht abgeschlossen werden.', 'pckz-canonical-engine' ),
				array( 'status' => $code, 'body' => $body )
			);
		}

		$status     = (string) ( $body['status'] ?? '' );
		$capture_id = '';
		if ( ! empty( $body['purchase_units'][0]['payments']['captures'][0]['id'] ) ) {
			$capture_id = (string) $body['purchase_units'][0]['payments']['captures'][0]['id'];
		}

		if ( 'COMPLETED' !== $status ) {
			return new WP_Error( 'paypal_not_completed', __( 'Zahlung wurde nicht bestätigt.', 'pckz-canonical-engine' ), $body );
		}

		return array(
			'status'      => $status,
			'capture_id'  => $capture_id,
			'raw'         => $body,
		);
	}

	/**
	 * Build return URL with query args.
	 *
	 * @param int $commerce_order_id Internal order ID.
	 * @return string
	 */
	public static function paypal_return_url( $commerce_order_id ) {
		$url = (string) PCKZ_Settings::get( 'paypal_success_url', '' );
		if ( ! $url ) {
			$url = home_url( '/' );
		}
		return add_query_arg(
			array(
				'pckz_paypal' => 'return',
				'pckz_order'  => absint( $commerce_order_id ),
			),
			$url
		);
	}

	/**
	 * Build cancel URL.
	 *
	 * @param int $commerce_order_id Internal order ID.
	 * @return string
	 */
	public static function paypal_cancel_url( $commerce_order_id ) {
		$url = (string) PCKZ_Settings::get( 'paypal_cancel_url', '' );
		if ( ! $url ) {
			$url = wp_get_referer() ?: home_url( '/' );
		}
		return add_query_arg(
			array(
				'pckz_paypal' => 'cancel',
				'pckz_order'  => absint( $commerce_order_id ),
			),
			$url
		);
	}

	/**
	 * Finalize paid order: WooCommerce order + emails.
	 *
	 * @param array $commerce_order Commerce DB row.
	 * @return array|WP_Error
	 */
	public static function finalize_paid_order( $commerce_order ) {
		$design_id = (int) ( $commerce_order['design_id'] ?? 0 );
		$config    = PCKZ_Post_Type::get_product_config( (int) ( $commerce_order['product_id'] ?? 0 ) );
		$woo_id    = (int) ( $config['woo_product_id'] ?? 0 );

		self::attach_customer_meta_to_design(
			$design_id,
			$commerce_order['customer_email'] ?? '',
			$commerce_order['customer_note'] ?? ''
		);

		$wc_order_id = 0;
		if ( class_exists( 'WooCommerce' ) && $woo_id && function_exists( 'wc_create_order' ) ) {
			$order = wc_create_order();
			if ( $order instanceof WC_Order ) {
				$product = wc_get_product( $woo_id );
				$item = null;
				if ( $product ) {
					$item_id = $order->add_product( $product, (int) ( $commerce_order['quantity'] ?? 1 ) );
					$item    = $item_id ? $order->get_item( $item_id ) : null;
				}
				$order->set_billing_email( $commerce_order['customer_email'] ?? '' );
				$order->set_status( 'processing', __( 'PayPal-Zahlung bestätigt.', 'pckz-canonical-engine' ) );
				$order->set_payment_method( 'pckz_paypal' );
				$order->set_payment_method_title( 'PayPal' );
				$order->set_total( (float) ( $commerce_order['amount'] ?? 0 ) );
				$order->update_meta_data( '_pckz_paypal_capture_id', $commerce_order['paypal_capture_id'] ?? '' );
				$order->update_meta_data( '_pckz_paypal_order_id', $commerce_order['paypal_order_id'] ?? '' );
				$order->update_meta_data( '_pckz_customer_email', $commerce_order['customer_email'] ?? '' );
				$order->update_meta_data( '_pckz_customer_wishes', $commerce_order['customer_note'] ?? '' );
				$order->update_meta_data( '_pckz_payment_status', 'paid' );

				$key = PCKZ_Settings::get( 'cart_meta_key', '_pckz_design' );
				$design = PCKZ_Design_Storage::get_design( $design_id );
				$meta   = array();
				if ( $design && ! empty( $design['meta_json'] ) ) {
					$meta = json_decode( $design['meta_json'], true );
				}
				$pack = array(
					'design_id'   => $design_id,
					'preview_url' => $design['preview_url'] ?? '',
					'export_url'  => $design['export_url'] ?? '',
					'product_id'  => (int) ( $commerce_order['product_id'] ?? 0 ),
					'selections'  => $meta['selections'] ?? array(),
					'production'  => $meta['production'] ?? array(),
					'customer_email' => $commerce_order['customer_email'] ?? '',
					'customer_wishes' => $commerce_order['customer_note'] ?? '',
				);

				if ( $item ) {
					$item->add_meta_data( '_pckz_design_id', $design_id, true );
					$item->add_meta_data( '_pckz_customer_email', $commerce_order['customer_email'] ?? '', true );
					$item->add_meta_data( '_pckz_customer_wishes', $commerce_order['customer_note'] ?? '', true );
					if ( ! empty( $pack['production'] ) ) {
						$item->add_meta_data( '_pckz_production', wp_json_encode( $pack['production'] ), true );
					}
					if ( ! empty( $pack['selections'] ) ) {
						$item->add_meta_data( '_pckz_selections', wp_json_encode( $pack['selections'] ), true );
					}
					$item->save();
				}

				$order->save();
				$wc_order_id = $order->get_id();
				$order->payment_complete( $commerce_order['paypal_capture_id'] ?? '' );
			}
		}

		self::update_order(
			(int) $commerce_order['id'],
			array(
				'status'      => 'paid',
				'wc_order_id' => $wc_order_id,
			)
		);

		if ( ! empty( $commerce_order['customer_email'] ) ) {
			self::send_customer_confirmation_email( $commerce_order, $wc_order_id );
		}

		return array(
			'wc_order_id' => $wc_order_id,
			'commerce_id' => (int) $commerce_order['id'],
		);
	}

	/**
	 * Simple HTML order confirmation to customer.
	 *
	 * @param array $commerce_order Commerce row.
	 * @param int   $wc_order_id    WC order ID.
	 */
	public static function send_customer_confirmation_email( $commerce_order, $wc_order_id = 0 ) {
		$to      = $commerce_order['customer_email'] ?? '';
		$subject = sprintf(
			/* translators: %s: site name */
			__( 'Bestellbestätigung – %s', 'pckz-canonical-engine' ),
			get_bloginfo( 'name' )
		);
		$body  = '<p>' . esc_html__( 'Vielen Dank für Ihre Bestellung. Wir haben Ihre Personalisierung erhalten und bereiten die Produktion professionell vor.', 'pckz-canonical-engine' ) . '</p>';
		if ( $wc_order_id ) {
			$body .= '<p>' . esc_html__( 'Bestellnummer:', 'pckz-canonical-engine' ) . ' #' . esc_html( (string) $wc_order_id ) . '</p>';
		}
		if ( ! empty( $commerce_order['customer_note'] ) ) {
			$body .= '<p><strong>' . esc_html__( 'Ihre Wünsche:', 'pckz-canonical-engine' ) . '</strong><br>' . esc_html( $commerce_order['customer_note'] ) . '</p>';
		}
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		wp_mail( $to, $subject, $body, $headers );
	}

	/**
	 * Config for creator JS.
	 *
	 * @param int $product_id Product ID.
	 * @return array
	 */
	public static function config_for_js( $product_id ) {
		$default_code = self::get_default_currency_code();
		$pricing      = self::get_frontend_pricing( $product_id, $default_code );
		$currencies   = array();
		foreach ( self::get_allowed_currency_codes() as $code ) {
			$p = self::get_frontend_pricing( $product_id, $code );
			$currencies[ $code ] = array(
				'code'           => $code,
				'symbol'         => $p['currency_symbol'],
				'label'          => self::currency_catalog()[ $code ]['label'] ?? $code,
				'base'           => $p['base'],
				'setup_fee'      => $p['setup_fee'],
				'unit_price'     => $p['unit_price'],
				'formatted_unit' => $p['formatted_unit'],
				'formatted_base' => $p['formatted_base'],
			);
		}
		return array(
			'pricing'              => $pricing,
			'currencies'           => $currencies,
			'defaultCurrency'      => $default_code,
			'allowCurrencySwitch'  => self::currency_switch_enabled(),
			'paypalEnabled'        => self::paypal_enabled(),
			'requireEmail'         => true,
			'successUrl'           => (string) PCKZ_Settings::get( 'paypal_success_url', '' ),
			'cancelUrl'            => (string) PCKZ_Settings::get( 'paypal_cancel_url', '' ),
		);
	}
}
