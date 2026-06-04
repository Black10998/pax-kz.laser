<?php
/**
 * Smoke: confirm the Master Control render path NEVER produces a blank page.
 *
 * Forces an exception through the protected recovery panel and asserts that
 * the fallback contains an actionable error notice instead of empty HTML.
 *
 * @package PCKZCanonicalEngine
 */

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) { unset( $hook, $callback, $priority, $accepted_args ); }
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) { unset( $hook, $callback, $priority, $accepted_args ); }
}

require_once dirname( __DIR__ ) . '/tests/smoke-bootstrap.php';

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = '' ) { unset( $domain ); return $text; }
}
if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $cap ) { unset( $cap ); return true; }
}
if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) { return parse_url( (string) $url, $component ); }
}

require_once dirname( __DIR__ ) . '/includes/class-pckz-master-control.php';
require_once dirname( __DIR__ ) . '/includes/class-pckz-licensing.php';

$class    = new ReflectionClass( 'PCKZ_Licensing' );
$method   = $class->getMethod( 'render_admin_page_recovery' );
$method->setAccessible( true );
$instance = $class->newInstanceWithoutConstructor();

ob_start();
$method->invoke( $instance, new RuntimeException( 'synthetic master-control failure', 0 ) );
$rendered = (string) ob_get_clean();

$markers = array(
	'Master Control could not render this view',
	'synthetic master-control failure',
	'pckz-license-dashboard--recovery',
	'Reload this page',
);
foreach ( $markers as $needle ) {
	if ( false === strpos( $rendered, $needle ) ) {
		fwrite( STDERR, "Recovery panel missing marker: {$needle}\n" );
		exit( 1 );
	}
}

if ( strlen( $rendered ) < 400 ) {
	fwrite( STDERR, 'Recovery panel output too short: ' . strlen( $rendered ) . "\n" );
	exit( 1 );
}

echo "master-control-recovery-smoke: OK\n";
exit( 0 );
