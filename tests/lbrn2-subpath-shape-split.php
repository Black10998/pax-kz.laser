#!/usr/bin/env php
<?php
/**
 * Multi-subpath text/icon paths must emit multiple LightBurn Path shapes in .lbrn2.
 * Run: php tests/lbrn2-subpath-shape-split.php
 */
define( 'ABSPATH', __DIR__ );
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $s ) {
		return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
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
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $message;
		public function __construct( $code = '', $message = '' ) {
			unset( $code );
			$this->message = $message;
		}
		public function get_error_message() {
			return $this->message;
		}
	}
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

require_once dirname( __DIR__ ) . '/includes/class-pckz-production-geometry.php';
require_once dirname( __DIR__ ) . '/includes/class-pckz-production-scene.php';
require_once dirname( __DIR__ ) . '/includes/class-pckz-production-lbrn2.php';

$d = 'M 0 0 L 10 0 L 10 10 L 0 10 Z M 20 0 L 30 0 L 30 10 L 20 10 Z';
$svg = sprintf(
	'<?xml version="1.0"?>
<svg xmlns="http://www.w3.org/2000/svg" width="529.1mm" height="116mm" viewBox="0 0 529.1 116">
<g id="pckz-text-engrave"><path d="%s" fill="#000"/></g>
</svg>',
	esc_attr( $d )
);

$scene = PCKZ_Production_Scene::parse_master_svg( $svg, 529.1, 116, array() );
$xml   = PCKZ_Production_Lbrn2::build_lbrn2_from_scene( $scene );
if ( is_wp_error( $xml ) ) {
	fwrite( STDERR, 'FAIL: ' . $xml->get_error_message() . "\n" );
	exit( 1 );
}

$shape_count = preg_match_all( '/<Shape Type="Path"/', $xml, $m );
if ( $shape_count < 2 ) {
	fwrite( STDERR, "FAIL: expected >=2 Path shapes in LBRN2, got {$shape_count}\n" );
	exit( 1 );
}
if ( false === strpos( $xml, '<VertList>' ) ) {
	fwrite( STDERR, "FAIL: LBRN2 missing VertList\n" );
	exit( 1 );
}

echo "OK: LBRN2 emits per-subpath Path shapes with VertList\n";
exit( 0 );
