<?php
/**
 * Smoke: commerce pricing, email validation, PayPal config (no live API).
 */

$root = dirname( __DIR__ );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $root . '/' );
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = false ) {
		global $pckz_smoke_options;
		return $pckz_smoke_options[ $key ] ?? $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $key, $value ) {
		global $pckz_smoke_options;
		$pckz_smoke_options[ $key ] = $value;
		return true;
	}
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

if ( ! function_exists( 'is_wp_error' ) ) {
	class WP_Error {
		public function __construct( $c = '', $m = '' ) {
			$this->message = $m;
		}
		public function get_error_message() {
			return $this->message;
		}
	}
	function is_wp_error( $t ) {
		return $t instanceof WP_Error;
	}
}

if ( ! function_exists( 'number_format_i18n' ) ) {
	function number_format_i18n( $n, $d = 0 ) {
		return number_format( (float) $n, $d, '.', '' );
	}
}

if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( $e ) {
		return filter_var( $e, FILTER_SANITIZE_EMAIL );
	}
}

if ( ! function_exists( 'is_email' ) ) {
	function is_email( $email ) {
		return (bool) filter_var( $email, FILTER_VALIDATE_EMAIL );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = '' ) {
		return $text;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $s ) {
		return trim( strip_tags( $s ) );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $s ) {
		return trim( strip_tags( $s ) );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $k ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $k ) );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $u ) {
		return $u;
	}
}

if ( ! function_exists( 'current_time' ) ) {
	function current_time( $t = 'mysql' ) {
		return gmdate( 'Y-m-d H:i:s' );
	}
}

if ( ! function_exists( 'dbDelta' ) ) {
	function dbDelta( $sql ) {
		return array();
	}
}

$GLOBALS['pckz_smoke_options'] = array(
	'pckz_settings' => array_merge(
		array(
			'price_show_enabled' => true,
			'price_base'         => 49.9,
			'price_setup_fee'    => 5,
			'price_currency_code'=> 'EUR',
			'price_currency_symbol' => '€',
			'paypal_enabled'     => true,
			'paypal_test_mode'    => true,
			'paypal_sandbox_client_id' => 'test-client',
			'paypal_sandbox_secret'    => 'test-secret',
		),
		class_exists( 'PCKZ_Settings' ) ? PCKZ_Settings::default_options() : array()
	),
);

if ( ! defined( 'PCKZCE_PLUGIN_DIR' ) ) {
	define( 'PCKZCE_PLUGIN_DIR', $root . '/' );
	define( 'PCKZCE_PLUGIN_URL', 'https://example.test/wp-content/plugins/pckz-canonical-engine/' );
}

require_once $root . '/includes/class-pckz-settings.php';
require_once $root . '/includes/class-pckz-commerce.php';

$total = PCKZ_Commerce::calculate_total( 2, 0 );
if ( abs( $total - 109.8 ) > 0.01 ) {
	fwrite( STDERR, "FAIL calculate_total expected 109.8 got {$total}\n" );
	exit( 1 );
}

$bad = PCKZ_Commerce::validate_email( 'not-an-email' );
if ( ! is_wp_error( $bad ) ) {
	fwrite( STDERR, "FAIL validate_email should reject invalid\n" );
	exit( 1 );
}

$good = PCKZ_Commerce::validate_email( 'kunde@example.com' );
if ( is_wp_error( $good ) || 'kunde@example.com' !== $good ) {
	fwrite( STDERR, "FAIL validate_email should accept valid\n" );
	exit( 1 );
}

if ( ! PCKZ_Commerce::paypal_enabled() ) {
	fwrite( STDERR, "FAIL paypal_enabled with sandbox creds\n" );
	exit( 1 );
}

$pricing = PCKZ_Commerce::get_frontend_pricing( 0 );
if ( empty( $pricing['show'] ) || (float) $pricing['base'] <= 0 ) {
	fwrite( STDERR, "FAIL get_frontend_pricing\n" );
	exit( 1 );
}

echo "OK commerce-smoke: pricing, email validation, PayPal config gate\n";
