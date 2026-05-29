<?php
/**
 * Google Fonts CSS parser must pick the Latin subset (Great Vibes regression).
 *
 * @package PCKZCanonicalEngine
 */

require_once __DIR__ . '/smoke-bootstrap.php';
require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-font-library.php';

$css = file_get_contents( __DIR__ . '/fixtures/great-vibes-google.css' );
if ( ! is_string( $css ) ) {
	fwrite( STDERR, "FAIL: fixture missing\n" );
	exit( 1 );
}

$url = PCKZ_Font_Library::parse_google_fonts_css_binary_url( $css );
if ( false === strpos( $url, '.ttf' ) ) {
	fwrite( STDERR, "FAIL: expected Latin Great Vibes TTF for export, got: {$url}\n" );
	exit( 1 );
}

delete_transient( 'pckzce_gfont_bin_great-vibes' );
$row  = PCKZ_Font_Library::default_catalog()['great-vibes'];
$live = PCKZ_Font_Library::resolve_google_font_binary_url( 'great-vibes', $row );
if ( false === strpos( $live, '.ttf' ) ) {
	fwrite( STDERR, "FAIL: live resolve great-vibes: {$live}\n" );
	exit( 1 );
}

echo "OK font-google-subset-smoke: Latin subset selected for Great Vibes\n";
