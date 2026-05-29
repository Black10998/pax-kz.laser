<?php
/**
 * Every visible catalog font must resolve to an OpenType binary URL for export.
 *
 * @package PCKZCanonicalEngine
 */

require_once __DIR__ . '/smoke-bootstrap.php';

require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-font-library.php';

$failed_parse = array();
$css          = '@font-face{font-weight:700;src:url(https://fonts.gstatic.com/s/a/v1/x.woff2) format("woff2");}';
$url          = PCKZ_Font_Library::parse_google_fonts_css_binary_url( $css );
if ( 'https://fonts.gstatic.com/s/a/v1/x.woff2' !== $url ) {
	$failed_parse[] = 'parse_google_fonts_css_binary_url';
}

$maps = PCKZ_Font_Library::build_font_file_maps();
$missing = array();

foreach ( PCKZ_Font_Library::default_catalog() as $id => $row ) {
	if ( ! PCKZ_Font_Library::is_visible( $id ) ) {
		continue;
	}
	$family = strtolower( trim( (string) ( $row['family'] ?? '' ) ) );
	if ( empty( $maps['byId'][ $id ] ) ) {
		$missing[] = $id . ' (byId)';
		continue;
	}
	if ( $family && empty( $maps['byFamily'][ $family ] ) ) {
		$missing[] = $id . ' (byFamily:' . $family . ')';
	}
}

if ( ! empty( $failed_parse ) ) {
	fwrite( STDERR, 'FAIL css parse: ' . implode( ', ', $failed_parse ) . "\n" );
	exit( 1 );
}

if ( ! empty( $missing ) ) {
	fwrite( STDERR, "FAIL fonts missing binary URL for export:\n" . implode( "\n", $missing ) . "\n" );
	exit( 1 );
}

$count = count( $maps['byId'] );
if ( $count < 15 ) {
	fwrite( STDERR, "FAIL expected at least 15 font binaries, got {$count}\n" );
	exit( 1 );
}

$customer = PCKZ_Font_Library::get_customer_fonts();
if ( count( $customer ) !== $count ) {
	fwrite( STDERR, 'FAIL customer font list must match export-ready count (' . count( $customer ) . " vs {$count})\n" );
	exit( 1 );
}

echo "OK font-export-urls-smoke: {$count} fonts have OpenType binary URLs (Google + uploads)\n";
