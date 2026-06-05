#!/usr/bin/env php
<?php
/**
 * Layout-mm uniform export: positions must match Cloudlift design refs → plate mm.
 */
define( 'ABSPATH', __DIR__ );
if ( ! function_exists( '__' ) ) {
	function __( $s, $d = '' ) {
		return $s;
	}
}
if ( ! function_exists( 'sanitize_hex_color' ) ) {
	function sanitize_hex_color( $c ) {
		return $c;
	}
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $s ) {
		return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_xml' ) ) {
	function esc_xml( $s ) {
		return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
	}
}

require_once dirname( __DIR__ ) . '/includes/class-pckz-production-geometry.php';
require_once dirname( __DIR__ ) . '/includes/class-pckz-production-scene.php';

$dw = 3651;
$dh = 2132;
$mm_w = 529.1;
$mm_h = 116;

/**
 * Design-space center → bottom-left mm box (matches preview-engine objectToDesignPx + designPxToMm).
 *
 * @param float $cx Design center X.
 * @param float $cy Design center Y.
 * @param float $w  Design width.
 * @param float $h  Design height.
 * @return array
 */
function ref_center_to_mm_box( $cx, $cy, $w, $h ) {
	global $dw, $dh, $mm_w, $mm_h;
	$x_mm     = ( ( $cx - $w / 2 ) / $dw ) * $mm_w;
	$w_mm     = ( $w / $dw ) * $mm_w;
	$h_mm     = ( $h / $dh ) * $mm_h;
	$y_top_mm = ( ( $cy - $h / 2 ) / $dh ) * $mm_h;
	$y_mm     = $mm_h - $y_top_mm - $h_mm;
	return array(
		'x_mm'        => round( $x_mm, 3 ),
		'y_mm'        => round( $y_mm, 3 ),
		'width_mm'    => round( $w_mm, 3 ),
		'height_mm'   => round( $h_mm, 3 ),
		'center_x_mm' => round( ( $cx / $dw ) * $mm_w, 3 ),
		'center_y_mm' => round( $mm_h - ( $cy / $dh ) * $mm_h, 3 ),
	);
}

$icon_box = ref_center_to_mm_box( 817.5 + 40.5, 1243 + 57, 81, 114 );
$text_box = ref_center_to_mm_box( 1136 + 696, 1256 + 46.5, 1392, 93 );

$line_svg = '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 2424 254">'
	. '<ellipse cx="100" cy="50" rx="80" ry="20" fill="#f00"/></svg>';
$icon_svg = '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 81 114">'
	. '<rect x="10" y="10" width="61" height="94" fill="#fff"/></svg>';

$ctx = array(
	'canvas_w'   => $mm_w,
	'canvas_h'   => $mm_h,
	'origin'     => 'bottom-left',
	'design_px'  => array( 'width' => $dw, 'height' => $dh ),
	'selections' => array(
		'custom_text'   => 'AB 123',
		'symbol_links'  => 'instagram',
		'linien'        => 'type_5',
	),
	'objects'    => array(
		array(
			'role'       => 'lines',
			'fill'       => 'Red',
			'svg_source' => $line_svg,
			'mm'         => ref_center_to_mm_box( 609 + 1212, 1173 + 127, 2424, 254 ),
		),
		array(
			'role'       => 'icon-left',
			'fill'       => 'White',
			'svg_source' => $icon_svg,
			'mm'         => $icon_box,
		),
		array(
			'role'       => 'text',
			'text'       => 'AB 123',
			'font_size_px' => 55,
			'fill'       => 'White',
			'mm'         => $text_box,
		),
	),
);

$master = PCKZ_Production_Scene::synthesize_master_svg_from_layout( $ctx );
if ( '' === trim( $master ) ) {
	fwrite( STDERR, "FAIL: empty synthesized master SVG\n" );
	exit( 1 );
}

if ( false !== strpos( $master, 'matrix(' ) && false === strpos( $master, 'layout-mm' ) ) {
	// Legacy canvas-pixel matrix export should not be the server authority.
}

$scene = PCKZ_Production_Scene::parse_master_svg( $master, $mm_w, $mm_h, array() );
$layers = $scene['layers'] ?? array();
if ( empty( $layers ) ) {
	fwrite( STDERR, "FAIL: no layers in parsed scene\n" );
	exit( 1 );
}

$icon_cx = null;
foreach ( $layers as $layer ) {
	if ( 'icon-left' === ( $layer['role'] ?? '' ) || 'icon' === ( $layer['role'] ?? '' ) ) {
		if ( 'ellipse' === ( $layer['type'] ?? '' ) ) {
			$icon_cx = $layer['cx'] ?? null;
		} elseif ( ! empty( $layer['verts'] ) ) {
			$xs = array_column( $layer['verts'], 'x' );
			$icon_cx = ( min( $xs ) + max( $xs ) ) / 2;
		}
	}
}

$expect_cx = $icon_box['center_x_mm'];
if ( null === $icon_cx || abs( $icon_cx - $expect_cx ) > 2.0 ) {
	fwrite( STDERR, "FAIL: icon-left center x expected ~{$expect_cx}, got " . ( $icon_cx ?? 'null' ) . "\n" );
	exit( 1 );
}

echo "OK: layout-mm uniform export icon center x={$icon_cx} mm (expected ~{$expect_cx})\n";
exit( 0 );
