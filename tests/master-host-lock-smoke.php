<?php
/**
 * Smoke test: master functionality must be host-locked to paxdesign.at.
 *
 * @package PCKZCanonicalEngine
 */

define( 'ABSPATH', '/tmp/wp/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'PCKZCE_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'PCKZCE_VERSION', '2.23.0-test' );
define( 'PCKZCE_BUILD', '2.23.0-test-build' );

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;
		public function __construct( $code = '', $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}
		public function get_error_code() { return $this->code; }
		public function get_error_message() { return $this->message; }
	}
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
	class WP_REST_Request {
		private $headers = array();
		private $params  = array();
		private $body    = '';
		public function __construct( $headers = array(), $params = array(), $body = '' ) {
			$this->headers = $headers;
			$this->params  = $params;
			$this->body    = $body;
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

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = '' ) { return $text; }
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
if ( ! function_exists( 'rest_ensure_response' ) ) {
	function rest_ensure_response( $data ) { return $data; }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) ); }
}
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) { return (string) $url; }
}
if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) { return parse_url( (string) $url, $component ); }
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
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value ) { return json_encode( $value ); }
}
if ( ! function_exists( 'wp_generate_uuid4' ) ) {
	function wp_generate_uuid4() { return '11111111-2222-3333-4444-555555555555'; }
}
if ( ! function_exists( 'wp_generate_password' ) ) {
	function wp_generate_password( $length = 12, $special = true, $extra = false ) {
		unset( $special, $extra );
		return substr( str_repeat( 'x', (int) $length ), 0, (int) $length );
	}
}
if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability ) {
		unset( $capability );
		return false;
	}
}
if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) { return 'https://client.example.com' . $path; }
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
			return 'Client Smoke';
		}
		return '';
	}
}
if ( ! function_exists( 'wp_salt' ) ) {
	function wp_salt( $scheme = 'auth' ) { return 'smoke-salt-' . $scheme; }
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

require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-settings.php';
require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-licensing.php';

$settings = PCKZ_Settings::default_options();
$settings['licensing_master_mode']    = true;
$settings['licensing_master_api_key'] = 'master-secret-key';
update_option( PCKZ_Settings::OPTION_KEY, $settings );

$licensing = new PCKZ_Licensing();

if ( true === PCKZ_Licensing::is_master_mode() ) {
	fwrite( STDERR, "Master mode should be disabled on non-paxdesign host.\n" );
	exit( 1 );
}

$allowed = $licensing->rest_master_permission(
	new WP_REST_Request(
		array( 'x-pckz-master-key' => 'master-secret-key' ),
		array(),
		''
	)
);
if ( true === $allowed ) {
	fwrite( STDERR, "Master REST permission should be denied on non-paxdesign host.\n" );
	exit( 1 );
}

$check_in = $licensing->rest_client_check_in(
	new WP_REST_Request(
		array(),
		array(),
		wp_json_encode(
			array(
				'license_key'  => 'PCKZCE-TEST',
				'domain'       => 'client.example.com',
				'install_uuid' => 'uuid',
			)
		)
	)
);
if ( ! is_array( $check_in ) || ! isset( $check_in['reason'] ) || 'master_mode_disabled' !== $check_in['reason'] ) {
	fwrite( STDERR, "Client check-in endpoint should be disabled outside authorized master host.\n" );
	exit( 1 );
}

echo "OK master-host-lock-smoke: master APIs disabled outside paxdesign.at\n";
