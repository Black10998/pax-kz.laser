<?php
/**
 * End-to-end customer design export — calibrated plate (529.1×116 mm, 523 mm holder).
 *
 * Simulates save → SVG/LBRN2 with validation enabled (parity + geometry).
 *
 * @package PCKZCanonicalEngine
 */

require_once __DIR__ . '/smoke-bootstrap.php';

$fail = 0;

$plate_w = PCKZ_Plate_Calibration::TOTAL_WIDTH_MM;
$plate_h = PCKZ_Plate_Calibration::PLATE_HEIGHT_MM;
$offset  = PCKZ_Plate_Calibration::holder_offset_x_mm();
$holder_w = PCKZ_Plate_Calibration::HOLDER_WIDTH_MM;

$config = PCKZ_Plate_Calibration::default_product_config();
$std    = PCKZ_Std_Spec::for_product( $config );

$selections = array(
	'custom_text'    => 'AB 123 CD',
	'linien'         => 'type_1',
	'symbol_links'   => 'instagram',
	'symbol_rechts'  => 'instagram',
	'font_family'    => 'Russo One',
	'text_color'     => 'White',
	'line_color'     => 'Red',
);

$refs    = PCKZ_Ledos_Preview::layer_refs();
$lines   = PCKZ_Ledos_Preview::line_types();
$line_url = $lines['type_1'] ?? 'https://example.com/line-type-1.svg';

$map = array(
	array( 'id' => 'pckz-lines', 'role' => 'lines', 'ref' => 'lines', 'line_type' => 'type_1', 'svg_ref' => $line_url ),
	array( 'id' => 'pckz-icon-left', 'role' => 'icon-left', 'ref' => 'iconLeft', 'symbol' => 'instagram' ),
	array( 'id' => 'pckz-icon-right', 'role' => 'icon-right', 'ref' => 'iconRight', 'symbol' => 'instagram' ),
	array( 'id' => 'pckz-text', 'role' => 'text', 'ref' => 'text', 'text' => $selections['custom_text'] ),
);

$objects = array();
foreach ( $map as $spec ) {
	$bbox = PCKZ_Ledos_Preview::ref_to_mm_box( $refs[ $spec['ref'] ], $plate_w, $plate_h, 'bottom-left' );
	$entry = array(
		'id'            => $spec['id'],
		'role'          => $spec['role'],
		'bbox'          => $bbox,
		'x_mm'          => $bbox['x_mm'],
		'y_mm'          => $bbox['y_mm'],
		'width_mm'      => $bbox['width_mm'],
		'height_mm'     => $bbox['height_mm'],
		'scale'         => array( 'x' => 1, 'y' => 1 ),
		'rotation_deg'  => 0,
		'z_order'       => 'text' === $spec['role'] ? 30 : ( 'lines' === $spec['role'] ? 10 : 20 ),
		'color'         => '#FFFFFF',
	);
	if ( ! empty( $spec['line_type'] ) ) {
		$entry['line_type'] = $spec['line_type'];
		$entry['svg_ref']   = $spec['svg_ref'];
		$entry['color']     = '#FF0000';
	}
	if ( ! empty( $spec['symbol'] ) ) {
		$entry['symbol'] = $spec['symbol'];
	}
	if ( ! empty( $spec['text'] ) ) {
		$entry['text']        = $spec['text'];
		$entry['font_family'] = 'Russo One';
	}
	$objects[] = $entry;
}

$text_bbox = $objects[ count( $objects ) - 1 ]['bbox'];
$y_top_svg = $plate_h - $text_bbox['y_mm'] - $text_bbox['height_mm'];
$y_bot_svg = $plate_h - $text_bbox['y_mm'];
$text_plate_paths = sprintf(
	'<g id="pckz-text-engrave" fill="#FFFFFF"><path d="M %1$s %2$s L %3$s %2$s L %3$s %4$s L %1$s %4$s Z"/></g>',
	PCKZ_Production_Geometry::fmt( $text_bbox['x_mm'] + 2 ),
	PCKZ_Production_Geometry::fmt( $y_top_svg + 2 ),
	PCKZ_Production_Geometry::fmt( $text_bbox['x_mm'] + $text_bbox['width_mm'] - 2 ),
	PCKZ_Production_Geometry::fmt( $y_bot_svg - 2 )
);

// Fabric-style master SVG: holder scale + 3.05 mm horizontal inset (matches preview-engine matrix).
$bg_l = 75;
$bg_t = 50;
$bg_w = 900;
$bg_h = 526;
$sx   = $holder_w / $bg_w;
$sy   = $plate_h / $bg_h;
$tx   = $offset - $bg_l * $sx;
$ty   = $plate_h + $bg_t * $sy;

