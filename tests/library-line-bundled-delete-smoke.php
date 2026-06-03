<?php
/**
 * Smoke: bundled line permanent delete removes file and catalog entry.
 */
$root = dirname( __DIR__ );
require_once $root . '/tests/smoke-bootstrap.php';

require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-ledos-preview.php';
require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-line-library.php';

$slug = 'type_199';
$dir  = PCKZ_Ledos_Preview::line_assets_dir();
$path = $dir . $slug . '.svg';
$svg  = '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 950 35"><path fill="#B22222" d="M9.5 17.5 L352 17.5"/></svg>';
file_put_contents( $path, $svg );

$result = PCKZ_Line_Library::delete_bundled_permanent( $slug );
if ( is_wp_error( $result ) ) {
	fwrite( STDERR, 'FAIL delete_bundled_permanent: ' . $result->get_error_message() . "\n" );
	exit( 1 );
}
if ( is_readable( $path ) ) {
	fwrite( STDERR, "FAIL SVG file should be deleted\n" );
	exit( 1 );
}
if ( ! PCKZ_Line_Library::is_permanently_deleted_bundled( $slug ) ) {
	fwrite( STDERR, "FAIL slug should be permanently deleted\n" );
	exit( 1 );
}
$types = PCKZ_Ledos_Preview::line_types();
if ( isset( $types[ $slug ] ) ) {
	fwrite( STDERR, "FAIL line_types should not register deleted slug\n" );
	exit( 1 );
}

echo "OK library-line-bundled-delete-smoke\n";
