<?php
/**
 * Smoke: master-signed asset token carries binding fields.
 *
 * @package PCKZCanonicalEngine
 */

require_once dirname( __DIR__ ) . '/tests/smoke-bootstrap.php';
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
		unset( $tag, $callback, $priority, $accepted_args );
		return true;
	}
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return max( 0, (int) $value );
	}
}
if ( ! function_exists( 'wp_salt' ) ) {
	function wp_salt( $scheme = 'auth' ) {
		unset( $scheme );
		return 'pckz-smoke-salt';
	}
}
require_once dirname( __DIR__ ) . '/includes/class-pckz-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-pckz-licensing.php';
require_once dirname( __DIR__ ) . '/includes/class-pckz-asset-sync.php';

$token = PCKZ_Asset_Sync::build_asset_download_token(
	array(
		'type' => 'line',
		'slug' => 'type_102',
		'file' => 'type_102.svg',
	),
	array(
		'license' => array( 'id' => 77 ),
		'domain' => 'example.test',
		'install_uuid' => '550e8400-e29b-41d4-a716-446655440000',
	)
);

$decoded = PCKZ_Licensing::verify_signed_token_public( $token );
if ( is_wp_error( $decoded ) ) {
	fwrite( STDERR, "FAIL: token did not verify\n" );
	exit( 1 );
}
if ( 'asset_file' !== (string) ( $decoded['typ'] ?? '' ) ) {
	fwrite( STDERR, "FAIL: token type missing\n" );
	exit( 1 );
}
if ( 77 !== (int) ( $decoded['license_id'] ?? 0 ) ) {
	fwrite( STDERR, "FAIL: license binding missing\n" );
	exit( 1 );
}
if ( 'example.test' !== (string) ( $decoded['domain'] ?? '' ) ) {
	fwrite( STDERR, "FAIL: domain binding missing\n" );
	exit( 1 );
}

echo "asset-token-binding-smoke: OK\n";
exit( 0 );
