#!/usr/bin/env php
<?php
/**
 * LBRN2 must contain Path shapes for text-engrave layers (not only icons/lines).
 *
 * Run: php tests/lbrn2-text-in-xml-smoke.php
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
$x2    = $text_box['x_mm'] + 22;
$x3    = $text_box['x_mm'] + 42;

$text_plate_paths = '<g id="pckz-text-engrave" fill="#FFFFFF"><path d="M '
	. PCKZ_Production_Geometry::fmt( $x0 ) . ' ' . PCKZ_Production_Geometry::fmt( $y_top )
	. ' L ' . PCKZ_Production_Geometry::fmt( $x1 ) . ' ' . PCKZ_Production_Geometry::fmt( $y_top )
	. ' L ' . PCKZ_Production_Geometry::fmt( $x1 ) . ' ' . PCKZ_Production_Geometry::fmt( $y_bot )
	. ' L ' . PCKZ_Production_Geometry::fmt( $x0 ) . ' ' . PCKZ_Production_Geometry::fmt( $y_bot )
	. ' Z M ' . PCKZ_Production_Geometry::fmt( $x2 ) . ' ' . PCKZ_Production_Geometry::fmt( $y_top )
	. ' L ' . PCKZ_Production_Geometry::fmt( $x3 ) . ' ' . PCKZ_Production_Geometry::fmt( $y_top )
	. ' L ' . PCKZ_Production_Geometry::fmt( $x3 ) . ' ' . PCKZ_Production_Geometry::fmt( $y_bot )
	. ' L ' . PCKZ_Production_Geometry::fmt( $x2 ) . ' ' . PCKZ_Production_Geometry::fmt( $y_bot )
	. ' Z"/></g>';

$canonical = array(
	'format'            => 'pckzce-canonical-scene',
	'version'           => 2,
	'coordinate_system' => 'lightburn-mm-bottom-left',
	'plate'             => array( 'width_mm' => $plate_w, 'height_mm' => $plate_h ),
	'selections'        => array(
		'custom_text'  => 'alsalam',
		'font_family'  => 'Playfair Display',
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
		'design_id'             => 9901,
		'selections'            => $canonical['selections'],
	)
);

if ( is_wp_error( $package ) ) {
	fwrite( STDERR, 'FAIL pipeline: ' . $package->get_error_message() . "\n" );
	exit( 1 );
}

$text_layers = 0;
foreach ( (array) ( $package['production_scene']['layers'] ?? array() ) as $layer ) {
	if ( 'path' === ( $layer['type'] ?? '' ) && ! empty( $layer['verts'] ) ) {
		$role = (string) ( $layer['role'] ?? '' );
		$lid  = (string) ( $layer['layer_id'] ?? '' );
		if ( in_array( $role, array( 'text-engrave' ), true ) || 0 === strpos( $lid, 'pckz-text-engrave' ) ) {
			++$text_layers;
		}
	}
}
if ( $text_layers < 1 ) {
	fwrite( STDERR, "FAIL: no text-engrave layers in production_scene\n" );
	exit( 1 );
}

$lbrn2 = PCKZ_Production_Lbrn2::build_from_package( $package );
if ( is_wp_error( $lbrn2 ) ) {
	fwrite( STDERR, 'FAIL lbrn2: ' . $lbrn2->get_error_message() . "\n" );
	exit( 1 );
}

$icon_shapes = preg_match_all( '/<!-- pckz-icon/', (string) $lbrn2 );
$text_shapes = preg_match_all( '/<!-- (pckz-)?text-engrave/', (string) $lbrn2 );
$path_shapes = preg_match_all( '/<Shape Type="Path"/', (string) $lbrn2 );
$vert_lists  = preg_match_all( '/<VertList>/', (string) $lbrn2 );

if ( $text_shapes < 1 ) {
	fwrite( STDERR, "FAIL: LBRN2 has icon_comments={$icon_shapes} but text_comments={$text_shapes} path_shapes={$path_shapes}\n" );
	exit( 1 );
}
if ( $path_shapes < $text_shapes + $icon_shapes ) {
	fwrite( STDERR, "FAIL: path_shapes={$path_shapes} < text+icon comments\n" );
	exit( 1 );
}

// Simulate persisted package: production_scene only, layout still has text_plate_paths.
$stored = array(
	'production_scene' => $package['production_scene'],
	'layout'           => $package['layout'],
	'text_plate_paths' => $package['text_plate_paths'],
	'canonical_scene'  => $package['canonical_scene'],
);
$lbrn2b = PCKZ_Production_Lbrn2::build_from_package( $stored );
$text_b = preg_match_all( '/<!-- (pckz-)?text-engrave/', (string) $lbrn2b );
if ( $text_b < 1 ) {
	fwrite( STDERR, "FAIL: rebuild from stored production_scene lost text (text_comments={$text_b})\n" );
	exit( 1 );
}

// Stale scene: icons only in snapshot, text must come from layout.text_plate_paths on LBRN2 build.
$stale_scene = $package['production_scene'];
$stale_scene['layers'] = array_values(
	array_filter(
		$stale_scene['layers'],
		function ( $layer ) {
			return 'text-engrave' !== ( $layer['role'] ?? '' );
		}
	)
);
$stale_pkg = array(
	'production_scene' => $stale_scene,
	'layout'           => $package['layout'],
	'text_plate_paths' => $package['text_plate_paths'],
	'canonical_scene'  => $package['canonical_scene'],
);
$lbrn2c = PCKZ_Production_Lbrn2::build_from_package( $stale_pkg );
$text_c = preg_match_all( '/<!-- (pckz-)?text-engrave/', (string) $lbrn2c );
if ( $text_c < 1 ) {
	fwrite( STDERR, "FAIL: stale production_scene without text layers must merge text_plate_paths (text_comments={$text_c})\n" );
	exit( 1 );
}

echo "OK: LBRN2 text shapes text_layers={$text_layers} text_comments={$text_shapes} path_shapes={$path_shapes} vert_lists={$vert_lists}\n";
exit( 0 );
