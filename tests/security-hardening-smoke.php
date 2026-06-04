<?php
/**
 * Smoke: security hardening helpers (UUID/IP/mmanifest validation).
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

$lic_ref = new ReflectionClass( 'PCKZ_Licensing' );

$uuid_check = $lic_ref->getMethod( 'is_valid_install_uuid' );
$uuid_check->setAccessible( true );
if ( ! $uuid_check->invoke( null, '550e8400-e29b-41d4-a716-446655440000' ) ) {
	fwrite( STDERR, "FAIL: valid install UUID rejected\n" );
	exit( 1 );
}
if ( $uuid_check->invoke( null, 'not-a-uuid' ) ) {
	fwrite( STDERR, "FAIL: invalid install UUID accepted\n" );
	exit( 1 );
}

$ip_match = $lic_ref->getMethod( 'ip_matches_allowlist_entry' );
$ip_match->setAccessible( true );
if ( ! $ip_match->invoke( null, '192.168.10.7', '192.168.10.0/24' ) ) {
	fwrite( STDERR, "FAIL: CIDR IP allowlist check failed\n" );
	exit( 1 );
}
if ( $ip_match->invoke( null, '10.10.10.5', '192.168.10.0/24' ) ) {
	fwrite( STDERR, "FAIL: CIDR IP allowlist false positive\n" );
	exit( 1 );
}

if ( ! class_exists( 'ZipArchive' ) ) {
	echo "security-hardening-smoke: SKIP (ZipArchive missing)\n";
	exit( 0 );
}

$tmp_dir = sys_get_temp_dir() . '/pckz-security-smoke-' . wp_generate_uuid4();
@mkdir( $tmp_dir, 0775, true );
@mkdir( $tmp_dir . '/pckz-canonical-engine', 0775, true );
$asset_path = $tmp_dir . '/pckz-canonical-engine/test.txt';
file_put_contents( $asset_path, "ok\n" );
$manifest = array(
	'slug'       => 'pckz-canonical-engine',
	'version'    => '2.99.0',
	'build'      => '2.99.0.test',
	'channel'    => 'customer-protected',
	'created_at' => gmdate( 'c' ),
	'files'      => array(
		'test.txt' => hash_file( 'sha256', $asset_path ),
	),
	'signature' => '',
	'signature_alg' => 'none',
	'signature_hint' => 'unsigned',
);
file_put_contents( $tmp_dir . '/pckz-canonical-engine/RELEASE_MANIFEST.json', wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );

$zip_path = $tmp_dir . '/release.zip';
$zip      = new ZipArchive();
if ( true !== $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
	fwrite( STDERR, "FAIL: could not create test zip\n" );
	exit( 1 );
}
$zip->addFile( $asset_path, 'pckz-canonical-engine/test.txt' );
$zip->addFile( $tmp_dir . '/pckz-canonical-engine/RELEASE_MANIFEST.json', 'pckz-canonical-engine/RELEASE_MANIFEST.json' );
$zip->close();

$validate = $lic_ref->getMethod( 'validate_protected_release_archive' );
$validate->setAccessible( true );
$validated = $validate->invoke( null, $zip_path, '2.99.0', true, false );
if ( is_wp_error( $validated ) ) {
	fwrite( STDERR, "FAIL: manifest validation failed: " . $validated->get_error_message() . "\n" );
	exit( 1 );
}
if ( empty( $validated['manifest_valid'] ) ) {
	fwrite( STDERR, "FAIL: manifest should be valid\n" );
	exit( 1 );
}

echo "security-hardening-smoke: OK\n";
exit( 0 );
