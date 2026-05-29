#!/usr/bin/env php
<?php
/**
 * Visual export smoke — browser WYSIWYG SVG + vector text paths → LBRN2.
 */
define( 'ABSPATH', '/tmp/wp/' );
define( 'WP_CONTENT_DIR', '/tmp/wp-content' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'PCKZCE_VERSION', '2.9.1' );

$plugin_dir = dirname( __DIR__ ) . '/';

foreach (
	array(
		'__'                 => fn( $s ) => $s,
		'current_time'       => fn() => gmdate( 'c' ),
		'is_wp_error'        => fn( $t ) => $t instanceof WP_Error,
		'wp_json_encode'     => fn( $d ) => json_encode( $d ),
		'wp_parse_args'      => fn( $a, $d = array() ) => array_merge( $d, is_array( $a ) ? $a : array() ),
		'sanitize_file_name' => fn( $f ) => $f,
		'sanitize_key'       => fn( $k ) => $k,
		'sanitize_hex_color' => fn( $c ) => $c,
		'sanitize_title'     => fn( $t ) => $t,
		'sanitize_svg_fragment' => fn( $s ) => $s,
		'esc_url_raw'        => fn( $u ) => $u,
		'esc_url'            => fn( $u ) => $u,
		'esc_attr'           => fn( $t ) => $t,
		'esc_html'           => fn( $t ) => $t,
		'esc_html__'         => fn( $t ) => $t,
		'esc_textarea'       => fn( $t ) => $t,
		'esc_xml'            => fn( $t ) => $t,
		'content_url'        => fn( $p = '' ) => 'http://ex/wp-content' . $p,
		'home_url'           => fn( $p = '' ) => 'http://ex' . $p,
		'trailingslashit'    => fn( $s ) => rtrim( $s, '/\\' ) . '/',
		'plugin_dir_path'    => fn( $f ) => dirname( $f ) . '/',
		'plugin_dir_url'     => fn( $f ) => 'file://' . dirname( $f ) . '/',
		'plugin_basename'    => fn( $f ) => basename( $f ),
		'wp_upload_dir'      => fn() => array(
			'path'    => '/tmp/up',
			'url'     => 'http://ex/up',
			'error'   => false,
			'basedir' => '/tmp/up',
			'baseurl' => 'http://ex/up',
		),
		'wp_mkdir_p'         => fn( $t ) => is_dir( $t ) || mkdir( $t, 0777, true ),
		'wp_unique_filename' => fn( $d, $f ) => $f,
		'wp_generate_uuid4'  => fn() => bin2hex( random_bytes( 16 ) ),
		'get_transient'      => fn() => false,
		'set_transient'      => fn() => true,
	) as $n => $fn
) {
	if ( ! function_exists( $n ) ) {
		$GLOBALS[ "s_$n" ] = $fn;
		eval( "function $n(...\$a){return (\$GLOBALS['s_$n'])(...\$a);}" );
	}
}

function wp_remote_get( $url, $args = array() ) {
	return new WP_Error( 'no', '' );
}

function wp_remote_retrieve_response_code( $r ) {
	return is_array( $r ) ? ( $r['response']['code'] ?? 0 ) : 0;
}

function wp_remote_retrieve_body( $r ) {
	return is_array( $r ) ? ( $r['body'] ?? '' ) : '';
}

class WP_Error {
	private $m;
	private $c;
	private $d;

	public function __construct( $c = '', $m = '', $d = '' ) {
		$this->c = $c;
		$this->m = $m;
		$this->d = $d;
	}

	public function get_error_message() {
		return $this->m;
	}

	public function get_error_data() {
		return $this->d;
	}

	public function get_error_code() {
		return $this->c;
	}
}

define( 'PCKZCE_PLUGIN_FILE', $plugin_dir . 'pckz-canonical-engine.php' );
define( 'PCKZCE_PLUGIN_DIR', $plugin_dir );
define( 'PCKZCE_PLUGIN_URL', 'file://' . $plugin_dir );
define( 'PCKZCE_PLUGIN_BASENAME', 'pckz-canonical-engine/pckz-canonical-engine.php' );

require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-autoloader.php';
PCKZ_Autoloader::register();

