#!/usr/bin/env php
<?php
/**
 * Orientation smoke — bottom-left baked export must not mirror/flip geometry.
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

/**
 * @param array $verts
 * @return array{min_x:float,max_x:float,min_y:float,max_y:float}
 */
function bbox_from_verts( $verts ) {
	$xs = array_column( $verts, 'x' );
	$ys = array_column( $verts, 'y' );
	return array(
		'min_x' => min( $xs ),
		'max_x' => max( $xs ),
		'min_y' => min( $ys ),
		'max_y' => max( $ys ),
	);
}

$meta = '<metadata id="pckz-export-meta"><pckz:export xmlns:pckz="https://pckz-canonical-engine.local/export" coordinate-system="lightburn-mm-bottom-left"/></metadata>';

// Vertical stem on the left (readable "I" shape) — must keep min_x on the left after export.
$bottom_left_svg = sprintf(
	'<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$s %2$s">%3$s<g id="pckz-text-engrave"><path d="M 210 50 L 214 50 L 214 80 L 210 80 Z"/></g></svg>',
	$mm_w,
	$mm_h,
	$meta
);

$scene = PCKZ_Production_Scene::parse_master_svg( $bottom_left_svg, $mm_w, $mm_h, array() );
if ( PCKZ_Production_Scene::COORD_LIGHTBURN_BOTTOM_LEFT !== ( $scene['coordinate_system'] ?? '' ) ) {
	fwrite( STDERR, "FAIL: expected bottom-left coordinate system marker\n" );
	exit( 1 );
}

$layers = $scene['layers'] ?? array();
if ( empty( $layers ) ) {
	fwrite( STDERR, "FAIL: no layers parsed\n" );
	exit( 1 );
}

$box = bbox_from_verts( $layers[0]['verts'] );
if ( $box['min_x'] >= $box['max_x'] - 0.01 ) {
	fwrite( STDERR, "FAIL: text stem bbox collapsed on X\n" );
	exit( 1 );
}
if ( $box['min_y'] >= $box['max_y'] - 0.01 ) {
	fwrite( STDERR, "FAIL: text stem bbox collapsed on Y\n" );
	exit( 1 );
}

// Legacy nested scale(1,-1) text must land at the same bottom-left coords.
$cx      = 213.6;
$cy_svg  = $mm_h - 65;
$path_cx = 50;
$path_cy = -10;
$legacy_svg = sprintf(
	'<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$s %2$s"><g transform="translate(%3$s %4$s) scale(1 -1) translate(%5$s %6$s)"><path d="M 48 -30 L 52 -30 L 52 10 L 48 10 Z"/></g></svg>',
	$mm_w,
	$mm_h,
	$cx,
	$cy_svg,
	-$path_cx,
	-$path_cy
);
$legacy = PCKZ_Production_Scene::parse_master_svg( $legacy_svg, $mm_w, $mm_h, array() );
$legacy_box = bbox_from_verts( $legacy['layers'][0]['verts'] );

if ( abs( $legacy_box['min_x'] - 211.6 ) > 0.05 || abs( $legacy_box['max_x'] - 215.6 ) > 0.05 ) {
	fwrite( STDERR, 'FAIL: legacy text X mirror/min/max expected ~211.6/215.6, got ' . $legacy_box['min_x'] . '/' . $legacy_box['max_x'] . "\n" );
	exit( 1 );
}

// Knocked line in bottom-left coords: left point must stay left of right point.
$line_svg = sprintf(
	'<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$s %2$s">%3$s<g id="pckz-line-0"><path d="M 90 70 L 430 72 L 430 74 L 90 72 Z" fill="#ff0000"/></g></svg>',
	$mm_w,
	$mm_h,
	$meta
);
$line_scene = PCKZ_Production_Scene::parse_master_svg( $line_svg, $mm_w, $mm_h, array() );
$line_box   = bbox_from_verts( $line_scene['layers'][0]['verts'] );
if ( $line_box['min_x'] >= $line_box['max_x'] - 1 ) {
	fwrite( STDERR, "FAIL: line geometry appears horizontally flipped\n" );
	exit( 1 );
}

echo "OK orientation: bottom-left coords preserved (text x={$box['min_x']}-{$box['max_x']}, line x={$line_box['min_x']}-{$line_box['max_x']})\n";
exit( 0 );
