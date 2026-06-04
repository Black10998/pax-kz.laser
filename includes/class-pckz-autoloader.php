<?php
/**
 * PSR-4 style autoloader for plugin classes.
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PCKZ_Autoloader
 */
class PCKZ_Autoloader {

	/**
	 * Register autoloader.
	 */
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Autoload PCKZ_* classes from includes/.
	 *
	 * @param string $class Class name.
	 */
	public static function autoload( $class ) {
		if ( strpos( $class, 'PCKZ_' ) !== 0 ) {
			return;
		}

		$file = PCKZCE_PLUGIN_DIR . 'includes/class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';
		$protected_file = PCKZCE_PLUGIN_DIR . 'includes/protected/' . basename( $file );

		if ( extension_loaded( 'ionCube Loader' ) && is_readable( $protected_file ) ) {
			require_once $protected_file;
			return;
		}

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}
