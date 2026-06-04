<?php
/**
 * Smoke: legacy /wp-admin/pckz-license-server route is redirected to
 * admin.php?page=pckz-license-server.
 *
 * @package PCKZCanonicalEngine
 */

require_once dirname( __DIR__ ) . '/tests/smoke-bootstrap.php';

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
if ( ! function_exists( 'wp_doing_ajax' ) ) {
	function wp_doing_ajax() {
		return false;
	}
}
if ( ! function_exists( 'wp_doing_cron' ) ) {
	function wp_doing_cron() {
		return false;
	}
}
if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( (string) $url, $component );
	}
}
if ( ! function_exists( 'wp_safe_redirect' ) ) {
	function wp_safe_redirect( $location, $status = 302 ) {
		$GLOBALS['pckz_smoke_redirect'] = array(
			'location' => (string) $location,
			'status'   => (int) $status,
		);
		return true;
	}
}

require_once dirname( __DIR__ ) . '/includes/class-pckz-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-pckz-licensing.php';

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI']    = '/wp-admin/pckz-license-server?foo=bar';
$_GET                      = array( 'foo' => 'bar' );

$licensing = ( new ReflectionClass( 'PCKZ_Licensing' ) )->newInstanceWithoutConstructor();
$licensing->maybe_redirect_legacy_master_control_path();

$redirect = $GLOBALS['pckz_smoke_redirect']['location'] ?? '';
$status   = (int) ( $GLOBALS['pckz_smoke_redirect']['status'] ?? 0 );
if ( 302 !== $status ) {
	fwrite( STDERR, "Expected 302 redirect, got {$status}\n" );
	exit( 1 );
}
if ( false === strpos( $redirect, 'admin.php?page=pckz-license-server' ) ) {
	fwrite( STDERR, "Redirect target missing canonical admin page: {$redirect}\n" );
	exit( 1 );
}
if ( false === strpos( $redirect, 'foo=bar' ) ) {
	fwrite( STDERR, "Redirect target lost query args: {$redirect}\n" );
	exit( 1 );
}

echo "master-control-routing-smoke: OK\n";
exit( 0 );
