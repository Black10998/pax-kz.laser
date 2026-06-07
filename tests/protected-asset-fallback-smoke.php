<?php
/**
 * Smoke: asset resolver falls back to min/protected files when source is stripped.
 *
 * @package PCKZCanonicalEngine
 */

$root = dirname( __DIR__ );
require_once $root . '/tests/smoke-bootstrap.php';
require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-assets.php';

$tmp_rel = 'public/js/tmp-fallback-smoke.js';
$tmp_min = 'public/js/tmp-fallback-smoke.min.js';
$tmp_path = PCKZCE_PLUGIN_DIR . $tmp_rel;
$tmp_min_path = PCKZCE_PLUGIN_DIR . $tmp_min;

@unlink( $tmp_path );
file_put_contents( $tmp_min_path, 'window.__pckz_tmp_fallback_smoke=1;' );

try {
	$resolved = PCKZ_Assets::asset_relative_path( $tmp_rel, array() );
	if ( $resolved !== $tmp_min ) {
		fwrite( STDERR, "FAIL expected minified fallback {$tmp_min}, got {$resolved}\n" );
		exit( 1 );
	}
	$url = PCKZ_Assets::script_url( $tmp_rel, array() );
	if ( false === strpos( $url, $tmp_min ) ) {
		fwrite( STDERR, "FAIL script_url should reference minified fallback\n" );
		exit( 1 );
	}
} finally {
	@unlink( $tmp_min_path );
}

echo "OK protected-asset-fallback-smoke\n";
