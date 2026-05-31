<?php
/**
 * Smoke test for Stripe provider checkout integration.
 *
 * @package PCKZCanonicalEngine
 */

define( 'ABSPATH', '/tmp/wp/' );
define( 'PCKZCE_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'PCKZCE_VERSION', '2.22.0-test' );
define( 'PCKZCE_BUILD', '2.22.0-test-build' );

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;
		private $data;
		public function __construct( $code = '', $message = '', $data = null ) {
			$this->code = $code;
			$this->message = $message;
			$this->data = $data;
		}
		public function get_error_code() { return $this->code; }
		public function get_error_message() { return $this->message; }
		public function get_error_data() { return $this->data; }
	}
}
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
}
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = '' ) { return $text; }
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) ); }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
}
if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $value ) { return trim( strip_tags( (string) $value ) ); }
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) { return abs( (int) $value ); }
}
if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( $value ) { return filter_var( (string) $value, FILTER_SANITIZE_EMAIL ); }
}
if ( ! function_exists( 'is_email' ) ) {
	function is_email( $value ) { return (bool) filter_var( (string) $value, FILTER_VALIDATE_EMAIL ); }
}
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $value ) { return (string) $value; }
}
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $value ) { return (string) $value; }
}
if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) { return 'https://example.test' . $path; }
}
if ( ! function_exists( 'remove_query_arg' ) ) {
	function remove_query_arg( $keys, $url ) {
		$keys = array_map( 'strval', (array) $keys );
		$parts = parse_url( (string) $url );
		if ( empty( $parts['query'] ) ) {
			return (string) $url;
		}
		parse_str( $parts['query'], $query );
		foreach ( $keys as $key ) {
			unset( $query[ $key ] );
		}
		$base = $parts['scheme'] . '://' . $parts['host'] . ( isset( $parts['path'] ) ? $parts['path'] : '' );
		return empty( $query ) ? $base : $base . '?' . http_build_query( $query );
	}
}
if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( $args, $url ) {
		$parts = parse_url( (string) $url );
		$query = array();
		if ( ! empty( $parts['query'] ) ) {
			parse_str( $parts['query'], $query );
		}
		foreach ( (array) $args as $key => $value ) {
			$query[ $key ] = $value;
		}
		$base = $parts['scheme'] . '://' . $parts['host'] . ( isset( $parts['path'] ) ? $parts['path'] : '' );
		return $base . '?' . http_build_query( $query );
	}
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data ) { return json_encode( $data ); }
}
if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults = array() ) {
		if ( is_object( $args ) ) {
			$args = get_object_vars( $args );
		}
		if ( ! is_array( $args ) ) {
			$args = array();
		}
		return array_merge( $defaults, $args );
	}
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = false ) {
		return $GLOBALS['pckz_test_options'][ $key ] ?? $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $key, $value ) {
		$GLOBALS['pckz_test_options'][ $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type = 'mysql' ) {
		return 'mysql' === $type ? gmdate( 'Y-m-d H:i:s' ) : time();
	}
}
if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( $field = '' ) {
		if ( 'version' === $field ) {
			return '6.7';
		}
		if ( 'name' === $field ) {
			return 'Smoke Shop';
		}
		return '';
	}
}
if ( ! function_exists( 'number_format_i18n' ) ) {
	function number_format_i18n( $number, $decimals = 0 ) {
		return number_format( (float) $number, (int) $decimals, '.', ',' );
	}
}
if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	function wp_remote_retrieve_response_code( $response ) {
		return (int) ( $response['response']['code'] ?? 0 );
	}
}
if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	function wp_remote_retrieve_body( $response ) {
		return (string) ( $response['body'] ?? '' );
	}
}
if ( ! function_exists( 'wp_remote_post' ) ) {
	function wp_remote_post( $url, $args = array() ) {
		$GLOBALS['pckz_test_last_remote_post'] = array(
			'url'  => $url,
			'args' => $args,
		);
		if ( false !== strpos( (string) $url, '/v1/checkout/sessions' ) ) {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode(
					array(
						'id'     => 'cs_test_123',
						'url'    => 'https://checkout.stripe.com/c/pay/cs_test_123',
						'status' => 'open',
					)
				),
			);
		}
		return new WP_Error( 'http_fail', 'Unexpected URL: ' . $url );
	}
}
if ( ! function_exists( 'wp_remote_get' ) ) {
	function wp_remote_get( $url, $args = array() ) {
		unset( $url, $args );
		return new WP_Error( 'http_fail', 'GET not expected in this smoke test' );
	}
}
if ( ! function_exists( 'wp_generate_password' ) ) {
	function wp_generate_password( $length = 12, $special = true, $extra = false ) {
		unset( $special, $extra );
		return substr( str_repeat( 'a', (int) $length ), 0, (int) $length );
	}
}
if ( ! function_exists( 'wp_generate_uuid4' ) ) {
	function wp_generate_uuid4() { return '11111111-2222-3333-4444-555555555555'; }
}

require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-settings.php';
require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-commerce.php';
require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-payments.php';

$settings = PCKZ_Settings::default_options();
$settings['payments_enable_stripe'] = true;
$settings['payments_primary_provider'] = 'stripe';
$settings['payments_stripe_secret_key'] = 'sk_test_123';
$settings['payments_stripe_publishable_key'] = 'pk_test_123';
$settings['payments_stripe_webhook_secret'] = 'whsec_test_123';
update_option( PCKZ_Settings::OPTION_KEY, $settings );

$provider = new PCKZ_Payment_Provider_Stripe();
$checkout = $provider->create_one_time_checkout(
	array(
		'amount'      => 89.5,
		'currency'    => 'EUR',
		'description' => 'Smoke Product',
		'page_url'    => 'https://client.test/configurator',
		'commerce_id' => 321,
		'design_id'   => 777,
		'product_id'  => 888,
	)
);
if ( is_wp_error( $checkout ) ) {
	fwrite( STDERR, "Stripe checkout failed: " . $checkout->get_error_message() . PHP_EOL );
	exit( 1 );
}
if ( empty( $checkout['approve_url'] ) || empty( $checkout['stripe_session_id'] ) || 'stripe' !== ( $checkout['provider'] ?? '' ) ) {
	fwrite( STDERR, "Stripe checkout payload invalid.\n" );
	exit( 1 );
}
if ( 'stripe' !== PCKZ_Payments::active_provider_slug() ) {
	fwrite( STDERR, "Active provider should be stripe.\n" );
	exit( 1 );
}
if ( empty( $GLOBALS['pckz_test_last_remote_post']['args']['body']['metadata[commerce_id]'] ) ) {
	fwrite( STDERR, "Stripe checkout request metadata missing.\n" );
	exit( 1 );
}

echo "OK stripe-provider-smoke: checkout session created and provider routing active\n";
