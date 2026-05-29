#!/usr/bin/env php
<?php
/**
 * StaticCanvas export smoke — unified scene SVG must preserve line orientation and layer ids.
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

$line_types = array(
	'type_1', 'type_5', 'type_10', 'type_17',
);

foreach ( $line_types as $lt ) {
	$meta = '<metadata id="pckz-export-meta"><pckz:export xmlns:pckz="https://pckz-canonical-engine.local/export" format="fabric-staticCanvas-toSVG" coordinate-system="lightburn-mm-bottom-left" line-type="' . $lt . '"/></metadata>';
	$svg  = sprintf(
		'<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$s %2$s">%3$s<g id="pckz-engrave" transform="matrix(%4$s 0 0 %5$s %6$s %7$s)"><g id="pckz-line-0"><path d="M 120 360 L 780 360 L 780 365 L 120 365 Z" fill="#ff0000"/></g><g id="pckz-icon-left"><path d="M 200 300 L 260 300 L 260 360 L 200 360 Z" fill="#ffffff"/></g><g id="pckz-main-text"><path d="M 350 320 L 650 320 L 650 380 L 350 380 Z" fill="#ffffff"/></g></g></svg>',
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
		fwrite( STDERR, "FAIL {$lt}: coordinate system\n" );
		exit( 1 );
	}
	$layers = $scene['layers'] ?? array();
	if ( count( $layers ) < 3 ) {
		fwrite( STDERR, "FAIL {$lt}: expected >=3 layers, got " . count( $layers ) . "\n" );
		exit( 1 );
	}
	$xs = array_column( $layers[0]['verts'], 'x' );
	if ( min( $xs ) >= max( $xs ) - 1 ) {
		fwrite( STDERR, "FAIL {$lt}: line not horizontal\n" );
		exit( 1 );
	}
}

echo 'OK scene-export: ' . count( $line_types ) . " line types + icons + text layers parsed\n";
exit( 0 );
