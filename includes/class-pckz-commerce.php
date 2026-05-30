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
			customer_details longtext,
			quantity int unsigned NOT NULL DEFAULT 1,
			amount decimal(12,2) NOT NULL DEFAULT 0,
			currency varchar(10) NOT NULL DEFAULT 'EUR',
			paypal_order_id varchar(64) NOT NULL DEFAULT '',
			paypal_capture_id varchar(64) NOT NULL DEFAULT '',
			status varchar(32) NOT NULL DEFAULT 'pending',
			wc_order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			return_url varchar(512) NOT NULL DEFAULT '',
			admin_notes longtext,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY design_id (design_id),
			KEY paypal_order_id (paypal_order_id),
			KEY status (status),
			KEY customer_email (customer_email)
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
	 * Country options for checkout (ISO label in German).
	 *
	 * @return array<string, string>
	 */
	public static function checkout_countries() {
		return array(
			'DE' => 'Deutschland',
			'AT' => 'Österreich',
			'CH' => 'Schweiz',
			'LI' => 'Liechtenstein',
			'IT' => 'Italien',
			'FR' => 'Frankreich',
			'NL' => 'Niederlande',
			'BE' => 'Belgien',
			'LU' => 'Luxemburg',
			'GB' => 'Vereinigtes Königreich',
			'US' => 'USA',
		);
	}

	/**
	 * Sanitize customer checkout fields.
	 *
	 * @param array $raw Raw input.
	 * @return array
	 */
	public static function sanitize_customer_details( $raw ) {
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}
		$countries = self::checkout_countries();
		$country   = strtoupper( sanitize_text_field( $raw['country'] ?? 'DE' ) );
		if ( ! isset( $countries[ $country ] ) ) {
			$country = 'DE';
		}
		return array(
			'first_name'    => sanitize_text_field( $raw['first_name'] ?? '' ),
			'last_name'     => sanitize_text_field( $raw['last_name'] ?? '' ),
			'email'         => sanitize_email( $raw['email'] ?? '' ),
			'phone'         => sanitize_text_field( $raw['phone'] ?? '' ),
			'street'        => sanitize_text_field( $raw['street'] ?? '' ),
			'house_number'  => sanitize_text_field( $raw['house_number'] ?? '' ),
			'postal_code'   => sanitize_text_field( $raw['postal_code'] ?? '' ),
			'city'          => sanitize_text_field( $raw['city'] ?? '' ),
			'country'       => $country,
			'wishes'        => sanitize_textarea_field( $raw['wishes'] ?? ( $raw['customer_wishes'] ?? '' ) ),
		);
	}

	/**
	 * Parse customer details from POST (JSON or individual fields).
	 *
	 * @return array
	 */
	public static function parse_customer_details_from_request() {
		if ( ! empty( $_POST['customer_details'] ) ) {
			$decoded = json_decode( wp_unslash( $_POST['customer_details'] ), true );
			if ( is_array( $decoded ) ) {
				return self::sanitize_customer_details( $decoded );
			}
		}
		return self::sanitize_customer_details(
			array(
				'first_name'   => $_POST['customer_first_name'] ?? '',
				'last_name'    => $_POST['customer_last_name'] ?? '',
				'email'        => $_POST['customer_email'] ?? '',
				'phone'        => $_POST['customer_phone'] ?? '',
				'street'       => $_POST['customer_street'] ?? '',
				'house_number' => $_POST['customer_house_number'] ?? '',
				'postal_code'  => $_POST['customer_postal_code'] ?? '',
				'city'         => $_POST['customer_city'] ?? '',
				'country'      => $_POST['customer_country'] ?? 'DE',
				'wishes'       => $_POST['customer_wishes'] ?? '',
			)
		);
	}

	/**
	 * Validate required checkout customer fields.
	 *
	 * @param array $details Sanitized details.
	 * @return true|WP_Error
	 */
	public static function validate_customer_details( $details ) {
		$required = array(
			'first_name'   => __( 'Bitte geben Sie Ihren Vornamen ein.', 'pckz-canonical-engine' ),
			'last_name'    => __( 'Bitte geben Sie Ihren Nachnamen ein.', 'pckz-canonical-engine' ),
			'phone'        => __( 'Bitte geben Sie Ihre Telefonnummer ein.', 'pckz-canonical-engine' ),
			'street'       => __( 'Bitte geben Sie Ihre Straße ein.', 'pckz-canonical-engine' ),
			'house_number' => __( 'Bitte geben Sie Ihre Hausnummer ein.', 'pckz-canonical-engine' ),
			'postal_code'  => __( 'Bitte geben Sie Ihre Postleitzahl ein.', 'pckz-canonical-engine' ),
			'city'         => __( 'Bitte geben Sie Ihren Ort ein.', 'pckz-canonical-engine' ),
		);
		foreach ( $required as $key => $message ) {
			if ( empty( $details[ $key ] ) ) {
				return new WP_Error( 'missing_' . $key, $message );
			}
		}
		$email = self::validate_email( $details['email'] ?? '' );
		if ( is_wp_error( $email ) ) {
			return $email;
		}
		return true;
	}

	/**
	 * Encode customer details for DB storage.
	 *
	 * @param array $details Details.
	 * @return string
	 */
	public static function encode_customer_details( $details ) {
		return wp_json_encode( $details );
	}

	/**
	 * Decode customer details from DB/meta.
	 *
	 * @param string|array $stored Stored value.
	 * @return array
	 */
	public static function decode_customer_details( $stored ) {
		if ( is_array( $stored ) ) {
			return self::sanitize_customer_details( $stored );
		}
		if ( ! is_string( $stored ) || '' === $stored ) {
			return array();
		}
		$decoded = json_decode( $stored, true );
		return is_array( $decoded ) ? self::sanitize_customer_details( $decoded ) : array();
	}

	/**
	 * Whether PayPal is the only allowed checkout path.
	 *
	 * @return bool
	 */
	public static function checkout_paypal_only() {
		return self::paypal_enabled();
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
		$product_id = absint( $data['product_id'] ?? 0 );
		$return_url = ! empty( $data['return_url'] )
			? esc_url_raw( $data['return_url'] )
			: self::resolve_creator_page_url( $product_id );
		$wpdb->insert(
			$wpdb->prefix . self::TABLE_ORDERS,
			array(
				'design_id'       => absint( $data['design_id'] ?? 0 ),
				'product_id'      => $product_id,
				'customer_email'  => sanitize_email( $data['customer_email'] ?? '' ),
				'customer_note'   => sanitize_textarea_field( $data['customer_note'] ?? '' ),
				'customer_details'=> is_string( $data['customer_details'] ?? '' ) ? $data['customer_details'] : self::encode_customer_details( $data['customer_details'] ?? array() ),
				'quantity'        => max( 1, absint( $data['quantity'] ?? 1 ) ),
				'amount'          => (float) ( $data['amount'] ?? 0 ),
				'currency'        => sanitize_text_field( $data['currency'] ?? 'EUR' ),
				'paypal_order_id' => sanitize_text_field( $data['paypal_order_id'] ?? '' ),
				'status'          => sanitize_key( $data['status'] ?? 'pending' ),
				'return_url'      => $return_url,
				'admin_notes'     => sanitize_textarea_field( $data['admin_notes'] ?? '' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%d', '%f', '%s', '%s', '%s', '%s', '%s' )
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
	 * Latest commerce order for a saved design.
	 *
	 * @param int $design_id Design ID.
	 * @return array|null
	 */
	public static function get_order_by_design_id( $design_id ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_ORDERS;
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE design_id = %d ORDER BY id DESC LIMIT 1",
				absint( $design_id )
			),
			ARRAY_A
		);
		return $row ?: null;
	}

	/**
	 * List commerce orders (newest first).
	 *
	 * @param int $limit Max rows.
	 * @return array<int,array>
	 */
	public static function list_orders( $limit = 100, $args = array() ) {
		global $wpdb;
		self::create_table();
		$table  = $wpdb->prefix . self::TABLE_ORDERS;
		$limit  = max( 1, min( 500, absint( $limit ) ) );
		$where  = array( '1=1' );
		$params = array();

		$search = trim( (string) ( $args['search'] ?? '' ) );
		if ( '' !== $search ) {
			$order_id = self::parse_order_number_input( $search );
			if ( $order_id ) {
				$where[]  = 'id = %d';
				$params[] = $order_id;
			} else {
				$like     = '%' . $wpdb->esc_like( $search ) . '%';
				$where[]  = '( customer_email LIKE %s OR customer_details LIKE %s OR customer_note LIKE %s )';
				$params[] = $like;
				$params[] = $like;
				$params[] = $like;
			}
		}

		$sql = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . " ORDER BY created_at DESC, id DESC LIMIT %d";
		$params[] = $limit;
		$query    = $wpdb->prepare( $sql, $params );
		$rows     = $wpdb->get_results( $query, ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Lookup order for customer tracking (by public order number).
	 *
	 * @param string $order_number Customer input.
	 * @return array|null
	 */
	public static function get_order_for_tracking( $order_number ) {
		$id = self::parse_order_number_input( $order_number );
		if ( ! $id ) {
			return null;
		}
		$row = self::get_order( $id );
		if ( ! $row ) {
			return null;
		}
		if ( in_array( $row['status'] ?? '', array( 'pending', 'paypal_created', 'failed' ), true ) ) {
			return null;
		}
		return $row;
	}

	/**
	 * Production workflow status codes stored in the orders table.
	 *
	 * @return array<string,string> code => German label.
	 */
	public static function workflow_statuses() {
		return array(
			'pending'        => __( 'Zahlung ausstehend', 'pckz-canonical-engine' ),
			'paid'           => __( 'Zahlung erhalten', 'pckz-canonical-engine' ),
			'in_progress'    => __( 'In Bearbeitung', 'pckz-canonical-engine' ),
			'production'     => __( 'Produktion', 'pckz-canonical-engine' ),
			'ready_to_ship'  => __( 'Versandbereit', 'pckz-canonical-engine' ),
			'shipped'        => __( 'Versendet', 'pckz-canonical-engine' ),
			'completed'      => __( 'Abgeschlossen', 'pckz-canonical-engine' ),
			'cancelled'      => __( 'Storniert', 'pckz-canonical-engine' ),
		);
	}

	/**
	 * Customer-facing tracking statuses (after payment).
	 *
	 * @return array<string,string>
	 */
	public static function customer_tracking_statuses() {
		return array(
			'paid'          => __( 'Zahlung erhalten', 'pckz-canonical-engine' ),
			'in_progress'   => __( 'In Bearbeitung', 'pckz-canonical-engine' ),
			'production'    => __( 'Produktion', 'pckz-canonical-engine' ),
			'ready_to_ship' => __( 'Versandbereit', 'pckz-canonical-engine' ),
			'shipped'       => __( 'Versendet', 'pckz-canonical-engine' ),
			'completed'     => __( 'Abgeschlossen', 'pckz-canonical-engine' ),
			'cancelled'     => __( 'Storniert', 'pckz-canonical-engine' ),
		);
	}

	/**
	 * Normalize legacy/internal status aliases to canonical workflow codes.
	 *
	 * @param string $status Raw status value.
	 * @return string
	 */
	public static function normalize_status_code( $status ) {
		$status = sanitize_key( (string) $status );
		$legacy = array(
			'paypal_created' => 'pending',
			'captured'       => 'paid',
		);
		return $legacy[ $status ] ?? $status;
	}

	/**
	 * CSS status modifier class for badges.
	 *
	 * @param string $status Raw status.
	 * @return string
	 */
	public static function status_badge_css_class( $status ) {
		$status = self::normalize_status_code( $status );
		if ( ! $status ) {
			$status = 'pending';
		}
		return 'pckz-status-badge--' . sanitize_html_class( $status );
	}

	/**
	 * Public tracking ID for customers (non-sequential).
	 *
	 * @param int $order_id Internal order row ID.
	 * @return string
	 */
	public static function format_order_number( $order_id ) {
		$order_id = max( 1, absint( $order_id ) );
		$encoded  = self::tracking_base32_encode_u32( self::tracking_permute_u32( $order_id ) );
		return sprintf( 'PAX-%s-%s', substr( $encoded, 0, 4 ), substr( $encoded, 4, 4 ) );
	}

	/**
	 * Legacy sequential customer number (kept for backward compatibility).
	 *
	 * @param int $order_id Internal order row ID.
	 * @return string
	 */
	public static function format_legacy_order_number( $order_id ) {
		return 'PCKZ-' . str_pad( (string) max( 1, absint( $order_id ) ), 6, '0', STR_PAD_LEFT );
	}

	/**
	 * Parse order number from customer input (new + legacy formats).
	 *
	 * @param string $input Customer input.
	 * @return int Zero if invalid.
	 */
	public static function parse_order_number_input( $input ) {
		$input = strtoupper( trim( (string) $input ) );
		if ( preg_match( '/^PCKZ-(\d+)$/', $input, $m ) ) {
			return absint( $m[1] );
		}
		if ( preg_match( '/^PAX[-\s]?([A-Z0-9]{4})[-\s]?([A-Z0-9]{4})$/', $input, $m ) ) {
			$payload = $m[1] . $m[2];
			$value   = self::tracking_base32_decode_u32( $payload );
			if ( null !== $value ) {
				$id = self::tracking_unpermute_u32( $value );
				if ( $id > 0 && self::format_order_number( $id ) === sprintf( 'PAX-%s-%s', substr( $payload, 0, 4 ), substr( $payload, 4, 4 ) ) ) {
					return absint( $id );
				}
			}
		}
		if ( ctype_digit( $input ) ) {
			return absint( $input );
		}
		return 0;
	}

	/**
	 * Derive deterministic 32-bit key for tracking ID obfuscation.
	 *
	 * @return int
	 */
	private static function tracking_key_u32() {
		static $key = null;
		if ( null !== $key ) {
			return $key;
		}
		$salt = function_exists( 'wp_salt' ) ? (string) wp_salt( 'auth' ) : ( defined( 'AUTH_SALT' ) ? (string) AUTH_SALT : 'pckz-tracking' );
		$hash = hash( 'sha256', 'pckz-public-tracking|' . $salt );
		$key  = (int) hexdec( substr( $hash, 0, 8 ) );
		if ( $key <= 0 ) {
			$key = 265443576;
		}
		return $key;
	}

	/**
	 * 16-bit Feistel round function.
	 *
	 * @param int $right Current right half.
	 * @param int $key   Round key.
	 * @param int $round Round index.
	 * @return int
	 */
	private static function tracking_round_f( $right, $key, $round ) {
		$mix = ( ( $right ^ ( ( $key >> ( $round * 3 ) ) & 0xFFFF ) ) + ( ( $key >> ( $round * 5 ) ) & 0xFFFF ) + ( $round * 977 ) ) & 0xFFFF;
		return (int) ( ( $mix * 1103 + 12345 ) & 0xFFFF );
	}

	/**
	 * Deterministic reversible permutation of 32-bit order IDs.
	 *
	 * @param int $value Order ID.
	 * @return int
	 */
	private static function tracking_permute_u32( $value ) {
		$value = max( 1, absint( $value ) ) & 0xFFFFFFFF;
		$left  = ( $value >> 16 ) & 0xFFFF;
		$right = $value & 0xFFFF;
		$key   = self::tracking_key_u32();
		for ( $round = 0; $round < 4; $round++ ) {
			$next_left  = $right;
			$next_right = ( $left ^ self::tracking_round_f( $right, $key, $round ) ) & 0xFFFF;
			$left       = $next_left;
			$right      = $next_right;
		}
		return (int) ( ( ( $left & 0xFFFF ) << 16 ) | ( $right & 0xFFFF ) );
	}

	/**
	 * Reverse permutation for public tracking payload.
	 *
	 * @param int $value Obfuscated payload.
	 * @return int
	 */
	private static function tracking_unpermute_u32( $value ) {
		$value = (int) $value & 0xFFFFFFFF;
		$left  = ( $value >> 16 ) & 0xFFFF;
		$right = $value & 0xFFFF;
		$key   = self::tracking_key_u32();
		for ( $round = 3; $round >= 0; $round-- ) {
			$prev_right = $left;
			$prev_left  = ( $right ^ self::tracking_round_f( $prev_right, $key, $round ) ) & 0xFFFF;
			$left       = $prev_left;
			$right      = $prev_right;
		}
		return (int) ( ( ( $left & 0xFFFF ) << 16 ) | ( $right & 0xFFFF ) );
	}

	/**
	 * Encode unsigned 32-bit integer to fixed 8-char base32 token.
	 *
	 * @param int $value Payload.
	 * @return string
	 */
	private static function tracking_base32_encode_u32( $value ) {
		$alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
		$value    = (int) $value & 0xFFFFFFFF;
		$out      = '';
		for ( $i = 0; $i < 8; $i++ ) {
			$out    = $alphabet[ $value & 31 ] . $out;
			$value  = $value >> 5;
		}
		return $out;
	}

	/**
	 * Decode fixed 8-char base32 token to unsigned 32-bit integer.
	 *
	 * @param string $payload Encoded payload.
	 * @return int|null
	 */
	private static function tracking_base32_decode_u32( $payload ) {
		$alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
		$payload  = strtoupper( trim( (string) $payload ) );
		if ( 8 !== strlen( $payload ) ) {
			return null;
		}
		$value = 0;
		for ( $i = 0; $i < 8; $i++ ) {
			$ch = $payload[ $i ];
			$pos = strpos( $alphabet, $ch );
			if ( false === $pos ) {
				return null;
			}
			$value = ( ( $value << 5 ) | $pos ) & 0xFFFFFFFF;
		}
		return (int) $value;
	}

	/**
	 * Label for customer tracking page.
	 *
	 * @param string $status Stored status.
	 * @return string
	 */
	public static function customer_status_label( $status ) {
		$status = self::normalize_status_code( $status );
		$labels = self::customer_tracking_statuses();
		if ( isset( $labels[ $status ] ) ) {
			return $labels[ $status ];
		}
		if ( in_array( $status, array( 'pending', 'paypal_created' ), true ) ) {
			return __( 'Zahlung ausstehend', 'pckz-canonical-engine' );
		}
		if ( in_array( $status, array( 'captured' ), true ) ) {
			return $labels['paid'];
		}
		return self::status_label( $status );
	}

	/**
	 * Customer-facing status helper text for tracking page.
	 *
	 * @param string $status Stored status.
	 * @return string
	 */
	public static function customer_status_message( $status ) {
		$status = self::normalize_status_code( $status );
		$messages = array(
			'paid'          => __( 'Vielen Dank. Ihre Zahlung wurde bestätigt und Ihre Bestellung ist bei uns eingegangen.', 'pckz-canonical-engine' ),
			'in_progress'   => __( 'Ihr Auftrag wird aktuell geprüft und für die Produktion vorbereitet.', 'pckz-canonical-engine' ),
			'production'    => __( 'Ihre Bestellung befindet sich momentan in der Produktion.', 'pckz-canonical-engine' ),
			'ready_to_ship' => __( 'Ihre Bestellung ist fertig produziert und bereit für den Versand.', 'pckz-canonical-engine' ),
			'shipped'       => __( 'Ihre Bestellung wurde versendet und ist auf dem Weg zu Ihnen.', 'pckz-canonical-engine' ),
			'completed'     => __( 'Ihre Bestellung wurde erfolgreich abgeschlossen. Vielen Dank für Ihr Vertrauen.', 'pckz-canonical-engine' ),
			'cancelled'     => __( 'Ihre Bestellung wurde storniert. Bei Fragen hilft Ihnen unser Support gerne weiter.', 'pckz-canonical-engine' ),
			'pending'       => __( 'Wir warten aktuell auf den Zahlungseingang.', 'pckz-canonical-engine' ),
		);
		return $messages[ $status ] ?? __( 'Der Bestellstatus wurde aktualisiert.', 'pckz-canonical-engine' );
	}

	/**
	 * Collect shipping information for customer tracking page (when available).
	 *
	 * @param array $order Commerce order row.
	 * @return array
	 */
	public static function customer_shipping_summary( $order ) {
		$summary = array(
			'carrier'         => '',
			'tracking_number' => '',
			'tracking_url'    => '',
			'shipping_date'   => '',
			'has_data'        => false,
		);
		if ( ! is_array( $order ) || empty( $order['wc_order_id'] ) || ! function_exists( 'wc_get_order' ) ) {
			return $summary;
		}
		$wc_order = wc_get_order( absint( $order['wc_order_id'] ) );
		if ( ! $wc_order || ! is_object( $wc_order ) ) {
			return $summary;
		}

		$carriers = array();
		if ( method_exists( $wc_order, 'get_items' ) ) {
			$shipping_items = $wc_order->get_items( 'shipping' );
			if ( is_array( $shipping_items ) ) {
				foreach ( $shipping_items as $item ) {
					if ( is_object( $item ) && method_exists( $item, 'get_name' ) ) {
						$name = trim( (string) $item->get_name() );
						if ( '' !== $name ) {
							$carriers[] = $name;
						}
					}
				}
			}
		}
		$summary['carrier'] = implode( ', ', array_unique( $carriers ) );

		$tracking_number_keys = array( '_tracking_number', 'tracking_number', '_shipment_tracking_number', '_ywot_tracking_code' );
		$tracking_url_keys    = array( '_tracking_url', 'tracking_url', '_ywot_tracking_url', '_aftership_tracking_url' );
		$provider_keys        = array( '_tracking_provider', 'tracking_provider', '_ywot_tracking_provider' );
		foreach ( $tracking_number_keys as $meta_key ) {
			$value = trim( (string) $wc_order->get_meta( $meta_key, true ) );
			if ( '' !== $value ) {
				$summary['tracking_number'] = $value;
				break;
			}
		}
		foreach ( $tracking_url_keys as $meta_key ) {
			$value = esc_url_raw( (string) $wc_order->get_meta( $meta_key, true ) );
			if ( '' !== $value ) {
				$summary['tracking_url'] = $value;
				break;
			}
		}
		if ( '' === $summary['carrier'] ) {
			foreach ( $provider_keys as $meta_key ) {
				$value = trim( (string) $wc_order->get_meta( $meta_key, true ) );
				if ( '' !== $value ) {
					$summary['carrier'] = $value;
					break;
				}
			}
		}

		$shipment_items = $wc_order->get_meta( '_wc_shipment_tracking_items', true );
		if ( is_array( $shipment_items ) && ! empty( $shipment_items[0] ) ) {
			$item = $shipment_items[0];
			if ( is_array( $item ) ) {
				if ( '' === $summary['tracking_number'] && ! empty( $item['tracking_number'] ) ) {
					$summary['tracking_number'] = sanitize_text_field( $item['tracking_number'] );
				}
				if ( '' === $summary['tracking_url'] && ! empty( $item['custom_tracking_link'] ) ) {
					$summary['tracking_url'] = esc_url_raw( $item['custom_tracking_link'] );
				}
				if ( '' === $summary['carrier'] && ! empty( $item['tracking_provider'] ) ) {
					$summary['carrier'] = sanitize_text_field( $item['tracking_provider'] );
				}
			}
		}

		if ( method_exists( $wc_order, 'get_date_completed' ) ) {
			$date = $wc_order->get_date_completed();
			if ( $date && is_object( $date ) && method_exists( $date, 'date_i18n' ) ) {
				$summary['shipping_date'] = $date->date_i18n( 'd.m.Y H:i' );
			}
		}
		$summary['has_data'] = ( '' !== $summary['carrier'] || '' !== $summary['tracking_number'] || '' !== $summary['tracking_url'] || '' !== $summary['shipping_date'] );
		return $summary;
	}

	/**
	 * Tracking timeline model for customer page.
	 *
	 * @param string $status Current status.
	 * @return array<int,array{code:string,label:string,state:string}>
	 */
	public static function customer_tracking_timeline( $status ) {
		$status = self::normalize_status_code( $status );
		$steps  = array(
			'paid'          => __( 'Zahlung erhalten', 'pckz-canonical-engine' ),
			'in_progress'   => __( 'In Bearbeitung', 'pckz-canonical-engine' ),
			'production'    => __( 'Produktion', 'pckz-canonical-engine' ),
			'ready_to_ship' => __( 'Versandbereit', 'pckz-canonical-engine' ),
			'shipped'       => __( 'Versendet', 'pckz-canonical-engine' ),
			'completed'     => __( 'Abgeschlossen', 'pckz-canonical-engine' ),
		);
		$keys   = array_keys( $steps );
		$current_index = array_search( $status, $keys, true );
		$out = array();
		foreach ( $steps as $code => $label ) {
			$step_index = array_search( $code, $keys, true );
			$state = 'inactive';
			if ( false !== $current_index && false !== $step_index ) {
				if ( $step_index < $current_index ) {
					$state = 'complete';
				} elseif ( $step_index === $current_index ) {
					$state = 'current';
				}
			} elseif ( 'cancelled' === $status ) {
				$state = 'inactive';
			}
			$out[] = array(
				'code'  => $code,
				'label' => $label,
				'state' => $state,
			);
		}
		return $out;
	}

	/**
	 * Find a published page URL that embeds the product creator shortcode.
	 *
	 * @param int $product_id Creator product post ID.
	 * @return string Empty if not found.
	 */
	public static function find_creator_page_url_for_product( $product_id = 0 ) {
		$product_id = absint( $product_id );
		$pages      = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
			)
		);
		foreach ( $pages as $page ) {
			$content = (string) $page->post_content;
			$has     = has_shortcode( $content, 'product_creator' ) || has_shortcode( $content, 'pckzce_creator' );
			if ( ! $has ) {
				continue;
			}
			if ( $product_id && preg_match( '/\[product_creator[^\]]*id\s*=\s*["\']?(\d+)/i', $content, $m ) ) {
				if ( absint( $m[1] ) !== $product_id ) {
					continue;
				}
			}
			$url = get_permalink( $page );
			if ( $url ) {
				return $url;
			}
		}
		$default_page = absint( PCKZ_Settings::get( 'creator_page_id', 0 ) );
		if ( $default_page ) {
			$url = get_permalink( $default_page );
			return $url ? $url : '';
		}
		return '';
	}

	/**
	 * URL where the customer configures / pays (konfigurator page).
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	public static function resolve_creator_page_url( $product_id = 0 ) {
		$page_id = absint( PCKZ_Settings::get( 'creator_page_id', 0 ) );
		if ( $page_id ) {
			$url = get_permalink( $page_id );
			if ( $url ) {
				return $url;
			}
		}
		$found = self::find_creator_page_url_for_product( $product_id );
		if ( $found ) {
			return $found;
		}
		$configured = (string) PCKZ_Settings::get( 'paypal_success_url', '' );
		if ( $configured && self::url_contains_creator_shortcode_page( $configured ) ) {
			return $configured;
		}
		return $configured ? $configured : home_url( '/' );
	}

	/**
	 * URL of the public tracking page shortcode.
	 *
	 * @return string
	 */
	public static function find_tracking_page_url() {
		if ( ! function_exists( 'get_posts' ) ) {
			return '';
		}
		$pages = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
			)
		);
		foreach ( $pages as $page ) {
			$content = (string) $page->post_content;
			$has     = ( function_exists( 'has_shortcode' ) && has_shortcode( $content, 'pckz_order_tracking' ) )
				|| ( function_exists( 'has_shortcode' ) && has_shortcode( $content, 'pckz_bestellung_verfolgen' ) );
			if ( ! $has ) {
				continue;
			}
			$url = function_exists( 'get_permalink' ) ? get_permalink( $page ) : '';
			if ( $url ) {
				return $url;
			}
		}
		return '';
	}

	/**
	 * Build customer tracking URL including public tracking ID when available.
	 *
	 * @param int $order_id Internal order ID.
	 * @return string
	 */
	public static function resolve_tracking_page_url( $order_id = 0 ) {
		$url = self::find_tracking_page_url();
		if ( ! $url ) {
			$url = home_url( '/' );
		}
		$order_id = absint( $order_id );
		if ( $order_id > 0 ) {
			$url = add_query_arg(
				array(
					'order' => self::format_order_number( $order_id ),
				),
				$url
			);
		}
		return $url;
	}

	/**
	 * @param string $url URL.
	 * @return bool
	 */
	private static function url_contains_creator_shortcode_page( $url ) {
		$post_id = url_to_postid( $url );
		if ( ! $post_id ) {
			return false;
		}
		$content = (string) get_post_field( 'post_content', $post_id );
		return false !== strpos( $content, 'product_creator' ) || false !== strpos( $content, 'pckzce_creator' );
	}

	/**
	 * Redirect target after successful PayPal capture (configurator + success flag).
	 *
	 * @param array $commerce_order Commerce row.
	 * @return string
	 */
	public static function resolve_post_payment_redirect( $commerce_order ) {
		$base = ! empty( $commerce_order['return_url'] )
			? (string) $commerce_order['return_url']
			: self::resolve_creator_page_url( (int) ( $commerce_order['product_id'] ?? 0 ) );
		$base = remove_query_arg( array( 'pckz_paypal', 'token', 'PayerID' ), $base );
		return add_query_arg(
			array(
				'pckz_paid'  => '1',
				'pckz_order' => (int) ( $commerce_order['id'] ?? 0 ),
			),
			$base
		);
	}

	/**
	 * Human-readable label for a stored status (includes legacy PayPal codes).
	 *
	 * @param string $status Raw status.
	 * @return string
	 */
	public static function status_label( $status ) {
		$status = self::normalize_status_code( $status );
		$labels = self::workflow_statuses();
		if ( isset( $labels[ $status ] ) ) {
			return $labels[ $status ];
		}
		$legacy = array(
			'paypal_created' => $labels['pending'],
			'captured'       => $labels['paid'],
			'failed'         => __( 'Zahlung fehlgeschlagen', 'pckz-canonical-engine' ),
		);
		return $legacy[ $status ] ?? $status;
	}

	/**
	 * Whether a status may be saved on an order row.
	 *
	 * @param string $status Status code.
	 * @return bool
	 */
	public static function is_valid_workflow_status( $status ) {
		return array_key_exists( sanitize_key( (string) $status ), self::workflow_statuses() );
	}

	/**
	 * Update order workflow status (admin).
	 *
	 * @param int    $id     Order ID.
	 * @param string $status New status.
	 * @return true|WP_Error
	 */
	public static function set_workflow_status( $id, $status ) {
		$status = sanitize_key( (string) $status );
		if ( ! self::is_valid_workflow_status( $status ) ) {
			return new WP_Error( 'invalid_status', __( 'Ungültiger Bestellstatus.', 'pckz-canonical-engine' ) );
		}
		$row = self::get_order( $id );
		if ( ! $row ) {
			return new WP_Error( 'not_found', __( 'Bestellung nicht gefunden.', 'pckz-canonical-engine' ) );
		}
		$old_status = self::normalize_status_code( $row['status'] ?? '' );
		$new_status = self::normalize_status_code( $status );
		if ( $old_status === $new_status ) {
			return true;
		}
		self::update_order( $id, array( 'status' => $new_status ) );
		$row['status'] = $new_status;
		self::send_customer_status_update_email( $row, $new_status, $old_status );
		return true;
	}

	/**
	 * Persist customer email/note on design meta.
	 *
	 * @param int    $design_id Design ID.
	 * @param string $email     Email.
	 * @param string $note      Customer note.
	 */
	public static function attach_customer_meta_to_design( $design_id, $email, $note = '', $details = array() ) {
		$design = PCKZ_Design_Storage::get_design( $design_id );
		if ( ! $design ) {
			return;
		}
		if ( ! empty( $details ) && is_array( $details ) ) {
			$details = self::sanitize_customer_details( $details );
			$email   = $details['email'] ?: $email;
			$note    = $details['wishes'] ?: $note;
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
		if ( ! empty( $details ) ) {
			$meta['customer_details'] = $details;
		}
		if ( empty( $meta['selections'] ) || ! is_array( $meta['selections'] ) ) {
			$meta['selections'] = array();
		}
		$meta['selections']['customer_email']  = $meta['customer_email'];
		$meta['selections']['customer_wishes'] = $meta['customer_note'];
		if ( ! empty( $details ) ) {
			$meta['selections']['customer_details'] = $details;
		}
		PCKZ_Design_Storage::update_meta( $design_id, $meta );
	}

	/**
	 * Apply customer address to WooCommerce order.
	 *
	 * @param WC_Order $order   Order.
	 * @param array    $details Customer details.
	 */
	public static function apply_customer_details_to_wc_order( $order, $details ) {
		if ( ! $order instanceof WC_Order || empty( $details ) ) {
			return;
		}
		$street_line = trim( ( $details['street'] ?? '' ) . ' ' . ( $details['house_number'] ?? '' ) );
		$address     = array(
			'first_name' => $details['first_name'] ?? '',
			'last_name'  => $details['last_name'] ?? '',
			'email'      => $details['email'] ?? '',
			'phone'      => $details['phone'] ?? '',
			'address_1'  => $street_line,
			'address_2'  => '',
			'city'       => $details['city'] ?? '',
			'postcode'   => $details['postal_code'] ?? '',
			'country'    => $details['country'] ?? 'DE',
		);
		$order->set_billing( $address );
		$order->set_shipping( $address );
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
		$order = self::get_order( $commerce_order_id );
		$base  = $order && ! empty( $order['return_url'] )
			? (string) $order['return_url']
			: self::resolve_creator_page_url( (int) ( $order['product_id'] ?? 0 ) );
		return add_query_arg(
			array(
				'pckz_paypal' => 'return',
				'pckz_order'  => absint( $commerce_order_id ),
			),
			$base
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

		$details = self::decode_customer_details( $commerce_order['customer_details'] ?? '' );
		if ( empty( $details ) && ! empty( $commerce_order['customer_email'] ) ) {
			$details = self::sanitize_customer_details(
				array(
					'email'  => $commerce_order['customer_email'],
					'wishes' => $commerce_order['customer_note'] ?? '',
				)
			);
		}

		self::attach_customer_meta_to_design(
			$design_id,
			$commerce_order['customer_email'] ?? '',
			$commerce_order['customer_note'] ?? '',
			$details
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
				self::apply_customer_details_to_wc_order( $order, $details );
				$order->set_status( 'processing', __( 'PayPal-Zahlung bestätigt.', 'pckz-canonical-engine' ) );
				$order->set_payment_method( 'pckz_paypal' );
				$order->set_payment_method_title( 'PayPal' );
				$order->set_total( (float) ( $commerce_order['amount'] ?? 0 ) );
				$order->update_meta_data( '_pckz_paypal_capture_id', $commerce_order['paypal_capture_id'] ?? '' );
				$order->update_meta_data( '_pckz_paypal_order_id', $commerce_order['paypal_order_id'] ?? '' );
				$order->update_meta_data( '_pckz_customer_email', $commerce_order['customer_email'] ?? '' );
				$order->update_meta_data( '_pckz_customer_wishes', $commerce_order['customer_note'] ?? '' );
				$order->update_meta_data( '_pckz_customer_details', self::encode_customer_details( $details ) );
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
					'customer_email'   => $commerce_order['customer_email'] ?? '',
					'customer_wishes'  => $commerce_order['customer_note'] ?? '',
					'customer_details' => $details,
				);

				if ( $item ) {
					$item->add_meta_data( '_pckz_design_id', $design_id, true );
					$item->add_meta_data( '_pckz_customer_email', $commerce_order['customer_email'] ?? '', true );
					$item->add_meta_data( '_pckz_customer_wishes', $commerce_order['customer_note'] ?? '', true );
					$item->add_meta_data( '_pckz_customer_details', self::encode_customer_details( $details ), true );
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
		$details = self::decode_customer_details( $commerce_order['customer_details'] ?? '' );
		$to      = $details['email'] ?? ( $commerce_order['customer_email'] ?? '' );
		if ( ! $to ) {
			return;
		}
		$subject = sprintf(
			/* translators: %s: site name */
			__( 'Bestellbestätigung – %s', 'pckz-canonical-engine' ),
			get_bloginfo( 'name' )
		);
		$tracking_id  = self::format_order_number( (int) ( $commerce_order['id'] ?? 0 ) );
		$tracking_url = self::resolve_tracking_page_url( (int) ( $commerce_order['id'] ?? 0 ) );
		$confirmed_at = function_exists( 'wp_date' ) ? wp_date( 'd.m.Y H:i' ) : gmdate( 'd.m.Y H:i' );
		$body  = '<p>' . esc_html__( 'Vielen Dank für Ihre Bestellung. Ihre PayPal-Zahlung wurde bestätigt und wir starten nun mit der Bearbeitung.', 'pckz-canonical-engine' ) . '</p>';
		$body .= '<p><strong>' . esc_html__( 'Ihre Tracking-ID:', 'pckz-canonical-engine' ) . '</strong> ' . esc_html( $tracking_id ) . '</p>';
		$body .= '<p><strong>' . esc_html__( 'Bestellung bestätigt am:', 'pckz-canonical-engine' ) . '</strong> ' . esc_html( $confirmed_at ) . '</p>';
		if ( $wc_order_id ) {
			$body .= '<p><strong>' . esc_html__( 'Interne Referenz:', 'pckz-canonical-engine' ) . '</strong> #' . esc_html( (string) $wc_order_id ) . '</p>';
		}
		if ( $tracking_url ) {
			$body .= '<p><a href="' . esc_url( $tracking_url ) . '" style="display:inline-block;padding:10px 14px;background:#111827;color:#ffffff;border-radius:6px;text-decoration:none;">' . esc_html__( 'Bestellung jetzt verfolgen', 'pckz-canonical-engine' ) . '</a></p>';
		}
		$body .= '<p>' . esc_html__( 'Über die Tracking-Seite sehen Sie jederzeit den aktuellen Produktions- und Versandstatus Ihrer Bestellung.', 'pckz-canonical-engine' ) . '</p>';
		if ( ! empty( $details['first_name'] ) ) {
			$countries = self::checkout_countries();
			$street    = trim( ( $details['street'] ?? '' ) . ' ' . ( $details['house_number'] ?? '' ) );
			$body     .= '<p><strong>' . esc_html__( 'Lieferadresse:', 'pckz-canonical-engine' ) . '</strong><br>';
			$body     .= esc_html( trim( ( $details['first_name'] ?? '' ) . ' ' . ( $details['last_name'] ?? '' ) ) ) . '<br>';
			$body     .= esc_html( $street ) . '<br>';
			$body     .= esc_html( ( $details['postal_code'] ?? '' ) . ' ' . ( $details['city'] ?? '' ) ) . '<br>';
			$body     .= esc_html( $countries[ $details['country'] ?? '' ] ?? ( $details['country'] ?? '' ) ) . '</p>';
		}
		$wishes = $details['wishes'] ?? ( $commerce_order['customer_note'] ?? '' );
		if ( $wishes ) {
			$body .= '<p><strong>' . esc_html__( 'Ihre Wünsche:', 'pckz-canonical-engine' ) . '</strong><br>' . esc_html( $wishes ) . '</p>';
		}
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		wp_mail( $to, $subject, $body, $headers );
	}

	/**
	 * Send status transition email to customer when admin changes workflow status.
	 *
	 * @param array  $order      Commerce row.
	 * @param string $new_status New canonical status.
	 * @param string $old_status Previous canonical status.
	 */
	public static function send_customer_status_update_email( $order, $new_status, $old_status = '' ) {
		$new_status = self::normalize_status_code( $new_status );
		$old_status = self::normalize_status_code( $old_status );
		if ( ! $new_status || $new_status === $old_status ) {
			return;
		}
		$templates = array(
			'paid' => array(
				'subject' => __( 'Zahlung erhalten', 'pckz-canonical-engine' ),
				'lead'    => __( 'Vielen Dank! Ihre Zahlung ist erfolgreich bei uns eingegangen.', 'pckz-canonical-engine' ),
			),
			'in_progress' => array(
				'subject' => __( 'Bestellung in Bearbeitung', 'pckz-canonical-engine' ),
				'lead'    => __( 'Ihre Bestellung wird aktuell bearbeitet und für die Produktion vorbereitet.', 'pckz-canonical-engine' ),
			),
			'production' => array(
				'subject' => __( 'Produktion gestartet', 'pckz-canonical-engine' ),
				'lead'    => __( 'Gute Nachrichten: Ihre Bestellung befindet sich jetzt in der Produktion.', 'pckz-canonical-engine' ),
			),
			'ready_to_ship' => array(
				'subject' => __( 'Bestellung versandbereit', 'pckz-canonical-engine' ),
				'lead'    => __( 'Ihre Bestellung ist versandbereit und wird zeitnah an den Versand übergeben.', 'pckz-canonical-engine' ),
			),
			'shipped' => array(
				'subject' => __( 'Bestellung versendet', 'pckz-canonical-engine' ),
				'lead'    => __( 'Ihre Bestellung wurde versendet und ist auf dem Weg zu Ihnen.', 'pckz-canonical-engine' ),
			),
			'completed' => array(
				'subject' => __( 'Bestellung abgeschlossen', 'pckz-canonical-engine' ),
				'lead'    => __( 'Ihre Bestellung wurde erfolgreich abgeschlossen. Vielen Dank für Ihr Vertrauen.', 'pckz-canonical-engine' ),
			),
			'cancelled' => array(
				'subject' => __( 'Bestellung storniert', 'pckz-canonical-engine' ),
				'lead'    => __( 'Ihre Bestellung wurde storniert. Bei Fragen hilft Ihnen unser Support gerne weiter.', 'pckz-canonical-engine' ),
			),
		);
		if ( empty( $templates[ $new_status ] ) ) {
			return;
		}
		$details = self::decode_customer_details( $order['customer_details'] ?? '' );
		$to      = sanitize_email( $details['email'] ?? ( $order['customer_email'] ?? '' ) );
		if ( ! $to || ! is_email( $to ) ) {
			return;
		}
		$order_number = self::format_order_number( (int) ( $order['id'] ?? 0 ) );
		$status_label = self::customer_status_label( $new_status );
		$tracking_url = self::resolve_tracking_page_url( (int) ( $order['id'] ?? 0 ) );
		$subject = sprintf(
			/* translators: 1: short status title, 2: shop name */
			__( '%1$s – %2$s', 'pckz-canonical-engine' ),
			$templates[ $new_status ]['subject'],
			get_bloginfo( 'name' )
		);
		$body  = '<p>' . esc_html( $templates[ $new_status ]['lead'] ) . '</p>';
		$body .= '<p><strong>' . esc_html__( 'Tracking-ID:', 'pckz-canonical-engine' ) . '</strong> ' . esc_html( $order_number ) . '</p>';
		$body .= '<p><strong>' . esc_html__( 'Aktueller Status:', 'pckz-canonical-engine' ) . '</strong> ';
		$body .= '<span style="display:inline-block;padding:6px 10px;border-radius:999px;background:#111827;color:#ffffff;font-weight:600;">' . esc_html( $status_label ) . '</span></p>';
		$body .= '<p>' . esc_html( self::customer_status_message( $new_status ) ) . '</p>';
		if ( $tracking_url ) {
			$body .= '<p><a href="' . esc_url( $tracking_url ) . '" style="display:inline-block;padding:10px 14px;background:#111827;color:#ffffff;border-radius:6px;text-decoration:none;">' . esc_html__( 'Status jetzt prüfen', 'pckz-canonical-engine' ) . '</a></p>';
		}
		$body .= '<p>' . esc_html__( 'Sie können den Bestellstatus jederzeit über die Tracking-Seite mit Ihrer Tracking-ID abrufen.', 'pckz-canonical-engine' ) . '</p>';
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
			'checkoutPaypalOnly'   => self::checkout_paypal_only(),
			'requireEmail'         => true,
			'successUrl'           => (string) PCKZ_Settings::get( 'paypal_success_url', '' ),
			'cancelUrl'            => (string) PCKZ_Settings::get( 'paypal_cancel_url', '' ),
		);
	}
}
