<?php
/**
 * Smoke: SVG upload must appear in catalogs even when slug was permanently deleted (type_102+).
 *
 * @package PCKZCanonicalEngine
 */

$root = dirname( __DIR__ );
require_once $root . '/tests/smoke-bootstrap.php';
if ( ! defined( 'PCKZCE_PLUGIN_DIR' ) ) {
	define( 'PCKZCE_PLUGIN_DIR', $root . '/' );
}

require_once $root . '/includes/class-pckz-svg-library.php';
require_once $root . '/includes/class-pckz-ledos-preview.php';
require_once $root . '/includes/class-pckz-line-library.php';

if ( ! function_exists( 'wp_check_filetype' ) ) {
	function wp_check_filetype( $filename, $mimes = null ) {
		if ( preg_match( '/\.svg$/i', $filename ) ) {
			return array(
				'ext'  => 'svg',
				'type' => 'image/svg+xml',
			);
		}
		return array(
			'ext'  => false,
			'type' => false,
		);
	}
}

// Simulate site that purged type_102–121 (common production state).
$deleted = PCKZ_Line_Library::permanently_deleted_slugs();
for ( $i = 102; $i <= 111; $i++ ) {
	$deleted[] = 'type_' . $i;
}
update_option( PCKZ_Line_Library::OPTION_DELETED, array_values( array_unique( $deleted ) ) );

if ( PCKZ_Line_Library::is_permanently_deleted_bundled( 'type_102' ) !== true ) {
	fwrite( STDERR, "FAIL type_102 should be marked permanently deleted before upload\n" );
	exit( 1 );
}

$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 40"><path d="M10 20h180" fill="none" stroke="#B22222"/></svg>';
$slug = 'type_99992';
$result = PCKZ_Line_Library::store_custom_line_svg( $svg, $slug, 'Smoke Catalog', 'upload' );
if ( is_wp_error( $result ) ) {
	fwrite( STDERR, 'FAIL store_custom_svg: ' . $result->get_error_message() . "\n" );
	exit( 1 );
}

// Reproduce production: next slug after bundled max is often type_102 while still on delete list.
$slug_102 = 'type_102';
update_option( PCKZ_Line_Library::OPTION_DELETED, array_merge( PCKZ_Line_Library::permanently_deleted_slugs(), array( $slug_102 ) ) );
PCKZ_Line_Library::delete_custom( $slug_102 );
$result102 = PCKZ_Line_Library::store_custom_line_svg( $svg, $slug_102, 'Smoke 102', 'upload' );
if ( is_wp_error( $result102 ) ) {
	fwrite( STDERR, 'FAIL store type_102: ' . $result102->get_error_message() . "\n" );
	exit( 1 );
}

if ( PCKZ_Line_Library::is_permanently_deleted_bundled( $slug_102 ) ) {
	fwrite( STDERR, "FAIL type_102 must not stay permanently deleted after custom upload\n" );
	exit( 1 );
}

if ( ! PCKZ_Line_Library::is_line_in_catalog( $slug_102, false ) ) {
	fwrite( STDERR, "FAIL type_102 missing from admin catalog after upload\n" );
	exit( 1 );
}

if ( ! PCKZ_Line_Library::is_line_in_catalog( $slug_102, true ) ) {
	fwrite( STDERR, "FAIL type_102 missing from customer catalog after upload\n" );
	exit( 1 );
}

$choices = PCKZ_Line_Library::get_customer_line_choices();
$found   = false;
foreach ( $choices as $choice ) {
	if ( ( $choice['value'] ?? '' ) === $slug_102 ) {
		$found = true;
		break;
	}
}
if ( ! $found ) {
	fwrite( STDERR, "FAIL type_102 missing from customer line choices\n" );
	exit( 1 );
}

PCKZ_Line_Library::delete_custom( $slug );
PCKZ_Line_Library::delete_custom( $slug_102 );

echo "OK line-svg-upload-catalog-smoke\n";
