<?php
/**
 * Payment architecture expansion (gateway abstraction scaffold).
 *
 * Keeps current checkout behavior unchanged while introducing a provider
 * abstraction layer for future Stripe/card/subscription support.
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
		return PCKZ_Commerce::create_paypal_checkout( (array) $payload );
	}

	public function create_subscription_checkout( $payload ) {
		return new WP_Error( 'not_supported', __( 'PayPal subscription checkout is not implemented in this architecture layer yet.', 'pckz-canonical-engine' ) );
	}

	public function handle_webhook( $request_body, $headers = array() ) {
		return new WP_Error( 'not_supported', __( 'PayPal webhook handling is not yet implemented in this architecture layer.', 'pckz-canonical-engine' ) );
	}
}

/**
 * Stripe provider adapter scaffold.
 */
class PCKZ_Payment_Provider_Stripe implements PCKZ_Payment_Provider {
	public function get_slug() {
		return 'stripe';
	}

	public function get_label() {
		return 'Stripe';
	}

	public function supports( $capability ) {
		return in_array( $capability, array( 'one_time', 'subscription', 'apple_pay', 'google_pay', 'card' ), true );
	}

	public function create_one_time_checkout( $payload ) {
		return new WP_Error( 'not_implemented', __( 'Stripe one-time checkout scaffold is ready, but runtime API call implementation is intentionally deferred to activation phase to avoid checkout behavior changes.', 'pckz-canonical-engine' ) );
	}

	public function create_subscription_checkout( $payload ) {
		return new WP_Error( 'not_implemented', __( 'Stripe subscription checkout scaffold is ready, but runtime API call implementation is intentionally deferred to activation phase.', 'pckz-canonical-engine' ) );
	}

	public function handle_webhook( $request_body, $headers = array() ) {
		return new WP_Error( 'not_implemented', __( 'Stripe webhook scaffold is ready, but runtime implementation is intentionally deferred to activation phase.', 'pckz-canonical-engine' ) );
	}
}

/**
 * Class PCKZ_Payments
 */
class PCKZ_Payments {

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
		$this->register_default_providers();
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Register built-in providers.
	 */
	private function register_default_providers() {
		$this->providers['paypal'] = new PCKZ_Payment_Provider_PayPal();
		$this->providers['stripe'] = new PCKZ_Payment_Provider_Stripe();
	}

	/**
	 * Register webhook/scaffold routes.
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
	 * Stripe webhook endpoint scaffold.
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
			return rest_ensure_response( array( 'ok' => false, 'reason' => $result->get_error_message() ) );
		}
		return rest_ensure_response( array( 'ok' => true ) );
	}

	/**
	 * Get selected primary provider object.
	 *
	 * @return PCKZ_Payment_Provider
	 */
	public function get_primary_provider() {
		$settings = PCKZ_Settings::get_all();
		$slug = sanitize_key( (string) ( $settings['payments_primary_provider'] ?? 'paypal' ) );
		if ( ! isset( $this->providers[ $slug ] ) ) {
			$slug = 'paypal';
		}
		return $this->providers[ $slug ];
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
