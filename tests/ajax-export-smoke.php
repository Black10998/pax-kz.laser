#!/usr/bin/env php
<?php
/**
 * CLI smoke test: canonical export pipeline + persist (mirrors handle_save_design).
 */
define( 'ABSPATH', __DIR__ . '/../' );

foreach ( array(
	'__' => fn( $s ) => $s,
	'current_time' => fn( $t = 'mysql' ) => gmdate( 'Y-m-d H:i:s' ),
	'is_wp_error' => fn( $t ) => $t instanceof WP_Error,
	'wp_json_encode' => fn( $d ) => json_encode( $d ),
	'wp_parse_args' => function( $args, $defaults = array() ) {
		return array_merge( $defaults, is_array( $args ) ? $args : array() );
	},
	'sanitize_file_name' => fn( $f ) => preg_replace( '/[^A-Za-z0-9._-]/', '', $f ),
	'sanitize_key' => fn( $k ) => $k,
	'sanitize_hex_color' => fn( $c ) => $c,
	'sanitize_title' => fn( $t ) => $t,
	'sanitize_svg_fragment' => fn( $s ) => $s,
	'esc_url_raw' => fn( $u ) => $u,
	'esc_url' => fn( $u ) => $u,
	'esc_attr' => fn( $t ) => $t,
	'esc_html' => fn( $t ) => $t,
	'esc_html__' => fn( $t ) => $t,
	'esc_textarea' => fn( $t ) => $t,
	'esc_xml' => fn( $t ) => $t,
	'content_url' => fn( $p = '' ) => 'http://example.com/wp-content' . $p,
	'home_url' => fn( $p = '' ) => 'http://example.com' . $p,
	'trailingslashit' => fn( $s ) => rtrim( $s, '/\\' ) . '/',
	'plugin_dir_path' => fn( $f ) => dirname( $f ) . '/',
	'plugin_dir_url' => fn( $f ) => 'file://' . dirname( $f ) . '/',
	'plugin_basename' => fn( $f ) => basename( $f ),
	'wp_upload_dir' => fn() => array(
		'path' => sys_get_temp_dir() . '/pckz-uploads',
		'url' => 'http://example.com/uploads',
		'error' => false,
		'basedir' => sys_get_temp_dir() . '/pckz-uploads',
		'baseurl' => 'http://example.com/uploads',
	),
	'wp_mkdir_p' => fn( $t ) => is_dir( $t ) || mkdir( $t, 0777, true ),
	'wp_unique_filename' => fn( $d, $f ) => $f,
	'wp_generate_uuid4' => fn() => bin2hex(random_bytes(16)),
	'get_option' => fn( $k, $d = array() ) => $d,
	'update_option' => fn( $k, $v ) => true,
	'get_transient' => fn() => false,
	'set_transient' => fn() => true,
	'wp_remote_get' => fn() => new WP_Error( 'no_http', 'HTTP disabled' ),
	'wp_remote_retrieve_response_code' => fn() => 0,
	'wp_remote_retrieve_body' => fn() => '',
) as $name => $fn ) {
	if ( ! function_exists( $name ) ) {
		$GLOBALS["__stub_$name"] = $fn;
		eval( "function $name(...\$args) { return (\$GLOBALS['__stub_$name'])(...\$args); }" );
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $message;
		private $data;
		public function __construct( $code = '', $message = '', $data = '' ) {
			$this->message = $message;
			$this->data = $data;
		}
		public function get_error_message() { return $this->message; }
		public function get_error_data() { return $this->data; }
	}
}

define( 'PCKZCE_PLUGIN_FILE', dirname( __DIR__ ) . '/pckz-canonical-engine.php' );
define( 'PCKZCE_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'PCKZCE_PLUGIN_URL', 'file://' . dirname( __DIR__ ) . '/' );
define( 'PCKZCE_PLUGIN_BASENAME', 'pckz-canonical-engine/pckz-canonical-engine.php' );

require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-autoloader.php';
PCKZ_Autoloader::register();

$canonical = array(
	'format' => 'pckzce-canonical-scene',
	'version' => 2,
	'coordinate_system' => 'lightburn-mm-bottom-left',
	'engine' => 'cloudlift-3651',
	'plate' => array( 'width_mm' => 525, 'height_mm' => 145 ),
	'design_px' => array( 'width' => 3651, 'height' => 2132 ),
	'selections' => array( 'linien' => 'type-1' ),
	'objects' => array(
		array(
			'id' => 'pckz-lines-0', 'role' => 'lines',
			'x_mm' => 88, 'y_mm' => 40, 'width_mm' => 348, 'height_mm' => 65,
			'bbox' => array( 'x_mm' => 88, 'y_mm' => 40, 'width_mm' => 348, 'height_mm' => 65, 'center_x_mm' => 262, 'center_y_mm' => 72.5 ),
			'scale' => array( 'x' => 1, 'y' => 1 ), 'rotation_deg' => 0, 'z_order' => 10, 'color' => '#FF0000', 'line_type' => 'type-1',
		),
		array(
			'id' => 'pckz-text', 'role' => 'main-text',
			'x_mm' => 113.6, 'y_mm' => 50, 'width_mm' => 200, 'height_mm' => 30,
			'bbox' => array( 'x_mm' => 113.6, 'y_mm' => 50, 'width_mm' => 200, 'height_mm' => 30, 'center_x_mm' => 213.6, 'center_y_mm' => 65 ),
			'scale' => array( 'x' => 1, 'y' => 1 ), 'rotation_deg' => 0, 'z_order' => 30, 'color' => '#FFFFFF',
			'text' => 'TEST 12', 'font_family' => 'Russo One',
		),
	),
);

$export_args = array(
	'canonical_scene' => wp_json_encode( $canonical ),
	'config' => array(),
	'canvas_json' => '{}',
	'preview_url' => '',
	'export_url' => '',
	'design_id' => 1,
	'selections' => $canonical['selections'],
	'layout' => array(),
);

try {
	$package = PCKZ_Export_Engine::run( $export_args );
} catch ( Throwable $e ) {
	fwrite( STDERR, "FAIL export engine: {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}\n" );
	exit( 1 );
}

if ( is_wp_error( $package ) ) {
	fwrite( STDERR, "FAIL validation: {$package->get_error_message()}\n" );
	fwrite( STDERR, json_encode( $package->get_error_data(), JSON_PRETTY_PRINT ) . "\n" );
	exit( 1 );
}

try {
	$package = PCKZ_Production::persist_export_files( $package, 1 );
} catch ( Throwable $e ) {
	fwrite( STDERR, "FAIL persist: {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}\n" );
	exit( 1 );
}

echo "OK: export pipeline completed\n";
echo "layers: " . count( $package['production_scene']['layers'] ?? array() ) . "\n";
exit( 0 );
