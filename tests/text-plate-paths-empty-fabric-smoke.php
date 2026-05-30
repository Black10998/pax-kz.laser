<?php
/**
 * text_plate_paths must merge when Fabric SVG has no path layers (text-only engrave geometry).
 *
 * Run: php tests/text-plate-paths-empty-fabric-smoke.php
 *
 * @package PCKZCanonicalEngine
 */

require_once __DIR__ . '/smoke-bootstrap.php';

$plate_w = PCKZ_Plate_Calibration::TOTAL_WIDTH_MM;
$plate_h = PCKZ_Plate_Calibration::PLATE_HEIGHT_MM;
$refs    = PCKZ_Ledos_Preview::layer_refs();
$text_box = PCKZ_Ledos_Preview::ref_to_mm_box( $refs['text'], $plate_w, $plate_h, 'bottom-left' );

$fragment = sprintf(
	'<g id="pckz-text-engrave" fill="#FFFFFF"><path d="M %1$s %2$s L %3$s %4$s L %5$s %6$s Z"/></g>',
	PCKZ_Production_Geometry::fmt( $text_box['center_x_mm'] - 20 ),
	PCKZ_Production_Geometry::fmt( $plate_h - $text_box['center_y_mm'] ),
	PCKZ_Production_Geometry::fmt( $text_box['center_x_mm'] + 20 ),
	PCKZ_Production_Geometry::fmt( $plate_h - $text_box['center_y_mm'] ),
	PCKZ_Production_Geometry::fmt( $text_box['center_x_mm'] - 20 ),
	PCKZ_Production_Geometry::fmt( $plate_h - $text_box['center_y_mm'] - 5 )
);

$canonical = array(
	'format'            => 'pckzce-canonical-scene',
	'version'           => 2,
	'coordinate_system' => 'lightburn-mm-bottom-left',
	'plate'             => array( 'width_mm' => $plate_w, 'height_mm' => $plate_h ),
	'selections'        => array( 'custom_text' => 'AB 123 CD' ),
	'objects'           => array(
		array(
			'id'           => 'pckz-text',
			'role'         => 'text',
			'text'         => 'AB 123 CD',
			'font_family'  => 'Great Vibes',
			'bbox'         => $text_box,
			'x_mm'         => $text_box['x_mm'],
			'y_mm'         => $text_box['y_mm'],
			'width_mm'     => $text_box['width_mm'],
			'height_mm'    => $text_box['height_mm'],
			'scale'        => array( 'x' => 1, 'y' => 1 ),
			'rotation_deg' => 0,
		),
	),
);

$production_vector_svg = sprintf(
	'<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$s %2$s">' .
	'<metadata id="pckz-export-meta"><pckz:export format="fabric-staticCanvas-toSVG" coordinate-system="lightburn-mm-bottom-left"/></metadata>' .
	'<g id="pckz-engrave"><g id="pckz-lines"></g></g></svg>',
	$plate_w,
	$plate_h
);

$package = PCKZ_Export_Engine::run(
	array(
		'canonical_scene'       => wp_json_encode( $canonical ),
		'config'                => PCKZ_Plate_Calibration::default_product_config(),
		'std_spec'              => PCKZ_Std_Spec::for_product( PCKZ_Plate_Calibration::default_product_config() ),
		'canvas_json'           => '{}',
		'design_id'             => 8804,
		'selections'            => $canonical['selections'],
		'production_vector_svg' => $production_vector_svg,
		'text_plate_paths'      => $fragment,
	)
);

if ( is_wp_error( $package ) ) {
	fwrite( STDERR, 'FAIL: ' . $package->get_error_code() . ' — ' . $package->get_error_message() . "\n" );
	exit( 1 );
}

$engrave = 0;
foreach ( (array) ( $package['production_scene']['layers'] ?? array() ) as $layer ) {
	if ( 'path' === ( $layer['type'] ?? '' ) && 'text-engrave' === ( $layer['role'] ?? '' ) && ! empty( $layer['verts'] ) ) {
		++$engrave;
	}
}
if ( $engrave < 1 ) {
	fwrite( STDERR, "FAIL: expected text-engrave after merge on empty Fabric paths\n" );
	exit( 1 );
}

echo "OK: text_plate_paths merge when Fabric SVG has no engrave paths (pre-validation)\n";
exit( 0 );
