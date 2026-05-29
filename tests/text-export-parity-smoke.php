#!/usr/bin/env php
<?php
/**
 * Text vector parity: measured path bbox must match canonical Fabric bbox (bottom-left mm).
 */
define( 'ABSPATH', __DIR__ );

if ( ! function_exists( 'sanitize_hex_color' ) ) {
	function sanitize_hex_color( $color ) {
		return $color;
	}
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data ) {
		return json_encode( $data );
	}
}
if ( ! function_exists( '__' ) ) {
	function __( $s, $d = '' ) {
		return $s;
	}
}
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public function __construct( $code = '', $message = '', $data = array() ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}
		public function get_error_message() {
			return $this->message;
		}
		public function get_error_code() {
			return $this->code;
		}
		public function get_error_data() {
			return $this->data;
		}
	}
}

require_once dirname( __DIR__ ) . '/includes/class-pckz-production-geometry.php';
require_once dirname( __DIR__ ) . '/includes/class-pckz-production-scene.php';
require_once dirname( __DIR__ ) . '/includes/class-pckz-export-parity.php';

$bbox = array(
	'x_mm'        => 113.6,
	'y_mm'        => 50,
	'width_mm'    => 200,
	'height_mm'   => 30,
	'center_x_mm' => 213.6,
	'center_y_mm' => 65,
);

$path_d = sprintf(
	'M %s %s L %s %s L %s %s L %s %s Z',
	$bbox['x_mm'],
	$bbox['y_mm'],
	$bbox['x_mm'] + $bbox['width_mm'],
	$bbox['y_mm'],
	$bbox['x_mm'] + $bbox['width_mm'],
	$bbox['y_mm'] + $bbox['height_mm'],
	$bbox['x_mm'],
	$bbox['y_mm'] + $bbox['height_mm']
);

$svg = '<?xml version="1.0"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 525 145">
<metadata id="pckz-export-meta"><pckz:export format="fabric-staticCanvas-toSVG" coordinate-system="lightburn-mm-bottom-left"/></metadata>
<g id="pckz-engrave"><g id="pckz-text-engrave"><path d="' . $path_d . '" fill="#fff"/></g></g>
</svg>';

$package = array(
	'layout'                => array(
		'canvas_mm' => array( 'width' => 525, 'height' => 145 ),
		'objects'   => array(
			array(
				'role' => 'text',
				'text' => 'AB',
				'mm'   => $bbox,
			),
		),
	),
	'production_vector_svg' => $svg,
);

$scene = PCKZ_Production_Scene::from_canonical_layout( $package );
if ( is_wp_error( $scene ) ) {
	fwrite( STDERR, 'FAIL scene: ' . $scene->get_error_message() . "\n" );
	exit( 1 );
}

$parity = PCKZ_Export_Parity::validate(
	array(
		'objects' => array(
			array(
				'id'   => 'pckz-text',
				'role' => 'text',
				'bbox' => $bbox,
			),
		),
	),
	$scene
);

if ( 'PASS' !== ( $parity['status'] ?? '' ) ) {
	fwrite( STDERR, "FAIL: text parity expected PASS\n" );
	fwrite( STDERR, wp_json_encode( $parity['errors'] ?? array() ) . "\n" );
	exit( 1 );
}

$msg = PCKZ_Export_Parity::format_errors_for_response(
	array(
		'errors' => array(
			array(
				'role'      => 'text',
				'object_id' => 'pckz-text',
				'message'   => 'test',
				'expected'  => $bbox,
				'actual'    => array(
					'x_mm'      => 118.6,
					'y_mm'      => 55,
					'width_mm'  => 190,
					'height_mm' => 20,
				),
				'delta'     => array(
					'x_mm'      => 5,
					'y_mm'      => 5,
					'width_mm'  => -10,
					'height_mm' => -10,
				),
			),
		),
	)
);
if ( false === strpos( $msg, 'pckz-text' ) || false === strpos( $msg, 'Δx=' ) ) {
	fwrite( STDERR, "FAIL: format_errors_for_response missing detail\n" );
	exit( 1 );
}

echo "OK: text export parity PASS + error formatting\n";
exit( 0 );
