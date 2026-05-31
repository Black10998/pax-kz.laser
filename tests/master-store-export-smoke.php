<?php
/**
 * Smoke test: master production store must not require client license credentials for export.
 *
 * @package PCKZCanonicalEngine
 */

define( 'ABSPATH', '/tmp/wp/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'PCKZCE_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'PCKZCE_VERSION', '2.27.1-test' );
define( 'PCKZCE_BUILD', '2.27.1-test-build' );

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;
		public function __construct( $code = '', $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}
		public function get_error_code() { return $this->code; }
		public function get_error_message() { return $this->message; }
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = '' ) { return $text; }
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		unset( $hook, $callback, $priority, $accepted_args );
	}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		unset( $hook, $callback, $priority, $accepted_args );
	}
}
if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) { return 'https://paxdesign.at' . $path; }
}
if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) { return parse_url( (string) $url, $component ); }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = false ) {
		return $GLOBALS['pckz_test_options'][ $key ] ?? $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $key, $value, $autoload = null ) {
		unset( $autoload );
		$GLOBALS['pckz_test_options'][ $key ] = $value;
		return true;
	}
}

require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-settings.php';
require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-licensing.php';

$settings = PCKZ_Settings::default_options();
$settings['licensing_enforce']          = true;
$settings['licensing_export_authorize'] = true;
$settings['licensing_export_remote_mode'] = true;
$settings['licensing_key']              = '';
$settings['licensing_master_url']        = 'https://paxdesign.at';
update_option( PCKZ_Settings::OPTION_KEY, $settings );

if ( ! PCKZ_Licensing::is_master_mode() ) {
	fwrite( STDERR, "FAIL master-store-export-smoke: expected paxdesign.at to be master mode\n" );
	exit( 1 );
}

if ( ! PCKZ_Licensing::can_run_feature( 'export' ) ) {
	fwrite( STDERR, "FAIL master-store-export-smoke: can_run_feature(export) should pass on master\n" );
	exit( 1 );
}

$auth = PCKZ_Licensing::authorize_export_operation( array( 'operation' => 'export-validate' ) );
if ( true !== $auth ) {
	$msg = is_wp_error( $auth ) ? $auth->get_error_message() : 'not true';
	fwrite( STDERR, "FAIL master-store-export-smoke: authorize_export_operation blocked master checkout: {$msg}\n" );
	exit( 1 );
}

echo "OK master-store-export-smoke: master store export/checkout does not require client license key\n";
