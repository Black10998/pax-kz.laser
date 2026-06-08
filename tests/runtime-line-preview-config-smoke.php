<?php
/**
 * Smoke: live preview runtime payloads expose display-normalized line URLs.
 *
 * @package PCKZCanonicalEngine
 */

$root = dirname( __DIR__ );
require_once $root . '/tests/smoke-bootstrap.php';

require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-ledos-preview.php';
require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-line-library.php';

update_option( PCKZ_Line_Library::OPTION_DELETED, array( 'type_102' ) );
PCKZ_Line_Library::ensure_bundled_naruto_lines_visible();

$ledos_preview = array(
	'designWidth'  => PCKZ_Ledos_Preview::DESIGN_WIDTH,
	'designHeight' => PCKZ_Ledos_Preview::DESIGN_HEIGHT,
	'layers'       => PCKZ_Ledos_Preview::layer_refs(),
	'lineTypes'    => PCKZ_Ledos_Preview::line_types_for_preview_js(),
	'lineCatalog'  => PCKZ_Ledos_Preview::line_catalog_for_js(),
);

foreach ( array( 'designWidth', 'designHeight', 'layers', 'lineTypes', 'lineCatalog' ) as $key ) {
	if ( empty( $ledos_preview[ $key ] ) ) {
		fwrite( STDERR, "FAIL ledosPreview missing {$key}\n" );
		exit( 1 );
	}
}

if ( empty( $ledos_preview['lineCatalog']['type_102'] ) ) {
	fwrite( STDERR, "FAIL lineCatalog missing type_102\n" );
	exit( 1 );
}

$row = $ledos_preview['lineCatalog']['type_102'];
if ( empty( $row['preserve_colors'] ) ) {
	fwrite( STDERR, "FAIL type_102 preserve_colors missing in lineCatalog\n" );
	exit( 1 );
}

$picker = (string) ( $row['preview'] ?? $row['url'] ?? '' );
if ( false === strpos( $picker, 'display/type_102.svg' ) && false === strpos( $picker, 'pckzce_line_preview' ) ) {
	fwrite( STDERR, "FAIL type_102 lineCatalog should expose display preview URL\n" );
	exit( 1 );
}

if ( empty( $ledos_preview['lineTypes']['type_102'] ) ) {
	fwrite( STDERR, "FAIL type_102 lineTypes should map to display preview URL\n" );
	exit( 1 );
}
$lt = (string) $ledos_preview['lineTypes']['type_102'];
if ( false === strpos( $lt, 'display/type_102.svg' ) && false === strpos( $lt, 'pckzce_line_preview' ) ) {
	fwrite( STDERR, "FAIL type_102 lineTypes URL must be display preview endpoint\n" );
	exit( 1 );
}

$export = (string) ( PCKZ_Ledos_Preview::line_types()['type_102'] ?? '' );
if ( '' === $export || false === strpos( $export, 'type_102.svg' ) ) {
	fwrite( STDERR, "FAIL type_102 export asset missing from line_types()\n" );
	exit( 1 );
}

$resolved_preview = PCKZ_Line_Library::picker_preview_url( 'type_102' );
if (
	false === strpos( $resolved_preview, 'display/type_102.svg' )
	&& false === strpos( $resolved_preview, 'pckzce_line_preview' )
) {
	fwrite( STDERR, "FAIL picker_preview_url must return direct display SVG or admin-ajax preview\n" );
	exit( 1 );
}

$display_path = PCKZ_Line_Library::display_asset_path( 'type_102' );
if ( ! is_readable( $display_path ) ) {
	fwrite( STDERR, "FAIL bundled display preview file missing for type_102\n" );
	exit( 1 );
}

echo "OK runtime-line-preview-config-smoke\n";
