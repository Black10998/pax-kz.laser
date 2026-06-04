<?php
/**
 * Smoke test: group security events for Master Control alerts.
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
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $key ) );
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( (string) $str );
	}
}

require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-master-control.php';

$events = array(
	array(
		'event_type' => 'rate_limit_exceeded',
		'severity'   => 'warning',
		'message'    => 'Rate limit exceeded for licensing endpoint.',
		'context'    => json_encode( array( 'scope' => 'client_update_meta', 'ip' => '1.2.3.4' ) ),
		'created_at' => '2026-06-04 12:00:00',
	),
	array(
		'event_type' => 'rate_limit_exceeded',
		'severity'   => 'warning',
		'message'    => 'Rate limit exceeded for licensing endpoint.',
		'context'    => json_encode( array( 'scope' => 'client_update_meta', 'ip' => '1.2.3.4' ) ),
		'created_at' => '2026-06-04 12:01:00',
	),
);

$groups = PCKZ_Master_Control::group_security_events( $events );
if ( 1 !== count( $groups ) ) {
	fwrite( STDERR, "Duplicate rate-limit events should collapse to one group.\n" );
	exit( 1 );
}
if ( 2 !== (int) ( $groups[0]['count'] ?? 0 ) ) {
	fwrite( STDERR, "Grouped alert count should be 2.\n" );
	exit( 1 );
}

echo "OK master-alert-grouping-smoke: security events group correctly\n";
