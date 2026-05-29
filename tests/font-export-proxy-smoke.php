<?php
/**
 * Google fonts must expose same-origin proxy URLs; binaries must be TTF/OTF/WOFF only.
 *
 * @package PCKZCanonicalEngine
 */

require_once __DIR__ . '/smoke-bootstrap.php';
require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-font-library.php';

PCKZ_Font_Library::clear_google_font_cache();
PCKZ_Font_Library::reset_font_file_maps_cache();

$maps = PCKZ_Font_Library::build_font_file_maps();
$gv   = $maps['byId']['great-vibes'] ?? '';

if ( false === strpos( $gv, 'pckzce_font_file' ) || false === strpos( $gv, 'great-vibes' ) ) {
	fwrite( STDERR, "FAIL: Great Vibes export URL must use proxy: {$gv}\n" );
	exit( 1 );
}

$binary = PCKZ_Font_Library::resolve_font_binary_url(
	'great-vibes',
	PCKZ_Font_Library::default_catalog()['great-vibes']
);

if ( ! PCKZ_Font_Library::is_export_safe_binary_url( $binary ) ) {
	fwrite( STDERR, "FAIL: Great Vibes binary not export-safe: {$binary}\n" );
	exit( 1 );
}

if ( false !== strpos( $binary, '.woff2' ) ) {
	fwrite( STDERR, "FAIL: must not cache woff2 for export\n" );
	exit( 1 );
}

foreach ( PCKZ_Font_Library::default_catalog() as $id => $row ) {
	if ( 'google' !== ( $row['source'] ?? '' ) ) {
		continue;
	}
	$export = $maps['byId'][ $id ] ?? '';
	if ( false === strpos( $export, 'pckzce_font_file' ) ) {
		fwrite( STDERR, "FAIL: {$id} missing proxy URL\n" );
		exit( 1 );
	}
}

echo "OK font-export-proxy-smoke: proxy URLs + TTF binaries for all Google fonts\n";
