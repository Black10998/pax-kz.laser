<?php
/**
 * Smoke: picker-only line preview scales compact bundled art to full width (no source file edits).
 *
 * @package PCKZCanonicalEngine
 */

$root = dirname( __DIR__ );
require_once $root . '/tests/smoke-bootstrap.php';

require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-svg-library.php';
require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-production-geometry.php';
require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-line-library.php';

$type41_path = PCKZ_Ledos_Preview::line_assets_dir() . 'type_41.svg';
if ( ! is_readable( $type41_path ) ) {
	fwrite( STDERR, "SKIP type_41.svg missing\n" );
	exit( 0 );
}
$raw = file_get_contents( $type41_path );
$draw   = PCKZ_Svg_Library::infer_line_draw_bounds( $raw );
$picker = PCKZ_Svg_Library::normalize_line_svg_for_picker_preview( $raw, false );
if ( ! $draw || ! preg_match( '/scale\(([\d.]+)\)/', $picker, $sm ) ) {
	fwrite( STDERR, "FAIL type_41 picker preview missing scale transform\n" );
	exit( 1 );
}
$scale      = (float) $sm[1];
$scaled_w   = (float) $draw['width'] * $scale;
$coverage   = $scaled_w / 950;
$raw_cov    = (float) $draw['width'] / 950;
if ( $coverage < 0.88 || $coverage <= $raw_cov + 0.02 ) {
	fwrite( STDERR, "FAIL type_41 picker should scale up width (coverage={$coverage}, raw={$raw_cov})\n" );
	exit( 1 );
}

$url = PCKZ_Line_Library::picker_preview_url( 'type_41' );
if ( false === strpos( $url, 'display/type_41.svg' ) && false === strpos( $url, 'pckz_line_picker=type_41' ) ) {
	fwrite( STDERR, "FAIL picker_preview_url must use display asset or picker endpoint\n" );
	exit( 1 );
}

$choices = PCKZ_Line_Library::get_customer_line_choices();
$found   = false;
foreach ( $choices as $choice ) {
	if ( 'type_41' === ( $choice['value'] ?? '' ) ) {
		$found = true;
		$img = (string) ( $choice['img'] ?? '' );
		if ( false === strpos( $img, 'display/type_41.svg' ) && false === strpos( $img, 'pckz_line_picker' ) ) {
			fwrite( STDERR, "FAIL customer choice should use display or picker preview URL\n" );
			exit( 1 );
		}
		break;
	}
}
if ( ! $found ) {
	fwrite( STDERR, "FAIL type_41 missing from customer choices\n" );
	exit( 1 );
}

$export_url = PCKZ_Ledos_Preview::line_types()['type_41'] ?? '';
if ( false === strpos( $export_url, 'public/assets/lines/type_41.svg' ) ) {
	fwrite( STDERR, "FAIL line_types must still reference original bundled asset\n" );
	exit( 1 );
}

echo "OK line-picker-preview-smoke\n";
