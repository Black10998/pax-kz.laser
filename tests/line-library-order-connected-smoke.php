<?php
/**
 * Smoke: line library display order and connected right-side flag.
 */

$root = dirname( __DIR__ );
require_once $root . '/tests/smoke-bootstrap.php';

require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-line-library.php';
require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-ledos-preview.php';

$slug_a = 'type_996';
$slug_b = 'type_997';
$dir    = PCKZ_Line_Library::upload_dir();
$svg    = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 50 20"><path fill="#ff0000" d="M0 10 H50"/></svg>';

foreach ( array( $slug_a => 'Line A', $slug_b => 'Line B' ) as $slug => $label ) {
	file_put_contents( $dir . '/' . $slug . '.svg', $svg );
}

update_option(
	PCKZ_Line_Library::OPTION_CUSTOM,
	array(
		$slug_a => array(
			'label'            => 'Line A',
			'file'             => $slug_a . '.svg',
			'customer_visible' => true,
			'connected_right'  => true,
		),
		$slug_b => array(
			'label'            => 'Line B',
			'file'             => $slug_b . '.svg',
			'customer_visible' => true,
			'connected_right'  => false,
		),
	)
);
update_option( PCKZ_Line_Library::OPTION_DISABLED, array() );
PCKZ_Line_Library::save_display_order( array( $slug_b, $slug_a ) );

$catalog = PCKZ_Ledos_Preview::line_catalog( true );
$keys    = array_keys( $catalog );
$idx_b   = array_search( $slug_b, $keys, true );
$idx_a   = array_search( $slug_a, $keys, true );

if ( false === $idx_b || false === $idx_a || $idx_b >= $idx_a ) {
	fwrite( STDERR, "FAIL customer catalog should follow admin display order (B before A)\n" );
	exit( 1 );
}

if ( empty( $catalog[ $slug_a ]['connected_right'] ) ) {
	fwrite( STDERR, "FAIL connected_right should be exposed in line catalog\n" );
	exit( 1 );
}

$js_catalog = PCKZ_Ledos_Preview::line_catalog_for_js();
if ( empty( $js_catalog[ $slug_a ]['connected_right'] ) ) {
	fwrite( STDERR, "FAIL connected_right missing from line_catalog_for_js\n" );
	exit( 1 );
}

$payload = PCKZ_Line_Library::build_admin_save_payload();
if ( empty( $payload['order'] ) || $payload['order'][0] !== $slug_b ) {
	fwrite( STDERR, "FAIL build_admin_save_payload should include order array\n" );
	exit( 1 );
}

$payload['lines'][ $slug_a ]['connected_right'] = false;
$payload['lines'][ $slug_b ]['connected_right'] = true;
$result = PCKZ_Line_Library::save_admin_state_from_post(
	array(
		'pckz_line_library_payload' => wp_json_encode( $payload ),
	)
);
if ( is_wp_error( $result ) ) {
	fwrite( STDERR, 'FAIL save_admin_state_from_post: ' . $result->get_error_message() . "\n" );
	exit( 1 );
}

if ( PCKZ_Line_Library::connected_right_for_slug( $slug_a ) ) {
	fwrite( STDERR, "FAIL connected_right should persist as false for slug A\n" );
	exit( 1 );
}
if ( ! PCKZ_Line_Library::connected_right_for_slug( $slug_b ) ) {
	fwrite( STDERR, "FAIL connected_right should persist as true for slug B\n" );
	exit( 1 );
}

PCKZ_Line_Library::delete_custom( $slug_a );
PCKZ_Line_Library::delete_custom( $slug_b );

echo "OK line-library-order-connected-smoke\n";
