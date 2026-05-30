<?php
/**
 * text_plate_paths_b64 must win over truncated/plain marker (WAF regression).
 *
 * Run: php tests/text-plate-paths-b64-decode-smoke.php
 *
 * @package PCKZCanonicalEngine
 */

require_once __DIR__ . '/smoke-bootstrap.php';

$full = '<g id="pckz-text-engrave" fill="#FFFFFF"><path d="M 10 20 L 30 40 Z"/></g>';
$b64  = base64_encode( $full );

$_POST['text_plate_paths']     = 'b64';
$_POST['text_plate_paths_b64'] = $b64;

$decoded = PCKZ_Export_Diagnostics::decode_text_plate_paths_from_request(
	(string) $_POST['text_plate_paths'],
	(string) $_POST['text_plate_paths_b64']
);

if ( $decoded !== $full ) {
	fwrite( STDERR, "FAIL: expected full fragment from b64, got len " . strlen( $decoded ) . "\n" );
	exit( 1 );
}

$probe = PCKZ_Export_Diagnostics::probe_text_fragment_parse(
	$decoded,
	PCKZ_Plate_Calibration::TOTAL_WIDTH_MM,
	PCKZ_Plate_Calibration::PLATE_HEIGHT_MM,
	array()
);

if ( empty( $probe['lbrn2_parse_ok'] ) ) {
	fwrite( STDERR, 'FAIL probe: ' . wp_json_encode( $probe ) . "\n" );
	exit( 1 );
}

echo "OK text-plate-paths-b64-decode-smoke\n";
exit( 0 );
