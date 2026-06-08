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

$slug = 'type_41';
$source_path = PCKZ_Ledos_Preview::line_assets_dir() . $slug . '.svg';
if ( ! is_readable( $source_path ) ) {
	fwrite( STDERR, "SKIP {$slug}.svg missing\n" );
	exit( 0 );
}

PCKZ_Line_Library::ensure_display_asset( $slug );

$ledos_preview = array(
	'designWidth'  => PCKZ_Ledos_Preview::DESIGN_WIDTH,
	'designHeight' => PCKZ_Ledos_Preview::DESIGN_HEIGHT,
	'layers'       => PCKZ_Ledos_Preview::layer_refs(),
	'lineTypes'    => PCKZ_Ledos_Preview::line_types_for_preview_js(),
	'lineCatalog'  => PCKZ_Ledos_Preview::line_catalog_for_js(),
	'iconCatalog'  => PCKZ_Ledos_Preview::icon_catalog_for_js(),
);

foreach ( array( 'designWidth', 'designHeight', 'layers', 'lineTypes', 'lineCatalog', 'iconCatalog' ) as $key ) {
	if ( empty( $ledos_preview[ $key ] ) ) {
		fwrite( STDERR, "FAIL ledosPreview missing {$key}\n" );
		exit( 1 );
	}
}

if ( empty( $ledos_preview['lineCatalog'][ $slug ] ) ) {
	fwrite( STDERR, "FAIL lineCatalog missing {$slug}\n" );
	exit( 1 );
}

$row = $ledos_preview['lineCatalog'][ $slug ];
$picker = (string) ( $row['preview'] ?? $row['url'] ?? '' );
if ( false === strpos( $picker, 'display/' . $slug . '.svg' ) && false === strpos( $picker, 'pckzce_line_preview' ) ) {
	fwrite( STDERR, "FAIL {$slug} lineCatalog should expose display preview URL\n" );
	exit( 1 );
}

if ( empty( $ledos_preview['lineTypes'][ $slug ] ) ) {
	fwrite( STDERR, "FAIL {$slug} lineTypes should map to display preview URL\n" );
	exit( 1 );
}
$lt = (string) $ledos_preview['lineTypes'][ $slug ];
if ( false === strpos( $lt, 'display/' . $slug . '.svg' ) && false === strpos( $lt, 'pckzce_line_preview' ) ) {
	fwrite( STDERR, "FAIL {$slug} lineTypes URL must be display preview endpoint\n" );
	exit( 1 );
}

$export = (string) ( PCKZ_Ledos_Preview::line_types()[ $slug ] ?? '' );
if ( '' === $export || false === strpos( $export, $slug . '.svg' ) ) {
	fwrite( STDERR, "FAIL {$slug} export asset missing from line_types()\n" );
	exit( 1 );
}

$resolved_preview = PCKZ_Line_Library::picker_preview_url( $slug );
if (
	false === strpos( $resolved_preview, 'display/' . $slug . '.svg' )
	&& false === strpos( $resolved_preview, 'pckzce_line_preview' )
) {
	fwrite( STDERR, "FAIL picker_preview_url must return direct display SVG or admin-ajax preview\n" );
	exit( 1 );
}

$display_path = PCKZ_Line_Library::display_asset_path( $slug );
if ( ! is_readable( $display_path ) ) {
	fwrite( STDERR, "FAIL bundled display preview file missing for {$slug}\n" );
	exit( 1 );
}

echo "OK runtime-line-preview-config-smoke\n";
