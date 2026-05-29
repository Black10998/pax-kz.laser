<?php
/**
 * Smoke: bundled type_21–38 SVGs match Cloudlift line artboard (950×35, white).
 */
if ( ! defined( 'PCKZCE_PLUGIN_DIR' ) ) {
	define( 'PCKZCE_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}

require_once dirname( __DIR__ ) . '/includes/class-pckz-ledos-preview.php';

$dir = PCKZ_Ledos_Preview::line_assets_dir();
for ( $i = 21; $i <= 38; $i++ ) {
	$path = $dir . 'type_' . $i . '.svg';
	if ( ! is_readable( $path ) ) {
		fwrite( STDERR, "FAIL missing {$path}\n" );
		exit( 1 );
	}
	$svg = file_get_contents( $path );
	if ( ! preg_match( '/viewBox="0 0 950 35"/', $svg ) ) {
		fwrite( STDERR, "FAIL viewBox 950×35 for type_{$i}\n" );
		exit( 1 );
	}
	if ( preg_match( '/#000000|fill="black"/i', $svg ) ) {
		fwrite( STDERR, "FAIL black fill/stroke in type_{$i}\n" );
		exit( 1 );
	}
	if ( ! preg_match( '/fill="white"|stroke="white"/', $svg ) ) {
		fwrite( STDERR, "FAIL no white primitives in type_{$i}\n" );
		exit( 1 );
	}
	// Side runners + open center (no continuous horizontal through text band ~200–758).
	if ( ! preg_match( '/M9\.5 [\d.]+ L(?:19[0-9]|20[0-5])\./', $svg ) ) {
		fwrite( STDERR, "FAIL missing left runner before text band in type_{$i}\n" );
		exit( 1 );
	}
	if ( ! preg_match( '/M75[0-9][\d.]* [\d.]+ L(?:93[0-9]|940)/', $svg ) ) {
		fwrite( STDERR, "FAIL missing right runner after text band in type_{$i}\n" );
		exit( 1 );
	}
}

echo "OK bundled-line-svg-format: 18 types use 950×35 white artboard with text band\n";
