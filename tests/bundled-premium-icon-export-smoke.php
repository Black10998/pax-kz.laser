<?php
/**
 * Smoke: bundled premium icon URL resolves; production pipeline accepts icon slug.
 */
require_once __DIR__ . '/smoke-bootstrap.php';

$slug = 'icon_1297690';
if ( ! PCKZ_Icon_Library::bundled_file_path( $slug ) ) {
	$slug = array_key_first( PCKZ_Icon_Library::bundled_manifest() );
}

$url = PCKZ_Icons::icon_url( $slug, 'white' );
if ( ! $url || false === strpos( $url, '/bundled/' . $slug . '.svg' ) ) {
	fwrite( STDERR, "FAIL icon_url for {$slug}\n" );
	exit( 1 );
}

$cat = PCKZ_Ledos_Preview::icon_catalog( true );
if ( empty( $cat[ $slug ]['url'] ) || empty( $cat[ $slug ]['tintable'] ) ) {
	fwrite( STDERR, "FAIL catalog entry for {$slug}\n" );
	exit( 1 );
}

$w   = 529.1;
$h   = 116;
$svg = sprintf(
	'<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %s %s">' .
	'<metadata id="pckz-export-meta"><pckz:export coordinate-system="lightburn-mm-bottom-left"/></metadata>' .
	'<g id="pckz-engrave"><g id="pckz-icon-left"><path d="M 200 300 L 260 300 L 260 360 L 200 360 Z" fill="#ffffff"/></g></g></svg>',
	$w,
	$h
);

$scene = PCKZ_Production_Scene::parse_master_svg( $svg, $w, $h, array( 'symbol_links' => $slug ) );
if ( empty( $scene['layers'] ) ) {
	fwrite( STDERR, "FAIL no layers for icon-left with symbol {$slug}\n" );
	exit( 1 );
}

$lbrn = PCKZ_Production_Lbrn2::build_lbrn2_from_scene( $scene );
if ( ! is_string( $lbrn ) || false === strpos( $lbrn, 'LightBurn' ) ) {
	fwrite( STDERR, "FAIL LBRN2 for symbol {$slug}\n" );
	exit( 1 );
}

echo "OK bundled-premium-icon-export: {$slug} catalog + scene + LBRN2\n";
