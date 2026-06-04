<?php
/**
 * Smoke: actually render the Master Control admin views and assert the page
 * is non-empty. Reproduces the v2.28.0 regression where the fleet partial
 * called `$format_datetime(...)` before it was defined, producing a PHP 8
 * fatal "Value of type null is not callable" and a blank Master Control page.
 *
 * @package PCKZCanonicalEngine
 */

require_once dirname( __DIR__ ) . '/tests/smoke-bootstrap.php';

// Minimal WordPress globals/funcs required by the views.
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) { return (string) $url; }
}
if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( $text, $domain = '' ) { unset( $domain ); echo htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
}
if ( ! function_exists( 'esc_attr_e' ) ) {
	function esc_attr_e( $text, $domain = '' ) { unset( $domain ); echo htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = '' ) { unset( $domain ); return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
}
if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) { return 'https://master.example.test/wp-admin/' . ltrim( (string) $path, '/' ); }
}
if ( ! function_exists( 'wp_nonce_field' ) ) {
	function wp_nonce_field( $action, $name = '' ) { unset( $action, $name ); echo '<input type="hidden" name="_wpnonce" value="smoke">'; }
}
if ( ! function_exists( 'checked' ) ) {
	function checked( $checked, $current = true, $echo = true ) {
		unset( $echo );
		$result = (bool) $checked === (bool) $current ? 'checked' : '';
		echo $result;
		return $result;
	}
}
if ( ! function_exists( 'selected' ) ) {
	function selected( $selected, $current = true, $echo = true ) {
		unset( $echo );
		$result = (string) $selected === (string) $current ? 'selected' : '';
		echo $result;
		return $result;
	}
}
if ( ! function_exists( 'number_format_i18n' ) ) {
	function number_format_i18n( $number, $decimals = 0 ) { return number_format( (float) $number, (int) $decimals ); }
}
if ( ! function_exists( 'size_format' ) ) {
	function size_format( $bytes, $decimals = 0 ) { return number_format( (float) $bytes / 1024, (int) $decimals ) . ' KB'; }
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $v ) { return abs( (int) $v ); }
}
if ( ! function_exists( '_n' ) ) {
	function _n( $single, $plural, $number, $domain = '' ) { unset( $domain ); return $number === 1 ? $single : $plural; }
}
if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $value ) { return (string) $value; }
}
if ( ! defined( 'PCKZCE_PLUGIN_DIR' ) ) {
	define( 'PCKZCE_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'PCKZCE_VERSION' ) ) {
	define( 'PCKZCE_VERSION', '2.28.2' );
}
$_GET = array();

// Build realistic render context (mirrors PCKZ_Licensing::render_admin_page_body).
$master_mode    = true;
$generated      = '';
$package_notice = null;
$package_error  = '';
$admin_notice   = null;
$client_notice  = null;
$release_meta   = array(
	'version'             => '2.28.2',
	'package_url'         => 'https://example.test/protected.zip',
	'changelog'           => '',
	'requires'            => '6.0',
	'requires_php'        => '7.4',
	'tested'              => '6.7',
	'min_client_build'    => '',
	'allow_remote_export' => false,
);
$client_state   = array();
$client_summary = array(
	'license_status'    => 'active',
	'connected_server'  => 'https://paxdesign.at',
	'domain'            => 'paxdesign.at',
	'license_type'      => 'Master',
	'installed_version' => '2.28.2',
	'installed_build'   => '2.28.2-build',
	'last_check_in_time'=> '',
	'last_check_in_human' => '',
	'update_status'     => 'ok',
	'update_label'      => 'Up to date',
	'latest_version'    => '2.28.2',
	'update_detail'     => '',
	'can_update_now'    => false,
);
$licenses = array(
	array(
		'id'            => 1,
		'license_key'   => 'PCKZ-AAAA-BBBB-CCCC',
		'label'         => 'Test Customer',
		'status'        => 'active',
		'domains'       => '["client.example.com"]',
		'permissions'   => '{"export":true,"updates":true,"asset_sync":true}',
		'max_installs'  => 2,
		'expires_at'    => null,
		'created_at'    => '2026-06-01 10:00:00',
		'updated_at'    => '2026-06-04 12:00:00',
	),
);
$installs = array(
	array(
		'id'              => 11,
		'license_id'      => 1,
		'install_uuid'    => '11111111-2222-3333-4444-555555555555',
		'domain'          => 'client.example.com',
		'status'          => 'active',
		'plugin_version'  => '2.28.0',
		'plugin_build'    => '2.28.0-build',
		'plugin_active'   => 1,
		'last_check_in'   => gmdate( 'Y-m-d H:i:s' ),
		'last_asset_sync' => gmdate( 'Y-m-d H:i:s' ),
		'last_activity_at'=> gmdate( 'Y-m-d H:i:s' ),
		'updated_at'      => gmdate( 'Y-m-d H:i:s' ),
		'tamper_signals'  => '[]',
		'site_name'       => 'Client Example Site',
	),
);
$downloads = array();
$recent_errors = array();
$stats = array(
	'licenses_total'        => 1,
	'licenses_active'       => 1,
	'installations_total'   => 1,
	'installations_active'  => 1,
	'installations_blocked' => 0,
	'downloads_total'       => 0,
	'downloads_24h'         => 0,
	'fleet_online'          => 1,
	'fleet_offline'         => 0,
	'fleet_warnings'        => 0,
	'fleet_critical_alerts' => 0,
	'fleet_updates_pending' => 1,
	'fleet_plugin_inactive' => 0,
);
$license_install_stats = array( 1 => array( 'active' => 1, 'blocked' => 0, 'total' => 1, 'max' => 2 ) );
$license_map           = array( 1 => $licenses[0] );

require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-master-control.php';
$fleet_rows = PCKZ_Master_Control::enrich_fleet_rows( $installs, $license_map, $release_meta );
$security_events = array(
	array(
		'event_type' => 'plugin_deactivated',
		'severity'   => 'critical',
		'message'    => 'Plugin disabled on client.example.com',
		'domain'     => 'client.example.com',
		'created_at' => gmdate( 'Y-m-d H:i:s' ),
	),
);
$customer_packages  = array();
$protected_releases = array(
	array( 'filename' => 'pckz-canonical-engine-2.28.2-protected.zip', 'version' => '2.28.2', 'size' => 1234567 ),
);

$failed = false;

// Render full dashboard (this is the exact failure path from v2.28.0).
ob_start();
try {
	include PCKZCE_PLUGIN_DIR . 'admin/views/licensing-dashboard.php';
} catch ( \Throwable $e ) {
	$failed = true;
	fwrite( STDERR, 'Fatal during full Master Control render: ' . $e->getMessage() . "\n" );
}
$rendered = (string) ob_get_clean();

if ( $failed ) {
	exit( 1 );
}

if ( strlen( $rendered ) < 500 ) {
	fwrite( STDERR, "Master Control rendered output is too short (len=" . strlen( $rendered ) . ").\n" );
	exit( 1 );
}

$expected_markers = array(
	'Master Control Center',
	'Licensed Installations',
	'pckz-fleet-dashboard',
	'pckz-license-stats',
	'Create License',
	'Customer Packages',
	'client.example.com',
);
foreach ( $expected_markers as $needle ) {
	if ( false === strpos( $rendered, $needle ) ) {
		fwrite( STDERR, "Expected marker missing from Master Control output: {$needle}\n" );
		exit( 1 );
	}
}

// Regression guard: render the fleet partial first in isolation to ensure it
// no longer depends on a closure defined by the management partial.
$only_fleet_failed = false;
ob_start();
try {
	// Reset the closures so the partial must self-heal via its own fallback.
	unset( $format_datetime, $badge_class, $status_label );
	include PCKZCE_PLUGIN_DIR . 'admin/views/partials/licensing-master-fleet.php';
} catch ( \Throwable $e ) {
	$only_fleet_failed = true;
	fwrite( STDERR, 'Fatal rendering fleet partial standalone: ' . $e->getMessage() . "\n" );
}
$fleet_only = (string) ob_get_clean();
if ( $only_fleet_failed ) {
	exit( 1 );
}
if ( false === strpos( $fleet_only, 'pckz-fleet-dashboard' ) ) {
	fwrite( STDERR, "Fleet-only render missing pckz-fleet-dashboard marker.\n" );
	exit( 1 );
}

// Empty-state guard: zero installations must still render an informative panel.
$fleet_rows      = array();
$security_events = array();
$stats['fleet_online']          = 0;
$stats['fleet_offline']         = 0;
$stats['fleet_updates_pending'] = 0;
$stats['fleet_critical_alerts'] = 0;
$stats['fleet_plugin_inactive'] = 0;
ob_start();
include PCKZCE_PLUGIN_DIR . 'admin/views/partials/licensing-master-fleet.php';
$empty_state = (string) ob_get_clean();
if ( false === strpos( $empty_state, 'No customer installations have checked in yet' ) ) {
	fwrite( STDERR, "Empty-state banner missing when no installations present.\n" );
	exit( 1 );
}

echo "master-control-render-smoke: OK\n";
exit( 0 );
