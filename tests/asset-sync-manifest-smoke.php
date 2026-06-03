<?php
/**
 * Smoke: asset sync manifest builder.
 *
 * @package PCKZCanonicalEngine
 */

require_once dirname( __DIR__ ) . '/tests/smoke-bootstrap.php';
require_once dirname( __DIR__ ) . '/includes/class-pckz-asset-sync.php';

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = false ) {
		global $pckz_smoke_options;
		return $pckz_smoke_options[ $key ] ?? $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $key, $value ) {
		global $pckz_smoke_options;
		$pckz_smoke_options[ $key ] = $value;
		return true;
	}
}
global $pckz_smoke_options;
$pckz_smoke_options = array();

$manifest = PCKZ_Asset_Sync::build_manifest( true );
if ( empty( $manifest['revision'] ) || ! is_array( $manifest['assets'] ) ) {
	fwrite( STDERR, "manifest invalid\n" );
	exit( 1 );
}

echo "asset-sync-manifest-smoke: OK\n";
exit( 0 );
