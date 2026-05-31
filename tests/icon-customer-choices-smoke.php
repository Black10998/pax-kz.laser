<?php
/**
 * Smoke: customer icon picker choices resolve from live catalog (includes custom uploads).
 *
 * @package PCKZCanonicalEngine
 */

$root = dirname( __DIR__ );
require_once $root . '/tests/smoke-bootstrap.php';

require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-icon-library.php';

$slug = 'smoke_customer_choice_icon';
$dir  = PCKZ_Icon_Library::upload_dir();
$file = 'smoke_customer_choice_icon.svg';
$svg  = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10"/></svg>';
file_put_contents( $dir . '/' . $file, $svg );

update_option(
	PCKZ_Icon_Library::OPTION_CUSTOM,
	array(
		$slug => array(
			'label' => 'Customer Choice Smoke',
			'file'  => $file,
		),
	)
);
update_option( PCKZ_Icon_Library::OPTION_DISABLED, array() );

$choices = PCKZ_Icon_Library::get_customer_icon_choices();
$found   = false;
foreach ( $choices as $choice ) {
	if ( ( $choice['value'] ?? '' ) === $slug ) {
		$found = true;
		if ( empty( $choice['img'] ) || empty( $choice['label'] ) ) {
			fwrite( STDERR, "FAIL custom icon choice missing img/label\n" );
			exit( 1 );
		}
		break;
	}
}

if ( ! $found ) {
	fwrite( STDERR, "FAIL custom upload missing from get_customer_icon_choices()\n" );
	exit( 1 );
}

$none_found = false;
foreach ( $choices as $choice ) {
	if ( 'none' === ( $choice['value'] ?? '' ) ) {
		$none_found = true;
		break;
	}
}
if ( ! $none_found ) {
	fwrite( STDERR, "FAIL 'none' choice missing from customer icon choices\n" );
	exit( 1 );
}

PCKZ_Icon_Library::delete_custom( $slug );

echo "OK icon-customer-choices-smoke\n";
