<?php
/**
 * Emit font export URL map JSON for Node OpenType tests.
 *
 * @package PCKZCanonicalEngine
 */

require_once dirname( __DIR__ ) . '/smoke-bootstrap.php';
require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-font-library.php';

PCKZ_Font_Library::clear_google_font_cache();
$maps = PCKZ_Font_Library::build_font_file_maps();
$catalog = array();
foreach ( PCKZ_Font_Library::default_catalog() as $id => $row ) {
	if ( ! PCKZ_Font_Library::is_visible( $id ) ) {
		continue;
	}
	$catalog[ $id ] = array(
		'family'    => $row['family'] ?? '',
		'source'    => $row['source'] ?? '',
		'google_id' => $row['google_id'] ?? '',
	);
}

echo wp_json_encode(
	array(
		'byId'    => $maps['byId'],
		'catalog' => $catalog,
	)
);
