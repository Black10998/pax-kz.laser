<?php
/**
 * Smoke: line library save payload builder + custom visibility persistence.
 *
 * @package PCKZCanonicalEngine
 */

$root = dirname( __DIR__ );
require_once $root . '/tests/smoke-bootstrap.php';

require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-line-library.php';

$slug = 'type_998';
$dir  = PCKZ_Line_Library::upload_dir();
$file = 'type_998.svg';
$svg  = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 20"><rect width="100" height="20"/></svg>';
file_put_contents( $dir . '/' . $file, $svg );

update_option(
	PCKZ_Line_Library::OPTION_CUSTOM,
	array(
		$slug => array(
			'label' => 'Payload Smoke Line',
			'file'  => $file,
		),
	)
);
update_option( PCKZ_Line_Library::OPTION_DISABLED, array( $slug ) );

$payload = PCKZ_Line_Library::build_admin_save_payload();
if ( empty( $payload['lines'][ $slug ] ) ) {
	fwrite( STDERR, "FAIL build_admin_save_payload missing custom slug\n" );
	exit( 1 );
}

$payload['lines'][ $slug ]['enabled'] = true;
$result = PCKZ_Line_Library::save_admin_state_from_post(
	array(
		'pckz_line_library_payload' => wp_json_encode( $payload ),
	)
);
if ( is_wp_error( $result ) ) {
	fwrite( STDERR, 'FAIL save_admin_state_from_post: ' . $result->get_error_message() . "\n" );
	exit( 1 );
}

if ( ! PCKZ_Line_Library::is_visible( $slug ) ) {
	fwrite( STDERR, "FAIL custom line should be visible after save\n" );
	exit( 1 );
}

$stored = get_option( PCKZ_Line_Library::OPTION_CUSTOM, array() );
if ( empty( $stored[ $slug ]['customer_visible'] ) ) {
	fwrite( STDERR, "FAIL customer_visible not persisted on custom manifest\n" );
	exit( 1 );
}

$choices = PCKZ_Line_Library::get_customer_line_choices();
$found   = false;
foreach ( $choices as $choice ) {
	if ( ( $choice['value'] ?? '' ) === $slug ) {
		$found = true;
		break;
	}
}
if ( ! $found ) {
	fwrite( STDERR, "FAIL enabled custom line missing from customer choices\n" );
	exit( 1 );
}

PCKZ_Line_Library::delete_custom( $slug );
update_option( PCKZ_Line_Library::OPTION_DISABLED, array() );

echo "OK line-library-payload-smoke\n";
