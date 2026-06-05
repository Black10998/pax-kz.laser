<?php
/**
 * Roboto must resolve to an export-safe same-origin font proxy URL.
 *
 * @package PCKZCanonicalEngine
 */

require_once __DIR__ . '/smoke-bootstrap.php';
require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-font-library.php';

PCKZ_Font_Library::reset_font_file_maps_cache();

$catalog = PCKZ_Font_Library::default_catalog();
if ( empty( $catalog['roboto'] ) ) {
	fwrite( STDERR, "FAIL: Roboto missing from font library defaults\n" );
	exit( 1 );
}

$roboto = $catalog['roboto'];
if ( 'Roboto' !== ( $roboto['family'] ?? '' ) ) {
	fwrite( STDERR, "FAIL: Roboto family name mismatch\n" );
	exit( 1 );
}

$export_url = PCKZ_Font_Library::export_url_for_frontend( 'roboto', $roboto );
if ( '' === $export_url ) {
	fwrite( STDERR, "FAIL: Roboto export_url_for_frontend returned empty URL\n" );
	exit( 1 );
}

if ( false === strpos( $export_url, 'pckzce_font_file' ) || false === strpos( $export_url, 'roboto' ) ) {
	fwrite( STDERR, "FAIL: Roboto export URL must use pckzce_font_file proxy: {$export_url}\n" );
	exit( 1 );
}

$maps = PCKZ_Font_Library::build_font_file_maps();
$family_key = 'roboto';
if ( empty( $maps['byFamily'][ $family_key ] ) ) {
	fwrite( STDERR, "FAIL: Roboto missing from byFamily font map\n" );
	exit( 1 );
}

if ( empty( $maps['byId']['roboto'] ) ) {
	fwrite( STDERR, "FAIL: Roboto missing from byId font map\n" );
	exit( 1 );
}

$customer = PCKZ_Font_Library::get_customer_fonts();
$found    = false;
foreach ( $customer as $row ) {
	if ( 'roboto' === ( $row['id'] ?? '' ) || 'Roboto' === ( $row['family'] ?? '' ) ) {
		$found = true;
		break;
	}
}
if ( ! $found ) {
	fwrite( STDERR, "FAIL: Roboto not exposed in customer font list\n" );
	exit( 1 );
}

echo "OK font-roboto-export-smoke: Roboto resolves to export-safe proxy URL\n";
