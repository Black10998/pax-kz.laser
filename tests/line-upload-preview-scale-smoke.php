<?php
/**
 * Smoke: uploaded/imported line SVGs with compact 950×35 art scale for picker and live preview.
 *
 * @package PCKZCanonicalEngine
 */

$root = dirname( __DIR__ );
require_once $root . '/tests/smoke-bootstrap.php';

require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-svg-library.php';
require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-production-geometry.php';
require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-line-library.php';

$compact = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 950 35"><path fill="#fefefe" d="M420 10 L530 10"/></svg>';

if ( PCKZ_Line_Library::is_artboard_normalized_bundled_line( 'type_fixture', $compact ) ) {
	fwrite( STDERR, "FAIL compact 950×35 line must not skip display normalization\n" );
	exit( 1 );
}

$display = PCKZ_Line_Library::normalize_line_svg_for_display( 'type_fixture', $compact, false );
if ( ! preg_match( '/scale\(([\d.]+)\)/', $display, $sm ) ) {
	fwrite( STDERR, "FAIL compact line display preview missing scale transform\n" );
	exit( 1 );
}
$scale = (float) $sm[1];
if ( $scale < 5 ) {
	fwrite( STDERR, "FAIL compact line display scale too low ({$scale})\n" );
	exit( 1 );
}
if ( false === stripos( $display, 'fill="#fefefe"' ) && false === stripos( $display, 'fill="#FEFEFE"' ) ) {
	fwrite( STDERR, "FAIL display normalization must preserve native path colors\n" );
	exit( 1 );
}

$slug = 'type_99991';
$result = PCKZ_Line_Library::store_custom_line_svg( $compact, $slug, 'Compact Upload Smoke', 'upload' );
if ( is_wp_error( $result ) ) {
	fwrite( STDERR, 'FAIL store_custom_line_svg: ' . $result->get_error_message() . "\n" );
	exit( 1 );
}

$picker_url = PCKZ_Line_Library::picker_preview_url( $slug );
if (
	false === strpos( $picker_url, '-preview.svg' )
	&& false === strpos( $picker_url, 'pckzce_line_preview' )
) {
	fwrite( STDERR, "FAIL custom upload picker_preview_url must use cached display preview\n" );
	exit( 1 );
}

$preview_path = PCKZ_Line_Library::upload_dir() . '/' . PCKZ_Line_Library::preview_filename( $slug );
if ( ! is_readable( $preview_path ) ) {
	fwrite( STDERR, "FAIL cached preview SVG missing after upload\n" );
	exit( 1 );
}
$cached = file_get_contents( $preview_path );
if ( ! preg_match( '/scale\(/', (string) $cached ) ) {
	fwrite( STDERR, "FAIL cached preview must include scale transform\n" );
	exit( 1 );
}

$choices = PCKZ_Line_Library::get_customer_line_choices();
$found   = false;
foreach ( $choices as $choice ) {
	if ( ( $choice['value'] ?? '' ) === $slug ) {
		$found = true;
		if (
			false === strpos( (string) ( $choice['img'] ?? '' ), '-preview.svg' )
			&& false === strpos( (string) ( $choice['img'] ?? '' ), 'pckzce_line_preview' )
		) {
			fwrite( STDERR, "FAIL customer picker thumb must use display preview URL\n" );
			exit( 1 );
		}
		break;
	}
}
if ( ! $found ) {
	fwrite( STDERR, "FAIL uploaded compact line missing from customer choices\n" );
	exit( 1 );
}

$export_url = PCKZ_Ledos_Preview::line_types()[ $slug ] ?? '';
if ( '' === $export_url || false === strpos( $export_url, $slug . '.svg' ) ) {
	fwrite( STDERR, "FAIL export line_types must still reference original upload asset\n" );
	exit( 1 );
}

PCKZ_Line_Library::delete_custom( $slug );

echo "OK line-upload-preview-scale-smoke\n";
