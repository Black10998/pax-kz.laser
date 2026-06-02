<?php
/**
 * Smoke: customer Linien picker choices resolve from live catalog (includes custom uploads).
 *
 * @package PCKZCanonicalEngine
 */

$root = dirname( __DIR__ );
require_once $root . '/tests/smoke-bootstrap.php';

require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-line-library.php';

$slug = 'type_999';
$dir  = PCKZ_Line_Library::upload_dir();
$file = 'type_999.svg';
$svg  = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 20"><rect width="100" height="20"/></svg>';
file_put_contents( $dir . '/' . $file, $svg );

update_option(
	PCKZ_Line_Library::OPTION_CUSTOM,
	array(
		$slug => array(
			'label' => 'Customer Line Smoke',
			'file'  => $file,
		),
	)
);
update_option( PCKZ_Line_Library::OPTION_DISABLED, array() );

$choices = PCKZ_Line_Library::get_customer_line_choices();
$found   = false;
foreach ( $choices as $choice ) {
	if ( ( $choice['value'] ?? '' ) === $slug ) {
		$found = true;
		if ( empty( $choice['img'] ) || empty( $choice['label'] ) ) {
			fwrite( STDERR, "FAIL custom line choice missing img/label\n" );
			exit( 1 );
		}
		if ( false === strpos( $choice['img'], 'pckz_line_picker=' . $slug ) ) {
			fwrite( STDERR, "FAIL custom line picker should use display-only picker preview endpoint\n" );
			exit( 1 );
		}
		break;
	}
}

if ( ! $found ) {
	fwrite( STDERR, "FAIL custom upload missing from get_customer_line_choices()\n" );
	exit( 1 );
}

$lines = PCKZ_Ledos_Preview::line_types();
if ( empty( $lines[ $slug ] ) ) {
	fwrite( STDERR, "FAIL custom line missing from line_types() for preview/export\n" );
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
	fwrite( STDERR, "FAIL 'none' choice missing from customer line choices\n" );
	exit( 1 );
}

PCKZ_Line_Library::delete_custom( $slug );

echo "OK line-customer-choices-smoke\n";
