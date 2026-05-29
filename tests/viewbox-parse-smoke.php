#!/usr/bin/env php
<?php
/**
 * Verify SVG viewBox (canvas px) → mm mapping for LBRN2 parser.
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

$sx = 525 / 900;
$sy = 145 / 526;
$tx = -75 * $sx;
$ty = -50 * $sy;
$svg = sprintf(
	'<?xml version="1.0"?>
<svg xmlns="http://www.w3.org/2000/svg" width="525mm" height="145mm" viewBox="0 0 525 145">
<g id="pckz-engrave" transform="matrix(%s 0 0 %s %s %s)">
<path id="pckz-icon-left" d="M 200 400 L 220 400 L 220 420 L 200 420 Z" fill="#ffffff"/>
</g>
</svg>',
	$sx,
	$sy,
	$tx,
	$ty
);

$scene = PCKZ_Production_Scene::parse_master_svg( $svg, 525, 145, array() );
$layers = $scene['layers'] ?? array();
if ( empty( $layers ) ) {
	fwrite( STDERR, "FAIL: no layers parsed\n" );
	exit( 1 );
}

$verts = $layers[0]['verts'] ?? array();
if ( empty( $verts ) ) {
	fwrite( STDERR, "FAIL: no verts\n" );
	exit( 1 );
}

$min_x = min( array_column( $verts, 'x' ) );
$max_x = max( array_column( $verts, 'x' ) );

// Canvas x=200 → mm x = 200*sx + tx = (200-75)/900*525 ≈ 72.9
if ( $min_x < 65 || $min_x > 85 ) {
	fwrite( STDERR, "FAIL: expected mm x ~73, got min_x={$min_x}\n" );
	exit( 1 );
}

echo "OK: engrave matrix + plate viewBox → mm (min_x={$min_x})\n";
exit( 0 );
