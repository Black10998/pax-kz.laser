<?php
/**
 * Smoke: bundled type_21–71 SVGs parse for production export.
 */
define( 'ABSPATH', __DIR__ );
if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $string ) {
		return rtrim( $string, '/\\' ) . '/';
	}
}
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

if ( ! defined( 'PCKZCE_PLUGIN_DIR' ) ) {
	define( 'PCKZCE_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'PCKZCE_PLUGIN_URL' ) ) {
	define( 'PCKZCE_PLUGIN_URL', 'https://example.test/wp-content/plugins/pckz-canonical-engine/' );
}

require_once dirname( __DIR__ ) . '/includes/class-pckz-ledos-preview.php';
require_once dirname( __DIR__ ) . '/includes/class-pckz-production-geometry.php';
require_once dirname( __DIR__ ) . '/includes/class-pckz-production-scene.php';

$lines = PCKZ_Ledos_Preview::line_types();
$w     = 529.1;
$h     = 116;
$ok    = 0;

for ( $i = PCKZ_Ledos_Preview::BUNDLED_LINE_TYPE_MIN; $i <= PCKZ_Ledos_Preview::BUNDLED_LINE_TYPE_MAX; $i++ ) {
	$key = 'type_' . $i;
	if ( empty( $lines[ $key ] ) ) {
		fwrite( STDERR, "FAIL missing registry {$key}\n" );
		exit( 1 );
	}
	$path = PCKZ_Ledos_Preview::line_assets_dir() . $key . '.svg';
	if ( ! is_readable( $path ) ) {
		fwrite( STDERR, "FAIL missing file {$path}\n" );
		exit( 1 );
	}
	$inner = file_get_contents( $path );
	$inner = preg_replace( '/<\?xml[^?]*\?>/i', '', $inner );
	$inner = preg_replace( '/<svg\b[^>]*>/i', '', $inner, 1 );
	$inner = preg_replace( '/<\/svg>\s*$/i', '', $inner );
	$svg   = sprintf(
		'<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %s %s">' .
		'<metadata id="pckz-export-meta"><pckz:export coordinate-system="lightburn-mm-bottom-left"/></metadata>' .
		'<g id="pckz-engrave"><g id="pckz-lines">%s</g></g></svg>',
		$w,
		$h,
		$inner
	);
	$scene = PCKZ_Production_Scene::parse_master_svg( $svg, $w, $h, array() );
	if ( empty( $scene['layers'] ) ) {
		fwrite( STDERR, "FAIL no layers for {$key}\n" );
		exit( 1 );
	}
	++$ok;
}

echo "OK bundled-line-export: {$ok} types parsed for production\n";
