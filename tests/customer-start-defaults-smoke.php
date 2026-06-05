#!/usr/bin/env php
<?php
/**
 * Customer configurator initial layout defaults.
 */
define( 'ABSPATH', __DIR__ );
if ( ! function_exists( '__' ) ) {
	function __( $s, $d = '' ) {
		return $s;
	}
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $key ) );
	}
}
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		return (string) $url;
	}
}
if ( ! defined( 'PCKZCE_PLUGIN_URL' ) ) {
	define( 'PCKZCE_PLUGIN_URL', 'https://example.test/wp-content/plugins/pckz-canonical-engine/' );
}
if ( ! defined( 'PCKZCE_PLUGIN_DIR' ) ) {
	define( 'PCKZCE_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}

require_once dirname( __DIR__ ) . '/includes/class-pckz-font-library.php';
require_once dirname( __DIR__ ) . '/includes/class-pckz-line-library.php';
require_once dirname( __DIR__ ) . '/includes/class-pckz-customizer-options.php';
require_once dirname( __DIR__ ) . '/includes/class-pckz-icons.php';
require_once dirname( __DIR__ ) . '/includes/class-pckz-ledos-preview.php';
require_once dirname( __DIR__ ) . '/includes/class-pckz-post-type.php';

$map = PCKZ_Customizer_Options::customer_start_default_map();
$expected_font = PCKZ_Font_Library::default_customer_font_family();
$expected_line = PCKZ_Line_Library::default_customer_line_slug();
$fonts = PCKZ_Font_Library::get_customer_fonts();

if ( 'Lumi-Plate' !== ( $map['custom_text'] ?? '' ) ) {
	fwrite( STDERR, "FAIL: custom_text default must be Lumi-Plate\n" );
	exit( 1 );
}
if ( 'instagram' !== ( $map['symbol_links'] ?? '' ) ) {
	fwrite( STDERR, "FAIL: symbol_links default must be instagram\n" );
	exit( 1 );
}
if ( 'tiktok' !== ( $map['symbol_rechts'] ?? '' ) ) {
	fwrite( STDERR, "FAIL: symbol_rechts default must be tiktok\n" );
	exit( 1 );
}
if ( ( $map['font_family'] ?? '' ) !== $expected_font ) {
	fwrite( STDERR, "FAIL: font_family default must match first picker font\n" );
	exit( 1 );
}
if ( ( $map['linien'] ?? '' ) !== $expected_line || 'none' === ( $map['linien'] ?? '' ) ) {
	fwrite( STDERR, "FAIL: linien default must be first line in picker ({$expected_line})\n" );
	exit( 1 );
}
if ( empty( $fonts[0]['family'] ) || $expected_font !== $fonts[0]['family'] ) {
	fwrite( STDERR, "FAIL: default_customer_font_family must be first sorted customer font\n" );
	exit( 1 );
}

$defaults_by_id = array();
foreach ( PCKZ_Ledos_Preview::default_cloudlift_options() as $option ) {
	$defaults_by_id[ $option['id'] ?? '' ] = $option['default'] ?? '';
}
foreach ( $map as $id => $value ) {
	if ( ( $defaults_by_id[ $id ] ?? null ) !== $value ) {
		fwrite( STDERR, "FAIL: default_cloudlift_options missing {$id} default\n" );
		exit( 1 );
	}
}

$refs = PCKZ_Ledos_Preview::layer_refs();
$left_ref_x  = PCKZ_Ledos_Preview::icon_ref_x( 'left' );
$right_ref_x = PCKZ_Ledos_Preview::icon_ref_x( 'right' );
if ( abs( $left_ref_x - (float) ( $refs['iconLeft']['refX'] ?? 0 ) ) > 0.01 ) {
	fwrite( STDERR, "FAIL: iconLeft refX must match ~0.5 cm inward shift\n" );
	exit( 1 );
}
if ( abs( $right_ref_x - (float) ( $refs['iconRight']['refX'] ?? 0 ) ) > 0.01 ) {
	fwrite( STDERR, "FAIL: iconRight refX must match ~0.5 cm inward shift\n" );
	exit( 1 );
}

echo "OK: customer start defaults, first line default, and icon refX positions\n";
