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

require_once dirname( __DIR__ ) . '/includes/class-pckz-font-library.php';
require_once dirname( __DIR__ ) . '/includes/class-pckz-customizer-options.php';
require_once dirname( __DIR__ ) . '/includes/class-pckz-icons.php';
require_once dirname( __DIR__ ) . '/includes/class-pckz-ledos-preview.php';
require_once dirname( __DIR__ ) . '/includes/class-pckz-post-type.php';

$map = PCKZ_Customizer_Options::customer_start_default_map();
$expected_font = PCKZ_Font_Library::default_customer_font_family();
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
if ( 'none' !== ( $defaults_by_id['linien'] ?? '' ) ) {
	fwrite( STDERR, "FAIL: linien default must remain none\n" );
	exit( 1 );
}

$refs = PCKZ_Ledos_Preview::layer_refs();
if ( 817.5 !== (float) ( $refs['iconLeft']['refX'] ?? 0 ) ) {
	fwrite( STDERR, "FAIL: iconLeft refX must be 817.5\n" );
	exit( 1 );
}
if ( 2748.5 !== (float) ( $refs['iconRight']['refX'] ?? 0 ) ) {
	fwrite( STDERR, "FAIL: iconRight refX must be 2748.5\n" );
	exit( 1 );
}

echo "OK: customer start defaults and icon refX positions\n";
