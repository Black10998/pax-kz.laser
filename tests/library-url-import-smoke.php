<?php
/**
 * Smoke: URL import stores SVG locally for icon and line libraries.
 *
 * @package PCKZCanonicalEngine
 */

$root = dirname( __DIR__ );
require_once $root . '/tests/smoke-bootstrap.php';

require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-svg-library.php';
require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-icon-library.php';
require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-line-library.php';

$bad = PCKZ_Svg_Library::validate_remote_url( 'ftp://example.com/icon.svg' );
if ( ! is_wp_error( $bad ) ) {
	fwrite( STDERR, "FAIL validate_remote_url should reject ftp\n" );
	exit( 1 );
}

$icon_url = 'https://cdn.example.test/custom/smoke-import-icon.svg';
$result   = PCKZ_Icon_Library::handle_url_import( $icon_url, 'Smoke URL Icon' );
if ( is_wp_error( $result ) ) {
	fwrite( STDERR, 'FAIL icon URL import: ' . $result->get_error_message() . "\n" );
	exit( 1 );
}
$slug = $result['slug'] ?? '';
if ( ! $slug || ! PCKZ_Icon_Library::is_custom( $slug ) ) {
	fwrite( STDERR, "FAIL icon URL import slug missing from custom manifest\n" );
	exit( 1 );
}
$url = PCKZ_Icon_Library::custom_url( $slug );
if ( '' === $url || ! is_readable( PCKZ_Icon_Library::upload_dir() . '/' . $slug . '.svg' ) ) {
	fwrite( STDERR, "FAIL icon URL import file not stored locally\n" );
	exit( 1 );
}

$line_url = 'https://cdn.example.test/custom/smoke-import-line.svg';
$line     = PCKZ_Line_Library::handle_url_import( $line_url, 'Smoke URL Line' );
if ( is_wp_error( $line ) ) {
	fwrite( STDERR, 'FAIL line URL import: ' . $line->get_error_message() . "\n" );
	exit( 1 );
}
$line_slug = $line['slug'] ?? '';
if ( ! $line_slug || ! PCKZ_Line_Library::is_custom( $line_slug ) ) {
	fwrite( STDERR, "FAIL line URL import slug missing from custom manifest\n" );
	exit( 1 );
}

PCKZ_Icon_Library::delete_custom( $slug );
PCKZ_Line_Library::delete_custom( $line_slug );

echo "OK library-url-import-smoke\n";
