<?php
/**
 * Smoke: master control fleet enrichment.
 *
 * @package PCKZCanonicalEngine
 */

require_once dirname( __DIR__ ) . '/tests/smoke-bootstrap.php';
require_once dirname( __DIR__ ) . '/includes/class-pckz-master-control.php';

$install = array(
	'plugin_version'  => '2.27.40',
	'plugin_active'   => 1,
	'last_check_in'   => gmdate( 'Y-m-d H:i:s' ),
	'status'          => 'active',
	'tamper_signals'  => '[]',
);
$license = array( 'status' => 'active' );
$release = array( 'version' => '2.28.0' );

$alerts = PCKZ_Master_Control::installation_alerts( $install, $license, $release );
$health = PCKZ_Master_Control::health_status( $install, $license, $release );
if ( 'success' !== $health ) {
	fwrite( STDERR, "expected success health, got {$health}\n" );
	exit( 1 );
}

$rows = PCKZ_Master_Control::enrich_fleet_rows( array( $install ), array( 1 => $license ), $release );
if ( empty( $rows[0]['online'] ) ) {
	fwrite( STDERR, "expected online\n" );
	exit( 1 );
}

$stats = PCKZ_Master_Control::fleet_stats( $rows, array() );
if ( ! isset( $stats['fleet_online'] ) ) {
	fwrite( STDERR, "fleet_stats missing keys\n" );
	exit( 1 );
}

echo "master-control-fleet-smoke: OK\n";
exit( 0 );
