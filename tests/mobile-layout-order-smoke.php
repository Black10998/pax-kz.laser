<?php
/**
 * Ensures creator DOM order is preview → config → checkout (mobile stack without CSS reorder hacks).
 *
 * @package PCKZCanonicalEngine
 */

$root = dirname( __DIR__ );
$html = file_get_contents( $root . '/public/templates/creator.php' );
if ( ! is_string( $html ) ) {
	fwrite( STDERR, "FAIL: could not read creator.php\n" );
	exit( 1 );
}

$pos_media    = strpos( $html, 'pckz-product__media-column' );
$pos_config   = strpos( $html, 'pckz-product__config-column' );
$pos_checkout = strpos( $html, 'pckz-checkout-panel' );
$pos_wrapper  = strpos( $html, 'pckz-product__checkout-column' );

if ( false === $pos_media || false === $pos_config || false === $pos_checkout ) {
	fwrite( STDERR, "FAIL: missing layout markers in creator.php\n" );
	exit( 1 );
}

if ( false !== $pos_wrapper ) {
	fwrite( STDERR, "FAIL: checkout-column wrapper must be removed (breaks mobile order)\n" );
	exit( 1 );
}

if ( ! ( $pos_media < $pos_config && $pos_config < $pos_checkout ) ) {
	fwrite( STDERR, "FAIL: DOM order must be media → config → checkout\n" );
	exit( 1 );
}

$css = file_get_contents( $root . '/public/css/creator.css' );
if ( ! is_string( $css ) ) {
	fwrite( STDERR, "FAIL: could not read creator.css\n" );
	exit( 1 );
}

if ( strpos( $css, 'display: contents' ) !== false ) {
	fwrite( STDERR, "FAIL: display:contents reorder removed in favor of DOM order + desktop grid\n" );
	exit( 1 );
}

if ( strpos( $css, 'grid-row: 1 / -1' ) === false ) {
	fwrite( STDERR, "FAIL: desktop config column grid span missing\n" );
	exit( 1 );
}

echo "OK mobile-layout-order-smoke: DOM order and desktop grid placement\n";
