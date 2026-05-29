<?php
/**
 * Bundled premium icon assets and admin visibility controls.
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PCKZ_Icon_Library
 */
class PCKZ_Icon_Library {

	const OPTION_DISABLED = 'pckz_icon_disabled_slugs';
	const BUNDLED_SUBDIR  = 'public/images/icons/bundled/';

	/**
	 * Bundled icon slugs => labels (manifest).
	 *
	 * @return array<string,string>
	 */
	public static function bundled_manifest() {
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}
		$file  = PCKZCE_PLUGIN_DIR . 'includes/bundled-premium-icons.php';
		$cache = is_readable( $file ) ? include $file : array();
		if ( ! is_array( $cache ) ) {
			$cache = array();
		}
		return $cache;
	}

	/**
	 * Whether slug is a bundled premium icon.
	 *
	 * @param string $slug Icon slug.
	 * @return bool
	 */
	public static function is_bundled( $slug ) {
		return isset( self::bundled_manifest()[ $slug ] );
	}

	/**
	 * Absolute path to bundled SVG (preserved artwork).
	 *
	 * @param string $slug Icon slug.
	 * @return string
	 */
	public static function bundled_file_path( $slug ) {
		$slug = sanitize_key( $slug );
		if ( ! self::is_bundled( $slug ) ) {
			return '';
		}
		$path = PCKZCE_PLUGIN_DIR . self::BUNDLED_SUBDIR . $slug . '.svg';
		return is_readable( $path ) ? $path : '';
	}

	/**
	 * Public URL for bundled SVG.
	 *
	 * @param string $slug Icon slug.
	 * @return string
	 */
	public static function bundled_url( $slug ) {
		if ( ! self::bundled_file_path( $slug ) ) {
			return '';
		}
		return esc_url_raw( PCKZCE_PLUGIN_URL . self::BUNDLED_SUBDIR . sanitize_key( $slug ) . '.svg' );
	}

	/**
	 * Slugs disabled in admin (hidden from customer selector).
	 *
	 * @return string[]
	 */
	public static function disabled_slugs() {
		$raw = get_option( self::OPTION_DISABLED, array() );
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out = array();
		foreach ( $raw as $slug ) {
			$s = sanitize_key( $slug );
			if ( $s && 'none' !== $s ) {
				$out[] = $s;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Whether icon appears in customer-facing catalog/selector.
	 *
	 * @param string $slug Icon slug.
	 * @return bool
	 */
	public static function is_visible( $slug ) {
		if ( 'none' === $slug || '' === $slug ) {
			return true;
		}
		return ! in_array( sanitize_key( $slug ), self::disabled_slugs(), true );
	}

	/**
	 * Filter catalog entries for customer UI.
	 *
	 * @param array<string,array> $items Icon catalog.
	 * @return array<string,array>
	 */
	public static function filter_visible_catalog( $items ) {
		foreach ( $items as $slug => $data ) {
			if ( ! self::is_visible( $slug ) ) {
				unset( $items[ $slug ] );
			}
		}
		return $items;
	}

	/**
	 * All catalog slugs for admin (unfiltered).
	 *
	 * @return array<string,array>
	 */
	public static function admin_catalog_entries() {
		if ( ! class_exists( 'PCKZ_Ledos_Preview' ) ) {
			return array();
		}
		return PCKZ_Ledos_Preview::icon_catalog( false );
	}

}
