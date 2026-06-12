<?php
/**
 * Smoke: shipment tracking payload persists without WooCommerce dependency.
 *
 * @package PCKZCanonicalEngine
 */

require_once dirname( __DIR__ ) . '/tests/smoke-bootstrap.php';

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
}
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		return (string) $url;
	}
}

require_once dirname( __DIR__ ) . '/includes/class-pckz-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-pckz-commerce.php';
require_once dirname( __DIR__ ) . '/includes/class-pckz-shipment-tracking.php';

$stored = PCKZ_Commerce::encode_shipment_tracking_payload(
	array(
		'carrier'            => 'DHL',
		'carrier_slug'       => 'dhl',
		'tracking_number'    => '00340434161234567890',
		'tracking_url'       => 'https://example.test/track/00340434161234567890',
		'shipment_status'    => 'InTransit',
		'current_location'   => 'Wien, AT',
		'estimated_delivery' => '2026-06-07',
		'shipping_date'      => '2026-06-04 12:30',
		'events'             => array(
			array(
				'date'     => '2026-06-04 12:30',
				'status'   => 'Shipped',
				'location' => 'Wien',
				'message'  => 'Package accepted',
			),
		),
		'auto_sync'          => true,
		'last_synced_at'     => '2026-06-04 13:00:00',
		'sync_error'         => '',
	)
);

$summary = PCKZ_Commerce::customer_shipping_summary(
	array(
		'shipment_tracking_json' => $stored,
		'wc_order_id'            => 0,
	)
);

if ( 'DHL' !== (string) ( $summary['carrier'] ?? '' ) ) {
	fwrite( STDERR, "FAIL: stored carrier not loaded\n" );
	exit( 1 );
}
if ( 'dhl' !== (string) ( $summary['carrier_slug'] ?? '' ) ) {
	fwrite( STDERR, "FAIL: stored carrier slug not loaded\n" );
	exit( 1 );
}
if ( 'InTransit' !== (string) ( $summary['shipment_status'] ?? '' ) ) {
	fwrite( STDERR, "FAIL: stored shipment status not loaded\n" );
	exit( 1 );
}
if ( empty( $summary['auto_sync'] ) ) {
	fwrite( STDERR, "FAIL: auto_sync should persist\n" );
	exit( 1 );
}
if ( empty( $summary['events'] ) || 'Shipped' !== (string) ( $summary['events'][0]['status'] ?? '' ) ) {
	fwrite( STDERR, "FAIL: stored events not loaded\n" );
	exit( 1 );
}

$reflector = new ReflectionClass( 'PCKZ_Shipment_Tracking' );
$method    = $reflector->getMethod( 'heuristic_carrier_slug' );
$method->setAccessible( true );
$austrian = (string) $method->invoke( null, 'AB123456789AT', '' );
$ups      = (string) $method->invoke( null, '1Z999AA10123456784', '' );
$dhl      = (string) $method->invoke( null, '00340434161234567890', 'DHL Express' );

if ( 'austrian-post' !== $austrian || 'ups' !== $ups || 'dhl' !== $dhl ) {
	fwrite( STDERR, "FAIL: carrier heuristics failed\n" );
	exit( 1 );
}

echo "shipment-tracking-persistence-smoke: OK\n";
exit( 0 );
