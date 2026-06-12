<?php
/**
 * Smoke test: client dashboard license key masking.
 *
 * @package PCKZCanonicalEngine
 */

define( 'ABSPATH', '/tmp/wp/' );
define( 'PCKZCE_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = '' ) { // phpcs:ignore
		unset( $domain );
		return $text;
	}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) { // phpcs:ignore
		unset( $hook, $callback, $priority, $accepted_args );
	}
}

require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-licensing.php';

$masked = PCKZ_Licensing::mask_license_key_for_display( 'PCKZCE-ABCDEFGHIJKLMNOPQRSTUV' );
if ( 'XXXX-XXXX-XXXX-STUV' !== $masked ) {
	fwrite( STDERR, "License key mask should show last four characters in final segment.\n" );
	exit( 1 );
}

if ( '' !== PCKZ_Licensing::mask_license_key_for_display( '' ) ) {
	fwrite( STDERR, "Empty license key should produce empty mask.\n" );
	exit( 1 );
}

if ( ! class_exists( 'PCKZ_Settings' ) ) {
	class PCKZ_Settings {
		private static $all = array( 'licensing_key' => 'PCKZCE-TEST-KEY-1234567890' );
		public static function get_all() {
			return self::$all;
		}
	}
}

if ( 'PCKZCE-TEST-KEY-1234567890' !== PCKZ_Licensing::get_configured_license_key() ) {
	fwrite( STDERR, "get_configured_license_key should return settings licensing_key.\n" );
	exit( 1 );
}

echo "OK license-key-display-smoke: dashboard license key masking works\n";
