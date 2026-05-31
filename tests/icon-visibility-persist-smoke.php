<?php
/**
 * Smoke: compact icon library payload persists visibility for large inventories.
 *
 * @package PCKZCanonicalEngine
 */

$root = dirname( __DIR__ );
require_once $root . '/tests/smoke-bootstrap.php';

require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-icon-library.php';

$dir = PCKZ_Icon_Library::upload_dir();
$custom = array();

for ( $i = 1; $i <= 150; $i++ ) {
	$slug = 'bulk_icon_' . $i;
	$file = $slug . '.svg';
	$svg  = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10"/></svg>';
	file_put_contents( $dir . '/' . $file, $svg );
	$custom[ $slug ] = array(
		'label' => 'Bulk ' . $i,
		'file'  => $file,
	);
}

update_option( PCKZ_Icon_Library::OPTION_CUSTOM, $custom );
update_option( PCKZ_Icon_Library::OPTION_DISABLED, array() );

$icons = array();
foreach ( array_keys( $custom ) as $slug ) {
	$icons[ $slug ] = array(
		'enabled' => true,
		'label'   => $custom[ $slug ]['label'],
	);
}

// Disable a few bundled icons to verify mixed state.
$icons['wolf'] = array(
	'enabled' => false,
	'label'   => 'Wolf',
);

$payload = wp_json_encode( array( 'icons' => $icons ) );
$parsed  = PCKZ_Icon_Library::parse_admin_save_payload( $payload );

if ( ! is_array( $parsed ) || count( $parsed['enabled'] ) !== 150 ) {
	fwrite( STDERR, 'FAIL expected 150 enabled custom icons, got ' . count( $parsed['enabled'] ?? array() ) . "\n" );
	exit( 1 );
}

PCKZ_Icon_Library::save_admin_state( $parsed['enabled'], $parsed['labels'] );

$disabled = PCKZ_Icon_Library::disabled_slugs();
if ( ! in_array( 'wolf', $disabled, true ) ) {
	fwrite( STDERR, "FAIL wolf should remain disabled after bulk save\n" );
	exit( 1 );
}

foreach ( array_keys( $custom ) as $slug ) {
	if ( in_array( $slug, $disabled, true ) ) {
		fwrite( STDERR, "FAIL {$slug} should be enabled after bulk save\n" );
		exit( 1 );
	}
}

$stored_custom = get_option( PCKZ_Icon_Library::OPTION_CUSTOM, array() );
foreach ( array( 'bulk_icon_1', 'bulk_icon_150' ) as $slug ) {
	if ( empty( $stored_custom[ $slug ]['customer_visible'] ) ) {
		fwrite( STDERR, "FAIL {$slug} customer_visible not persisted in custom manifest\n" );
		exit( 1 );
	}
}

$choices = PCKZ_Icon_Library::get_customer_icon_choices();
$choice_slugs = array_column( $choices, 'value' );
foreach ( array( 'bulk_icon_1', 'bulk_icon_150' ) as $slug ) {
	if ( ! in_array( $slug, $choice_slugs, true ) ) {
		fwrite( STDERR, "FAIL {$slug} missing from customer icon choices\n" );
		exit( 1 );
	}
}

if ( in_array( 'wolf', $choice_slugs, true ) ) {
	fwrite( STDERR, "FAIL disabled wolf should not appear in customer choices\n" );
	exit( 1 );
}

foreach ( array_keys( $custom ) as $slug ) {
	PCKZ_Icon_Library::delete_custom( $slug );
}
update_option( PCKZ_Icon_Library::OPTION_DISABLED, array() );

echo "OK icon-visibility-persist-smoke: bulk payload save + customer choices\n";