$refs = PCKZ_Ledos_Preview::layer_refs();
$mm_w = 525;
$mm_h = 145;
$objects = array();
$map     = array(
	array( 'id' => 'pckz-lines', 'role' => 'lines', 'ref' => 'lines', 'line_type' => 'type_17' ),
	array( 'id' => 'pckz-icon-bg-left', 'role' => 'icon-bg-left', 'ref' => 'iconBgLeft' ),
	array( 'id' => 'pckz-icon-bg-right', 'role' => 'icon-bg-right', 'ref' => 'iconBgRight' ),
	array( 'id' => 'pckz-icon-left', 'role' => 'icon-left', 'ref' => 'iconLeft', 'symbol' => 'instagram' ),
	array( 'id' => 'pckz-icon-right', 'role' => 'icon-right', 'ref' => 'iconRight', 'symbol' => 'instagram' ),
	array( 'id' => 'pckz-text', 'role' => 'text', 'ref' => 'text', 'text' => 'hello' ),
);

foreach ( $map as $spec ) {
	$bbox  = PCKZ_Ledos_Preview::ref_to_mm_box( $refs[ $spec['ref'] ], $mm_w, $mm_h, 'bottom-left' );
	$entry = array(
		'id'           => $spec['id'],
		'role'         => $spec['role'],
		'bbox'         => $bbox,
		'x_mm'         => $bbox['x_mm'],
		'y_mm'         => $bbox['y_mm'],
		'width_mm'     => $bbox['width_mm'],
		'height_mm'    => $bbox['height_mm'],
		'scale'        => array( 'x' => 1, 'y' => 1 ),
		'rotation_deg' => 0,
		'z_order'      => 10,
		'color'        => '#fff',
	);
	if ( ! empty( $spec['line_type'] ) ) {
		$entry['line_type'] = $spec['line_type'];
	}
	if ( ! empty( $spec['symbol'] ) ) {
		$entry['symbol'] = $spec['symbol'];
	}
	if ( ! empty( $spec['text'] ) ) {
		$entry['text']         = $spec['text'];
		$entry['font_family']  = 'Russo One';
	}
	$objects[] = $entry;
}

$text_bbox = $objects[5]['bbox'];
$text_cx   = $text_bbox['center_x_mm'];
$text_cy   = $text_bbox['center_y_mm'];
$text_cy_svg = $mm_h - $text_cy;

$icon_left = $objects[3]['bbox'];
$icon_right = $objects[4]['bbox'];

// Simulated browser WYSIWYG export: knocked line segments + real icon path groups.
$browser_svg = sprintf(
	'<?xml version="1.0" encoding="UTF-8"?>' .
	'<svg xmlns="http://www.w3.org/2000/svg" width="525mm" height="145mm" viewBox="0 0 525 145">' .
	'<g id="pckz-engrave">' .
	'<g id="pckz-lines">' .
	'<g id="pckz-line-0" fill="#FF0000"><path d="M 88 72.5 L 200 72.5"/></g>' .
	'<g id="pckz-line-1" fill="#FF0000"><path d="M 325 72.5 L 436 72.5"/></g>' .
	'</g>' .
	'<g id="pckz-icon-bg-left" fill="#FFFFFF"><circle cx="%1$s" cy="%2$s" r="8"/></g>' .
	'<g id="pckz-icon-bg-right" fill="#FFFFFF"><circle cx="%3$s" cy="%4$s" r="8"/></g>' .
	'<g id="pckz-icon-left" fill="#FFFFFF"><path d="M %5$s %6$s l 4 0 l 0 4 l -4 0 z"/></g>' .
	'<g id="pckz-icon-right" fill="#FFFFFF"><path d="M %7$s %8$s l 4 0 l 0 4 l -4 0 z"/></g>' .
	'</g></svg>',
	$icon_left['center_x_mm'],
	$mm_h - $icon_left['center_y_mm'],
	$icon_right['center_x_mm'],
	$mm_h - $icon_right['center_y_mm'],
	$icon_left['center_x_mm'] - 2,
	$mm_h - $icon_left['center_y_mm'] - 2,
	$icon_right['center_x_mm'] - 2,
	$mm_h - $icon_right['center_y_mm'] - 2
);

