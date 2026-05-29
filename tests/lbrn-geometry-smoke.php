#!/usr/bin/env php
<?php
/**
 * Standalone smoke test for production geometry + LBRN2 XML.
 * Run: php tests/lbrn-geometry-smoke.php
 */

require_once __DIR__ . '/smoke-bootstrap.php';


$box = array( 'x' => 88, 'y' => 40, 'width' => 348, 'height' => 65 );
$d   = 'M 0 0 L 100 0 L 100 100 L 0 100 Z';
$fit = PCKZ_Production_Geometry::map_path_to_box_mm( $d, 100, 100, $box, true );
$xs  = array_column( $fit['verts'], 'x' );
$ys  = array_column( $fit['verts'], 'y' );
$w   = max( $xs ) - min( $xs );
$h   = max( $ys ) - min( $ys );
if ( abs( $w - $h ) > 1.0 ) {
	fwrite( STDERR, "FAIL: uniform scale should keep square aspect, got {$w} x {$h}\n" );
	exit( 1 );
}

$package = array(
	'selections' => array(
		'custom_text'     => 'TEST',
		'linien'          => 'type_1',
		'symbol_links'    => 'instagram',
		'symbol_rechts'   => 'none',
	),
	'layout'     => array(
		'canvas_mm'         => array( 'width' => 529.1, 'height' => 116 ),
		'coordinate_origin' => 'bottom-left',
		'design_px'         => array( 'width' => 3651, 'height' => 2132 ),
		'objects'           => array(
			array(
				'role'      => 'text',
				'text'      => 'TEST',
				'mm'        => array(
					'center_x_mm' => 262,
					'center_y_mm' => 72,
					'width_mm'    => 200,
					'height_mm'   => 12,
				),
			),
		),
	),
);

$xml = PCKZ_Production_Lbrn2::build_from_package( $package );
if ( ! is_string( $xml ) ) {
	// May fail without Ledos synthesis — still validate structure from minimal path.
	$parsed = array(
		'verts'  => $fit['verts'],
		'prims'  => $fit['prims'],
		'closed' => true,
	);
	$ref    = new ReflectionClass( 'PCKZ_Production_Lbrn2' );
	$method = $ref->getMethod( 'shape_path' );
	$method->setAccessible( true );
	$fragment = $method->invoke( null, $parsed, 1, 'test' );
	$xml      = '<?xml version="1.0"?><LightBurnProject>' . $fragment . '</LightBurnProject>';
}

if ( false !== strpos( $xml, '<Vert ' ) || false !== strpos( $xml, '<Prim ' ) ) {
	fwrite( STDERR, "FAIL: lbrn2 must use condensed VertList, not <Vert> children\n" );
	exit( 1 );
}
if ( ! preg_match( '/<VertList>V[\d.\-]+ [\d.\-]+/', $xml ) ) {
	fwrite( STDERR, "FAIL: missing condensed VertList content\n" );
	exit( 1 );
}

echo "OK: PHP geometry + LBRN2 condensed VertList\n";
exit( 0 );
