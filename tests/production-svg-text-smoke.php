#!/usr/bin/env php
<?php
/**
 * Production SVG must include merged customer text vector paths (text_plate_paths).
 *
 * Run: php tests/production-svg-text-smoke.php
 *
 * @package PCKZCanonicalEngine
 */
require_once __DIR__ . '/smoke-bootstrap.php';

$plate_w = PCKZ_Plate_Calibration::TOTAL_WIDTH_MM;
$plate_h = PCKZ_Plate_Calibration::PLATE_HEIGHT_MM;
$refs    = PCKZ_Ledos_Preview::layer_refs();
$text_box = PCKZ_Ledos_Preview::ref_to_mm_box( $refs['text'], $plate_w, $plate_h, 'bottom-left' );

$y_top = $plate_h - $text_box['y_mm'] - $text_box['height_mm'] + 2;
$y_bot = $plate_h - $text_box['y_mm'] - 2;
$x0    = $text_box['x_mm'] + 2;
$x1    = $text_box['x_mm'] + $text_box['width_mm'] - 2;

$text_plate_paths = '<g id="pckz-text-engrave" fill="#FFFFFF"><path d="M '
	. PCKZ_Production_Geometry::fmt( $x0 ) . ' ' . PCKZ_Production_Geometry::fmt( $y_top )
	. ' L ' . PCKZ_Production_Geometry::fmt( $x1 ) . ' ' . PCKZ_Production_Geometry::fmt( $y_top )
	. ' L ' . PCKZ_Production_Geometry::fmt( $x1 ) . ' ' . PCKZ_Production_Geometry::fmt( $y_bot )
	. ' L ' . PCKZ_Production_Geometry::fmt( $x0 ) . ' ' . PCKZ_Production_Geometry::fmt( $y_bot )
	. ' Z"/></g>';

$canonical = array(
	'format'            => 'pckzce-canonical-scene',
	'version'           => 2,
	'coordinate_system' => 'lightburn-mm-bottom-left',
	'plate'             => array( 'width_mm' => $plate_w, 'height_mm' => $plate_h ),
	'selections'        => array(
		'custom_text' => 'alsalam',
		'font_family' => 'Playfair Display',
	),
	'objects'           => array(
		array(
			'id'          => 'pckz-text',
			'role'        => 'text',
			'text'        => 'alsalam',
			'font_family' => 'Playfair Display',
			'bbox'        => $text_box,
			'x_mm'        => $text_box['x_mm'],
			'y_mm'        => $text_box['y_mm'],
			'width_mm'    => $text_box['width_mm'],
			'height_mm'   => $text_box['height_mm'],
			'scale'       => array( 'x' => 1, 'y' => 1 ),
			'rotation_deg'=> 0,
		),
	),
);

$production_vector_svg = sprintf(
	'<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$s %2$s">' .
	'<metadata id="pckz-export-meta"><pckz:export format="fabric-staticCanvas-toSVG" coordinate-system="lightburn-mm-bottom-left"/></metadata>' .
	'<g id="pckz-engrave"><path id="pckz-icon-left" d="M 50 50 L 60 50 L 60 60 L 50 60 Z" fill="#000"/></g></svg>',
	$plate_w,
	$plate_h
);

$package = PCKZ_Export_Engine::run(
	array(
		'canonical_scene'       => wp_json_encode( $canonical ),
		'production_vector_svg' => $production_vector_svg,
		'text_plate_paths'      => $text_plate_paths,
		'config'                => PCKZ_Plate_Calibration::default_product_config(),
		'canvas_json'           => '{}',
		'design_id'             => 9902,
		'selections'            => $canonical['selections'],
	)
);

if ( is_wp_error( $package ) ) {
	fwrite( STDERR, 'FAIL pipeline: ' . $package->get_error_message() . "\n" );
	exit( 1 );
}

$svg = PCKZ_Production_Svg::build_from_package( $package );
if ( is_wp_error( $svg ) ) {
	fwrite( STDERR, 'FAIL svg build: ' . $svg->get_error_message() . "\n" );
	exit( 1 );
}

if ( false === stripos( $svg, 'id="pckz-text-engrave"' ) ) {
	fwrite( STDERR, "FAIL: production SVG missing pckz-text-engrave group\n" );
	exit( 1 );
}
if ( ! preg_match( '/<path\b[^>]*\bd="/i', $svg ) ) {
	fwrite( STDERR, "FAIL: production SVG missing text path d attribute\n" );
	exit( 1 );
}

$probe = PCKZ_Export_Diagnostics::probe_svg_generation( $package );
if ( empty( $probe['svg_text_path_count'] ) && empty( $probe['svg_text_group_count'] ) ) {
	fwrite( STDERR, "FAIL: svg probe reports no customer text paths\n" );
	exit( 1 );
}

$stored = array(
	'production_scene' => $package['production_scene'],
	'layout'           => $package['layout'],
	'text_plate_paths' => $package['text_plate_paths'],
	'canonical_scene'  => $package['canonical_scene'],
);
$svg_b = PCKZ_Production_Svg::build_from_package( $stored );
if ( is_wp_error( $svg_b ) || false === stripos( (string) $svg_b, 'pckz-text-engrave' ) ) {
	fwrite( STDERR, "FAIL: rebuild from stored production_scene lost SVG text\n" );
	exit( 1 );
}

echo "OK: production SVG includes customer text paths (probe paths={$probe['svg_text_path_count']})\n";
exit( 0 );
