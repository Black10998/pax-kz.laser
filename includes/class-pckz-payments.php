<?php
/**
 * Payment architecture expansion with provider abstraction and Stripe support.
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Interface PCKZ_Payment_Provider
 */
interface PCKZ_Payment_Provider {
	public function get_slug();
	public function get_label();
	public function supports( $capability );
	public function create_one_time_checkout( $payload );
	public function create_subscription_checkout( $payload );
	public function handle_webhook( $request_body, $headers = array() );
}

/**
 * PayPal provider adapter (wraps existing production flow).
 */
class PCKZ_Payment_Provider_PayPal implements PCKZ_Payment_Provider {
	public function get_slug() {
		return 'paypal';
	}

	public function get_label() {
		return 'PayPal';
	}

	public function supports( $capability ) {
		return in_array( $capability, array( 'one_time' ), true );
	}

	public function create_one_time_checkout( $payload ) {
		if ( ! class_exists( 'PCKZ_Commerce' ) ) {
			return new WP_Error( 'commerce_missing', __( 'Commerce module missing.', 'pckz-canonical-engine' ) );
		}
		$result = PCKZ_Commerce::create_paypal_checkout( (array) $payload );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$result['provider'] = 'paypal';
		return $result;
	}

	public function create_subscription_checkout( $payload ) {
		return new WP_Error( 'not_supported', __( 'PayPal subscription checkout is not implemented in this architecture layer.', 'pckz-canonical-engine' ) );
	}

	public function handle_webhook( $request_body, $headers = array() ) {
		unset( $request_body, $headers );
		return new WP_Error( 'not_supported', __( 'PayPal webhook handling is not implemented in this architecture layer.', 'pckz-canonical-engine' ) );
	}
}

/**
 * Stripe provider implementation.
 */
class PCKZ_Payment_Provider_Stripe implements PCKZ_Payment_Provider {

	/**
	 * Stripe API base.
	 *
	 * @var string
	 */
	private $api_base = 'https://api.stripe.com/v1';

	public function get_slug() {
		return 'stripe';
	}

	public function get_label() {
		return 'Stripe';
	}

	public function supports( $capability ) {
		return in_array( $capability, array( 'one_time', 'subscription', 'apple_pay', 'google_pay', 'card' ), true );
	}

	/**
	 * Whether Stripe is configured and enabled.
	 *
	 * @return bool
	 */
	private function is_enabled() {
		$settings = PCKZ_Settings::get_all();
		if ( empty( $settings['payments_enable_stripe'] ) ) {
			return false;
		}
		return '' !== trim( (string) ( $settings['payments_stripe_secret_key'] ?? '' ) );
	}

	/**
	 * Stripe secret key.
	 *
	 * @return string
	 */
	private function secret_key() {
		$settings = PCKZ_Settings::get_all();
		return trim( (string) ( $settings['payments_stripe_secret_key'] ?? '' ) );
	}

	/**
	 * Stripe publishable key.
	 *
	 * @return string
	 */
	private function publishable_key() {
		$settings = PCKZ_Settings::get_all();
		return trim( (string) ( $settings['payments_stripe_publishable_key'] ?? '' ) );
	}

	/**
	 * Build URL used for Stripe return paths.
	 *
	 * @param string $type        success|cancel.
	 * @param int    $commerce_id Commerce order ID.
	 * @param string $page_url    Current page URL.
	 * @return string
	 */
	private function return_url( $type, $commerce_id, $page_url ) {
		$settings = PCKZ_Settings::get_all();
		$configured = 'success' === $type
			? (string) ( $settings['payments_stripe_success_url'] ?? '' )
			: (string) ( $settings['payments_stripe_cancel_url'] ?? '' );
		$base = $configured ? $configured : ( $page_url ? $page_url : home_url( '/' ) );
		$base = remove_query_arg(
			array(
				'pckz_paypal',
				'pckz_payment',
				'pckz_order',
				'token',
				'PayerID',
				'session_id',
			),
			$base
		);
		if ( 'success' === $type ) {
			$url = add_query_arg(
				array(
					'pckz_payment' => 'stripe_return',
					'pckz_order'   => absint( $commerce_id ),
					'session_id'   => '{CHECKOUT_SESSION_ID}',
				),
				$base
			);
			return str_replace( rawurlencode( '{CHECKOUT_SESSION_ID}' ), '{CHECKOUT_SESSION_ID}', $url );
		}
		return add_query_arg(
			array(
				'pckz_payment' => 'stripe_cancel',
				'pckz_order'   => absint( $commerce_id ),
			),
			$base
		);
	}

