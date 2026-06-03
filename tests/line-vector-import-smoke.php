<?php
/**
 * Smoke: vector line import converter and store_custom_line_svg registration.
 */
$root = dirname( __DIR__ );
require_once $root . '/tests/smoke-bootstrap.php';
if ( ! defined( 'PCKZCE_PLUGIN_DIR' ) ) {
	define( 'PCKZCE_PLUGIN_DIR', $root . '/' );
}

require_once $root . '/includes/class-pckz-svg-library.php';
require_once $root . '/includes/class-pckz-ledos-preview.php';
require_once $root . '/includes/class-pckz-line-importer.php';
require_once $root . '/includes/class-pckz-line-library.php';

if ( ! PCKZ_Line_Importer::converter_available() ) {
	fwrite( STDERR, "SKIP line-vector-import-smoke: python converter not available\n" );
	exit( 0 );
}

$ai = $root . '/import/line-models/model 21.ai';
if ( ! is_readable( $ai ) ) {
	fwrite( STDERR, "SKIP line-vector-import-smoke: model 21.ai missing\n" );
	exit( 0 );
}

$tmp = sys_get_temp_dir() . '/pckz-line-import-smoke-' . uniqid( 't', true ) . '.svg';
$python = PCKZ_Line_Importer::python_binary();
$script = PCKZ_Line_Importer::converter_script_path();
$cmd    = escapeshellarg( $python ) . ' ' . escapeshellarg( $script )
	. ' ' . escapeshellarg( $ai ) . ' ' . escapeshellarg( $tmp ) . ' --fill-color white 2>&1';
exec( $cmd, $out, $code );
if ( 0 !== $code || ! is_readable( $tmp ) ) {
	fwrite( STDERR, "FAIL converter exit {$code}: " . implode( "\n", $out ) . "\n" );
	exit( 1 );
}

$svg = file_get_contents( $tmp );
@unlink( $tmp );

if ( ! preg_match( '/viewBox="0 0 950 35"/', $svg ) ) {
	fwrite( STDERR, "FAIL converted SVG missing 950×35 viewBox\n" );
	exit( 1 );
}

if ( ! PCKZ_Line_Library::is_safe_svg( $svg ) ) {
	fwrite( STDERR, "FAIL converted SVG failed safety check\n" );
	exit( 1 );
}

$slug = 'type_99991';
$result = PCKZ_Line_Library::store_custom_line_svg( $svg, $slug, 'Smoke Import', 'import_ai', array( 'source_file' => 'model 21.ai' ) );
if ( is_wp_error( $result ) ) {
	fwrite( STDERR, 'FAIL store_custom_line_svg: ' . $result->get_error_message() . "\n" );
	exit( 1 );
}

$manifest = PCKZ_Line_Library::custom_manifest();
if ( empty( $manifest[ $slug ] ) || 'import_ai' !== ( $manifest[ $slug ]['source'] ?? '' ) ) {
	fwrite( STDERR, "FAIL manifest missing import_ai source\n" );
	exit( 1 );
}

$choices = PCKZ_Line_Library::get_customer_line_choices();
$found   = false;
foreach ( $choices as $choice ) {
	if ( ( $choice['value'] ?? '' ) === $slug ) {
		$found = true;
		break;
	}
}
if ( ! $found ) {
	fwrite( STDERR, "FAIL imported line missing from customer choices\n" );
	exit( 1 );
}

PCKZ_Line_Library::delete_custom( $slug );

echo "OK line-vector-import-smoke\n";
