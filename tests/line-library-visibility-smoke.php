<?php
/**
 * Smoke: retired type_21–40, visibility flags, bulk hide/delete.
 *
 * @package PCKZCanonicalEngine
 */

$root = dirname( __DIR__ );
require_once $root . '/tests/smoke-bootstrap.php';

require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-line-library.php';

if ( empty( PCKZ_Ledos_Preview::line_types()['type_21'] ) ) {
	fwrite( STDERR, "FAIL type_21 missing from line_types (legacy)\n" );
	exit( 1 );
}

$customer = PCKZ_Ledos_Preview::line_catalog( true, false );
if ( isset( $customer['type_21'] ) || isset( $customer['type_40'] ) ) {
	fwrite( STDERR, "FAIL retired type_21–40 must not appear in customer catalog\n" );
	exit( 1 );
}

$admin = PCKZ_Line_Library::admin_catalog_entries();
if ( isset( $admin['type_25'] ) ) {
	fwrite( STDERR, "FAIL retired type_25 must not appear in admin catalog\n" );
	exit( 1 );
}

if ( ! isset( $admin['type_41'] ) && is_readable( PCKZ_Ledos_Preview::line_assets_dir() . 'type_41.svg' ) ) {
	fwrite( STDERR, "FAIL type_41 should appear in admin catalog\n" );
	exit( 1 );
}

update_option( PCKZ_Line_Library::OPTION_DISABLED, array( 'type_41' ) );
update_option( PCKZ_Line_Library::OPTION_ADMIN_HIDDEN, array( 'type_42' ) );
update_option( PCKZ_Line_Library::OPTION_INACTIVE, array( 'type_43' ) );

if ( PCKZ_Line_Library::is_visible( 'type_41' ) ) {
	fwrite( STDERR, "FAIL type_41 should be hidden for customers\n" );
	exit( 1 );
}
if ( PCKZ_Line_Library::admin_visible_flag( 'type_42' ) ) {
	fwrite( STDERR, "FAIL type_42 should be admin-hidden\n" );
	exit( 1 );
}
if ( PCKZ_Line_Library::is_active( 'type_43' ) ) {
	fwrite( STDERR, "FAIL type_43 should be inactive\n" );
	exit( 1 );
}

$payload = PCKZ_Line_Library::build_admin_save_payload();
if ( empty( $payload['lines']['type_41'] ) ) {
	fwrite( STDERR, "FAIL build_admin_save_payload missing type_41\n" );
	exit( 1 );
}
$payload['lines']['type_41']['enabled']       = true;
$payload['lines']['type_41']['admin_visible'] = true;
$payload['lines']['type_41']['active']        = true;

$result = PCKZ_Line_Library::save_admin_state_from_post(
	array(
		'pckz_line_library_payload' => wp_json_encode( $payload ),
	)
);
if ( is_wp_error( $result ) ) {
	fwrite( STDERR, 'FAIL save: ' . $result->get_error_message() . "\n" );
	exit( 1 );
}

if ( ! PCKZ_Line_Library::is_visible( 'type_41' ) ) {
	fwrite( STDERR, "FAIL type_41 should be customer-visible after save\n" );
	exit( 1 );
}

$bulk = PCKZ_Line_Library::delete_selected_bulk( array( 'type_50' ) );
if ( is_wp_error( $bulk ) || empty( $bulk['hidden'] ) ) {
	fwrite( STDERR, "FAIL bulk hide built-in type_50\n" );
	exit( 1 );
}

update_option( PCKZ_Line_Library::OPTION_DISABLED, array() );
update_option( PCKZ_Line_Library::OPTION_ADMIN_HIDDEN, array() );
update_option( PCKZ_Line_Library::OPTION_INACTIVE, array() );

echo "OK line-library-visibility-smoke\n";
