<?php
/**
 * Smoke test for master-control API key auth, release validation, and revocation propagation.
 *
 * @package PCKZCanonicalEngine
 */

define( 'ABSPATH', '/tmp/wp/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );
if ( ! defined( 'OBJECT' ) ) {
	define( 'OBJECT', 'OBJECT' );
}
define( 'PCKZCE_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'PCKZCE_VERSION', '2.22.0-test' );
define( 'PCKZCE_BUILD', '2.22.0-test-build' );

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;
		private $data;
		public function __construct( $code = '', $message = '', $data = null ) {
			$this->code = $code;
			$this->message = $message;
			$this->data = $data;
		}
		public function get_error_code() { return $this->code; }
		public function get_error_message() { return $this->message; }
		public function get_error_data() { return $this->data; }
	}
}
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
}
if ( ! class_exists( 'WP_REST_Request' ) ) {
	class WP_REST_Request {
		private $headers = array();
		private $params = array();
		private $body = '';
		public function __construct( $headers = array(), $params = array(), $body = '' ) {
			$this->headers = $headers;
			$this->params = $params;
			$this->body = $body;
		}
		public function get_header( $name ) {
			$name = strtolower( (string) $name );
			foreach ( $this->headers as $key => $value ) {
				if ( strtolower( (string) $key ) === $name ) {
					return $value;
				}
			}
			return '';
		}
		public function get_param( $name ) {
			return $this->params[ $name ] ?? null;
		}
		public function get_body() {
			return $this->body;
		}
	}
}
if ( ! class_exists( 'WP_REST_Response' ) ) {
	class WP_REST_Response {
		public $data;
		public $status;
		public function __construct( $data = null, $status = 200 ) {
			$this->data = $data;
			$this->status = $status;
		}
	}
}
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = '' ) { return $text; }
}
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) { return (string) $url; }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) ); }
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) { return abs( (int) $value ); }
}
if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( $value ) { return filter_var( (string) $value, FILTER_SANITIZE_EMAIL ); }
}
if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $value ) { return trim( strip_tags( (string) $value ) ); }
}
if ( ! function_exists( 'sanitize_hex_color' ) ) {
	function sanitize_hex_color( $value ) { return (string) $value; }
}
if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) { return parse_url( (string) $url, $component ); }
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data ) { return json_encode( $data ); }
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
if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) { return $value; }
}
if ( ! function_exists( 'wp_generate_password' ) ) {
	function wp_generate_password( $length = 12, $special = true, $extra = false ) {
		unset( $special, $extra );
		return substr( str_repeat( 'x', (int) $length ), 0, (int) $length );
	}
}
if ( ! function_exists( 'wp_generate_uuid4' ) ) {
	function wp_generate_uuid4() { return '11111111-2222-3333-4444-555555555555'; }
}
if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) { return 'https://paxdesign.at' . $path; }
}
if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type = 'mysql' ) {
		return 'mysql' === $type ? gmdate( 'Y-m-d H:i:s' ) : time();
	}
}
if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( $field = '' ) {
		if ( 'version' === $field ) {
			return '6.7';
		}
		if ( 'name' === $field ) {
			return 'Master Smoke';
		}
		return '';
	}
}
if ( ! function_exists( 'wp_salt' ) ) {
	function wp_salt( $scheme = 'auth' ) { return 'smoke-salt-' . $scheme; }
}
if ( ! function_exists( 'rest_ensure_response' ) ) {
	function rest_ensure_response( $data ) { return $data; }
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		unset( $hook, $callback, $priority, $accepted_args );
	}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		unset( $hook, $callback, $priority, $accepted_args );
	}
}
if ( ! function_exists( 'register_rest_route' ) ) {
	function register_rest_route( $namespace, $route, $args = array() ) {
		unset( $namespace, $route, $args );
	}
}
if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability ) {
		unset( $capability );
		return false;
	}
}
if ( ! function_exists( 'wp_remote_get' ) ) {
	function wp_remote_get( $url, $args = array() ) {
		unset( $args );
		if ( 'https://releases.example.test/pckz.zip' === $url ) {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => '',
			);
		}
		return new WP_Error( 'http_fail', 'Unexpected URL: ' . $url );
	}
}
if ( ! function_exists( 'wp_remote_post' ) ) {
	function wp_remote_post( $url, $args = array() ) {
		unset( $url, $args );
		return new WP_Error( 'http_fail', 'Not used in this smoke test' );
	}
}
if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	function wp_remote_retrieve_response_code( $response ) {
		return (int) ( $response['response']['code'] ?? 0 );
	}
}
if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	function wp_remote_retrieve_body( $response ) {
		return (string) ( $response['body'] ?? '' );
	}
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = false ) {
		return $GLOBALS['pckz_test_options'][ $key ] ?? $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $key, $value, $autoload = null ) {
		unset( $autoload );
		$GLOBALS['pckz_test_options'][ $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $key ) {
		return $GLOBALS['pckz_test_transients'][ $key ] ?? false;
	}
}
if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $key, $value, $expiration = 0 ) {
		unset( $expiration );
		$GLOBALS['pckz_test_transients'][ $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $key ) {
		unset( $GLOBALS['pckz_test_transients'][ $key ] );
		return true;
	}
}
if ( ! function_exists( 'wp_next_scheduled' ) ) {
	function wp_next_scheduled( $hook ) {
		unset( $hook );
		return false;
	}
}
if ( ! function_exists( 'wp_schedule_event' ) ) {
	function wp_schedule_event( $timestamp, $recurrence, $hook, $args = array() ) {
		unset( $timestamp, $recurrence, $hook, $args );
		return true;
	}
}
if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) { return 'https://master.example.test/wp-admin/' . ltrim( (string) $path, '/' ); }
}
if ( ! function_exists( 'wp_nonce_field' ) ) {
	function wp_nonce_field( $action, $name = '' ) { unset( $action, $name ); }
}
if ( ! function_exists( 'submit_button' ) ) {
	function submit_button( $text = '', $type = 'primary', $name = 'submit', $wrap = true ) {
		unset( $text, $type, $name, $wrap );
	}
}
if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( $text, $domain = '' ) { unset( $domain ); echo $text; }
}
if ( ! function_exists( 'checked' ) ) {
	function checked( $checked, $current = true, $echo = true ) {
		unset( $echo );
		return (bool) $checked === (bool) $current ? 'checked' : '';
	}
}
if ( ! function_exists( 'selected' ) ) {
	function selected( $selected, $current = true, $echo = true ) {
		unset( $echo );
		return (string) $selected === (string) $current ? 'selected' : '';
	}
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $value ) { return (string) $value; }
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $value ) { return (string) $value; }
}
if ( ! function_exists( 'esc_textarea' ) ) {
	function esc_textarea( $value ) { return (string) $value; }
}
if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $value ) { return (string) $value; }
}
if ( ! function_exists( 'wp_redirect' ) ) {
	function wp_redirect( $url, $status = 302 ) {
		$GLOBALS['pckz_test_redirect'] = array( 'url' => $url, 'status' => $status );
		return true;
	}
}
if ( ! function_exists( 'wp_safe_redirect' ) ) {
	function wp_safe_redirect( $url, $status = 302 ) {
		$GLOBALS['pckz_test_safe_redirect'] = array( 'url' => $url, 'status' => $status );
		return true;
	}
}
if ( ! function_exists( 'wp_die' ) ) {
	function wp_die( $message = '' ) {
		throw new RuntimeException( (string) $message );
	}
}