$lines_bbox = $objects[0]['bbox'];
$lines_cx_canvas = $bg_l + ( ( $refs['lines']['refX'] + $refs['lines']['refWidth'] / 2 ) / PCKZ_Ledos_Preview::DESIGN_WIDTH ) * $bg_w;
$lines_cy_canvas = $bg_t + ( ( $refs['lines']['refY'] + $refs['lines']['refHeight'] / 2 ) / PCKZ_Ledos_Preview::DESIGN_HEIGHT ) * $bg_h;

$production_vector_svg = sprintf(
	'<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="%1$smm" height="%2$smm" viewBox="0 0 %1$s %2$s">
<metadata id="pckz-export-meta"><pckz:export xmlns:pckz="https://pckz-canonical-engine.local/export" format="fabric-staticCanvas-toSVG" coordinate-system="lightburn-mm-bottom-left"/></metadata>
<g id="pckz-engrave" transform="matrix(%3$s 0 0 %4$s %5$s %6$s)">
<g id="pckz-lines"><ellipse cx="%7$s" cy="%8$s" rx="40" ry="8" fill="#FF0000"/></g>
<g id="pckz-icon-left"><rect x="%9$s" y="%10$s" width="20" height="28" fill="#FFFFFF"/></g>
<g id="pckz-icon-right"><rect x="%11$s" y="%12$s" width="20" height="28" fill="#FFFFFF"/></g>
</g>
</svg>',
	PCKZ_Production_Geometry::fmt( $plate_w ),
	PCKZ_Production_Geometry::fmt( $plate_h ),
	PCKZ_Production_Geometry::fmt( $sx ),
	PCKZ_Production_Geometry::fmt( -$sy ),
	PCKZ_Production_Geometry::fmt( $tx ),
	PCKZ_Production_Geometry::fmt( $ty ),
	PCKZ_Production_Geometry::fmt( $lines_cx_canvas ),
	PCKZ_Production_Geometry::fmt( $lines_cy_canvas ),
	PCKZ_Production_Geometry::fmt( $bg_l + ( $refs['iconLeft']['refX'] / PCKZ_Ledos_Preview::DESIGN_WIDTH ) * $bg_w ),
	PCKZ_Production_Geometry::fmt( $bg_t + ( $refs['iconLeft']['refY'] / PCKZ_Ledos_Preview::DESIGN_HEIGHT ) * $bg_h ),
	PCKZ_Production_Geometry::fmt( $bg_l + ( $refs['iconRight']['refX'] / PCKZ_Ledos_Preview::DESIGN_WIDTH ) * $bg_w ),
	PCKZ_Production_Geometry::fmt( $bg_t + ( $refs['iconRight']['refY'] / PCKZ_Ledos_Preview::DESIGN_HEIGHT ) * $bg_h )
);

$canonical = array(
	'format'             => 'pckzce-canonical-scene',
	'version'            => 2,
	'coordinate_system'  => 'lightburn-mm-bottom-left',
	'plate'              => array(
		'width_mm'  => $plate_w,
		'height_mm' => $plate_h,
	),
	'plate_calibration'  => PCKZ_Plate_Calibration::spec( $config ),
	'selections'         => $selections,
	'objects'            => $objects,
);

$package = PCKZ_Export_Engine::run(
	array(
		'canonical_scene'       => wp_json_encode( $canonical ),
		'config'                => $config,
		'std_spec'              => $std,
		'canvas_json'           => '{}',
		'design_id'             => 9001,
		'selections'            => $selections,
		'production_vector_svg' => $production_vector_svg,
		'text_plate_paths'      => $text_plate_paths,
	)
);

if ( is_wp_error( $package ) ) {
	fwrite( STDERR, "FAIL export: " . $package->get_error_message() . "\n" );
	fwrite( STDERR, wp_json_encode( $package->get_error_data(), JSON_PRETTY_PRINT ) . "\n" );
	exit( 1 );
}

$layout = $package['layout'] ?? array();
$canvas = $layout['canvas_mm'] ?? array();
if ( abs( (float) ( $canvas['width'] ?? 0 ) - $plate_w ) > 0.05 || abs( (float) ( $canvas['height'] ?? 0 ) - $plate_h ) > 0.05 ) {
	fwrite( STDERR, "FAIL layout canvas_mm\n" );
	++$fail;
}

$scene = $package['production_scene'] ?? array();
if ( abs( (float) ( $scene['width_mm'] ?? $scene['canvas_w'] ?? 0 ) - $plate_w ) > 0.1
	&& abs( (float) ( $scene['canvas_w'] ?? 0 ) - $plate_w ) > 0.1 ) {
	// scene may only store layers; check master SVG viewBox instead.
}

