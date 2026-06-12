<?php
/**
 * Smoke: release pipeline must not fatal when PHP exec() is disabled.
 *
 * @package PCKZCanonicalEngine
 */

require_once dirname( __DIR__ ) . '/tests/smoke-bootstrap.php';

if ( ! defined( 'PCKZCE_PLUGIN_DIR' ) ) {
	define( 'PCKZCE_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'PCKZCE_VERSION' ) ) {
	define( 'PCKZCE_VERSION', '2.28.26' );
}
if ( ! defined( 'PCKZCE_BUILD' ) ) {
	define( 'PCKZCE_BUILD', '2.28.26-test' );
}

require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-settings.php';
require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-licensing.php';

if ( ! method_exists( 'PCKZ_Licensing', 'exec_available' ) ) {
	fwrite( STDERR, "Missing PCKZ_Licensing::exec_available()\n" );
	exit( 1 );
}

$exec_flag = PCKZ_Licensing::exec_available();
if ( ! is_bool( $exec_flag ) ) {
	fwrite( STDERR, "exec_available() must return bool\n" );
	exit( 1 );
}

$ref = new ReflectionMethod( 'PCKZ_Licensing', 'run_js_protection_on_directory' );
$ref->setAccessible( true );
$result = $ref->invoke( null, PCKZCE_PLUGIN_DIR );
if ( true !== $result ) {
	fwrite( STDERR, "run_js_protection_on_directory() must return true on this host\n" );
	exit( 1 );
}

$warnings = PCKZ_Licensing::pop_release_build_warnings();
if ( ! $exec_flag && empty( $warnings ) ) {
	fwrite( STDERR, "Expected release build warning when exec() is unavailable\n" );
	exit( 1 );
}

$source = file_get_contents( PCKZCE_PLUGIN_DIR . 'includes/class-pckz-licensing.php' );
if ( false === $source || false === strpos( $source, 'function_exists( \'exec\' )' ) && false === strpos( $source, 'is_php_function_available' ) ) {
	fwrite( STDERR, "Release pipeline is missing exec availability guard\n" );
	exit( 1 );
}

echo "OK release-exec-fallback-smoke\n";
exit( 0 );