class PCKZ_Test_WPDB {
	public $prefix = 'wp_';
	public $updates = array();
	public $insert_id = 1;

	public function prepare( $query ) {
		$args = func_get_args();
		array_shift( $args );
		if ( count( $args ) === 1 && is_array( $args[0] ) ) {
			$args = $args[0];
		}
		$formatted = array_map(
			static function ( $value ) {
				if ( is_int( $value ) || is_float( $value ) ) {
					return (string) $value;
				}
				return "'" . str_replace( "'", "\\'", (string) $value ) . "'";
			},
			$args
		);
		$query = str_replace( array( '%d', '%f', '%s' ), array( '%s', '%s', '%s' ), $query );
		return vsprintf( $query, $formatted );
	}

	public function update( $table, $data, $where, $format = null, $where_format = null ) {
		unset( $format, $where_format );
		$this->updates[] = array(
			'table' => $table,
			'data'  => $data,
			'where' => $where,
		);
		return 1;
	}

	public function insert( $table, $data, $format = null ) {
		unset( $format );
		$this->updates[] = array(
			'table' => $table,
			'data'  => $data,
			'where' => array(),
		);
		$this->insert_id++;
		return 1;
	}

	public function get_var( $query ) {
		unset( $query );
		return 0;
	}

	public function get_results( $query, $output = OBJECT ) {
		unset( $query, $output );
		return array();
	}

