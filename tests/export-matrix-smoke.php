#!/usr/bin/env php
<?php
/**
 * LightBurn-style parse: canvas px + engrave matrix → mm plate coords.
 */
define( 'ABSPATH', __DIR__ );
if ( ! function_exists( 'sanitize_hex_color' ) ) {
	function sanitize_hex_color( $c ) {
		return $c;
	}
}
if ( ! function_exists( '__' ) ) {
	function __( $s, $d = '' ) {
		return $s;
	}
}

require_once dirname( __DIR__ ) . '/includes/class-pckz-production-geometry.php';
require_once dirname( __DIR__ ) . '/includes/class-pckz-production-scene.php';

$mm_w = 529.1;
$mm_h = 116;
$bg_l = 75;
$bg_t = 50;
$bg_w = 900;
$bg_h = 526;
$sx   = $mm_w / $bg_w;
$sy   = $mm_h / $bg_h;
$tx   = -$bg_l * $sx;
$ty   = $mm_h + $bg_t * $sy;
$sy   = -$sy;

// Icon center on canvas (example).
$canvas_x = $bg_l + ( 850.5 + 40.5 ) * ( $bg_w / 3651 );
$canvas_y = $bg_t + ( 1243 + 57 ) * ( $bg_h / 2132 );
$expect_mm_x = $sx * $canvas_x + $tx;
$expect_mm_y = ( $mm_h / $bg_h ) * ( $bg_t + $bg_h - $canvas_y );

$svg = sprintf(
	'<?xml version="1.0"?>
<svg xmlns="http://www.w3.org/2000/svg" width="525mm" height="145mm" viewBox="0 0 529.1 116">
<metadata id="pckz-export-meta"><pckz:export xmlns:pckz="https://pckz-canonical-engine.local/export" format="fabric-staticCanvas-toSVG" coordinate-system="lightburn-mm-bottom-left"/></metadata>
<g id="pckz-engrave" transform="matrix(%s 0 0 %s %s %s)">
<circle id="pckz-icon-left" cx="%s" cy="%s" r="2" fill="#fff"/>
</g>
</svg>',
	$sx,
	$sy,
	$tx,
	$ty,
	$canvas_x,
	$canvas_y
);

$scene  = PCKZ_Production_Scene::parse_master_svg( $svg, $mm_w, $mm_h, array() );
$layers = $scene['layers'] ?? array();
if ( empty( $layers ) ) {
	fwrite( STDERR, "FAIL: no layers\n" );
	exit( 1 );
}

$cx = $layers[0]['cx'] ?? ( $layers[0]['verts'][0]['x'] ?? 0 );
$cy = $layers[0]['verts'][0]['y'] ?? ( $layers[0]['cy'] ?? 0 );

if ( abs( $cx - $expect_mm_x ) > 1.25 || abs( $cy - $expect_mm_y ) > 1.25 ) {
	fwrite( STDERR, "FAIL: mm expected (~{$expect_mm_x}, ~{$expect_mm_y}), got ({$cx}, {$cy})\n" );
	exit( 1 );
}

echo "OK: canvas→mm matrix export matches layout mapping (x={$cx} mm)\n";
exit( 0 );