$text_plate_paths = sprintf(
	'<g id="pckz-text-engrave" fill="#FFFFFF"><path d="M %1$s %2$s L %3$s %2$s L %3$s %4$s L %1$s %4$s Z"/></g>',
	$text_bbox['x_mm'] + 5,
	$mm_h - ( $text_bbox['y_mm'] + $text_bbox['height_mm'] ) + 5,
	$text_bbox['x_mm'] + $text_bbox['width_mm'] - 5,
	$mm_h - $text_bbox['y_mm'] - 5
);

$canonical = array(
	'format'            => 'pckzce-canonical-scene',
	'version'           => 2,
	'coordinate_system' => 'lightburn-mm-bottom-left',
	'plate'             => array( 'width_mm' => $mm_w, 'height_mm' => $mm_h ),
	'selections'        => array(
		'linien'         => 'type_17',
		'symbol_links'   => 'instagram',
		'symbol_rechts'  => 'instagram',
		'custom_text'    => 'hello',
	),
	'objects'           => $objects,
);

$package = PCKZ_Export_Engine::run(
	array(
		'canonical_scene'       => wp_json_encode( $canonical ),
		'production_vector_svg' => $browser_svg,
		'text_plate_paths'      => $text_plate_paths,
		'config'                => array(),
		'canvas_json'           => '{}',
		'design_id'             => 42,
		'selections'            => $canonical['selections'],
	)
);

if ( is_wp_error( $package ) ) {
	fwrite( STDERR, wp_json_encode( array( 'message' => $package->get_error_message(), 'data' => $package->get_error_data() ), JSON_PRETTY_PRINT ) . "\n" );
	exit( 1 );
}

$scene = $package['production_scene'] ?? array();
if ( ( $scene['export_source'] ?? '' ) !== 'browser-wysiwyg' ) {
	fwrite( STDERR, "FAIL: expected browser-wysiwyg export source\n" );
	exit( 1 );
}

$line_layers = 0;
$icon_layers = 0;
$text_path_layers = 0;
foreach ( $scene['layers'] ?? array() as $layer ) {
	$lid = (string) ( $layer['layer_id'] ?? '' );
	if ( 0 === strpos( $lid, 'pckz-line-' ) ) {
		++$line_layers;
	}
	if ( in_array( $lid, array( 'pckz-icon-left', 'pckz-icon-right', 'pckz-icon-bg-left', 'pckz-icon-bg-right' ), true ) ) {
		++$icon_layers;
	}
	if ( 'pckz-text-engrave' === $lid && 'path' === ( $layer['type'] ?? '' ) ) {
		++$text_path_layers;
	}
}

if ( $line_layers < 2 ) {
	fwrite( STDERR, "FAIL: expected knocked line path layers, got {$line_layers}\n" );
	exit( 1 );
}
if ( $icon_layers < 4 ) {
	fwrite( STDERR, "FAIL: expected icon vector layers, got {$icon_layers}\n" );
	exit( 1 );
}
if ( $text_path_layers < 1 ) {
	fwrite( STDERR, "FAIL: expected vector text engrave paths, got {$text_path_layers}\n" );
	exit( 1 );
}

$lbrn2 = PCKZ_Production_Lbrn2::build_from_package( $package );
if ( is_wp_error( $lbrn2 ) ) {
	fwrite( STDERR, 'FAIL LBRN2: ' . $lbrn2->get_error_message() . "\n" );
	exit( 1 );
}
if ( false === strpos( $lbrn2, 'pckz-line-0' ) && false === strpos( $lbrn2, 'pckz-text-engrave' ) ) {
	fwrite( STDERR, "FAIL: LBRN2 missing expected layer labels\n" );
	exit( 1 );
}
if ( false !== strpos( $lbrn2, 'Type="Text"' ) ) {
	fwrite( STDERR, "FAIL: LBRN2 contains font text objects\n" );
	exit( 1 );
}
if ( false === strpos( $lbrn2, 'pckz-text-engrave' ) ) {
	fwrite( STDERR, "FAIL: LBRN2 missing vector text engrave layer\n" );
	exit( 1 );
}

echo "OK visual export: lines={$line_layers} icons={$icon_layers} text_paths={$text_path_layers}\n";
exit( 0 );
