<?php
/**
 * Smoke: bulk delete removes only custom library items.
 *
 * @package PCKZCanonicalEngine
 */

$root = dirname( __DIR__ );
require_once $root . '/tests/smoke-bootstrap.php';

require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-icon-library.php';
require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-ledos-preview.php';
require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-line-library.php';

$icon_dir = PCKZ_Icon_Library::upload_dir();
$line_dir = PCKZ_Line_Library::upload_dir();
$icon_a   = 'bulk_smoke_icon_a';
$icon_b   = 'bulk_smoke_icon_b';
$line_a   = 'type_997';
$svg      = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10"/></svg>';

file_put_contents( $icon_dir . '/' . $icon_a . '.svg', $svg );
file_put_contents( $icon_dir . '/' . $icon_b . '.svg', $svg );
file_put_contents( $line_dir . '/' . $line_a . '.svg', $svg );

update_option(
	PCKZ_Icon_Library::OPTION_CUSTOM,
	array(
		$icon_a => array( 'label' => 'A', 'file' => $icon_a . '.svg', 'customer_visible' => true ),
		$icon_b => array( 'label' => 'B', 'file' => $icon_b . '.svg', 'customer_visible' => true ),
	)
);
update_option(
	PCKZ_Line_Library::OPTION_CUSTOM,
	array(
		$line_a => array( 'label' => 'Line A', 'file' => $line_a . '.svg', 'customer_visible' => true ),
	)
);

$icon_result = PCKZ_Icon_Library::delete_custom_bulk( array( $icon_a, $icon_b, 'wolf' ) );
if ( is_wp_error( $icon_result ) || (int) ( $icon_result['deleted'] ?? 0 ) !== 2 || (int) ( $icon_result['skipped'] ?? 0 ) !== 1 ) {
	fwrite( STDERR, "FAIL icon bulk delete counts\n" );
	exit( 1 );
}
if ( PCKZ_Icon_Library::is_custom( $icon_a ) || PCKZ_Icon_Library::is_custom( $icon_b ) ) {
	fwrite( STDERR, "FAIL icon bulk delete left custom entries\n" );
	exit( 1 );
}

$bundled_slug = 'type_198';
$bundled_path = PCKZ_Ledos_Preview::line_assets_dir() . $bundled_slug . '.svg';
file_put_contents( $bundled_path, $svg );

$line_result = PCKZ_Line_Library::delete_selected_bulk( array( $line_a, $bundled_slug, 'type_1' ) );
if ( is_wp_error( $line_result ) ) {
	fwrite( STDERR, "FAIL line bulk delete wp_error\n" );
	exit( 1 );
}
if ( (int) ( $line_result['deleted'] ?? 0 ) !== 2 || (int) ( $line_result['failed'] ?? 0 ) !== 1 ) {
	fwrite( STDERR, "FAIL line bulk delete counts deleted=" . (int) ( $line_result['deleted'] ?? 0 ) . " failed=" . (int) ( $line_result['failed'] ?? 0 ) . "\n" );
	exit( 1 );
}
if ( PCKZ_Line_Library::is_custom( $line_a ) ) {
	fwrite( STDERR, "FAIL line bulk delete left custom entry\n" );
	exit( 1 );
}

echo "OK library-bulk-delete-smoke\n";
