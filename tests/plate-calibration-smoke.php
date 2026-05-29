<?php
/**
 * Engineering plate calibration smoke test.
 *
 * @package PCKZCanonicalEngine
 */

require_once __DIR__ . '/smoke-bootstrap.php';

$fail = 0;

$defaults = PCKZ_Plate_Calibration::default_product_config();
if ( abs( $defaults['canvas_width_mm'] - 529.1 ) > 0.01 ) {
	fwrite( STDERR, "FAIL: canvas_width_mm expected 529.1\n" );
	++$fail;
}
if ( abs( $defaults['canvas_height_mm'] - 116 ) > 0.01 ) {
	fwrite( STDERR, "FAIL: canvas_height_mm expected 116\n" );
	++$fail;
}

$offset = PCKZ_Plate_Calibration::holder_offset_x_mm();
if ( abs( $offset - 3.05 ) > 0.02 ) {
	fwrite( STDERR, "FAIL: holder offset expected ~3.05 got {$offset}\n" );
	++$fail;
}

$refs       = PCKZ_Ledos_Preview::layer_refs();
$lines_box  = PCKZ_Ledos_Preview::ref_to_mm_box( $refs['lines'], 529.1, 116, 'bottom-left' );
$icon_box   = PCKZ_Ledos_Preview::ref_to_mm_box( $refs['iconLeft'], 529.1, 116, 'bottom-left' );
$text_box   = PCKZ_Ledos_Preview::ref_to_mm_box( $refs['text'], 529.1, 116, 'bottom-left' );

$expect_lines_w = ( 2424 / PCKZ_Ledos_Preview::DESIGN_WIDTH ) * PCKZ_Plate_Calibration::HOLDER_WIDTH_MM;
if ( abs( $lines_box['width_mm'] - $expect_lines_w ) > 0.5 ) {
	fwrite( STDERR, "FAIL: lines width expected ~{$expect_lines_w}, got {$lines_box['width_mm']}\n" );
	++$fail;
}

if ( $lines_box['x_mm'] < $offset - 0.05 ) {
	fwrite( STDERR, "FAIL: lines x {$lines_box['x_mm']} before holder offset {$offset}\n" );
	++$fail;
}

if ( $icon_box['x_mm'] < $offset - 0.05 ) {
	fwrite( STDERR, "FAIL: icon x {$icon_box['x_mm']} before holder offset\n" );
	++$fail;
}

if ( $text_box['x_mm'] <= $icon_box['x_mm'] ) {
	fwrite( STDERR, "FAIL: text should be right of left icon in plate mm\n" );
	++$fail;
}

if ( $fail ) {
	exit( 1 );
}

echo "OK: plate calibration (529.1×116 plate, 523mm holder, offset {$offset}mm)\n";
exit( 0 );
