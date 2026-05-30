#!/usr/bin/env php
<?php
/**
 * Multi-subpath SVG paths must not connect contours (Z closes to subpath start, not index 0).
 * Run: php tests/subpath-close-regression.php
 */
define( 'ABSPATH', __DIR__ );
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $s ) {
		return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
	}
}

require_once dirname( __DIR__ ) . '/includes/class-pckz-production-geometry.php';
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
require_once dirname( __DIR__ ) . '/includes/class-pckz-production-scene.php';

$d = 'M 0 0 L 10 0 L 10 10 L 0 10 Z M 20 0 L 30 0 L 30 10 L 20 10 Z';
$r = PCKZ_Production_Geometry::parse_svg_path_to_verts( $d );

foreach ( $r['prims'] as $prim ) {
	if ( 7 === (int) $prim['p0'] && 0 === (int) $prim['p1'] ) {
		fwrite( STDERR, "FAIL: second Z incorrectly closes to global vertex 0\n" );
		exit( 1 );
	}
}

$svg = sprintf(
	'<?xml version="1.0"?>
<svg xmlns="http://www.w3.org/2000/svg" width="529.1mm" height="116mm" viewBox="0 0 529.1 116">
<metadata id="pckz-export-meta"><pckz:export format="fabric-staticCanvas-toSVG" coordinate-system="lightburn-mm-bottom-left"/></metadata>
<g id="pckz-engrave">
<path id="pckz-icon-left" d="%s" fill="#000"/>
</g>
</svg>',
	esc_attr( $d )
);

$scene = PCKZ_Production_Scene::parse_master_svg( $svg, 529.1, 116, array() );
$loops = PCKZ_Production_Scene::path_loops_from_scene( $scene );
if ( count( $loops ) < 2 ) {
	fwrite( STDERR, "FAIL: expected 2 path loops for 2-subpath icon (got " . count( $loops ) . ")\n" );
	exit( 1 );
}

foreach ( $loops as $loop ) {
	foreach ( $loop['prims'] as $prim ) {
		$v0 = $loop['verts'][ (int) $prim['p0'] ] ?? null;
		$v1 = $loop['verts'][ (int) $prim['p1'] ] ?? null;
		if ( ! $v0 || ! $v1 ) {
			continue;
		}
		$dist = hypot( $v1['x'] - $v0['x'], $v1['y'] - $v0['y'] );
		if ( $dist > 15 ) {
			fwrite( STDERR, "FAIL: spurious long segment ({$dist} mm) in export loop\n" );
			exit( 1 );
		}
	}
}

echo "OK: subpath Z-close and per-contour LightBurn layers\n";
exit( 0 );
