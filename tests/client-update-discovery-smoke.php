<?php
/**
 * Smoke test: release token changes when master publish metadata changes.
 *
 * @package PCKZCanonicalEngine
 */

define( 'ABSPATH', '/tmp/wp/' );
define( 'PCKZCE_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = '' ) { // phpcs:ignore
		unset( $domain );
		return $text;
	}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) { // phpcs:ignore
		unset( $hook, $callback, $priority, $accepted_args );
	}
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value, $flags = 0, $depth = 512 ) { // phpcs:ignore
		return json_encode( $value, $flags, $depth );
	}
}

require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-licensing.php';

$token_a = PCKZ_Licensing::build_release_token(
	array(
		'version'        => '2.28.11',
		'package_sha256' => 'abc',
		'package_url'    => 'https://example.test/pkg.zip',
		'published_at'   => '2026-06-04 10:00:00',
	)
);
$token_b = PCKZ_Licensing::build_release_token(
	array(
		'version'        => '2.28.12',
		'package_sha256' => 'abc',
		'package_url'    => 'https://example.test/pkg.zip',
		'published_at'   => '2026-06-04 10:00:00',
	)
);

if ( $token_a === $token_b ) {
	fwrite( STDERR, "Release token should change when published version changes.\n" );
	exit( 1 );
}

if ( 32 !== strlen( $token_a ) ) {
	fwrite( STDERR, "Release token should be 32 characters.\n" );
	exit( 1 );
}

echo "OK client-update-discovery-smoke: release token propagation helpers work\n";
