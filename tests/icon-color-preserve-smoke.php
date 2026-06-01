<?php
/**
 * Smoke: multi-color custom SVG icons preserve native colors in catalog.
 */

$root = dirname( __DIR__ );
require_once $root . '/tests/smoke-bootstrap.php';

require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-svg-library.php';
require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-icon-library.php';
require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-ledos-preview.php';

$multi_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10">'
	. '<rect fill="#ff0000" width="5" height="10"/>'
	. '<rect fill="#0000ff" x="5" width="5" height="10"/>'
	. '</svg>';

if ( ! PCKZ_Svg_Library::svg_should_preserve_colors( $multi_svg ) ) {
	fwrite( STDERR, "FAIL svg_should_preserve_colors should detect multi-color SVG\n" );
	exit( 1 );
}

$mono_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect fill="#ffffff" width="10" height="10"/></svg>';
if ( PCKZ_Svg_Library::svg_should_preserve_colors( $mono_svg ) ) {
	fwrite( STDERR, "FAIL svg_should_preserve_colors should not flag single-color SVG\n" );
	exit( 1 );
}

$slug = 'color_preserve_smoke';
$dir  = PCKZ_Icon_Library::upload_dir();
$file = $slug . '.svg';
file_put_contents( $dir . '/' . $file, $multi_svg );

update_option(
	PCKZ_Icon_Library::OPTION_CUSTOM,
	array(
		$slug => array(
			'label'      => 'Color Preserve Smoke',
			'file'       => $file,
			'color_mode' => 'preserve',
		),
	)
);

$catalog = PCKZ_Ledos_Preview::icon_catalog( true );
if ( empty( $catalog[ $slug ]['preserve_colors'] ) || ! empty( $catalog[ $slug ]['tintable'] ) ) {
	fwrite( STDERR, "FAIL custom multi-color icon should be preserve_colors and not tintable\n" );
	exit( 1 );
}

@unlink( $dir . '/' . $file );
PCKZ_Icon_Library::delete_custom( $slug );

echo "OK icon-color-preserve-smoke\n";
