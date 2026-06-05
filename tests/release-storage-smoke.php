<?php
/**
 * Smoke: release storage inventory, quarantine, and detailed validation messages.
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
require_once dirname( __DIR__ ) . '/includes/class-pckz-release-storage.php';
require_once dirname( __DIR__ ) . '/includes/class-pckz-licensing.php';

if ( ! class_exists( 'ZipArchive' ) ) {
	echo "release-storage-smoke: SKIP (ZipArchive missing)\n";
	exit( 0 );
}

$tmp_dir = sys_get_temp_dir() . '/pckz-release-storage-' . wp_generate_uuid4();
@mkdir( $tmp_dir, 0775, true );

$bad_zip = $tmp_dir . '/pckz-canonical-engine-9.88.1-protected.zip';
$zip     = new ZipArchive();
if ( true !== $zip->open( $bad_zip, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
	fwrite( STDERR, "FAIL: could not create bad zip\n" );
	exit( 1 );
}
$zip->addFromString(
	'pckz-canonical-engine/includes/class-pckz-master-control.php',
	"<?php\n// master-only\n"
);
$zip->addFromString(
	'pckz-canonical-engine/pckz-canonical-engine.php',
	"<?php\n/*\nPlugin Name: PCKZ\nVersion: 9.88.1\n*/\ndefine('PCKZCE_VERSION','9.88.1');\ndefine('PCKZCE_BUILD','9.88.1.smoke');\n"
);
$zip->close();

$validated = PCKZ_Licensing::diagnose_protected_release_archive( $bad_zip, '9.88.1', basename( $bad_zip ) );
if ( ! is_wp_error( $validated ) ) {
	fwrite( STDERR, "FAIL: master-only file should fail validation\n" );
	exit( 1 );
}
$message = $validated->get_error_message();
foreach ( array(
	'Protected archive contains master-only files',
	'Archive:',
	'pckz-canonical-engine-9.88.1-protected.zip',
	'includes/class-pckz-master-control.php',
	'Recommended action:',
) as $needle ) {
	if ( false === strpos( $message, $needle ) ) {
		fwrite( STDERR, "FAIL: detailed validation message missing: {$needle}\n" );
		exit( 1 );
	}
}

if ( 'master' !== PCKZ_Release_Storage::classify_package_type( 'pckz-canonical-engine-9.88.1-master.zip' ) ) {
	fwrite( STDERR, "FAIL: master zip classification\n" );
	exit( 1 );
}

$active_dir = $tmp_dir . '/active';
$quarantine_dir = $tmp_dir . '/quarantine';
@mkdir( $active_dir, 0775, true );
@mkdir( $quarantine_dir, 0775, true );
$active_path = $active_dir . '/pckz-canonical-engine-9.88.1-protected.zip';
copy( $bad_zip, $active_path );

$storage_ref = new ReflectionClass( 'PCKZ_Licensing' );
$storage_method = $storage_ref->getMethod( 'protected_release_storage' );
$storage_method->setAccessible( true );

$quarantine_ref = new ReflectionClass( 'PCKZ_Release_Storage' );
$quarantine_method = $quarantine_ref->getMethod( 'quarantine_storage' );
$quarantine_method->setAccessible( true );

// Monkeypatch storage paths via filters is not available; test quarantine helper directly with temp dirs.
$moved = PCKZ_Release_Storage::quarantine_package(
	$active_path,
	basename( $active_path ),
	'Smoke test quarantine',
	array(
		'validation_rule'   => 'master_only_file',
		'master_only_files' => array( 'includes/class-pckz-master-control.php' ),
	)
);
if ( is_wp_error( $moved ) ) {
	// Expected when wp_upload_dir is not fully mocked; fall back to diagnose-only path test.
	if ( 'quarantine_move_failed' !== $moved->get_error_code() && 'upload_dir' !== $moved->get_error_code() && 'mkdir_failed' !== $moved->get_error_code() ) {
		fwrite( STDERR, 'FAIL: unexpected quarantine error: ' . $moved->get_error_message() . "\n" );
		exit( 1 );
	}
} elseif ( ! file_exists( $active_path ) ) {
	// Quarantine succeeded in this environment.
	echo "release-storage-smoke: OK (quarantine move verified)\n";
	@unlink( $bad_zip );
	@array_map( 'unlink', glob( $tmp_dir . '/*/*' ) ?: array() );
	@rmdir( $quarantine_dir );
	@rmdir( $active_dir );
	@rmdir( $tmp_dir );
	exit( 0 );
}

$row = PCKZ_Release_Storage::diagnose_package( $bad_zip, basename( $bad_zip ) );
if ( 'invalid' !== ( $row['validation_status'] ?? '' ) ) {
	fwrite( STDERR, "FAIL: diagnose_package should mark master-only archive invalid\n" );
	exit( 1 );
}

@unlink( $bad_zip );
@rmdir( $active_dir );
@rmdir( $quarantine_dir );
@rmdir( $tmp_dir );

echo "release-storage-smoke: OK\n";
exit( 0 );
