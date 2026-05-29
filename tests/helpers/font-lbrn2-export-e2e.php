<?php
/**
 * PHP leg: merge text_plate_paths + build LBRN2 (invoked from font-lbrn2-export-e2e.sh).
 *
 * @package PCKZCanonicalEngine
 */

$fragment = getenv( 'FRAGMENT' );
if ( ! is_string( $fragment ) || '' === trim( $fragment ) ) {
	fwrite( STDERR, "FAIL: FRAGMENT env missing\n" );
	exit( 1 );
}

require_once dirname( __DIR__ ) . '/smoke-bootstrap.php';

$plate_w = PCKZ_Plate_Calibration::TOTAL_WIDTH_MM;
$plate_h = PCKZ_Plate_Calibration::PLATE_HEIGHT_MM;
$refs    = PCKZ_Ledos_Preview::layer_refs();
$text_box = PCKZ_Ledos_Preview::ref_to_mm_box( $refs['text'], $plate_w, $plate_h, 'bottom-left' );
$family  = getenv( 'FONT_NAME' );
$family  = is_string( $family ) && 'great-vibes' === $family ? 'Great Vibes' : 'Russo One';

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
			'font_family'  => $family,
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
		'design_id'             => 8803,
		'selections'            => $canonical['selections'],
		'production_vector_svg' => $production_vector_svg,
		'text_plate_paths'      => $fragment,
	)
);

if ( is_wp_error( $package ) ) {
	fwrite( STDERR, 'FAIL ' . getenv( 'FONT_NAME' ) . ': ' . $package->get_error_code() . ' — ' . $package->get_error_message() . "\n" );
	exit( 1 );
}

$engrave = 0;
foreach ( (array) ( $package['production_scene']['layers'] ?? array() ) as $layer ) {
	if ( 'path' === ( $layer['type'] ?? '' ) && 'text-engrave' === ( $layer['role'] ?? '' ) && ! empty( $layer['verts'] ) ) {
		++$engrave;
	}
}
if ( $engrave < 1 ) {
	fwrite( STDERR, 'FAIL ' . getenv( 'FONT_NAME' ) . ": no text-engrave layer\n" );
	exit( 1 );
}

$lbrn2 = PCKZ_Production_Lbrn2::build_from_package( $package );
if ( is_wp_error( $lbrn2 ) || ! preg_match( '/VertList/i', (string) $lbrn2 ) ) {
	fwrite( STDERR, 'FAIL ' . getenv( 'FONT_NAME' ) . ": LBRN2 missing VertList\n" );
	exit( 1 );
}

echo 'OK font-lbrn2-export-e2e: ' . getenv( 'FONT_NAME' ) . " verts_engrave={$engrave}\n";
