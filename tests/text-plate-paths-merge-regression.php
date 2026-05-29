<?php
/**
 * text_plate_paths must merge when Fabric SVG only has a pckz-text path stub (Great Vibes regression).
 *
 * Run: php tests/text-plate-paths-merge-regression.php
 *
 * @package PCKZCanonicalEngine
 */

require_once __DIR__ . '/smoke-bootstrap.php';

$plate_w = PCKZ_Plate_Calibration::TOTAL_WIDTH_MM;
$plate_h = PCKZ_Plate_Calibration::PLATE_HEIGHT_MM;
$refs    = PCKZ_Ledos_Preview::layer_refs();
$text_box = PCKZ_Ledos_Preview::ref_to_mm_box( $refs['text'], $plate_w, $plate_h, 'bottom-left' );

$text_plate_paths = sprintf(
	'<g id="pckz-text-engrave" fill="#FFFFFF"><path d="M %1$s %2$s L %3$s %2$s L %4$s %5$s L %1$s %5$s Z"/></g>',
	PCKZ_Production_Geometry::fmt( $text_box['x_mm'] + 2 ),
	PCKZ_Production_Geometry::fmt( $plate_h - $text_box['y_mm'] - $text_box['height_mm'] + 2 ),
	PCKZ_Production_Geometry::fmt( $text_box['x_mm'] + $text_box['width_mm'] - 2 ),
	PCKZ_Production_Geometry::fmt( $plate_h - $text_box['y_mm'] - 2 ),
	PCKZ_Production_Geometry::fmt( $plate_h - $text_box['y_mm'] - $text_box['height_mm'] + 2 )
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
	'<g id="pckz-engrave"><path id="pckz-text" d="M 118 90 L 308 90" fill="#ffffff"/></g></svg>',
	$plate_w,
	$plate_h
);

$package = PCKZ_Export_Engine::run(
	array(
		'canonical_scene'       => wp_json_encode( $canonical ),
		'config'                => PCKZ_Plate_Calibration::default_product_config(),
		'std_spec'              => PCKZ_Std_Spec::for_product( PCKZ_Plate_Calibration::default_product_config() ),
		'canvas_json'           => '{}',
		'design_id'             => 8802,
		'selections'            => $canonical['selections'],
		'production_vector_svg' => $production_vector_svg,
		'text_plate_paths'      => $text_plate_paths,
	)
);

if ( is_wp_error( $package ) ) {
	fwrite( STDERR, 'FAIL: ' . $package->get_error_code() . ' — ' . $package->get_error_message() . "\n" );
	exit( 1 );
}

$engrave = 0;
foreach ( (array) ( $package['production_scene']['layers'] ?? array() ) as $layer ) {
	if ( 'path' !== ( $layer['type'] ?? '' ) || empty( $layer['verts'] ) ) {
		continue;
	}
	if ( in_array( $layer['role'] ?? '', array( 'text-engrave' ), true ) ) {
		++$engrave;
	}
	if ( 'pckz-text' === ( $layer['layer_id'] ?? '' ) && 'text-engrave' !== ( $layer['role'] ?? '' ) ) {
		fwrite( STDERR, "FAIL: stale pckz-text stub remained after merge\n" );
		exit( 1 );
	}
}

if ( $engrave < 1 ) {
	fwrite( STDERR, "FAIL: expected text-engrave path after text_plate_paths merge\n" );
	exit( 1 );
}

$lbrn2 = PCKZ_Production_Lbrn2::build_from_package( $package );
if ( is_wp_error( $lbrn2 ) || ! preg_match( '/VertList/i', (string) $lbrn2 ) ) {
	fwrite( STDERR, "FAIL: LBRN2 missing vector geometry\n" );
	exit( 1 );
}

echo "OK: text_plate_paths merge overrides Fabric pckz-text stub (vector_text_invalid regression)\n";
exit( 0 );
