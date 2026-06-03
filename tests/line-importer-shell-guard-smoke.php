<?php
/**
 * Smoke: Line importer must not fatal when PHP exec() is disabled.
 *
 * Run: php -d disable_functions=exec tests/line-importer-shell-guard-smoke.php
 *
 * @package PCKZCanonicalEngine
 */

require __DIR__ . '/smoke-bootstrap.php';

if ( function_exists( 'exec' ) ) {
	fwrite( STDERR, "SKIP line-importer-shell-guard-smoke: run with php -d disable_functions=exec\n" );
	exit( 0 );
}

if ( ! class_exists( 'PCKZ_Line_Importer' ) ) {
	fwrite( STDERR, "FAIL: PCKZ_Line_Importer not loaded\n" );
	exit( 1 );
}

if ( PCKZ_Line_Importer::shell_exec_available() ) {
	fwrite( STDERR, "FAIL: shell_exec_available should be false when exec is disabled\n" );
	exit( 1 );
}

if ( PCKZ_Line_Importer::converter_available() ) {
	fwrite( STDERR, "FAIL: converter_available should be false when exec is disabled\n" );
	exit( 1 );
}

$notice = PCKZ_Line_Importer::environment_notice();
if ( '' === $notice ) {
	fwrite( STDERR, "FAIL: environment_notice should be non-empty when exec is disabled\n" );
	exit( 1 );
}

if ( '.svg' !== PCKZ_Line_Importer::accept_attribute_for_environment() ) {
	fwrite( STDERR, "FAIL: accept should be .svg only without converter\n" );
	exit( 1 );
}

// Must not throw (was fatal in 2.27.35).
PCKZ_Line_Importer::python_binary();

echo "OK line-importer-shell-guard-smoke\n";
