<?php
/**
 * Smoke: icon library save payload builder + custom visibility persistence.
 */

$root = dirname( __DIR__ );
require_once $root . '/tests/smoke-bootstrap.php';

require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-icon-library.php';

$slug = 'payload_smoke_icon';
$dir  = PCKZ_Icon_Library::upload_dir();
$file = $slug . '.svg';
$svg  = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10"/></svg>';
file_put_contents( $dir . '/' . $file, $svg );

update_option(
	PCKZ_Icon_Library::OPTION_CUSTOM,
	array(
		$slug => array(
			'label'            => 'Payload Smoke',
			'file'             => $file,
			'customer_visible' => false,
		),
	)
);
update_option( PCKZ_Icon_Library::OPTION_DISABLED, array( $slug ) );

$payload = PCKZ_Icon_Library::build_admin_save_payload();
if ( empty( $payload['icons'][ $slug ] ) || ! empty( $payload['icons'][ $slug ]['enabled'] ) ) {
	fwrite( STDERR, "FAIL build_admin_save_payload should reflect disabled custom icon\n" );
	exit( 1 );
}

$payload['icons'][ $slug ]['enabled'] = true;
$result = PCKZ_Icon_Library::save_admin_state_from_post(
	array(
		'pckz_icon_library_payload' => wp_json_encode( $payload ),
	)
);

if ( true !== $result ) {
	fwrite( STDERR, "FAIL save_admin_state_from_post returned error\n" );
	exit( 1 );
}

if ( ! PCKZ_Icon_Library::is_visible( $slug ) ) {
	fwrite( STDERR, "FAIL custom icon should be visible after payload save\n" );
	exit( 1 );
}

$stored = get_option( PCKZ_Icon_Library::OPTION_CUSTOM, array() );
if ( empty( $stored[ $slug ]['customer_visible'] ) ) {
	fwrite( STDERR, "FAIL customer_visible not stored on custom manifest row\n" );
	exit( 1 );
}

$choices = PCKZ_Icon_Library::get_customer_icon_choices();
$choice_slugs = array_column( $choices, 'value' );
if ( ! in_array( $slug, $choice_slugs, true ) ) {
	fwrite( STDERR, "FAIL saved custom icon missing from customer choices\n" );
	exit( 1 );
}

PCKZ_Icon_Library::delete_custom( $slug );
update_option( PCKZ_Icon_Library::OPTION_DISABLED, array() );

echo "OK icon-library-payload-smoke\n";
