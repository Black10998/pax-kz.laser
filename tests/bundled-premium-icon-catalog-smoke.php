<?php
/**
 * Smoke: NEU icon modele bundled icons register in customer catalog.
 *
 * @package PCKZCanonicalEngine
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
		return $default;
	}
}

if ( ! defined( 'PCKZCE_PLUGIN_DIR' ) ) {
	define( 'PCKZCE_PLUGIN_DIR', $root . '/' );
	define( 'PCKZCE_PLUGIN_URL', 'https://example.test/wp-content/plugins/pckz-canonical-engine/' );
}

require_once $root . '/includes/class-pckz-icon-library.php';
require_once $root . '/includes/class-pckz-icons.php';
require_once $root . '/includes/class-pckz-ledos-preview.php';

$manifest = PCKZ_Icon_Library::bundled_manifest();
$count    = count( $manifest );

if ( $count < 100 ) {
	fwrite( STDERR, "FAIL expected >=100 bundled premium icons, got {$count}\n" );
	exit( 1 );
}

$catalog = PCKZ_Ledos_Preview::icon_catalog( true );
$ok      = 0;

foreach ( $manifest as $slug => $label ) {
	$path = PCKZ_Icon_Library::bundled_file_path( $slug );
	if ( ! $path ) {
		fwrite( STDERR, "FAIL missing file for {$slug}\n" );
		exit( 1 );
	}
	if ( empty( $catalog[ $slug ]['url'] ) || empty( $catalog[ $slug ]['tintable'] ) ) {
		fwrite( STDERR, "FAIL {$slug} not in customer catalog\n" );
		exit( 1 );
	}
	if ( ! in_array( $slug, PCKZ_Icons::all_slugs(), true ) ) {
		fwrite( STDERR, "FAIL {$slug} not in all_slugs()\n" );
		exit( 1 );
	}
	++$ok;
}

if ( empty( $catalog['wolf']['url'] ) || empty( $catalog['instagram_v4']['url'] ) ) {
	fwrite( STDERR, "FAIL legacy CDN icons missing from catalog\n" );
	exit( 1 );
}

echo "OK bundled-premium-icon-catalog: {$ok} icons registered; legacy icons intact\n";
