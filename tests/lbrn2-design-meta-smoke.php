<?php
/**
 * LBRN2 URL resolution from design meta + in-memory generation probe.
 *
 * Run: php tests/lbrn2-design-meta-smoke.php
 *
 * @package PCKZCanonicalEngine
 */

require_once __DIR__ . '/smoke-bootstrap.php';

$plate_w = PCKZ_Plate_Calibration::TOTAL_WIDTH_MM;
$plate_h = PCKZ_Plate_Calibration::PLATE_HEIGHT_MM;
$refs    = PCKZ_Ledos_Preview::layer_refs();
$text_box = PCKZ_Ledos_Preview::ref_to_mm_box( $refs['text'], $plate_w, $plate_h, 'bottom-left' );
$y_svg   = $plate_h - $text_box['center_y_mm'];

$fragment = sprintf(
	'<g id="pckz-text-engrave"><path d="M %s %s L %s %s Z" fill="#fff"/></g>',
	PCKZ_Production_Geometry::fmt( $text_box['center_x_mm'] - 20 ),
	PCKZ_Production_Geometry::fmt( $y_svg ),
	PCKZ_Production_Geometry::fmt( $text_box['center_x_mm'] + 20 ),
	PCKZ_Production_Geometry::fmt( $y_svg )
);

$production_vector_svg = sprintf(
	'<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$s %2$s"><metadata id="pckz-export-meta"><pckz:export format="fabric-staticCanvas-toSVG" coordinate-system="lightburn-mm-bottom-left"/></metadata><g id="pckz-engrave"><path id="pckz-line-0" d="M 118 90 L 308 90" fill="#ff0000"/></g></svg>',
	$plate_w,
	$plate_h
);

$canonical = array(
	'format'            => 'pckzce-canonical-scene',
	'version'           => 2,
	'coordinate_system' => 'lightburn-mm-bottom-left',
	'plate'             => array( 'width_mm' => $plate_w, 'height_mm' => $plate_h ),
	'selections'        => array( 'custom_text' => 'AB 12', 'font_family' => 'Russo One' ),
	'objects'           => array(
		array(
			'id'          => 'pckz-text',
			'role'        => 'text',
			'text'        => 'AB 12',
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

$package = PCKZ_Export_Engine::run(
	array(
		'canonical_scene'       => wp_json_encode( $canonical ),
		'config'                => PCKZ_Plate_Calibration::default_product_config(),
		'std_spec'              => PCKZ_Std_Spec::for_product( PCKZ_Plate_Calibration::default_product_config() ),
		'canvas_json'           => '{}',
		'design_id'             => 0,
		'selections'            => $canonical['selections'],
		'production_vector_svg' => $production_vector_svg,
		'text_plate_paths'      => $fragment,
	)
);

if ( is_wp_error( $package ) ) {
	fwrite( STDERR, 'FAIL export: ' . $package->get_error_message() . "\n" );
	exit( 1 );
}

$probe = PCKZ_Export_Diagnostics::probe_lbrn2_generation( $package );
if ( empty( $probe['lbrn2_generated'] ) || empty( $probe['lbrn2_exists'] ) ) {
	fwrite( STDERR, 'FAIL lbrn2 probe: ' . wp_json_encode( $probe ) . "\n" );
	exit( 1 );
}

$package['production_lbrn2_url'] = 'https://example.test/production-design-99.lbrn2';
$design_row                      = array(
	'meta' => array(
		'production_lbrn2_url' => $package['production_lbrn2_url'],
		'production'           => $package,
	),
);

$url = PCKZ_Design_Storage::get_production_lbrn2_url( $design_row );
if ( $url !== $package['production_lbrn2_url'] ) {
	fwrite( STDERR, "FAIL meta URL resolve: got {$url}\n" );
	exit( 1 );
}

// Legacy wrong path must not be required.
$wrong_row = array( 'production' => array( 'production_lbrn2_url' => '' ), 'meta' => $design_row['meta'] );
if ( '' === PCKZ_Design_Storage::get_production_lbrn2_url( $wrong_row ) ) {
	fwrite( STDERR, "FAIL expected meta fallback\n" );
	exit( 1 );
}

echo 'OK lbrn2-design-meta-smoke lbrn2_length=' . (int) $probe['lbrn2_length'] . "\n";
exit( 0 );
