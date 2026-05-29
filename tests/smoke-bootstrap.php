<?php
/**
 * Shared WordPress stubs for CLI smoke tests.
 *
 * @package PCKZCanonicalEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wp/' );
}
if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	define( 'WP_CONTENT_DIR', '/tmp/wp-content' );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

$GLOBALS['pckz_smoke_line_svg'] = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 20"><ellipse cx="25" cy="10" rx="20" ry="3" fill="#f00"/><ellipse cx="75" cy="10" rx="20" ry="3" fill="#f00"/></svg>';
$GLOBALS['pckz_smoke_icon_svg']  = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 81 114"><rect x="10" y="10" width="61" height="94" fill="#fff"/></svg>';

if ( ! function_exists( '__' ) ) {
	function __( $s, $d = '' ) {
		return $s;
	}
}
if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type = 'mysql' ) {
		return gmdate( 'c' );
	}
}
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;
		private $data;

		public function __construct( $code = '', $message = '', $data = '' ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		public function get_error_message() {
			return $this->message;
		}

		public function get_error_data() {
			return $this->data;
		}

		public function get_error_code() {
			return $this->code;
		}
	}
}
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options, $depth );
	}
}
if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults = array() ) {
		if ( is_object( $args ) ) {
			$args = get_object_vars( $args );
		}
		if ( ! is_array( $args ) ) {
			$args = array();
		}
		return array_merge( $defaults, $args );
	}
}
if ( ! function_exists( 'sanitize_file_name' ) ) {
	function sanitize_file_name( $filename ) {
		return $filename;
	}
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		global $pckz_smoke_options;
		return $pckz_smoke_options[ $option ] ?? $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $option, $value ) {
		global $pckz_smoke_options;
		$pckz_smoke_options[ $option ] = $value;
		return true;
	}
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return $key;
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( strip_tags( (string) $str ) );
	}
}
if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $title ) {
		return strtolower( preg_replace( '/[^a-z0-9\-]/', '-', (string) $title ) );
	}
}
if ( ! function_exists( 'wp_delete_file' ) ) {
	function wp_delete_file( $file ) {
		return is_readable( $file ) ? unlink( $file ) : false;
	}
}
if ( ! function_exists( 'sanitize_hex_color' ) ) {
	function sanitize_hex_color( $color ) {
		return $color;
	}
}
if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $title ) {
		return $title;
	}
}
if ( ! function_exists( 'sanitize_svg_fragment' ) ) {
	function sanitize_svg_fragment( $svg ) {
		return $svg;
	}
}
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		return $url;
	}
}
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return $url;
	}
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text ) {
		return $text;
	}
}
if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( $email ) {
		return filter_var( $email, FILTER_SANITIZE_EMAIL );
	}
}
if ( ! function_exists( 'is_email' ) ) {
	function is_email( $email ) {
		return (bool) filter_var( $email, FILTER_VALIDATE_EMAIL );
	}
}
if ( ! function_exists( 'esc_textarea' ) ) {
	function esc_textarea( $text ) {
		return $text;
	}
}
if ( ! function_exists( 'esc_xml' ) ) {
	function esc_xml( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'content_url' ) ) {
	function content_url( $path = '' ) {
		return 'http://ex/wp-content' . $path;
	}
}
if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) {
		return 'http://ex' . $path;
	}
}
if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $string ) {
		return rtrim( $string, '/\\' ) . '/';
	}
}
if ( ! function_exists( 'plugin_dir_path' ) ) {
	function plugin_dir_path( $file ) {
		return dirname( $file ) . '/';
	}
}
if ( ! function_exists( 'plugin_dir_url' ) ) {
	function plugin_dir_url( $file ) {
		return 'file://' . dirname( $file ) . '/';
	}
}
if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( $file ) {
		return basename( $file );
	}
}
if ( ! function_exists( 'wp_upload_dir' ) ) {
	function wp_upload_dir() {
		return array(
			'path'    => '/tmp/up',
			'url'     => 'http://ex/up',
			'error'   => false,
			'basedir' => '/tmp/up',
			'baseurl' => 'http://ex/up',
		);
	}
}
if ( ! function_exists( 'wp_mkdir_p' ) ) {
	function wp_mkdir_p( $target ) {
		return is_dir( $target ) || mkdir( $target, 0777, true );
	}
}
if ( ! function_exists( 'wp_unique_filename' ) ) {
	function wp_unique_filename( $dir, $filename ) {
		return $filename;
	}
}
if ( ! function_exists( 'wp_generate_uuid4' ) ) {
	function wp_generate_uuid4() {
		return bin2hex( random_bytes( 16 ) );
	}
}
if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $key ) {
		return $GLOBALS['pckz_smoke_transients'][ $key ] ?? false;
	}
}
if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $key, $value, $expiration ) {
		$GLOBALS['pckz_smoke_transients'][ $key ] = $value;
		return true;
	}
}
	if ( ! function_exists( 'wp_remote_get' ) ) {
	function wp_remote_get( $url, $args = array() ) {
		if ( strpos( $url, 'fonts.googleapis.com' ) !== false ) {
			$woff = 'https://fonts.gstatic.com/s/russoone/v14/1Ptxg8zYS_SKggPN.woff2';
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => '@font-face{font-weight:700;font-style:normal;src:url(' . $woff . ') format("woff2");}',
			);
		}
		if ( strpos( $url, 'line' ) !== false || strpos( $url, 'Line' ) !== false ) {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => $GLOBALS['pckz_smoke_line_svg'],
			);
		}
		if ( strpos( $url, 'Icon_background' ) !== false || strpos( $url, 'instagram' ) !== false ) {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => $GLOBALS['pckz_smoke_icon_svg'],
			);
		}
		return new WP_Error( 'http_fail', 'not found' );
	}
}
if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $key ) {
		unset( $GLOBALS['pckz_smoke_transients'][ $key ] );
		return true;
	}
}
if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	function wp_remote_retrieve_response_code( $response ) {
		return is_array( $response ) ? (int) ( $response['response']['code'] ?? 0 ) : 0;
	}
}
if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	function wp_remote_retrieve_body( $response ) {
		return is_array( $response ) ? (string) ( $response['body'] ?? '' ) : '';
	}
}

$plugin_dir = dirname( __DIR__ ) . '/';
if ( ! defined( 'PCKZCE_PLUGIN_FILE' ) ) {
	define( 'PCKZCE_PLUGIN_FILE', $plugin_dir . 'pckz-canonical-engine.php' );
	define( 'PCKZCE_PLUGIN_DIR', $plugin_dir );
	define( 'PCKZCE_PLUGIN_URL', 'file://' . $plugin_dir );
	define( 'PCKZCE_VERSION', '2.9.9' );
	define( 'PCKZCE_BUILD', '2.9.9.20260524-plate-calibration' );
	define( 'PCKZCE_PLUGIN_BASENAME', 'pckz-canonical-engine/pckz-canonical-engine.php' );
}

if ( ! class_exists( 'PCKZ_Autoloader' ) ) {
	require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-autoloader.php';
	PCKZ_Autoloader::register();
}
