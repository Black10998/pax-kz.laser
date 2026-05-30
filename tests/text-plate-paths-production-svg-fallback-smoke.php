#!/usr/bin/env php
<?php
/**
 * When text_plate_paths is missing, recover text paths from production_vector_svg.
 *
 * Run: php tests/text-plate-paths-production-svg-fallback-smoke.php
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

$canonical = array(
	'format'            => 'pckzce-canonical-scene',
	'version'           => 2,
	'coordinate_system' => 'lightburn-mm-bottom-left',
	'plate'             => array( 'width_mm' => $plate_w, 'height_mm' => $plate_h ),
	'selections'        => array(
		'custom_text' => 'fallback',
		'font_family' => 'Russo One',
	),
	'objects'           => array(
		array(
			'id'          => 'pckz-text',
			'role'        => 'text',
			'text'        => 'fallback',
			'font_family' => 'Russo One',
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
	'<g id="pckz-engrave"><path id="pckz-icon-left" d="M 50 50 L 60 50 L 60 60 L 50 60 Z" fill="#000"/></g>' .
	'<g id="pckz-text-engrave-export" transform="matrix(1 0 0 -1 0 %2$s)">' .
	'<g id="pckz-text-engrave"><path d="M %3$s %4$s L %5$s %4$s L %5$s %6$s L %3$s %6$s Z" fill="#fff"/></g>' .
	'</g></svg>',
	PCKZ_Production_Geometry::fmt( $plate_w ),
	PCKZ_Production_Geometry::fmt( $plate_h ),
	PCKZ_Production_Geometry::fmt( $x0 ),
	PCKZ_Production_Geometry::fmt( $y_top ),
	PCKZ_Production_Geometry::fmt( $x1 ),
	PCKZ_Production_Geometry::fmt( $y_bot )
);

$package = PCKZ_Export_Engine::run(
	array(
		'canonical_scene'       => wp_json_encode( $canonical ),
		'production_vector_svg' => $production_vector_svg,
		'config'                => PCKZ_Plate_Calibration::default_product_config(),
		'canvas_json'           => '{}',
		'design_id'             => 9903,
		'selections'            => $canonical['selections'],
	)
);

if ( is_wp_error( $package ) ) {
	fwrite( STDERR, 'FAIL pipeline: ' . $package->get_error_message() . "\n" );
	exit( 1 );
}

$resolved = PCKZ_Production_Scene::resolve_text_plate_paths_from_package( $package );
if ( '' === trim( $resolved ) || false === strpos( $resolved, 'pckz-text-engrave' ) ) {
	fwrite( STDERR, "FAIL: did not recover text fragment from production_vector_svg\n" );
	exit( 1 );
}

$lbrn2 = PCKZ_Production_Lbrn2::build_from_package( $package );
if ( is_wp_error( $lbrn2 ) ) {
	fwrite( STDERR, 'FAIL lbrn2: ' . $lbrn2->get_error_message() . "\n" );
	exit( 1 );
}

$text_shapes = preg_match_all( '/<!-- (pckz-)?text-engrave/', (string) $lbrn2 );
if ( $text_shapes < 1 ) {
	fwrite( STDERR, "FAIL: LBRN2 missing text shapes when only production SVG carried text paths\n" );
	exit( 1 );
}

echo "OK: production SVG fallback restored text paths (text_comments={$text_shapes})\n";
exit( 0 );
