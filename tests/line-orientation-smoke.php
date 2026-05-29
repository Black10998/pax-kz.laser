#!/usr/bin/env php
<?php
/**
 * Line type orientation smoke — horizontal line segments must not mirror under fabric-toSVG export.
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

$mm_w = 525;
$mm_h = 145;
$bg_l = 75;
$bg_t = 50;
$bg_w = 900;
$bg_h = 526;
$sx   = $mm_w / $bg_w;
$sy   = -$mm_h / $bg_h;
$tx   = -$bg_l * $sx;
$ty   = $mm_h + $bg_t * ( $mm_h / $bg_h );

// Canvas-space horizontal segment (left point must stay left after export).
$meta = '<metadata id="pckz-export-meta"><pckz:export xmlns:pckz="https://pckz-canonical-engine.local/export" format="fabric-toSVG" coordinate-system="lightburn-mm-bottom-left"/></metadata>';
$svg  = sprintf(
	'<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$s %2$s">%3$s<g id="pckz-engrave" transform="matrix(%4$s 0 0 %5$s %6$s %7$s)"><g id="pckz-line-0"><path d="M 120 360 L 780 360 L 780 365 L 120 365 Z" fill="#ff0000"/></g></g></svg>',
	$mm_w,
	$mm_h,
	$meta,
	$sx,
	$sy,
	$tx,
	$ty
);

$scene = PCKZ_Production_Scene::parse_master_svg( $svg, $mm_w, $mm_h, array() );
if ( PCKZ_Production_Scene::COORD_LIGHTBURN_BOTTOM_LEFT !== ( $scene['coordinate_system'] ?? '' ) ) {
	fwrite( STDERR, "FAIL: expected lightburn-mm-bottom-left coordinate system\n" );
	exit( 1 );
}

$layers = $scene['layers'] ?? array();
if ( empty( $layers[0]['verts'] ) ) {
	fwrite( STDERR, "FAIL: no line layer parsed\n" );
	exit( 1 );
}

$xs = array_column( $layers[0]['verts'], 'x' );
$ys = array_column( $layers[0]['verts'], 'y' );
$min_x = min( $xs );
$max_x = max( $xs );
$min_y = min( $ys );
$max_y = max( $ys );

if ( $min_x >= $max_x - 1 ) {
	fwrite( STDERR, "FAIL: line appears vertically oriented or collapsed (x={$min_x}-{$max_x})\n" );
	exit( 1 );
}
if ( $max_y - $min_y > 15 ) {
	fwrite( STDERR, "FAIL: line thickness/orientation drift too large (y={$min_y}-{$max_y})\n" );
	exit( 1 );
}
if ( $max_x - $min_x < 200 ) {
	fwrite( STDERR, "FAIL: line horizontal span too narrow (x={$min_x}-{$max_x})\n" );
	exit( 1 );
}
if ( $min_x < 0 || $max_x > $mm_w + 1 ) {
	fwrite( STDERR, "FAIL: line span outside plate width (x={$min_x}-{$max_x})\n" );
	exit( 1 );
}

echo "OK line orientation: horizontal span x={$min_x}-{$max_x}, y={$min_y}-{$max_y}\n";
exit( 0 );
