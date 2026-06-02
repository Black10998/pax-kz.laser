<?php
/**
 * Smoke: line SVG preview normalization and connected mirror markup.
 */

$root = dirname( __DIR__ );
require_once $root . '/tests/smoke-bootstrap.php';

require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-svg-library.php';
require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-line-library.php';

$asymmetric = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 40">'
	. '<path fill="#ff0000" d="M10 20 L90 20 L90 30 L10 30 Z"/>'
	. '</svg>';

$full = PCKZ_Svg_Library::normalize_line_svg_for_preview( $asymmetric, false );
if ( ! preg_match( '/viewBox="0 0 950 35"/', $full ) ) {
	fwrite( STDERR, "FAIL normalized full preview should use 950×35 viewBox\n" );
	exit( 1 );
}

$connected = PCKZ_Svg_Library::normalize_line_svg_for_preview( $asymmetric, true );
if ( ! preg_match( '/scale\(-1,1\)/', $connected ) ) {
	fwrite( STDERR, "FAIL connected preview should include horizontal mirror transform\n" );
	exit( 1 );
}

$slug = 'type_995';
$dir  = PCKZ_Line_Library::upload_dir();
$file = $slug . '.svg';
file_put_contents( $dir . '/' . $file, $asymmetric );

update_option(
	PCKZ_Line_Library::OPTION_CUSTOM,
	array(
		$slug => array(
			'label'            => 'Normalize Smoke',
			'file'             => $file,
			'customer_visible' => true,
			'connected_right'  => true,
		),
	)
);

if ( ! PCKZ_Line_Library::regenerate_preview_svg( $slug ) ) {
	fwrite( STDERR, "FAIL regenerate_preview_svg\n" );
	exit( 1 );
}

$preview_path = $dir . '/' . PCKZ_Line_Library::preview_filename( $slug );
if ( ! is_readable( $preview_path ) ) {
	fwrite( STDERR, "FAIL preview file not written\n" );
	exit( 1 );
}
$preview_body = file_get_contents( $preview_path );
if ( ! preg_match( '/scale\(-1,1\)/', $preview_body ) ) {
	fwrite( STDERR, "FAIL cached preview missing mirror transform\n" );
	exit( 1 );
}

$url = PCKZ_Line_Library::preview_url( $slug );
if ( '' === $url || false === strpos( $url, '-preview.svg' ) ) {
	fwrite( STDERR, "FAIL preview_url should point to preview asset\n" );
	exit( 1 );
}

$catalog = PCKZ_Ledos_Preview::line_catalog( true );
if ( empty( $catalog[ $slug ]['preview'] ) || $catalog[ $slug ]['preview'] === $catalog[ $slug ]['url'] ) {
	fwrite( STDERR, "FAIL line catalog should expose normalized preview URL for custom line\n" );
	exit( 1 );
}

PCKZ_Line_Library::delete_custom( $slug );

echo "OK line-scale-normalize-smoke\n";
