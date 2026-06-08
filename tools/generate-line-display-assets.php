#!/usr/bin/env php
<?php
/**
 * Generate bundled display-normalized line preview SVGs (public/assets/lines/display/).
 *
 * Usage: php tools/generate-line-display-assets.php
 *
 * @package PCKZCanonicalEngine
 */

declare(strict_types=1);

$root = dirname( __DIR__ );
require_once $root . '/tests/smoke-bootstrap.php';
require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-svg-library.php';
require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-line-library.php';

if ( ! class_exists( 'PCKZ_Ledos_Preview' ) ) {
	fwrite( STDERR, "PCKZ_Ledos_Preview missing\n" );
	exit( 1 );
}

$written = 0;
foreach ( array_keys( PCKZ_Ledos_Preview::line_types() ) as $slug ) {
	if ( 'none' === $slug ) {
		continue;
	}
	if ( ! PCKZ_Line_Library::ensure_display_asset( $slug ) ) {
		continue;
	}
	$path = PCKZ_Line_Library::display_asset_path( $slug );
	if ( $path && is_readable( $path ) ) {
		++$written;
		fwrite( STDOUT, "OK {$slug} -> {$path}\n" );
	}
}

fwrite( STDOUT, "Generated {$written} display preview SVG(s).\n" );
