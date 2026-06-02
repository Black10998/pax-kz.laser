<?php
/**
 * Smoke: bundled type_21–121 SVGs match Cloudlift line artboard (950×35).
 */
$root = dirname( __DIR__ );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $root . '/' );
}

if ( ! function_exists( 'trailingslashit' ) ) {
	/**
	 * @param string $string Path.
	 * @return string
	 */
	function trailingslashit( $string ) {
		return rtrim( $string, '/\\' ) . '/';
	}
}

if ( ! defined( 'PCKZCE_PLUGIN_DIR' ) ) {
	define( 'PCKZCE_PLUGIN_DIR', $root . '/' );
}

require_once $root . '/includes/class-pckz-ledos-preview.php';

$dir       = PCKZ_Ledos_Preview::line_assets_dir();
$red_min   = 102;
$red_color = '#b22222';

for ( $i = PCKZ_Ledos_Preview::BUNDLED_LINE_TYPE_MIN; $i <= PCKZ_Ledos_Preview::BUNDLED_LINE_TYPE_MAX; $i++ ) {
	$path = $dir . 'type_' . $i . '.svg';
	if ( ! is_readable( $path ) ) {
		fwrite( STDERR, "FAIL missing {$path}\n" );
		exit( 1 );
	}
	$svg     = file_get_contents( $path );
	$is_red  = ( $i >= $red_min );
	if ( ! preg_match( '/viewBox="0 0 950 35"/', $svg ) ) {
		fwrite( STDERR, "FAIL viewBox 950×35 for type_{$i}\n" );
		exit( 1 );
	}
	if ( preg_match( '/#000000|fill="black"/i', $svg ) ) {
		fwrite( STDERR, "FAIL black fill/stroke in type_{$i}\n" );
		exit( 1 );
	}
	if ( $is_red ) {
		if ( ! preg_match( '/' . preg_quote( $red_color, '/' ) . '/i', $svg ) ) {
			fwrite( STDERR, "FAIL matte red (#B22222) expected in type_{$i}\n" );
			exit( 1 );
		}
	} elseif ( ! preg_match( '/fill="white"|stroke="white"/', $svg ) ) {
		fwrite( STDERR, "FAIL no white primitives in type_{$i}\n" );
		exit( 1 );
	}
	$stroke_pat = $is_red
		? '<path d="M[^"]+ L[^"]+" fill="none" stroke="#B22222"'
		: '<path d="M[^"]+ L[^"]+" fill="none" stroke="white"';
	$has_stroke_runner = (bool) preg_match( '/' . $stroke_pat . '/i', $svg );
	if ( $has_stroke_runner && preg_match( '/M598 [\d.]+ L940/', $svg ) ) {
		if ( ! preg_match( '/M9\.5 [\d.]+ L352/', $svg ) ) {
			fwrite( STDERR, "FAIL missing left runner (9.5→352) in type_{$i}\n" );
			exit( 1 );
		}
	}
}

$total = PCKZ_Ledos_Preview::BUNDLED_LINE_TYPE_MAX - PCKZ_Ledos_Preview::BUNDLED_LINE_TYPE_MIN + 1;
echo "OK bundled-line-svg-format: {$total} bundled types use 950×35 artboard\n";