	/**
	 * Stripe API request helper.
	 *
	 * @param string $method         HTTP method.
	 * @param string $path           API path.
	 * @param array  $form_params    Form body for POST.
	 * @param string $idempotency_key Optional idempotency key.
	 * @return array|WP_Error
	 */
	private function stripe_request( $method, $path, $form_params = array(), $idempotency_key = '' ) {
		if ( ! $this->is_enabled() ) {
			return new WP_Error( 'stripe_disabled', __( 'Stripe is not enabled or configured.', 'pckz-canonical-engine' ) );
		}
		$key = $this->secret_key();
		$url = $this->api_base . $path;
		$headers = array(
			'Authorization' => 'Bearer ' . $key,
		);
		if ( $idempotency_key ) {
			$headers['Idempotency-Key'] = $idempotency_key;
		}
		$args = array(
			'headers' => $headers,
			'timeout' => 45,
		);
		$method = strtoupper( sanitize_text_field( $method ) );
		if ( 'GET' === $method ) {
			$response = wp_remote_get( $url, $args );
		} else {
			$args['method']  = $method;
			$args['body']    = $form_params;
			$response = wp_remote_post( $url, $args );
		}
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			$body = array();
		}
		if ( $code < 200 || $code >= 300 ) {
			$message = __( 'Stripe API request failed.', 'pckz-canonical-engine' );
			if ( ! empty( $body['error']['message'] ) ) {
				$message = sanitize_text_field( (string) $body['error']['message'] );
			}
			return new WP_Error(
				'stripe_api_error',
				$message,
				array(
					'status' => $code,
					'body'   => $body,
				)
			);
		}
		return $body;
	}

	/**
	 * Parse Stripe signature header map.
	 *
	 * @param string $header Header value.
	 * @return array
	 */
	private static function parse_signature_header( $header ) {
		$out = array();
		foreach ( explode( ',', (string) $header ) as $part ) {
			$pair = explode( '=', trim( $part ), 2 );
			if ( 2 !== count( $pair ) ) {
				continue;
			}
			$key = trim( $pair[0] );
			$value = trim( $pair[1] );
			if ( '' === $key || '' === $value ) {
				continue;
			}
			if ( ! isset( $out[ $key ] ) ) {
				$out[ $key ] = array();
			}
			$out[ $key ][] = $value;
		}
		return $out;
	}

	/**
	 * Header helper.
	 *
	 * @param array  $headers Header map.
	 * @param string $name    Header name.
	 * @return string
	 */
	private static function header_value( $headers, $name ) {
		$name = strtolower( (string) $name );
		foreach ( (array) $headers as $key => $value ) {
			if ( strtolower( (string) $key ) !== $name ) {
				continue;
			}
			if ( is_array( $value ) ) {
				return sanitize_text_field( (string) reset( $value ) );
			}
			return sanitize_text_field( (string) $value );
		}
		return '';
	}

	/**
	 * Verify Stripe webhook signature.
	 *
	 * @param string $request_body Raw request body.
	 * @param array  $headers      Header map.
	 * @return true|WP_Error
	 */
	private function verify_webhook_signature( $request_body, $headers ) {
		$settings = PCKZ_Settings::get_all();
		$secret   = trim( (string) ( $settings['payments_stripe_webhook_secret'] ?? '' ) );
		if ( '' === $secret ) {
			return new WP_Error( 'missing_webhook_secret', __( 'Stripe webhook secret is missing.', 'pckz-canonical-engine' ) );
		}
		$signature_header = self::header_value( $headers, 'stripe-signature' );
		if ( '' === $signature_header ) {
			return new WP_Error( 'missing_webhook_signature', __( 'Missing Stripe signature header.', 'pckz-canonical-engine' ) );
		}
		$parts = self::parse_signature_header( $signature_header );
		$ts    = isset( $parts['t'][0] ) ? (int) $parts['t'][0] : 0;
		$v1    = isset( $parts['v1'] ) ? (array) $parts['v1'] : array();
		if ( ! $ts || empty( $v1 ) ) {
			return new WP_Error( 'invalid_webhook_signature', __( 'Invalid Stripe signature payload.', 'pckz-canonical-engine' ) );
		}
		$tolerance = max( 60, min( 1800, (int) ( $settings['payments_stripe_webhook_tolerance'] ?? 300 ) ) );
		if ( abs( time() - $ts ) > $tolerance ) {
			return new WP_Error( 'webhook_signature_expired', __( 'Stripe webhook signature expired.', 'pckz-canonical-engine' ) );
		}
		$expected = hash_hmac( 'sha256', $ts . '.' . (string) $request_body, $secret );
		$ok = false;
		foreach ( $v1 as $sig ) {
			if ( hash_equals( $expected, (string) $sig ) ) {
				$ok = true;
				break;
			}
		}
		if ( ! $ok ) {
			return new WP_Error( 'webhook_signature_mismatch', __( 'Stripe webhook signature mismatch.', 'pckz-canonical-engine' ) );
		}
		return true;
	}

	/**
	 * Resolve commerce order for Stripe session.
	 *
	 * @param array $session Stripe checkout session.
	 * @return array|WP_Error
	 */
	private function resolve_commerce_order_for_session( $session ) {
		if ( ! class_exists( 'PCKZ_Commerce' ) ) {
			return new WP_Error( 'commerce_missing', __( 'Commerce module missing.', 'pckz-canonical-engine' ) );
		}
		$commerce_id = absint( $session['metadata']['commerce_id'] ?? 0 );
		if ( $commerce_id > 0 ) {
			$order = PCKZ_Commerce::get_order( $commerce_id );
			if ( $order ) {
				return $order;
			}
		}
		$session_id = sanitize_text_field( (string) ( $session['id'] ?? '' ) );
		if ( '' === $session_id ) {
			return new WP_Error( 'stripe_session_missing', __( 'Stripe session ID is missing.', 'pckz-canonical-engine' ) );
		}
		$order = PCKZ_Commerce::get_order_by_payment_reference( $session_id, 'stripe' );
		if ( ! $order ) {
			return new WP_Error( 'order_not_found', __( 'Commerce order not found for Stripe session.', 'pckz-canonical-engine' ) );
		}
		return $order;
	}

	/**
	 * Extract a scalar identifier from Stripe session values.
	 *
	 * Stripe can return expanded objects (arrays) for keys such as
	 * `payment_intent` when `expand[]=payment_intent` is used. Casting such
	 * arrays to string triggers PHP warnings ("Array to string conversion").
	 *
	 * @param mixed  $value   Raw value from Stripe session.
	 * @param string $sub_key Preferred object key.
	 * @return string
	 */
	private function session_scalar_id( $value, $sub_key = 'id' ) {
		if ( is_array( $value ) ) {
			if ( isset( $value[ $sub_key ] ) && ! is_array( $value[ $sub_key ] ) && ! is_object( $value[ $sub_key ] ) ) {
				return sanitize_text_field( (string) $value[ $sub_key ] );
			}
			return '';
		}
		if ( is_object( $value ) ) {
			$value = (array) $value;
			if ( isset( $value[ $sub_key ] ) && ! is_array( $value[ $sub_key ] ) && ! is_object( $value[ $sub_key ] ) ) {
				return sanitize_text_field( (string) $value[ $sub_key ] );
			}
			return '';
		}
		return sanitize_text_field( (string) $value );
	}

	/**
	 * Apply successful Stripe session to commerce order.
	 *
	 * @param array $session Session object.
	 * @return array|WP_Error
	 */
	private function apply_paid_checkout_session( $session ) {
		$order = $this->resolve_commerce_order_for_session( $session );
		if ( is_wp_error( $order ) ) {
			return $order;
		}
		$order_id = (int) ( $order['id'] ?? 0 );
		if ( ! $order_id ) {
			return new WP_Error( 'order_not_found', __( 'Commerce order missing.', 'pckz-canonical-engine' ) );
		}
		$current_status = sanitize_key( (string) ( $order['status'] ?? '' ) );
		if ( 'paid' === $current_status ) {
			return array(
				'ok'         => true,
				'commerce_id'=> $order_id,
				'already_paid' => true,
			);
		}
		$session_id = $this->session_scalar_id( $session['id'] ?? '' );
		$intent_id  = $this->session_scalar_id( $session['payment_intent'] ?? '', 'id' );
		PCKZ_Commerce::update_order(
			$order_id,
			array(
				'payment_provider' => 'stripe',
				'paypal_order_id'  => $session_id,
				'paypal_capture_id'=> $intent_id,
				'status'           => 'paid',
			)
		);
		$order = PCKZ_Commerce::get_order( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'order_reload_failed', __( 'Could not reload commerce order after Stripe update.', 'pckz-canonical-engine' ) );
		}
		$result = PCKZ_Commerce::finalize_paid_order( $order );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return array(
			'ok'          => true,
			'commerce_id' => (int) ( $result['commerce_id'] ?? $order_id ),
			'wc_order_id' => (int) ( $result['wc_order_id'] ?? 0 ),
		);
	}

	/**
	 * Mark failed/expired Stripe checkout.
	 *
	 * @param array  $session Session object.
	 * @param string $status  Local status.
	 */
	private function apply_failed_checkout_session( $session, $status = 'failed' ) {
		if ( ! class_exists( 'PCKZ_Commerce' ) ) {
			return;
		}
		$order = $this->resolve_commerce_order_for_session( $session );
		if ( is_wp_error( $order ) ) {
			return;
		}
		$order_id = (int) ( $order['id'] ?? 0 );
		if ( ! $order_id ) {
			return;
		}
		if ( in_array( sanitize_key( (string) ( $order['status'] ?? '' ) ), array( 'paid', 'captured', 'completed' ), true ) ) {
			return;
		}
		PCKZ_Commerce::update_order(
			$order_id,
			array(
				'payment_provider' => 'stripe',
				'paypal_order_id'  => sanitize_text_field( (string) ( $session['id'] ?? '' ) ),
				'status'           => sanitize_key( $status ),
			)
		);
	}

	/**
	 * Retrieve Stripe checkout session.
	 *
	 * @param string $session_id Session ID.
	 * @return array|WP_Error
	 */
	public function retrieve_checkout_session( $session_id ) {
		$session_id = sanitize_text_field( (string) $session_id );
		if ( '' === $session_id ) {
			return new WP_Error( 'missing_session_id', __( 'Stripe session ID is required.', 'pckz-canonical-engine' ) );
		}
		$path = '/checkout/sessions/' . rawurlencode( $session_id ) . '?expand[]=payment_intent';
		return $this->stripe_request( 'GET', $path );
	}

	/**
	 * Confirm Stripe checkout session and finalize if paid.
	 *
	 * @param string $session_id Session ID.
	 * @return array|WP_Error
	 */
	public function confirm_checkout_session( $session_id ) {
		$session = $this->retrieve_checkout_session( $session_id );
		if ( is_wp_error( $session ) ) {
			return $session;
		}
		$payment_status = sanitize_key( (string) ( $session['payment_status'] ?? '' ) );
		if ( 'paid' !== $payment_status ) {
			return new WP_Error(
				'stripe_not_paid',
				__( 'Stripe checkout session is not paid yet.', 'pckz-canonical-engine' ),
				array( 'session' => $session )
			);
		}
		return $this->apply_paid_checkout_session( $session );
	}

	public function create_one_time_checkout( $payload ) {
		if ( ! class_exists( 'PCKZ_Commerce' ) ) {
			return new WP_Error( 'commerce_missing', __( 'Commerce module missing.', 'pckz-canonical-engine' ) );
		}
		if ( ! $this->is_enabled() ) {
			return new WP_Error( 'stripe_disabled', __( 'Stripe is not enabled.', 'pckz-canonical-engine' ) );
		}
		$amount = (float) ( $payload['amount'] ?? 0 );
		if ( $amount <= 0 ) {
			return new WP_Error( 'invalid_amount', __( 'Invalid Stripe checkout amount.', 'pckz-canonical-engine' ) );
		}
		$currency = PCKZ_Commerce::sanitize_currency_code( (string) ( $payload['currency'] ?? 'EUR' ) );
		$page_url = esc_url_raw( (string) ( $payload['page_url'] ?? '' ) );
		$commerce_id = absint( $payload['commerce_id'] ?? 0 );
		$line_name = sanitize_text_field( (string) ( $payload['description'] ?? __( 'Personalisiertes Produkt', 'pckz-canonical-engine' ) ) );
		$unit_amount = (int) round( $amount * 100 );
		$session_payload = array(
			'mode'                               => 'payment',
			'payment_method_types[0]'            => 'card',
			'success_url'                        => $this->return_url( 'success', $commerce_id, $page_url ),
			'cancel_url'                         => $this->return_url( 'cancel', $commerce_id, $page_url ),
			'line_items[0][quantity]'            => 1,
			'line_items[0][price_data][currency]' => strtolower( $currency ),
			'line_items[0][price_data][unit_amount]' => (string) $unit_amount,
			'line_items[0][price_data][product_data][name]' => $line_name,
			'client_reference_id'                => (string) $commerce_id,
			'metadata[commerce_id]'              => (string) $commerce_id,
			'metadata[design_id]'                => (string) absint( $payload['design_id'] ?? 0 ),
			'metadata[product_id]'               => (string) absint( $payload['product_id'] ?? 0 ),
			'metadata[payment_provider]'         => 'stripe',
		);
		$idempotency = 'pckz-stripe-checkout-' . md5(
			wp_json_encode(
				array(
					$commerce_id,
					$unit_amount,
					$currency,
					(string) ( $payload['design_id'] ?? 0 ),
				)
			)
		);
		$result = $this->stripe_request( 'POST', '/checkout/sessions', $session_payload, $idempotency );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$session_id = sanitize_text_field( (string) ( $result['id'] ?? '' ) );
		$url        = esc_url_raw( (string) ( $result['url'] ?? '' ) );
		if ( '' === $session_id || '' === $url ) {
			return new WP_Error( 'stripe_checkout_missing_url', __( 'Stripe checkout session URL missing.', 'pckz-canonical-engine' ) );
		}
		return array(
			'provider'          => 'stripe',
			'approve_url'       => $url,
			'stripe_session_id' => $session_id,
			'paypal_order_id'   => $session_id, // kept for schema compatibility.
			'status'            => sanitize_key( (string) ( $result['status'] ?? 'open' ) ),
			'publishable_key'   => $this->publishable_key(),
		);
	}

	public function create_subscription_checkout( $payload ) {
		if ( ! $this->is_enabled() ) {
			return new WP_Error( 'stripe_disabled', __( 'Stripe is not enabled.', 'pckz-canonical-engine' ) );
		}
		$price_id = sanitize_text_field( (string) ( $payload['stripe_price_id'] ?? '' ) );
		if ( '' === $price_id ) {
			return new WP_Error( 'missing_price_id', __( 'Stripe subscription price ID missing.', 'pckz-canonical-engine' ) );
		}
		$page_url = esc_url_raw( (string) ( $payload['page_url'] ?? '' ) );
		$commerce_id = absint( $payload['commerce_id'] ?? 0 );
		$session_payload = array(
			'mode'                    => 'subscription',
			'success_url'             => $this->return_url( 'success', $commerce_id, $page_url ),
			'cancel_url'              => $this->return_url( 'cancel', $commerce_id, $page_url ),
			'line_items[0][price]'    => $price_id,
			'line_items[0][quantity]' => 1,
			'client_reference_id'     => (string) $commerce_id,
			'metadata[commerce_id]'   => (string) $commerce_id,
			'metadata[payment_provider]' => 'stripe',
		);
		$result = $this->stripe_request( 'POST', '/checkout/sessions', $session_payload );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$session_id = sanitize_text_field( (string) ( $result['id'] ?? '' ) );
		$url        = esc_url_raw( (string) ( $result['url'] ?? '' ) );
		if ( '' === $session_id || '' === $url ) {
			return new WP_Error( 'stripe_checkout_missing_url', __( 'Stripe subscription checkout URL missing.', 'pckz-canonical-engine' ) );
		}
		return array(
			'provider'          => 'stripe',
			'approve_url'       => $url,
			'stripe_session_id' => $session_id,
			'status'            => sanitize_key( (string) ( $result['status'] ?? 'open' ) ),
		);
	}

	public function handle_webhook( $request_body, $headers = array() ) {
		$verified = $this->verify_webhook_signature( (string) $request_body, (array) $headers );
		if ( is_wp_error( $verified ) ) {
			return $verified;
		}
		$event = json_decode( (string) $request_body, true );
		if ( ! is_array( $event ) || empty( $event['type'] ) ) {
			return new WP_Error( 'invalid_webhook_event', __( 'Invalid Stripe webhook event.', 'pckz-canonical-engine' ) );
		}
		$type = sanitize_text_field( (string) $event['type'] );
		$session = is_array( $event['data']['object'] ?? null ) ? $event['data']['object'] : array();
		if ( in_array( $type, array( 'checkout.session.completed', 'checkout.session.async_payment_succeeded' ), true ) ) {
			$status = sanitize_key( (string) ( $session['payment_status'] ?? '' ) );
			if ( 'paid' === $status ) {
				return $this->apply_paid_checkout_session( $session );
			}
			return array(
				'ok'      => true,
				'ignored' => 'session_not_paid',
			);
		}
		if ( in_array( $type, array( 'checkout.session.expired', 'checkout.session.async_payment_failed' ), true ) ) {
			$this->apply_failed_checkout_session( $session, 'failed' );
			return array(
				'ok'      => true,
				'handled' => 'failed',
			);
		}
		return array(
			'ok'      => true,
			'ignored' => 'event_not_handled',
			'event'   => $type,
		);
	}
}

