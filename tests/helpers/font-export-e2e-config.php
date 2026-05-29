<?php
/**
 * JSON config for browser E2E font export test (mirrors wp_localize_script).
 *
 * @package PCKZCanonicalEngine
 */

putenv( 'PCKZ_LIVE_FONT_HTTP=1' );
$_ENV['PCKZ_LIVE_FONT_HTTP'] = '1';

if ( ! defined( 'PCKZCE_VERSION' ) ) {
	define( 'PCKZCE_VERSION', '2.17.8' );
}
require_once dirname( __DIR__ ) . '/smoke-bootstrap.php';
require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-font-library.php';

PCKZ_Font_Library::clear_google_font_cache();
PCKZ_Font_Library::reset_font_file_maps_cache();

$maps = PCKZ_Font_Library::build_font_file_maps();
$binary = PCKZ_Font_Library::resolve_font_binary_url( 'great-vibes', PCKZ_Font_Library::default_catalog()['great-vibes'] );

echo wp_json_encode(
	array(
		'pluginVersion'        => PCKZCE_VERSION,
		'fontExportRev'        => 2,
		'fontFiles'            => $maps['byFamily'],
		'fontFilesById'        => $maps['byId'],
		'greatVibesExportUrl'  => $maps['byId']['great-vibes'] ?? '',
		'greatVibesBinaryUrl'  => $binary,
		'ajaxUrl'              => 'https://example.test/wp-admin/admin-ajax.php',
	)
);
