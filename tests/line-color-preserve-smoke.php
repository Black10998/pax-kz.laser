<?php
/**
 * Smoke: custom line SVG color preservation in catalog and customer choices.
 *
 * @package PCKZCanonicalEngine
 */

$root = dirname( __DIR__ );
require_once $root . '/tests/smoke-bootstrap.php';

require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-svg-library.php';
require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-line-library.php';

$red_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 20"><path fill="#ff0000" d="M10 10 L90 10"/></svg>';
$mono_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 20"><path fill="#000000" d="M10 10 L90 10"/></svg>';

if ( ! PCKZ_Svg_Library::svg_line_should_preserve_colors( $red_svg ) ) {
	fwrite( STDERR, "FAIL red line SVG should preserve native colors\n" );
	exit( 1 );
}
if ( PCKZ_Svg_Library::svg_line_should_preserve_colors( $mono_svg ) ) {
	fwrite( STDERR, "FAIL black-only line SVG should remain tintable\n" );
	exit( 1 );
}

$slug = 'type_998';
$dir  = PCKZ_Line_Library::upload_dir();
$file = $slug . '.svg';
file_put_contents( $dir . '/' . $file, $red_svg );

update_option(
	PCKZ_Line_Library::OPTION_CUSTOM,
	array(
		$slug => array(
			'label'      => 'Red Line Smoke',
			'file'       => $file,
			'color_mode' => 'preserve',
		),
	)
);
update_option( PCKZ_Line_Library::OPTION_DISABLED, array() );

$catalog = PCKZ_Ledos_Preview::line_catalog( true );
if ( empty( $catalog[ $slug ]['preserve_colors'] ) || ! empty( $catalog[ $slug ]['tintable'] ) ) {
	fwrite( STDERR, "FAIL red custom line catalog should preserve colors\n" );
	exit( 1 );
}

$choices = PCKZ_Line_Library::get_customer_line_choices();
$found   = false;
foreach ( $choices as $choice ) {
	if ( ( $choice['value'] ?? '' ) === $slug ) {
		$found = true;
		if ( empty( $choice['preserve_colors'] ) ) {
			fwrite( STDERR, "FAIL customer line choice should expose preserve_colors\n" );
			exit( 1 );
		}
		break;
	}
}
if ( ! $found ) {
	fwrite( STDERR, "FAIL preserve-colors line missing from customer choices\n" );
	exit( 1 );
}

$js_catalog = PCKZ_Ledos_Preview::line_catalog_for_js();
if ( empty( $js_catalog[ $slug ]['preserve_colors'] ) ) {
	fwrite( STDERR, "FAIL JS line catalog should expose preserve_colors\n" );
	exit( 1 );
}

PCKZ_Line_Library::delete_custom( $slug );

$bundled = 'type_102';
$bundled_path = PCKZ_Ledos_Preview::line_assets_dir() . $bundled . '.svg';
if ( is_readable( $bundled_path ) ) {
	$catalog = PCKZ_Ledos_Preview::line_catalog( true );
	if ( empty( $catalog[ $bundled ]['preserve_colors'] ) || ! empty( $catalog[ $bundled ]['tintable'] ) ) {
		fwrite( STDERR, "FAIL bundled matte-red line {$bundled} should preserve colors in catalog\n" );
		exit( 1 );
	}
	$choices = PCKZ_Line_Library::get_customer_line_choices();
	$found_bundled = false;
	foreach ( $choices as $choice ) {
		if ( ( $choice['value'] ?? '' ) === $bundled ) {
			$found_bundled = true;
			if ( empty( $choice['preserve_colors'] ) ) {
				fwrite( STDERR, "FAIL bundled preserve line should expose preserve_colors in customer choices\n" );
				exit( 1 );
			}
			break;
		}
	}
	if ( ! $found_bundled ) {
		fwrite( STDERR, "FAIL {$bundled} missing from customer line choices\n" );
		exit( 1 );
	}
}

echo "OK line-color-preserve-smoke\n";
