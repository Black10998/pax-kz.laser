<?php
/**
 * Smoke: protected release validation reports detailed version mismatches.
 *
 * @package PCKZCanonicalEngine
 */

require_once dirname( __DIR__ ) . '/tests/smoke-bootstrap.php';
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
		unset( $tag, $callback, $priority, $accepted_args );
		return true;
	}
}
require_once dirname( __DIR__ ) . '/includes/class-pckz-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-pckz-licensing.php';

if ( ! class_exists( 'ZipArchive' ) ) {
	echo "protected-release-validation-smoke: SKIP (ZipArchive missing)\n";
	exit( 0 );
}

$lic_ref = new ReflectionClass( 'PCKZ_Licensing' );

$build = $lic_ref->getMethod( 'build_protected_release_zip' );
$build->setAccessible( true );
$validate = $lic_ref->getMethod( 'validate_protected_release_archive' );
$validate->setAccessible( true );
$mismatch = $lic_ref->getMethod( 'protected_release_version_mismatch_lines' );
$mismatch->setAccessible( true );

$tmp_dir = sys_get_temp_dir() . '/pckz-protected-validation-' . wp_generate_uuid4();
@mkdir( $tmp_dir, 0775, true );
$good_zip = $tmp_dir . '/pckz-canonical-engine-2.99.1-protected.zip';
$built    = $build->invoke( null, $good_zip, '2.99.1', '2.99.1.validation-smoke' );
if ( is_wp_error( $built ) ) {
	fwrite( STDERR, 'FAIL: could not build validation ZIP: ' . $built->get_error_message() . "\n" );
	exit( 1 );
}
$validated = $validate->invoke( null, $good_zip, '2.99.1', true, false, basename( $good_zip ) );
if ( is_wp_error( $validated ) ) {
	fwrite( STDERR, 'FAIL: generated ZIP failed validation: ' . $validated->get_error_message() . "\n" );
	exit( 1 );
}

$bad_zip = $tmp_dir . '/pckz-canonical-engine-2.99.2-protected.zip';
copy( $good_zip, $bad_zip );
$bad = $validate->invoke( null, $bad_zip, '2.99.2', true, false, basename( $bad_zip ) );
if ( ! is_wp_error( $bad ) ) {
	fwrite( STDERR, "FAIL: filename/version mismatch should fail validation\n" );
	exit( 1 );
}
$message = $bad->get_error_message();
foreach ( array( 'Expected release version: 2.99.2', 'Plugin header Version: 2.99.1', 'PCKZCE_VERSION: 2.99.1', 'Filename version: 2.99.2' ) as $needle ) {
	if ( false === strpos( $message, $needle ) ) {
		fwrite( STDERR, "FAIL: mismatch message missing detail: {$needle}\n" );
		exit( 1 );
	}
}

$layout_zip = $tmp_dir . '/pckz-canonical-engine-2.99.3-protected.zip';
$zip        = new ZipArchive();
if ( true !== $zip->open( $layout_zip, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
	fwrite( STDERR, "FAIL: could not create layout test zip\n" );
	exit( 1 );
}
$zip->addFromString(
	'release-packages/nested/pckz-canonical-engine.php',
	"<?php\n/*\nPlugin Name: Bad Layout\nVersion: 2.99.3\n*/\n"
);
$zip->close();
$layout = $validate->invoke( null, $layout_zip, '2.99.3', false, false, basename( $layout_zip ) );
if ( ! is_wp_error( $layout ) ) {
	fwrite( STDERR, "FAIL: invalid layout ZIP should fail validation\n" );
	exit( 1 );
}
if ( false === strpos( $layout->get_error_message(), 'pckz-canonical-engine/' ) ) {
	fwrite( STDERR, "FAIL: layout error should mention expected plugin folder\n" );
	exit( 1 );
}

@unlink( $good_zip );
@unlink( $bad_zip );
@unlink( $layout_zip );
@rmdir( $tmp_dir );

echo "protected-release-validation-smoke: OK\n";
exit( 0 );