/**
 * Class PCKZ_Payments
 */
class PCKZ_Payments {

	/**
	 * Singleton instance.
	 *
	 * @var PCKZ_Payments|null
	 */
	private static $instance = null;

	/**
	 * Providers map.
	 *
	 * @var array<string,PCKZ_Payment_Provider>
	 */
	private $providers = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		self::$instance = $this;
		$this->register_default_providers();
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Singleton getter.
	 *
	 * @return PCKZ_Payments
	 */
	public static function instance() {
		if ( self::$instance instanceof self ) {
			return self::$instance;
		}
		return new self();
	}

	/**
	 * Register built-in providers.
	 */
	private function register_default_providers() {
		$this->providers['paypal'] = new PCKZ_Payment_Provider_PayPal();
		$this->providers['stripe'] = new PCKZ_Payment_Provider_Stripe();
	}

	/**
	 * Active provider slug for checkout.
	 *
	 * @return string
	 */
	public static function active_provider_slug() {
		$settings = PCKZ_Settings::get_all();
		$requested = sanitize_key( (string) ( $settings['payments_primary_provider'] ?? 'paypal' ) );
		if ( 'stripe' === $requested && ! empty( $settings['payments_enable_stripe'] ) && ! empty( $settings['payments_stripe_secret_key'] ) ) {
			return 'stripe';
		}
		return 'paypal';
	}

