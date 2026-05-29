<?php
/**
 * Smoke: font library catalog and visibility.
 */

$root = dirname( __DIR__ );
require_once $root . '/tests/smoke-bootstrap.php';

require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-font-library.php';

$catalog = PCKZ_Font_Library::default_catalog();
if ( count( $catalog ) < 15 ) {
	fwrite( STDERR, "FAIL expected at least 15 default fonts\n" );
	exit( 1 );
}

$cats = array();
foreach ( $catalog as $row ) {
	$cats[ $row['category'] ?? '' ] = true;
}
foreach ( array( 'luxury', 'elegant', 'sport', 'technical', 'modern' ) as $need ) {
	if ( empty( $cats[ $need ] ) ) {
		fwrite( STDERR, "FAIL missing category {$need}\n" );
		exit( 1 );
	}
}

update_option( PCKZ_Font_Library::OPTION_DISABLED, array( 'cinzel' ) );
$customer = PCKZ_Font_Library::get_customer_fonts();
foreach ( $customer as $font ) {
	if ( ( $font['id'] ?? '' ) === 'cinzel' ) {
		fwrite( STDERR, "FAIL disabled font should be hidden\n" );
		exit( 1 );
	}
}

$url = PCKZ_Font_Library::google_fonts_css_url();
if ( '' === $url || false === strpos( $url, 'fonts.googleapis.com' ) ) {
	fwrite( STDERR, "FAIL google_fonts_css_url\n" );
	exit( 1 );
}

update_option( PCKZ_Font_Library::OPTION_DISABLED, array() );

$maps = PCKZ_Font_Library::build_font_file_maps();
if ( empty( $maps['byFamily']['russo one'] ) || empty( $maps['byId']['russo-one'] ) ) {
	fwrite( STDERR, "FAIL russo one missing from font export map\n" );
	exit( 1 );
}

echo "OK font-library-smoke: catalog, categories, visibility, Google URL, export map\n";
