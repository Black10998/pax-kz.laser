<?php
/**
 * Smoke: admin disabled icons hidden from customer catalog only.
 */

$root = dirname( __DIR__ );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $root . '/' );
}

if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $string ) {
		return rtrim( $string, '/\\' ) . '/';
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		return $url;
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $key ) );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = false ) {
		global $pckz_test_options;
		return $pckz_test_options[ $key ] ?? $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $key, $value ) {
		global $pckz_test_options;
		$pckz_test_options[ $key ] = $value;
		return true;
	}
}

$GLOBALS['pckz_test_options'] = array();

if ( ! defined( 'PCKZCE_PLUGIN_DIR' ) ) {
	define( 'PCKZCE_PLUGIN_DIR', $root . '/' );
	define( 'PCKZCE_PLUGIN_URL', 'https://example.test/wp-content/plugins/pckz-canonical-engine/' );
}

require_once $root . '/includes/class-pckz-icon-library.php';
require_once $root . '/includes/class-pckz-icons.php';
require_once $root . '/includes/class-pckz-ledos-preview.php';

$slug = 'icon_30325';
if ( ! PCKZ_Icon_Library::bundled_file_path( $slug ) ) {
	$slug = array_key_first( PCKZ_Icon_Library::bundled_manifest() );
}

update_option( PCKZ_Icon_Library::OPTION_DISABLED, array( $slug, 'wolf' ) );

$customer = PCKZ_Ledos_Preview::icon_catalog( true );
$admin    = PCKZ_Ledos_Preview::icon_catalog( false );

if ( isset( $customer[ $slug ] ) || isset( $customer['wolf'] ) ) {
	fwrite( STDERR, "FAIL disabled icons still in customer catalog\n" );
	exit( 1 );
}

if ( empty( $admin[ $slug ] ) || empty( $admin['wolf'] ) ) {
	fwrite( STDERR, "FAIL disabled icons missing from admin catalog\n" );
	exit( 1 );
}

if ( PCKZ_Icon_Library::bundled_file_path( $slug ) === '' ) {
	fwrite( STDERR, "FAIL bundled SVG file removed when disabled\n" );
	exit( 1 );
}

update_option( PCKZ_Icon_Library::OPTION_DISABLED, array() );
$customer2 = PCKZ_Ledos_Preview::icon_catalog( true );
if ( empty( $customer2[ $slug ] ) || empty( $customer2['wolf'] ) ) {
	fwrite( STDERR, "FAIL icons not restored after clearing disabled list\n" );
	exit( 1 );
}

echo "OK icon-visibility: admin disable hides icons; files preserved\n";
