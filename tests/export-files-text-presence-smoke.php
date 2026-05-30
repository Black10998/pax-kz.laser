#!/usr/bin/env php
<?php
/**
 * Persist real SVG/LBRN2 files and verify customer text paths exist on disk.
 *
 * Run: php tests/export-files-text-presence-smoke.php
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
		'custom_text' => 'FILE PROOF',
		'font_family' => 'Playfair Display',
	),
	'objects'           => array(
		array(
			'id'          => 'pckz-text',
			'role'        => 'text',
			'text'        => 'FILE PROOF',
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
		'design_id'             => 9904,
		'selections'            => $canonical['selections'],
	)
);

if ( is_wp_error( $package ) ) {
	fwrite( STDERR, 'FAIL pipeline: ' . $package->get_error_message() . "\n" );
	exit( 1 );
}

$package = PCKZ_Production::persist_export_files( $package, 9904 );
$svg_url = (string) ( $package['production_svg_url'] ?? '' );
$lb_url  = (string) ( $package['production_lbrn2_url'] ?? '' );
if ( '' === $svg_url || '' === $lb_url ) {
	fwrite( STDERR, "FAIL: missing persisted svg/lbrn2 URLs\n" );
	exit( 1 );
}

$upload = wp_upload_dir();
$svg_path = str_replace( $upload['baseurl'], $upload['basedir'], $svg_url );
$lb_path  = str_replace( $upload['baseurl'], $upload['basedir'], $lb_url );

if ( ! is_readable( $svg_path ) || ! is_readable( $lb_path ) ) {
	fwrite( STDERR, "FAIL: persisted files not readable\n" );
	exit( 1 );
}

$svg = (string) file_get_contents( $svg_path );
$lb  = (string) file_get_contents( $lb_path );

$svg_ok = ( false !== strpos( $svg, 'pckz-text-engrave' ) ) && preg_match( '/<path\b[^>]*\bd="/i', $svg );
$lb_ok  = preg_match_all( '/<!-- (pckz-)?text-engrave/', $lb ) > 0
	&& preg_match_all( '/<Shape Type="Path"/', $lb ) > 0
	&& preg_match_all( '/<VertList>/', $lb ) > 0;

if ( ! $svg_ok ) {
	fwrite( STDERR, "FAIL: persisted SVG missing customer text paths\n" );
	exit( 1 );
}
if ( ! $lb_ok ) {
	fwrite( STDERR, "FAIL: persisted LBRN2 missing customer text path shapes\n" );
	exit( 1 );
}

preg_match( '/(<g id="pckz-text-engrave"[\s\S]*?<\/g>)/i', $svg, $svg_match );
preg_match( '/(<!-- (pckz-)?text-engrave[\s\S]*?<VertList>[^<]+<\/VertList>)/i', $lb, $lb_match );

echo 'OK: persisted SVG=' . $svg_path . "\n";
echo 'OK: persisted LBRN2=' . $lb_path . "\n";
echo 'SVG_TEXT_SNIPPET: ' . substr( preg_replace( '/\s+/', ' ', (string) ( $svg_match[1] ?? '' ) ), 0, 220 ) . "\n";
echo 'LBRN2_TEXT_SNIPPET: ' . substr( preg_replace( '/\s+/', ' ', (string) ( $lb_match[1] ?? '' ) ), 0, 220 ) . "\n";
exit( 0 );
