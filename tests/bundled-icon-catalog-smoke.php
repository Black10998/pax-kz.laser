<?php
/**
 * Smoke: 50 bundled generic icons register in catalog with readable SVG assets.
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

if ( ! defined( 'PCKZCE_PLUGIN_DIR' ) ) {
	define( 'PCKZCE_PLUGIN_DIR', $root . '/' );
	define( 'PCKZCE_PLUGIN_URL', 'https://example.test/wp-content/plugins/pckz-canonical-engine/' );
}

require_once $root . '/includes/class-pckz-icons.php';
require_once $root . '/includes/class-pckz-ledos-preview.php';

$expected = PCKZ_Icons::bundled_generic_icons();
$count    = count( $expected );

if ( 50 !== $count ) {
	fwrite( STDERR, "FAIL expected 50 bundled generic icons, got {$count}\n" );
	exit( 1 );
}

$icons_dir = $root . '/public/images/icons/';
$catalog   = PCKZ_Ledos_Preview::icon_catalog();
$ok        = 0;

foreach ( $expected as $slug => $label ) {
	$white = $icons_dir . $slug . '-white.svg';
	$black = $icons_dir . $slug . '-black.svg';
	if ( ! is_readable( $white ) || ! is_readable( $black ) ) {
		fwrite( STDERR, "FAIL missing SVG pair for {$slug}\n" );
		exit( 1 );
	}
	if ( empty( $catalog[ $slug ]['url'] ) || empty( $catalog[ $slug ]['tintable'] ) ) {
		fwrite( STDERR, "FAIL {$slug} not in icon_catalog or not tintable\n" );
		exit( 1 );
	}
	if ( ( $catalog[ $slug ]['label'] ?? '' ) !== $label ) {
		fwrite( STDERR, "FAIL {$slug} label mismatch\n" );
		exit( 1 );
	}
	if ( ! in_array( $slug, PCKZ_Icons::all_slugs(), true ) ) {
		fwrite( STDERR, "FAIL {$slug} missing from all_slugs()\n" );
		exit( 1 );
	}
	++$ok;
}

$legacy = array( 'instagram_v4', 'wolf', 'football' );
foreach ( $legacy as $slug ) {
	if ( empty( $catalog[ $slug ]['url'] ) ) {
		fwrite( STDERR, "FAIL legacy icon missing: {$slug}\n" );
		exit( 1 );
	}
}

echo "OK bundled-icon-catalog: {$ok} generic icons registered; legacy icons intact\n";
