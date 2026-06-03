<?php
/**
 * Smoke: customer artwork upload + order attachment.
 *
 * @package PCKZCanonicalEngine
 */

require_once dirname( __DIR__ ) . '/tests/smoke-bootstrap.php';
require_once dirname( __DIR__ ) . '/includes/class-pckz-customer-artwork.php';
require_once dirname( __DIR__ ) . '/includes/class-pckz-svg-library.php';

$GLOBALS['pckz_smoke_options'] = array();
$GLOBALS['pckz_smoke_upload_dir'] = sys_get_temp_dir() . '/pckz-smoke-artwork-' . getmypid();

if ( ! function_exists( 'wp_upload_dir' ) ) {
	function wp_upload_dir() {
		$base = $GLOBALS['pckz_smoke_upload_dir'];
		if ( ! is_dir( $base ) ) {
			mkdir( $base, 0777, true );
		}
		return array(
			'basedir' => $base,
			'baseurl' => 'file://' . $base,
		);
	}
}

if ( ! function_exists( 'wp_mkdir_p' ) ) {
	function wp_mkdir_p( $target ) {
		return is_dir( $target ) || mkdir( $target, 0777, true );
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $key, $value ) {
		$GLOBALS['pckz_smoke_options'][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = false ) {
		return $GLOBALS['pckz_smoke_options'][ $key ] ?? $default;
	}
}

if ( ! function_exists( 'wp_generate_password' ) ) {
	function wp_generate_password( $length = 12 ) {
		return substr( md5( uniqid( '', true ) ), 0, $length );
	}
}

if ( ! function_exists( 'move_uploaded_file' ) ) {
	function move_uploaded_file( $from, $to ) {
		return copy( $from, $to );
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $n ) {
		return abs( (int) $n );
	}
}

if ( ! function_exists( 'wp_check_filetype' ) ) {
	function wp_check_filetype( $filename, $mimes = array() ) {
		$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		if ( isset( $mimes[ $ext ] ) ) {
			return array(
				'ext'  => $ext,
				'type' => $mimes[ $ext ],
			);
		}
		return array( 'ext' => '', 'type' => '' );
	}
}

wp_mkdir_p( $GLOBALS['pckz_smoke_upload_dir'] );
$svg_path = $GLOBALS['pckz_smoke_upload_dir'] . '/logo.svg';
file_put_contents(
	$svg_path,
	'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10" fill="#000"/></svg>'
);

$result = PCKZ_Customer_Artwork::handle_upload(
	array(
		'name'     => 'company-logo.svg',
		'tmp_name' => $svg_path,
		'size'     => filesize( $svg_path ),
	),
	0
);

if ( is_wp_error( $result ) ) {
	fwrite( STDERR, 'upload failed: ' . $result->get_error_message() . PHP_EOL );
	exit( 1 );
}

if ( empty( $result['token'] ) || 0 !== strpos( $result['token'], 'art_' ) ) {
	fwrite( STDERR, "invalid token\n" );
	exit( 1 );
}

$details = PCKZ_Customer_Artwork::apply_token_to_details( array(), $result['token'] );
if ( empty( $details['customer_artwork']['stored'] ) ) {
	fwrite( STDERR, "artwork not in details\n" );
	exit( 1 );
}

$resolved = PCKZ_Customer_Artwork::resolve_file_meta( $details['customer_artwork'] );
if ( ! $resolved || ! is_readable( $resolved['path'] ) ) {
	fwrite( STDERR, "file not readable on disk\n" );
	exit( 1 );
}

echo "customer-artwork-smoke: OK\n";
exit( 0 );
