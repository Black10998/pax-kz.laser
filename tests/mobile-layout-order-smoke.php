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

$pos_stack    = strpos( $html, 'pckz-product__configure-stack' );
$pos_media    = strpos( $html, 'pckz-product__media-column' );
$pos_config   = strpos( $html, 'pckz-product__config-column' );
$pos_checkout = strpos( $html, 'pckz-checkout-panel' );
$pos_wrapper  = strpos( $html, 'pckz-product__checkout-column' );

if ( false === $pos_stack || false === $pos_media || false === $pos_config || false === $pos_checkout ) {
	fwrite( STDERR, "FAIL: missing layout markers in creator.php\n" );
	exit( 1 );
}

if ( false !== $pos_wrapper ) {
	fwrite( STDERR, "FAIL: checkout-column wrapper must be removed (breaks mobile order)\n" );
	exit( 1 );
}

if ( ! ( $pos_stack < $pos_media && $pos_media < $pos_config && $pos_checkout > $pos_config ) ) {
	fwrite( STDERR, "FAIL: DOM order must be configure-stack(media → config) then checkout\n" );
	exit( 1 );
}

$stack_close = strpos( $html, 'pckz-product__configure-stack' );
$stack_close = strpos( $html, '</div>', strpos( $html, 'pckz-product__config-column', $pos_stack ) );
if ( false !== $stack_close && $pos_checkout < $stack_close ) {
	fwrite( STDERR, "FAIL: checkout must be outside configure-stack\n" );
	exit( 1 );
}

$css = file_get_contents( $root . '/public/css/creator.css' );
if ( ! is_string( $css ) ) {
	fwrite( STDERR, "FAIL: could not read creator.css\n" );
	exit( 1 );
}

if ( preg_match( '/@media\s*\(\s*max-width:\s*989px\s*\)[^{]*\{[^}]*display:\s*contents/s', $css ) ) {
	fwrite( STDERR, "FAIL: display:contents must not be used for mobile reorder\n" );
	exit( 1 );
}

if ( strpos( $css, 'grid-row: 1 / -1' ) === false ) {
	fwrite( STDERR, "FAIL: desktop config column grid span missing\n" );
	exit( 1 );
}

if ( strpos( $css, 'pckz-product__configure-stack' ) === false || strpos( $css, '100dvh' ) === false ) {
	fwrite( STDERR, "FAIL: mobile configure-stack viewport scroll region missing\n" );
	exit( 1 );
}

echo "OK mobile-layout-order-smoke: DOM order and desktop grid placement\n";
