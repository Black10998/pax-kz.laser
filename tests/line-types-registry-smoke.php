<?php
/**
 * Smoke: bundled line types 21–71 register when SVG assets exist.
 *
 * @package PCKZCanonicalEngine
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

require_once $root . '/includes/class-pckz-ledos-preview.php';

if ( ! defined( 'PCKZCE_PLUGIN_DIR' ) ) {
	define( 'PCKZCE_PLUGIN_DIR', $root . '/' );
	define( 'PCKZCE_PLUGIN_URL', 'https://example.test/wp-content/plugins/pckz-canonical-engine/' );
}

$lines = PCKZ_Ledos_Preview::line_types();

for ( $i = 1; $i <= 20; $i++ ) {
	$key = 'type_' . $i;
	if ( empty( $lines[ $key ] ) ) {
		fwrite( STDERR, "FAIL missing CDN line {$key}\n" );
		exit( 1 );
	}
}

$bundled = 0;
for ( $i = PCKZ_Ledos_Preview::BUNDLED_LINE_TYPE_MIN; $i <= PCKZ_Ledos_Preview::BUNDLED_LINE_TYPE_MAX; $i++ ) {
	$key  = 'type_' . $i;
	$path = PCKZ_Ledos_Preview::line_assets_dir() . $key . '.svg';
	if ( is_readable( $path ) ) {
		if ( empty( $lines[ $key ] ) || strpos( $lines[ $key ], 'public/assets/lines/' ) === false ) {
			fwrite( STDERR, "FAIL {$key} file exists but not registered\n" );
			exit( 1 );
		}
		++$bundled;
	} elseif ( ! empty( $lines[ $key ] ) ) {
		fwrite( STDERR, "FAIL {$key} registered without readable file\n" );
		exit( 1 );
	}
}

echo "OK line-types-registry: CDN 1-20 present; bundled registered={$bundled}\n";
