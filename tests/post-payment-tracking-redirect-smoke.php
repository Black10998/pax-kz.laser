<?php
/**
 * Smoke: paid checkout redirects to tracking page when available.
 *
 * @package PCKZCanonicalEngine
 */

require_once dirname( __DIR__ ) . '/tests/smoke-bootstrap.php';

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return max( 0, (int) $value );
	}
}
if ( ! function_exists( 'get_posts' ) ) {
	function get_posts( $args = array() ) {
		unset( $args );
		return $GLOBALS['pckz_smoke_tracking_pages'] ?? array();
	}
}
if ( ! function_exists( 'has_shortcode' ) ) {
	function has_shortcode( $content, $tag ) {
		return false !== strpos( (string) $content, '[' . $tag );
	}
}
if ( ! function_exists( 'get_permalink' ) ) {
	function get_permalink( $page ) {
		$id = is_object( $page ) ? (int) ( $page->ID ?? 0 ) : (int) $page;
		return $id > 0 ? 'https://example.test/page-' . $id . '/' : '';
	}
}
if ( ! function_exists( 'remove_query_arg' ) ) {
	function remove_query_arg( $keys, $url ) {
		$parts = parse_url( (string) $url );
		if ( empty( $parts['query'] ) ) {
			return (string) $url;
		}
		parse_str( (string) $parts['query'], $query );
		foreach ( (array) $keys as $key ) {
			unset( $query[ $key ] );
		}
		$base = ( $parts['scheme'] ?? 'https' ) . '://' . ( $parts['host'] ?? 'example.test' ) . ( $parts['path'] ?? '/' );
		if ( empty( $query ) ) {
			return $base;
		}
		return $base . '?' . http_build_query( $query );
	}
}

require_once dirname( __DIR__ ) . '/includes/class-pckz-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-pckz-commerce.php';

$GLOBALS['pckz_smoke_tracking_pages'] = array(
	(object) array(
		'ID'           => 77,
		'post_content' => '[pckz_order_tracking]',
	),
);

$order = array(
	'id'         => 123,
	'product_id' => 11,
	'return_url' => 'https://example.test/creator/?foo=bar&pckz_paypal=return',
);

$redirect = PCKZ_Commerce::resolve_post_payment_redirect( $order );
if ( false === strpos( $redirect, 'https://example.test/page-77/' ) ) {
	fwrite( STDERR, "FAIL: expected tracking page redirect, got {$redirect}\n" );
	exit( 1 );
}
if ( false === strpos( $redirect, 'order=PAX-' ) ) {
	fwrite( STDERR, "FAIL: expected obfuscated order query arg, got {$redirect}\n" );
	exit( 1 );
}
if ( false !== strpos( $redirect, 'pckz_paid=' ) ) {
	fwrite( STDERR, "FAIL: tracking redirect should not use creator success flag, got {$redirect}\n" );
	exit( 1 );
}

$GLOBALS['pckz_smoke_tracking_pages'] = array();
$fallback = PCKZ_Commerce::resolve_post_payment_redirect( $order );
if ( false === strpos( $fallback, 'pckz_paid=1' ) || false === strpos( $fallback, 'pckz_order=123' ) ) {
	fwrite( STDERR, "FAIL: fallback redirect missing success payload, got {$fallback}\n" );
	exit( 1 );
}
if ( false !== strpos( $fallback, 'pckz_paypal=' ) ) {
	fwrite( STDERR, "FAIL: fallback redirect should strip payment return args, got {$fallback}\n" );
	exit( 1 );
}

echo "post-payment-tracking-redirect-smoke: OK\n";
exit( 0 );