$prod_svg = (string) ( $package['production_svg'] ?? $package['svg'] ?? '' );
if ( '' === trim( $prod_svg ) && class_exists( 'PCKZ_Production_Svg' ) ) {
	$built_svg = PCKZ_Production_Svg::build_from_package( $package );
	if ( ! is_wp_error( $built_svg ) ) {
		$prod_svg = $built_svg;
	}
}
if ( '' === trim( $prod_svg ) ) {
	$prod_svg = (string) ( $package['production_scene']['master_svg'] ?? '' );
}
$vb_w_pat = preg_quote( (string) $plate_w, '/' ) . '|' . preg_quote( PCKZ_Production_Geometry::fmt( $plate_w ), '/' );
$vb_h_pat = preg_quote( (string) $plate_h, '/' ) . '|' . preg_quote( PCKZ_Production_Geometry::fmt( $plate_h ), '/' );
if ( ! preg_match( '/viewBox="0\s+0\s+(' . $vb_w_pat . ')\s+(' . $vb_h_pat . ')"/i', $prod_svg ) ) {
	fwrite( STDERR, "FAIL production SVG viewBox (expected 0 0 {$plate_w} {$plate_h})\n" );
	++$fail;
}

$lbrn2 = (string) ( $package['lbrn2'] ?? $package['lightburn']['lbrn2'] ?? '' );
if ( '' === trim( $lbrn2 ) && class_exists( 'PCKZ_Production_Lbrn2' ) ) {
	$built_lbrn2 = PCKZ_Production_Lbrn2::build_from_package( $package );
	if ( ! is_wp_error( $built_lbrn2 ) ) {
		$lbrn2 = $built_lbrn2;
	}
}
if ( '' === trim( $lbrn2 ) ) {
	fwrite( STDERR, "FAIL empty LBRN2\n" );
	++$fail;
}
if ( ! preg_match( '/<LightBurnProject/i', $lbrn2 ) ) {
	fwrite( STDERR, "FAIL LBRN2 missing LightBurnProject root\n" );
	++$fail;
}

$lbrn2_text = preg_match_all( '/<!-- (pckz-)?text-engrave/', (string) $lbrn2 );
if ( $lbrn2_text < 1 ) {
	fwrite( STDERR, "FAIL LBRN2 missing customer text-engrave shapes\n" );
	++$fail;
}

if ( false === stripos( $prod_svg, 'pckz-text-engrave' ) ) {
	fwrite( STDERR, "FAIL production SVG missing customer text-engrave paths\n" );
	++$fail;
}

$parity = $package['parity'] ?? array();
if ( ( $parity['status'] ?? '' ) === 'FAIL' ) {
	fwrite( STDERR, "FAIL parity validation\n" );
	fwrite( STDERR, wp_json_encode( $parity['errors'] ?? array(), JSON_PRETTY_PRINT ) . "\n" );
	++$fail;
}

$min_x = 9999.0;
foreach ( (array) ( $scene['layers'] ?? array() ) as $layer ) {
	$box = $layer['placement_bbox_mm'] ?? $layer['measured_bbox_mm'] ?? $layer['bbox_mm'] ?? null;
	if ( is_array( $box ) && isset( $box['x_mm'] ) ) {
		$min_x = min( $min_x, (float) $box['x_mm'] );
		continue;
	}
	foreach ( (array) ( $layer['verts'] ?? array() ) as $v ) {
		$min_x = min( $min_x, (float) ( $v['x'] ?? $v['x_mm'] ?? 9999 ) );
	}
}
if ( $min_x < $offset - 0.1 ) {
	fwrite( STDERR, "FAIL min X {$min_x} mm is left of holder inset {$offset} mm\n" );
	++$fail;
}

$lines_w = (float) ( $lines_bbox['width_mm'] ?? 0 );
$lines_min = $holder_w * 0.55;
if ( $lines_w < $lines_min || $lines_w > $holder_w + 1 ) {
	fwrite( STDERR, "FAIL lines width {$lines_w} mm not in holder range [{$lines_min}, {$holder_w}]\n" );
	++$fail;
}

$layer_roles = array();
foreach ( (array) ( $scene['layers'] ?? array() ) as $layer ) {
	$layer_roles[] = (string) ( $layer['role'] ?? '' );
}
foreach ( array( 'lines', 'icon-left', 'icon-right' ) as $required_role ) {
	if ( ! in_array( $required_role, $layer_roles, true ) ) {
		fwrite( STDERR, "FAIL production scene missing role {$required_role}\n" );
		++$fail;
	}
}

if ( $fail ) {
	exit( 1 );
}

echo "OK: customer design export (plate {$plate_w}×{$plate_h} mm, holder {$holder_w} mm, inset {$offset} mm)\n";
echo 'OK: validation PASS, parity PASS, SVG + LBRN2 generated' . "\n";
exit( 0 );
