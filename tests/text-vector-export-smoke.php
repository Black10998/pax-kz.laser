<?php
/**
 * Vector text must be present in export payload (text_plate_paths → text-engrave paths).
 *
 * Run: php tests/text-vector-export-smoke.php
 *
 * @package PCKZCanonicalEngine
 */

require_once __DIR__ . '/smoke-bootstrap.php';

$plate_w = PCKZ_Plate_Calibration::TOTAL_WIDTH_MM;
$plate_h = PCKZ_Plate_Calibration::PLATE_HEIGHT_MM;
$config  = PCKZ_Plate_Calibration::default_product_config();
$std     = PCKZ_Std_Spec::for_product( $config );

$refs = PCKZ_Ledos_Preview::layer_refs();
$text_box = PCKZ_Ledos_Preview::ref_to_mm_box( $refs['text'], $plate_w, $plate_h, 'bottom-left' );
$y_top_svg = $plate_h - $text_box['y_mm'] - $text_box['height_mm'];
$y_bot_svg = $plate_h - $text_box['y_mm'];

$text_plate_paths = sprintf(
	'<g id="pckz-text-engrave" fill="#FFFFFF"><path d="M %1$s %2$s L %3$s %2$s L %4$s %5$s L %1$s %5$s Z"/></g>',
	PCKZ_Production_Geometry::fmt( $text_box['x_mm'] + 2 ),
	PCKZ_Production_Geometry::fmt( $y_top_svg + 2 ),
	PCKZ_Production_Geometry::fmt( $text_box['x_mm'] + $text_box['width_mm'] - 2 ),
	PCKZ_Production_Geometry::fmt( $y_bot_svg - 2 ),
	PCKZ_Production_Geometry::fmt( $y_top_svg + 2 )
);

// Fabric export with font <text> only (no vector paths) — must still pass when text_plate_paths provided.
$production_vector_svg = sprintf(
	'<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$s %2$s"><metadata id="pckz-export-meta"><pckz:export format="fabric-staticCanvas-toSVG" coordinate-system="lightburn-mm-bottom-left"/></metadata><g id="pckz-engrave"><g id="pckz-lines"><ellipse cx="200" cy="60" rx="40" ry="8" fill="#FF0000"/></g><text id="pckz-text" x="200" y="60" font-size="12">AB 123 CD</text></g></svg>',
	PCKZ_Production_Geometry::fmt( $plate_w ),
	PCKZ_Production_Geometry::fmt( $plate_h )
);

$canonical = array(
	'format'            => 'pckzce-canonical-scene',
	'version'           => 2,
	'coordinate_system' => 'lightburn-mm-bottom-left',
	'plate'             => array( 'width_mm' => $plate_w, 'height_mm' => $plate_h ),
	'selections'        => array( 'custom_text' => 'AB 123 CD' ),
	'objects'           => array(
		array(
			'id'            => 'pckz-text',
			'role'          => 'text',
			'bbox'          => $text_box,
			'x_mm'          => $text_box['x_mm'],
			'y_mm'          => $text_box['y_mm'],
			'width_mm'      => $text_box['width_mm'],
			'height_mm'     => $text_box['height_mm'],
			'text'          => 'AB 123 CD',
			'font_family'   => 'Russo One',
			'rotation_deg'  => 0,
			'scale'         => array( 'x' => 1, 'y' => 1 ),
		),
	),
);

$run = function ( $args, $expect_ok ) use ( $canonical, $config, $std ) {
	$package = PCKZ_Export_Engine::run( $args );
	if ( $expect_ok ) {
		if ( is_wp_error( $package ) ) {
			fwrite( STDERR, 'FAIL unexpected error: ' . $package->get_error_message() . "\n" );
			exit( 1 );
		}
		$has_engrave = false;
		foreach ( (array) ( $package['production_scene']['layers'] ?? array() ) as $layer ) {
			if ( 'path' === ( $layer['type'] ?? '' )
				&& in_array( $layer['role'] ?? '', array( 'text-engrave' ), true )
				&& ! empty( $layer['verts'] ) ) {
				$has_engrave = true;
				break;
			}
		}
		if ( ! $has_engrave ) {
			fwrite( STDERR, "FAIL: expected text-engrave path layer\n" );
			exit( 1 );
		}
		return $package;
	}
	if ( ! is_wp_error( $package ) ) {
		fwrite( STDERR, "FAIL: expected vector_text_missing error\n" );
		exit( 1 );
	}
	if ( ! in_array( $package->get_error_code(), array( 'vector_text_missing', 'vector_text_invalid' ), true ) ) {
		fwrite( STDERR, 'FAIL: wrong error code ' . $package->get_error_code() . ': ' . $package->get_error_message() . "\n" );
		exit( 1 );
	}
	return null;
};

$base = array(
	'canonical_scene'       => wp_json_encode( $canonical ),
	'config'                => $config,
	'std_spec'              => $std,
	'canvas_json'           => '{}',
	'design_id'             => 8801,
	'selections'            => $canonical['selections'],
	'production_vector_svg' => $production_vector_svg,
);

$run( $base, false );

$package = $run( array_merge( $base, array( 'text_plate_paths' => $text_plate_paths ) ), true );

$lbrn2 = PCKZ_Production_Lbrn2::build_from_package( $package );
if ( is_wp_error( $lbrn2 ) || ! preg_match( '/VertList|Shape/i', (string) $lbrn2 ) ) {
	fwrite( STDERR, "FAIL: LBRN2 missing vector shapes\n" );
	exit( 1 );
}

echo "OK: vector text export (text_plate_paths required when Fabric ships <text> only)\n";
exit( 0 );
