<?php
/**
 * Smoke: bundled generic icons embed and parse for production SVG/LBRN2 pipeline.
 */
require_once __DIR__ . '/smoke-bootstrap.php';

$root = dirname( __DIR__ );

$w   = 529.1;
$h   = 116;
$ok  = 0;
$try = array( 'car_sedan', 'wolf_head', 'tech_chip', 'shield_classic', 'decor_laurel' );

foreach ( $try as $slug ) {
	$path = $root . '/public/images/icons/' . $slug . '-white.svg';
	if ( ! is_readable( $path ) ) {
		fwrite( STDERR, "FAIL missing {$path}\n" );
		exit( 1 );
	}
	$inner = file_get_contents( $path );
	$inner = preg_replace( '/<\?xml[^?]*\?>/i', '', $inner );
	$inner = preg_replace( '/<svg\b[^>]*>/i', '', $inner, 1 );
	$inner = preg_replace( '/<\/svg>\s*$/i', '', $inner );
	$svg   = sprintf(
		'<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %s %s">' .
		'<metadata id="pckz-export-meta"><pckz:export coordinate-system="lightburn-mm-bottom-left"/></metadata>' .
		'<g id="pckz-engrave"><g id="pckz-icon-left" fill="#FF0000">%s</g></g></svg>',
		$w,
		$h,
		$inner
	);
	$scene = PCKZ_Production_Scene::parse_master_svg( $svg, $w, $h, array( 'symbol_links' => $slug ) );
	if ( empty( $scene['layers'] ) ) {
		fwrite( STDERR, "FAIL no layers for icon {$slug}\n" );
		exit( 1 );
	}
	$lbrn = PCKZ_Production_Lbrn2::build_lbrn2_from_scene( $scene );
	if ( ! is_string( $lbrn ) || false === strpos( $lbrn, 'LightBurn' ) ) {
		fwrite( STDERR, "FAIL LBRN2 missing for {$slug}\n" );
		exit( 1 );
	}
	++$ok;
}

echo "OK bundled-icon-export: {$ok} generic icons parsed to scene + LBRN2\n";
