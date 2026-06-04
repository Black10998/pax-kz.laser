<?php
/**
 * Smoke: normalize_master_url strips admin/rest endpoint fragments.
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
if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( (string) $url, $component );
	}
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $v ) {
		return abs( (int) $v );
	}
}

require_once dirname( __DIR__ ) . '/includes/class-pckz-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-pckz-licensing.php';

$class  = new ReflectionClass( 'PCKZ_Licensing' );
$method = $class->getMethod( 'normalize_master_url' );
$method->setAccessible( true );

$cases = array(
	array( 'in' => 'https://paxdesign.at/wp-admin/pckz-license-server', 'out' => 'https://paxdesign.at' ),
	array( 'in' => 'https://paxdesign.at/wp-admin/admin.php?page=pckz-license-server', 'out' => 'https://paxdesign.at' ),
	array( 'in' => 'https://paxdesign.at/wp-json/pckzce-license/v1/client/check-in', 'out' => 'https://paxdesign.at' ),
	array( 'in' => 'https://paxdesign.at/subsite/wp-admin/admin.php?page=pckz-license-server', 'out' => 'https://paxdesign.at/subsite' ),
	array( 'in' => 'https://paxdesign.at/subsite', 'out' => 'https://paxdesign.at/subsite' ),
);

foreach ( $cases as $idx => $row ) {
	$actual = (string) $method->invoke( null, $row['in'] );
	if ( $actual !== $row['out'] ) {
		fwrite( STDERR, "Case {$idx} failed: expected {$row['out']}, got {$actual}\n" );
		exit( 1 );
	}
}

echo "master-url-normalize-smoke: OK\n";
exit( 0 );
