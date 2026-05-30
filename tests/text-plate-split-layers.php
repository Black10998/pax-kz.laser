#!/usr/bin/env php
<?php
/**
 * Text plate paths must produce multiple path layers (one per SVG subpath).
 */
define( 'ABSPATH', __DIR__ );
if ( ! function_exists( 'sanitize_hex_color' ) ) {
	function sanitize_hex_color( $c ) {
		return $c;
	}
}

require_once dirname( __DIR__ ) . '/includes/class-pckz-production-geometry.php';
require_once dirname( __DIR__ ) . '/includes/class-pckz-production-scene.php';

$d = 'M 0 0 L 10 0 L 10 10 L 0 10 Z M 20 0 L 30 0 L 30 10 L 20 10 Z';
$fragment = '<g id="pckz-text-engrave"><path d="' . $d . '" fill="#fff"/></g>';
$parsed   = PCKZ_Production_Scene::parse_text_plate_paths_fragment( $fragment, 529.1, 116, array() );
$layers   = $parsed['layers'] ?? array();
if ( count( $layers ) < 2 ) {
	fwrite( STDERR, 'FAIL: expected >=2 text path layers, got ' . count( $layers ) . "\n" );
	exit( 1 );
}

echo "OK: text plate split into " . count( $layers ) . " layers\n";
exit( 0 );
