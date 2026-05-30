#!/usr/bin/env php
<?php
/**
 * Failed text_plate_paths re-merge must not strip existing text-engrave layers from LBRN2.
 *
 * Run: php tests/lbrn2-text-merge-backup-smoke.php
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

$good_fragment = '<g id="pckz-text-engrave" fill="#FFFFFF"><path d="M '
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
	'selections'        => array( 'custom_text' => 'backup-test', 'font_family' => 'Russo One' ),
	'objects'           => array(
		array(
			'id'          => 'pckz-text',
			'role'        => 'text',
			'text'        => 'backup-test',
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
	'<g id="pckz-engrave"><path id="pckz-icon-left" d="M 50 50 L 60 50 L 60 60 L 50 60 Z" fill="#000"/></g></svg>',
	$plate_w,
	$plate_h
);

$package = PCKZ_Export_Engine::run(
	array(
		'canonical_scene'       => wp_json_encode( $canonical ),
		'production_vector_svg' => $production_vector_svg,
		'text_plate_paths'      => $good_fragment,
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

$bad_pkg = array(
	'production_scene' => $package['production_scene'],
	'layout'           => $package['layout'],
	'text_plate_paths' => '<g id="pckz-text-engrave"><path d=""/></g>',
	'canonical_scene'  => $package['canonical_scene'],
);

$lbrn2 = PCKZ_Production_Lbrn2::build_from_package( $bad_pkg );
if ( is_wp_error( $lbrn2 ) ) {
	fwrite( STDERR, 'FAIL lbrn2: ' . $lbrn2->get_error_message() . "\n" );
	exit( 1 );
}

$text_shapes = preg_match_all( '/<!-- (pckz-)?text-engrave/', (string) $lbrn2 );
if ( $text_shapes < 1 ) {
	fwrite( STDERR, "FAIL: unparseable text_plate_paths must not remove existing text (text_comments={$text_shapes})\n" );
	exit( 1 );
}

echo "OK: text-engrave backup preserved text_comments={$text_shapes}\n";
exit( 0 );
