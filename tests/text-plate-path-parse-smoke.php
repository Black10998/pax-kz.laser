<?php
/**
 * text_plate_paths SVG→vertex parse (entity-encoded, invalid UTF-8, group transform).
 *
 * Run: php tests/text-plate-path-parse-smoke.php
 *
 * @package PCKZCanonicalEngine
 */

require_once __DIR__ . '/smoke-bootstrap.php';

$plate_w = PCKZ_Plate_Calibration::TOTAL_WIDTH_MM;
$plate_h = PCKZ_Plate_Calibration::PLATE_HEIGHT_MM;
$refs    = PCKZ_Ledos_Preview::layer_refs();
$box     = PCKZ_Ledos_Preview::ref_to_mm_box( $refs['text'], $plate_w, $plate_h, 'bottom-left' );
$y_svg   = $plate_h - $box['center_y_mm'];

$fragment = sprintf(
	'<g id="pckz-text-engrave-0" fill="#FFFFFF" stroke="none"><path d="M %s %s L %s %s C %s %s %s %s %s %s Z" fill="#FFFFFF" stroke="none"/></g>',
	PCKZ_Production_Geometry::fmt( $box['center_x_mm'] - 25 ),
	PCKZ_Production_Geometry::fmt( $y_svg ),
	PCKZ_Production_Geometry::fmt( $box['center_x_mm'] + 25 ),
	PCKZ_Production_Geometry::fmt( $y_svg ),
	PCKZ_Production_Geometry::fmt( $box['center_x_mm'] + 27 ),
	PCKZ_Production_Geometry::fmt( $y_svg - 2 ),
	PCKZ_Production_Geometry::fmt( $box['center_x_mm'] + 29 ),
	PCKZ_Production_Geometry::fmt( $y_svg - 4 ),
	PCKZ_Production_Geometry::fmt( $box['center_x_mm'] + 31 ),
	PCKZ_Production_Geometry::fmt( $y_svg - 2 )
);

$probe = PCKZ_Export_Diagnostics::probe_text_fragment_parse( $fragment, $plate_w, $plate_h, array() );
if ( empty( $probe['lbrn2_parse_ok'] ) ) {
	fwrite( STDERR, 'FAIL clean fragment: ' . wp_json_encode( $probe ) . "\n" );
	exit( 1 );
}

$encoded = htmlspecialchars( $fragment, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
$probe2  = PCKZ_Export_Diagnostics::probe_text_fragment_parse( $encoded, $plate_w, $plate_h, array() );
if ( empty( $probe2['lbrn2_parse_ok'] ) ) {
	fwrite( STDERR, 'FAIL entity-encoded: ' . wp_json_encode( $probe2 ) . "\n" );
	exit( 1 );
}

// Invalid UTF-8 byte broke legacy /u regex — must still parse after normalize.
$broken_utf8 = $fragment . "\x80\x81";
$probe3      = PCKZ_Export_Diagnostics::probe_text_fragment_parse( $broken_utf8, $plate_w, $plate_h, array() );
if ( empty( $probe3['lbrn2_parse_ok'] ) || (int) ( $probe3['path_entry_count'] ?? 0 ) < 1 ) {
	fwrite( STDERR, 'FAIL invalid UTF-8 suffix: ' . wp_json_encode( $probe3 ) . "\n" );
	exit( 1 );
}

$transform_frag = '<g id="pckz-text-engrave" transform="matrix(0.12 0 0 -0.12 200 60)"><path d="M 0 0 L 80 0 L 80 40 L 0 40 Z"/></g>';
$probe4         = PCKZ_Export_Diagnostics::probe_text_fragment_parse( $transform_frag, $plate_w, $plate_h, array() );
if ( empty( $probe4['lbrn2_parse_ok'] ) ) {
	fwrite( STDERR, 'FAIL group transform: ' . wp_json_encode( $probe4 ) . "\n" );
	exit( 1 );
}

echo 'OK text-plate-path-parse-smoke verts=' . (int) ( $probe['parsed_vert_count'] ?? 0 ) . "\n";
exit( 0 );
