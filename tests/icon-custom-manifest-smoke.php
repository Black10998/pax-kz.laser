<?php
/**
 * Smoke: custom icon manifest merge (no file upload).
 */

$root = dirname( __DIR__ );
require_once $root . '/tests/smoke-bootstrap.php';

require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-icon-library.php';

$slug = 'smoke_test_icon';
$dir  = PCKZ_Icon_Library::upload_dir();
$file = 'smoke_test_icon.svg';
$svg  = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10"/></svg>';
file_put_contents( $dir . '/' . $file, $svg );

update_option(
	PCKZ_Icon_Library::OPTION_CUSTOM,
	array(
		$slug => array(
			'label' => 'Smoke Test',
			'file'  => $file,
		),
	)
);

$url = PCKZ_Icon_Library::icon_url( $slug );
if ( '' === $url ) {
	fwrite( STDERR, "FAIL custom icon_url\n" );
	exit( 1 );
}

if ( 'Smoke Test Label' !== PCKZ_Icon_Library::label_for_slug( $slug, 'Smoke Test' ) ) {
	update_option( PCKZ_Icon_Library::OPTION_LABELS, array( $slug => 'Smoke Test Label' ) );
	if ( 'Smoke Test Label' !== PCKZ_Icon_Library::label_for_slug( $slug, 'Smoke Test' ) ) {
		fwrite( STDERR, "FAIL label override\n" );
		exit( 1 );
	}
}

update_option( PCKZ_Icon_Library::OPTION_DISABLED, array( $slug ) );
$catalog = PCKZ_Ledos_Preview::icon_catalog( true );
if ( isset( $catalog[ $slug ] ) ) {
	fwrite( STDERR, "FAIL disabled custom icon in customer catalog\n" );
	exit( 1 );
}

PCKZ_Icon_Library::delete_custom( $slug );
update_option( PCKZ_Icon_Library::OPTION_DISABLED, array() );

echo "OK icon-custom-manifest-smoke\n";