	public function get_row( $query, $output = OBJECT ) {
		unset( $query, $output );
		return null;
	}

	public function esc_like( $value ) {
		return addslashes( (string) $value );
	}
}

$GLOBALS['wpdb'] = new PCKZ_Test_WPDB();

require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-settings.php';
require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-licensing.php';

$settings = PCKZ_Settings::default_options();
$settings['licensing_master_api_key'] = 'master-secret-key';
update_option( PCKZ_Settings::OPTION_KEY, $settings );
update_option(
	PCKZ_Licensing::OPTION_RELEASE_META,
	array(
		'version' => '2.22.0',
		'package_url' => 'https://releases.example.test/pckz.zip',
		'changelog' => 'Smoke',
		'requires' => '6.0',
		'requires_php' => '7.4',
		'tested' => '6.7',
		'min_client_build' => '',
		'allow_remote_export' => true,
	)
);

$licensing = new PCKZ_Licensing();

if ( true !== PCKZ_Licensing::is_master_mode() ) {
	fwrite( STDERR, "Master mode should be enabled automatically on paxdesign.at.\n" );
	exit( 1 );
}

$allowed = $licensing->rest_master_permission(
	new WP_REST_Request(
		array( 'x-pckz-master-key' => 'master-secret-key' ),
		array(),
		''
	)
);
if ( true !== $allowed ) {
	fwrite( STDERR, "Master API key auth should pass.\n" );
	exit( 1 );
}

$denied = $licensing->rest_master_permission(
	new WP_REST_Request(
		array( 'x-pckz-master-key' => 'wrong-key' ),
		array(),
		''
	)
);
if ( true === $denied ) {
	fwrite( STDERR, "Master API key auth should fail for wrong key.\n" );
	exit( 1 );
}

$validation = $licensing->rest_master_validate_release();
if ( empty( $validation['ok'] ) ) {
	fwrite( STDERR, "Release validation should pass with reachable package URL.\n" );
	exit( 1 );
}

$update_response = $licensing->rest_master_update_license(
	new WP_REST_Request(
		array( 'x-pckz-master-key' => 'master-secret-key' ),
		array( 'id' => 42 ),
		wp_json_encode( array( 'status' => 'revoked' ) )
	)
);
if ( empty( $update_response['ok'] ) ) {
	fwrite( STDERR, "License update response should be ok.\n" );
	exit( 1 );
}

$blocked_installs = array_values(
	array_filter(
		$GLOBALS['wpdb']->updates,
		static function ( $entry ) {
			return false !== strpos( $entry['table'], 'pckz_license_installations' )
				&& isset( $entry['data']['status'] )
				&& 'blocked' === $entry['data']['status'];
		}
	)
);
if ( empty( $blocked_installs ) ) {
	fwrite( STDERR, "Revocation should propagate to installation blocking update.\n" );
	exit( 1 );
}

echo "OK master-control-integration-smoke: key auth, release validation, revocation propagation\n";
