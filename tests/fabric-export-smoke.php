#!/usr/bin/env php
<?php
define( 'ABSPATH', __DIR__ );
if ( ! function_exists( '__' ) ) { function __( $s, $d = '' ) { return $s; } }
if ( ! function_exists( 'sanitize_hex_color' ) ) { function sanitize_hex_color( $c ) { return $c; } }
require_once dirname( __DIR__ ) . '/includes/class-pckz-production-geometry.php';
require_once dirname( __DIR__ ) . '/includes/class-pckz-production-scene.php';
$mm_w = 525; $mm_h = 145; $bg_l = 75; $bg_t = 50; $bg_w = 900; $bg_h = 526;
$sx = $mm_w / $bg_w; $sy = $mm_h / $bg_h; $tx = -$bg_l * $sx; $ty = $mm_h + $bg_t * $sy; $sy = -$sy;
$meta = '<metadata id="pckz-export-meta"><pckz:export xmlns:pckz="https://pckz-canonical-engine.local/export" format="fabric-toSVG" coordinate-system="lightburn-mm-bottom-left"/></metadata>';
$svg = sprintf('<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$s %2$s">%3$s<g id="pckz-engrave" transform="matrix(%4$s 0 0 %5$s %6$s %7$s)"><path d="M %8$s %9$s L %10$s %9$s" fill="#ff0000"/></g></svg>', $mm_w, $mm_h, $meta, $sx, $sy, $tx, $ty, 200, 400, 400);
$scene = PCKZ_Production_Scene::parse_master_svg( $svg, $mm_w, $mm_h, array() );
if ( PCKZ_Production_Scene::COORD_LIGHTBURN_BOTTOM_LEFT !== ( $scene['coordinate_system'] ?? '' ) ) { fwrite(STDERR, "FAIL coord\n"); exit(1);} 
$layers = $scene['layers'] ?? array();
if ( empty( $layers[0]['verts'] ) ) { fwrite(STDERR, "FAIL layers\n"); exit(1);} 
$xs = array_column( $layers[0]['verts'], 'x' );
echo "OK fabric-toSVG x=".min($xs)."-".max($xs)."\n";
