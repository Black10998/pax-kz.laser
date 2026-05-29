#!/usr/bin/env php
<?php
/**
 * Fabric-toSVG placement parity — icon/text/line centers must match Cloudlift refs → canvas → mm.
 */
define( 'ABSPATH', __DIR__ );
if ( ! function_exists( '__' ) ) {
	function __( $s, $d = '' ) {
		return $s;
	}
}
if ( ! function_exists( 'sanitize_hex_color' ) ) {
	function sanitize_hex_color( $c ) {
		return $c;
	}
}

require_once dirname( __DIR__ ) . '/includes/class-pckz-production-geometry.php';
require_once dirname( __DIR__ ) . '/includes/class-pckz-production-scene.php';
require_once dirname( __DIR__ ) . '/includes/class-pckz-ledos-preview.php';

$mm_w = 525;
$mm_h = 145;
$design_w = 3651;
$design_h = 2132;
$bg = array(
	'left'   => 75,
	'top'    => 50,
	'width'  => 900,
	'height' => 526,
);

/**
 * @param array $ref Cloudlift ref box.
 * @param array $bg  Background fit on canvas.
 * @return array{cx:float,cy:float}
 */
function ref_center_canvas( $ref, $bg ) {
	global $design_w, $design_h;
	$cx = ( $ref['refX'] + $ref['refWidth'] / 2 ) / $design_w * $bg['width'] + $bg['left'];
	$cy = ( $ref['refY'] + $ref['refHeight'] / 2 ) / $design_h * $bg['height'] + $bg['top'];
	return array(
		'cx' => $cx,
		'cy' => $cy,
	);
}

/**
 * Canvas px → svg-top-left mm (same as preview-engine canvasToMmMatrixValues).
 *
 * @param float $x Canvas X.
 * @param float $y Canvas Y.
 * @return array{x:float,y:float}
 */
function canvas_to_svg_mm( $x, $y ) {
	global $mm_w, $mm_h, $bg;
	$sx = $mm_w / $bg['width'];
	$sy = $mm_h / $bg['height'];
	return array(
		'x' => ( $x - $bg['left'] ) * $sx,
		'y' => ( $y - $bg['top'] ) * $sy,
	);
}

/**
 * @param array $verts Bottom-left mm verts.
 * @return array{center_x:float,center_y:float}
 */
function layer_center_bl_mm( $verts ) {
	$xs = array_column( $verts, 'x' );
	$ys = array_column( $verts, 'y' );
	return array(
		'center_x' => ( min( $xs ) + max( $xs ) ) / 2,
		'center_y' => ( min( $ys ) + max( $ys ) ) / 2,
	);
}

$refs = PCKZ_Ledos_Preview::layer_refs();
$targets = array(
	'pckz-icon-left'  => ref_center_canvas( $refs['iconLeft'], $bg ),
	'pckz-icon-right' => ref_center_canvas( $refs['iconRight'], $bg ),
	'pckz-text-engrave' => ref_center_canvas( $refs['text'], $bg ),
);

$sx = $mm_w / $bg['width'];
$sy = -$mm_h / $bg['height'];
$tx = -$bg['left'] * $sx;
$ty = $mm_h + $bg['top'] * ( $mm_h / $bg['height'] );

$inner = array();
foreach ( $targets as $id => $pt ) {
	$size = 8;
	$inner[] = sprintf(
		'<g id="%1$s"><path d="M %2$s %3$s L %4$s %3$s L %4$s %5$s L %2$s %5$s Z" fill="#fff"/></g>',
		$id,
		PCKZ_Production_Geometry::fmt( $pt['cx'] - $size / 2 ),
		PCKZ_Production_Geometry::fmt( $pt['cy'] - $size / 2 ),
		PCKZ_Production_Geometry::fmt( $pt['cx'] + $size / 2 ),
		PCKZ_Production_Geometry::fmt( $pt['cy'] + $size / 2 )
	);
}

$meta = '<metadata id="pckz-export-meta"><pckz:export xmlns:pckz="https://pckz-canonical-engine.local/export" format="fabric-toSVG" coordinate-system="lightburn-mm-bottom-left"/></metadata>';
$svg  = sprintf(
	'<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$s %2$s">%3$s<g id="pckz-engrave" transform="matrix(%4$s 0 0 %5$s %6$s %7$s)">%8$s</g></svg>',
	$mm_w,
	$mm_h,
	$meta,
	$sx,
	$sy,
	$tx,
	$ty,
	implode( '', $inner )
);

$scene = PCKZ_Production_Scene::parse_master_svg( $svg, $mm_w, $mm_h, array() );
$by_id = array();
foreach ( $scene['layers'] ?? array() as $layer ) {
	$id = (string) ( $layer['layer_id'] ?? '' );
	if ( $id && ! empty( $layer['verts'] ) ) {
		$by_id[ $id ] = layer_center_bl_mm( $layer['verts'] );
	}
}

$tol = 0.75;
foreach ( array( 'pckz-icon-left', 'pckz-icon-right', 'pckz-text-engrave' ) as $id ) {
	if ( empty( $by_id[ $id ] ) ) {
		fwrite( STDERR, "FAIL: missing parsed layer {$id}\n" );
		exit( 1 );
	}
	$ref_box = PCKZ_Ledos_Preview::ref_to_mm_box(
		$refs[ 'pckz-icon-left' === $id ? 'iconLeft' : ( 'pckz-icon-right' === $id ? 'iconRight' : 'text' ) ],
		$mm_w,
		$mm_h,
		'bottom-left'
	);
	$got = $by_id[ $id ];
	$dx  = abs( $got['center_x'] - $ref_box['center_x_mm'] );
	$dy  = abs( $got['center_y'] - $ref_box['center_y_mm'] );
	if ( $dx > $tol || $dy > $tol ) {
		fwrite(
			STDERR,
			sprintf(
				"FAIL: %s center drift dx=%.3f dy=%.3f (expected %.3f,%.3f got %.3f,%.3f)\n",
				$id,
				$dx,
				$dy,
				$ref_box['center_x_mm'],
				$ref_box['center_y_mm'],
				$got['center_x'],
				$got['center_y']
			)
		);
		exit( 1 );
	}
}

echo "OK placement parity: icon-left icon-right text centers within {$tol}mm\n";
exit( 0 );
