<?php
/**
 * Smoke: bundled Naruto eye models appear in customer picker after stale purge flags.
 *
 * Simulates production sites that permanently deleted type_102–111 during v2.27.31–2.27.34
 * and verifies register/ensure restores customer visibility.
 *
 * @package PCKZCanonicalEngine
 */

$root = dirname( __DIR__ );
require_once $root . '/tests/smoke-bootstrap.php';

require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-svg-library.php';
require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-line-library.php';

$path102 = PCKZ_Ledos_Preview::line_assets_dir() . 'type_102.svg';
if ( ! is_readable( $path102 ) ) {
	fwrite( STDERR, "SKIP type_102.svg missing\n" );
	exit( 0 );
}

// Simulate stale permanent-delete list from old purge builds.
update_option(
	PCKZ_Line_Library::OPTION_DELETED,
	array(
		'type_102',
		'type_103',
		'type_104',
		'type_105',
		'type_106',
		'type_107',
		'type_108',
		'type_109',
		'type_110',
		'type_111',
	)
);
update_option(
	PCKZ_Line_Library::OPTION_DISABLED,
	array( 'type_102', 'type_105' )
);
update_option(
	PCKZ_Line_Library::OPTION_INACTIVE,
	array( 'type_107' )
);

if ( ! PCKZ_Line_Library::is_permanently_deleted_bundled( 'type_102' ) ) {
	fwrite( STDERR, "FAIL setup: type_102 should be permanently deleted before fix\n" );
	exit( 1 );
}

PCKZ_Line_Library::register_imported_customer_red_lines();

if ( PCKZ_Line_Library::is_permanently_deleted_bundled( 'type_102' ) ) {
	fwrite( STDERR, "FAIL type_102 still permanently deleted after register_imported_customer_red_lines()\n" );
	exit( 1 );
}
if ( ! PCKZ_Line_Library::is_visible( 'type_102' ) || ! PCKZ_Line_Library::is_active( 'type_107' ) ) {
	fwrite( STDERR, "FAIL Naruto eye slugs should be customer-visible after register\n" );
	exit( 1 );
}

$expected = PCKZ_Line_Library::bundled_labels();
$choices  = PCKZ_Line_Library::get_customer_line_choices();
$found    = 0;

foreach ( range( 102, 111 ) as $i ) {
	$slug = 'type_' . $i;
	$hit  = false;
	foreach ( $choices as $choice ) {
		if ( ( $choice['value'] ?? '' ) !== $slug ) {
			continue;
		}
		$hit = true;
		if ( ( $choice['label'] ?? '' ) !== ( $expected[ $slug ] ?? '' ) ) {
			fwrite( STDERR, "FAIL {$slug} label mismatch in customer choices\n" );
			exit( 1 );
		}
		if ( empty( $choice['preserve_colors'] ) ) {
			fwrite( STDERR, "FAIL {$slug} should preserve colors in customer choices\n" );
			exit( 1 );
		}
		break;
	}
	if ( ! $hit ) {
		fwrite( STDERR, "FAIL {$slug} missing from customer line choices after register\n" );
		exit( 1 );
	}
	++$found;
}

$catalog = PCKZ_Ledos_Preview::line_catalog( true );
if ( 10 !== count( array_intersect( array_keys( $expected ), array_keys( $catalog ) ) ) ) {
	fwrite( STDERR, "FAIL not all 10 Naruto models in customer line_catalog()\n" );
	exit( 1 );
}

echo "OK bundled-naruto-line-picker-visibility-smoke: {$found} models restored in customer picker\n";
