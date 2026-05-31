<?php
/**
 * Smoke test: master release metadata auto-sync on version bump.
 *
 * @package PCKZCanonicalEngine
 */

define( 'ABSPATH', '/tmp/wp/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'PCKZCE_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'PCKZCE_VERSION', '2.23.2-test' );
define( 'PCKZCE_BUILD', '2.23.2-test-build' );

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = '' ) { // phpcs:ignore
		unset( $domain );
		return $text;
	}
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) { // phpcs:ignore
		unset( $hook, $callback, $priority, $accepted_args );
	}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) { // phpcs:ignore
		unset( $hook, $callback, $priority, $accepted_args );
	}
}
if ( ! function_exists( 'register_rest_route' ) ) {
	function register_rest_route( $namespace, $route, $args = array() ) { // phpcs:ignore
		unset( $namespace, $route, $args );
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
}
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) { return (string) $url; }
}
if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $string ) { return rtrim( (string) $string, '/\\' ) . '/'; }
}
if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) { return parse_url( (string) $url, $component ); }
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
if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) { return 'https://paxdesign.at' . $path; }
}
if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( $field = '' ) {
		if ( 'version' === $field ) {
			return '6.7';
		}
		return '';
	}
}
if ( ! function_exists( 'wp_generate_uuid4' ) ) {
	function wp_generate_uuid4() { return '11111111-2222-3333-4444-555555555555'; }
}
if ( ! function_exists( 'wp_next_scheduled' ) ) {
	function wp_next_scheduled( $hook, $args = array() ) { // phpcs:ignore
		unset( $hook, $args );
		return false;
	}
}
if ( ! function_exists( 'wp_schedule_event' ) ) {
	function wp_schedule_event( $timestamp, $recurrence, $hook, $args = array() ) { // phpcs:ignore
		unset( $timestamp, $recurrence, $hook, $args );
		return true;
	}
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = false ) {
		return $GLOBALS['pckz_test_options'][ $key ] ?? $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $key, $value, $autoload = null ) { // phpcs:ignore
		unset( $autoload );
		$GLOBALS['pckz_test_options'][ $key ] = $value;
		return true;
	}
}

require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-settings.php';
require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-licensing.php';

$expected_url = PCKZ_Licensing::default_protected_package_url( PCKZCE_VERSION );
if ( '' === $expected_url || false === strpos( $expected_url, 'pckz-canonical-engine-' . PCKZCE_VERSION . '-protected.zip' ) ) {
	fwrite( STDERR, "Default protected package URL should include versioned zip name.\n" );
	exit( 1 );
}

update_option(
	PCKZ_Licensing::OPTION_RELEASE_META,
	array(
		'version'     => '2.23.0',
		'package_url' => 'https://releases.example.test/pckz-canonical-engine-2.23.0-protected.zip',
		'changelog'   => 'Manual changelog',
		'requires'    => '6.0',
	)
);

$licensing = new PCKZ_Licensing();
$licensing->bootstrap();

$meta = get_option( PCKZ_Licensing::OPTION_RELEASE_META, array() );
if ( ( $meta['version'] ?? '' ) !== PCKZCE_VERSION ) {
	fwrite( STDERR, "Release version should sync to installed plugin version.\n" );
	exit( 1 );
}
if ( ( $meta['package_url'] ?? '' ) !== $expected_url ) {
	fwrite( STDERR, "Release package URL should sync to default protected package URL.\n" );
	exit( 1 );
}
if ( ( $meta['changelog'] ?? '' ) !== 'Manual changelog' ) {
	fwrite( STDERR, "Existing changelog should be preserved during auto-sync.\n" );
	exit( 1 );
}

echo "OK release-meta-sync-smoke: master release metadata auto-sync works\n";
