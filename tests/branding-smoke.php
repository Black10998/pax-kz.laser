<?php
/**
 * PAXDesign branding metadata and admin views smoke test.
 *
 * @package PCKZCanonicalEngine
 */

$root = dirname( __DIR__ );

$plugin = file_get_contents( $root . '/pckz-canonical-engine.php' );
if ( ! is_string( $plugin ) ) {
	fwrite( STDERR, "FAIL: plugin header missing\n" );
	exit( 1 );
}

$checks = array(
	'Author:            PAXDesign'       => 'plugin author',
	'Author URI:        https://paxdesign.at' => 'author uri',
	'Plugin URI:        https://paxdesign.at' => 'plugin uri',
	"define( 'PCKZCE_VERSION', '2.17.5' )" => 'version constant',
);

foreach ( $checks as $needle => $label ) {
	if ( strpos( $plugin, $needle ) === false ) {
		fwrite( STDERR, "FAIL: {$label}\n" );
		exit( 1 );
	}
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $root . '/' );
}
if ( ! defined( 'PCKZCE_VERSION' ) ) {
	define( 'PCKZCE_VERSION', '2.17.7' );
	define( 'PCKZCE_BUILD', '2.17.7.20260529-font-export-ttf-latin' );
	define( 'PCKZCE_PLUGIN_DIR', $root . '/' );
}
require_once $root . '/includes/class-pckz-branding.php';

if ( PCKZ_Branding::AUTHOR_NAME !== 'PAXDesign' ) {
	fwrite( STDERR, "FAIL: author name\n" );
	exit( 1 );
}

if ( PCKZ_Branding::SUPPORT_EMAIL !== 'info@paxdesign.at' ) {
	fwrite( STDERR, "FAIL: support email\n" );
	exit( 1 );
}

$admin = file_get_contents( $root . '/includes/class-pckz-admin.php' );
if ( strpos( $admin, 'pckz-about' ) === false || strpos( $admin, 'render_about_developer' ) === false ) {
	fwrite( STDERR, "FAIL: about admin menu\n" );
	exit( 1 );
}

$about = file_get_contents( $root . '/admin/views/about-developer.php' );
if ( strpos( $about, 'Über den Entwickler' ) === false || strpos( $about, 'Ahmad Alkhalaf' ) === false ) {
	fwrite( STDERR, "FAIL: about page content\n" );
	exit( 1 );
}

$settings = file_get_contents( $root . '/admin/views/settings.php' );
if ( strpos( $settings, 'render_settings_panel' ) === false ) {
	fwrite( STDERR, "FAIL: settings branding panel\n" );
	exit( 1 );
}

echo "OK branding-smoke: PAXDesign metadata and admin branding\n";
