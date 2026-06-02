<?php
/**
 * Smoke: bundled type_21–81 SVGs match Cloudlift line artboard (950×35, white).
 */
if ( ! defined( 'PCKZCE_PLUGIN_DIR' ) ) {
	define( 'PCKZCE_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}

require_once dirname( __DIR__ ) . '/includes/class-pckz-ledos-preview.php';

$dir = PCKZ_Ledos_Preview::line_assets_dir();

for ( $i = PCKZ_Ledos_Preview::BUNDLED_LINE_TYPE_MIN; $i <= PCKZ_Ledos_Preview::BUNDLED_LINE_TYPE_MAX; $i++ ) {
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
	// Types with horizontal stroke runners must use plate-side text band (like CDN type 5).
	$has_stroke_runner = (bool) preg_match(
		'/<path d="M[^"]+ L[^"]+" fill="none" stroke="white"/',
		$svg
	);
	if ( $has_stroke_runner ) {
		if ( ! preg_match( '/M9\.5 [\d.]+ L(?:19[0-9]|20[0-5])\./', $svg ) ) {
			fwrite( STDERR, "FAIL missing left runner before text band in type_{$i}\n" );
			exit( 1 );
		}
		if ( ! preg_match( '/M75[0-9][\d.]* [\d.]+ L(?:93[0-9]|940)/', $svg ) ) {
			fwrite( STDERR, "FAIL missing right runner after text band in type_{$i}\n" );
			exit( 1 );
		}
	}
}

$total = PCKZ_Ledos_Preview::BUNDLED_LINE_TYPE_MAX - PCKZ_Ledos_Preview::BUNDLED_LINE_TYPE_MIN + 1;
echo "OK bundled-line-svg-format: {$total} bundled types use 950×35 white artboard\n";