	/**
	 * Active provider label.
	 *
	 * @return string
	 */
	public static function active_provider_label() {
		return 'stripe' === self::active_provider_slug() ? 'Stripe' : 'PayPal';
	}

	/**
	 * Provider button label for frontend.
	 *
	 * @return string
	 */
	public static function active_button_label() {
		return 'stripe' === self::active_provider_slug()
			? __( 'Jetzt mit Stripe bezahlen', 'pckz-canonical-engine' )
			: __( 'Jetzt mit PayPal bezahlen', 'pckz-canonical-engine' );
	}

	/**
	 * Provider hint for checkout panel.
	 *
	 * @return string
	 */
	public static function active_provider_hint() {
		return 'stripe' === self::active_provider_slug()
			? __( 'Sie werden sicher zu Stripe weitergeleitet. Nach erfolgreicher Zahlung erhalten Sie eine Bestellbestätigung per E-Mail.', 'pckz-canonical-engine' )
			: __( 'Sie werden sicher zu PayPal weitergeleitet. Nach erfolgreicher Zahlung erhalten Sie eine Bestellbestätigung per E-Mail.', 'pckz-canonical-engine' );
	}

	/**
	 * Register webhook routes.
	 */
	public function register_rest_routes() {
		register_rest_route(
			'pckzce-payments/v1',
			'/webhook/stripe',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_stripe_webhook' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Stripe webhook endpoint.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function rest_stripe_webhook( WP_REST_Request $request ) {
		$settings = PCKZ_Settings::get_all();
		if ( empty( $settings['payments_enable_stripe'] ) ) {
			return rest_ensure_response( array( 'ok' => false, 'reason' => 'stripe_disabled' ) );
		}
		$result = $this->providers['stripe']->handle_webhook( (string) $request->get_body(), $request->get_headers() );
		if ( is_wp_error( $result ) ) {
			return rest_ensure_response(
				array(
					'ok'     => false,
					'reason' => $result->get_error_message(),
					'code'   => $result->get_error_code(),
				)
			);
		}
		return rest_ensure_response(
			array(
				'ok'     => true,
				'result' => $result,
			)
		);
	}

	/**
	 * Provider getter by slug.
	 *
	 * @param string $slug Provider slug.
	 * @return PCKZ_Payment_Provider|WP_Error
	 */
	public function provider( $slug ) {
		$slug = sanitize_key( (string) $slug );
		if ( isset( $this->providers[ $slug ] ) ) {
			return $this->providers[ $slug ];
		}
		return new WP_Error( 'provider_not_found', __( 'Payment provider not found.', 'pckz-canonical-engine' ) );
	}

	/**
	 * Create checkout for active or requested provider.
	 *
	 * @param array  $payload  Checkout payload.
	 * @param string $provider Optional provider slug.
	 * @return array|WP_Error
	 */
	public static function create_checkout( $payload, $provider = '' ) {
		$provider = $provider ? sanitize_key( $provider ) : self::active_provider_slug();
		$manager  = self::instance();
		$gateway  = $manager->provider( $provider );
		if ( is_wp_error( $gateway ) ) {
			return $gateway;
		}
		return $gateway->create_one_time_checkout( $payload );
	}

	/**
	 * Confirm Stripe checkout session payment.
	 *
	 * @param string $session_id Session ID.
	 * @return array|WP_Error
	 */
	public static function confirm_stripe_checkout( $session_id ) {
		$manager = self::instance();
		$gateway = $manager->provider( 'stripe' );
		if ( is_wp_error( $gateway ) ) {
			return $gateway;
		}
		if ( ! method_exists( $gateway, 'confirm_checkout_session' ) ) {
			return new WP_Error( 'stripe_confirm_missing', __( 'Stripe confirmation method missing.', 'pckz-canonical-engine' ) );
		}
		return $gateway->confirm_checkout_session( $session_id );
	}

	/**
	 * Capability map for admin/diagnostics.
	 *
	 * @return array
	 */
	public static function capability_matrix() {
		$paypal = new PCKZ_Payment_Provider_PayPal();
		$stripe = new PCKZ_Payment_Provider_Stripe();
		$caps = array( 'one_time', 'subscription', 'card', 'apple_pay', 'google_pay' );
		$rows = array(
			'paypal' => array(),
			'stripe' => array(),
		);
		foreach ( $caps as $cap ) {
			$rows['paypal'][ $cap ] = $paypal->supports( $cap );
			$rows['stripe'][ $cap ] = $stripe->supports( $cap );
		}
		return $rows;
	}
}
