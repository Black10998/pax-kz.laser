<?php
/**
 * Smoke: standard SVG upload stores file as-is (no conversion pipeline).
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

$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 40"><path d="M10 20h180" fill="none" stroke="#B22222"/></svg>';
$tmp = tempnam( sys_get_temp_dir(), 'pckz-svg-upload-' );
file_put_contents( $tmp, $svg );

$file = array(
	'name'     => 'smoke-line.svg',
	'tmp_name' => $tmp,
	'error'    => UPLOAD_ERR_OK,
	'size'     => strlen( $svg ),
);

$result = PCKZ_Line_Library::handle_upload( $file );
@unlink( $tmp );

if ( is_wp_error( $result ) ) {
	fwrite( STDERR, 'FAIL handle_upload: ' . $result->get_error_message() . "\n" );
	exit( 1 );
}

$slug = $result['slug'] ?? '';
if ( '' === $slug ) {
	fwrite( STDERR, "FAIL handle_upload missing slug\n" );
	exit( 1 );
}

$path = PCKZ_Line_Library::upload_dir() . '/' . $slug . '.svg';
if ( ! is_readable( $path ) ) {
	fwrite( STDERR, "FAIL stored SVG missing at {$path}\n" );
	exit( 1 );
}

$stored = file_get_contents( $path );
if ( $stored !== $svg ) {
	fwrite( STDERR, "FAIL stored SVG was modified (conversion pipeline must not run)\n" );
	exit( 1 );
}

$manifest = PCKZ_Line_Library::custom_manifest();
if ( empty( $manifest[ $slug ] ) || 'upload' !== ( $manifest[ $slug ]['source'] ?? '' ) ) {
	fwrite( STDERR, "FAIL manifest source must be upload\n" );
	exit( 1 );
}

if ( ! PCKZ_Line_Library::is_line_in_catalog( $slug, false ) ) {
	fwrite( STDERR, "FAIL uploaded line missing from admin catalog\n" );
	exit( 1 );
}

if ( ! PCKZ_Line_Library::is_line_in_catalog( $slug, true ) ) {
	fwrite( STDERR, "FAIL uploaded line missing from customer catalog\n" );
	exit( 1 );
}

PCKZ_Line_Library::delete_custom( $slug );

echo "OK line-svg-upload-smoke\n";
