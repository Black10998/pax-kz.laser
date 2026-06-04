<?php
/**
 * Smoke: public creator pages enqueue only production assets when protection is enabled.
 *
 * @package PCKZCanonicalEngine
 */

require_once dirname( __DIR__ ) . '/tests/smoke-bootstrap.php';

if ( ! defined( 'PCKZCE_PLUGIN_DIR' ) ) {
	define( 'PCKZCE_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'PCKZCE_PLUGIN_URL' ) ) {
	define( 'PCKZCE_PLUGIN_URL', 'https://example.test/wp-content/plugins/pckz-canonical-engine/' );
}
if ( ! defined( 'PCKZCE_VERSION' ) ) {
	define( 'PCKZCE_VERSION', 'smoke-test' );
}
if ( ! defined( 'PCKZCE_BUILD' ) ) {
	define( 'PCKZCE_BUILD', 'smoke-test-build' );
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( $handle, $src, $deps = array(), $ver = false, $media = 'all' ) {
		$GLOBALS['pckz_smoke_styles'][ $handle ] = array(
			'src' => (string) $src,
			'deps' => (array) $deps,
			'ver' => (string) $ver,
			'media' => (string) $media,
		);
	}
}
if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( $handle, $src, $deps = array(), $ver = false, $in_footer = false ) {
		$GLOBALS['pckz_smoke_scripts'][ $handle ] = array(
			'src' => (string) $src,
			'deps' => (array) $deps,
			'ver' => (string) $ver,
			'in_footer' => (bool) $in_footer,
		);
	}
}
if ( ! function_exists( 'wp_add_inline_style' ) ) {
	function wp_add_inline_style( $handle, $data ) {
		unset( $handle, $data );
	}
}
if ( ! function_exists( 'wp_add_inline_script' ) ) {
	function wp_add_inline_script( $handle, $data, $position = 'after' ) {
		unset( $handle, $data, $position );
	}
}
if ( ! function_exists( 'wp_localize_script' ) ) {
	function wp_localize_script( $handle, $name, $data ) {
		$GLOBALS['pckz_smoke_localized'][ $handle . ':' . $name ] = $data;
	}
}
if ( ! function_exists( 'get_the_title' ) ) {
	function get_the_title( $post_id ) {
		return 'Product #' . (int) $post_id;
	}
}
if ( ! function_exists( 'number_format_i18n' ) ) {
	function number_format_i18n( $number, $decimals = 0 ) {
		return number_format( (float) $number, (int) $decimals, '.', '' );
	}
}

if ( ! class_exists( 'PCKZ_Icons' ) ) {
	class PCKZ_Icons {
		public static function registry_for_js() {
			return array();
		}
	}
}
if ( ! class_exists( 'PCKZ_Commerce' ) ) {
	class PCKZ_Commerce {
		public static function config_for_js( $product_id ) {
			unset( $product_id );
			return array();
		}
	}
}

require_once dirname( __DIR__ ) . '/includes/class-pckz-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-pckz-assets.php';

$GLOBALS['pckz_smoke_options'][ PCKZ_Settings::OPTION_KEY ] = array(
	'security_prefer_protected_assets' => true,
);

$config = array(
	'background_day' => 'https://example.test/day.png',
	'background_night' => 'https://example.test/night.png',
	'background_image' => 'https://example.test/day.png',
	'woo_product_id' => 0,
);

PCKZ_Assets::enqueue_creator( 123, $config );

$styles = $GLOBALS['pckz_smoke_styles'] ?? array();
$scripts = $GLOBALS['pckz_smoke_scripts'] ?? array();

if ( empty( $styles['pckzce-creator']['src'] ) || ! preg_match( '/\.min\.css(?:\?|$)/', (string) $styles['pckzce-creator']['src'] ) ) {
	fwrite( STDERR, "Creator stylesheet is not served as .min.css in protection mode.\n" );
	exit( 1 );
}

foreach ( $scripts as $handle => $script ) {
	$src = (string) ( $script['src'] ?? '' );
	if ( '' === $src || false === strpos( $src, '/public/js/' ) || false !== strpos( $src, '/vendor/' ) ) {
		continue;
	}
	if ( ! preg_match( '/\.(?:min|protected)\.js(?:\?|$)/', $src ) ) {
		fwrite( STDERR, "Non-production JS enqueued for {$handle}: {$src}\n" );
		exit( 1 );
	}
}

$missing = PCKZ_Assets::missing_production_assets( array( 'security_prefer_protected_assets' => true ) );
if ( ! empty( $missing ) ) {
	fwrite( STDERR, 'Missing production assets: ' . wp_json_encode( $missing ) . "\n" );
	exit( 1 );
}

$map_found = false;
foreach ( array( PCKZCE_PLUGIN_DIR . 'public/js', PCKZCE_PLUGIN_DIR . 'public/css' ) as $asset_root ) {
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $asset_root, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::LEAVES_ONLY
	);
	foreach ( $iterator as $file_info ) {
		if ( preg_match( '/\.map$/i', $file_info->getPathname() ) ) {
			$map_found = true;
			break 2;
		}
	}
}
if ( $map_found ) {
	fwrite( STDERR, "Source map files are present in public assets.\n" );
	exit( 1 );
}

echo "public-protected-assets-smoke: OK\n";
exit( 0 );
