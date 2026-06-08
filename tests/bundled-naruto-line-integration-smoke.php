<?php
/**
 * Smoke: Naruto eye models register via manifest without heavy SVG parsing.
 *
 * @package PCKZCanonicalEngine
 */

$root = dirname( __DIR__ );
require_once $root . '/tests/smoke-bootstrap.php';

require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-svg-library.php';
require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-line-library.php';

$manifest = PCKZ_Line_Library::bundled_manifest();
if ( 10 !== count( $manifest ) ) {
	fwrite( STDERR, 'FAIL expected 10 manifest entries, got ' . count( $manifest ) . "\n" );
	exit( 1 );
}

$path102 = PCKZ_Ledos_Preview::line_assets_dir() . 'type_102.svg';
if ( ! is_readable( $path102 ) ) {
	fwrite( STDERR, "SKIP type_102.svg missing\n" );
	exit( 0 );
}

update_option( PCKZ_Line_Library::OPTION_DELETED, array( 'type_102', 'type_103', 'type_104', 'type_105', 'type_106', 'type_107', 'type_108', 'type_109', 'type_110', 'type_111' ) );
PCKZ_Line_Library::ensure_bundled_naruto_lines_visible();

$choices = PCKZ_Line_Library::get_customer_line_choices();
$found   = 0;
foreach ( range( 102, 111 ) as $i ) {
	$slug = 'type_' . $i;
	$hit  = false;
	foreach ( $choices as $choice ) {
		if ( ( $choice['value'] ?? '' ) !== $slug ) {
			continue;
		}
		$hit = true;
		if ( empty( $choice['preserve_colors'] ) ) {
			fwrite( STDERR, "FAIL {$slug} missing preserve_colors in customer choices\n" );
			exit( 1 );
		}
		if (
			false === strpos( (string) ( $choice['img'] ?? '' ), 'display/' . $slug . '.svg' )
			&& false === strpos( (string) ( $choice['img'] ?? '' ), 'pckzce_line_preview' )
		) {
			fwrite( STDERR, "FAIL {$slug} picker should use display-normalized preview URL\n" );
			exit( 1 );
		}
		break;
	}
	if ( ! $hit ) {
		fwrite( STDERR, "FAIL {$slug} missing from customer choices\n" );
		exit( 1 );
	}
	++$found;
}

$svg = (string) file_get_contents( $path102 );
$start = microtime( true );
$out   = PCKZ_Line_Library::normalize_line_svg_for_display( 'type_102', $svg, false );
$elapsed = microtime( true ) - $start;
if ( false === strpos( $out, 'scale(' ) ) {
	fwrite( STDERR, "FAIL type_102 display normalization should scale compact art for preview\n" );
	exit( 1 );
}
if ( $elapsed > 0.25 ) {
	fwrite( STDERR, "FAIL type_102 display normalization too slow ({$elapsed}s)\n" );
	exit( 1 );
}

$catalog = PCKZ_Ledos_Preview::line_catalog_for_js();
if ( empty( $catalog['type_102']['preserve_colors'] ) ) {
	fwrite( STDERR, "FAIL type_102 missing preserve_colors in JS catalog\n" );
	exit( 1 );
}

echo "OK bundled-naruto-line-integration-smoke: {$found} models visible with fast display path\n";
