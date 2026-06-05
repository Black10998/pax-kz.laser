<?php
/**
 * Smoke: master admin-post handlers register early and redirect safely.
 *
 * @package PCKZCanonicalEngine
 */

define( 'ABSPATH', '/tmp/wp/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'PCKZCE_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'PCKZCE_VERSION', '2.28.16' );
define( 'PCKZCE_BUILD', '2.28.16-test' );
define( 'PCKZCE_PLUGIN_BASENAME', 'pckz-canonical-engine/pckz-canonical-engine.php' );

$GLOBALS['pckz_smoke_actions'] = array();

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = '' ) { // phpcs:ignore
		unset( $domain );
		return $text;
	}
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) { // phpcs:ignore
		if ( 0 === strpos( (string) $hook, 'admin_post_' ) ) {
			$GLOBALS['pckz_smoke_actions'][ $hook ] = true;
		}
		unset( $callback, $priority, $accepted_args );
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
if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $key, $value, $expiration = 0 ) { // phpcs:ignore
		unset( $expiration );
		$GLOBALS['pckz_test_transients'][ $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) { return 'https://paxdesign.at' . $path; }
}
if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) { return 'https://paxdesign.at/wp-admin/' . ltrim( (string) $path, '/' ); }
}
if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( $args, $url ) {
		$sep = ( false === strpos( $url, '?' ) ) ? '?' : '&';
		$parts = array();
		foreach ( (array) $args as $key => $value ) {
			$parts[] = rawurlencode( (string) $key ) . '=' . rawurlencode( (string) $value );
		}
		return $url . $sep . implode( '&', $parts );
	}
}
if ( ! function_exists( 'wp_safe_redirect' ) ) {
	function wp_safe_redirect( $location, $status = 302 ) { // phpcs:ignore
		$GLOBALS['pckz_smoke_redirect'] = (string) $location;
		throw new RuntimeException( 'redirect' );
	}
}
if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $cap ) { // phpcs:ignore
		unset( $cap );
		return true;
	}
}
if ( ! function_exists( 'wp_verify_nonce' ) ) {
	function wp_verify_nonce( $nonce, $action ) { // phpcs:ignore
		unset( $nonce, $action );
		return false;
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) { // phpcs:ignore
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
}
if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) { return $value; }
}

require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-settings.php';
require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-licensing.php';

new PCKZ_Licensing();

$required = array(
	'admin_post_pckzce_clear_security_events',
	'admin_post_pckzce_upload_protected_release',
	'admin_post_pckzce_generate_protected_release',
	'admin_post_pckzce_download_protected_release',
	'admin_post_pckzce_publish_release',
	'admin_post_pckzce_save_release_meta',
);
foreach ( $required as $hook ) {
	if ( empty( $GLOBALS['pckz_smoke_actions'][ $hook ] ) ) {
		fwrite( STDERR, "Missing early registration for {$hook}\n" );
		exit( 1 );
	}
}

$_POST = array(
	'redirect_section' => 'fleet',
);
$licensing = new PCKZ_Licensing();
try {
	$licensing->handle_clear_security_events();
} catch ( RuntimeException $e ) {
	if ( 'redirect' !== $e->getMessage() ) {
		throw $e;
	}
}
$redirect = (string) ( $GLOBALS['pckz_smoke_redirect'] ?? '' );
if ( false === strpos( $redirect, 'page=pckz-license-server' ) ) {
	fwrite( STDERR, "Clear alerts should redirect to Master Control.\n" );
	exit( 1 );
}
if ( false === strpos( $redirect, 'pckz_section=fleet' ) ) {
	fwrite( STDERR, "Clear alerts should preserve fleet section.\n" );
	exit( 1 );
}
$notice = $GLOBALS['pckz_test_transients']['pckzce_master_admin_notice'] ?? null;
if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
	fwrite( STDERR, "Failed nonce should set a master admin notice.\n" );
	exit( 1 );
}

echo "OK master-control-actions-smoke\n";
exit( 0 );
