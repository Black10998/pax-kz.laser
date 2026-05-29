<?php
/**
 * Monochrome social / symbol icons for license plate strip preview.
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PCKZ_Icons
 */
class PCKZ_Icons {

	/**
	 * Symbol choices for left/right pickers (Ledos-style).
	 *
	 * @return array
	 */
	public static function symbol_choices() {
		return array(
			array(
				'value' => 'none',
				'label' => 'Kein Symbol',
			),
			array(
				'value' => 'instagram',
				'label' => 'Instagram',
			),
			array(
				'value' => 'telegram',
				'label' => 'Telegram',
			),
			array(
				'value' => 'facebook',
				'label' => 'Facebook',
			),
			array(
				'value' => 'snapchat',
				'label' => 'Snapchat',
			),
			array(
				'value' => 'tiktok',
				'label' => 'TikTok',
			),
			array(
				'value' => 'whatsapp',
				'label' => 'WhatsApp',
			),
		);
	}

	/**
	 * All registered icon slugs.
	 *
	 * @return string[]
	 */
	public static function all_slugs() {
		$base = array( 'none', 'instagram', 'telegram', 'facebook', 'snapchat', 'tiktok', 'whatsapp', 'lines' );
		if ( class_exists( 'PCKZ_Icon_Library' ) ) {
			$base = array_merge( $base, array_keys( PCKZ_Icon_Library::bundled_manifest() ) );
		}
		return array_values( array_unique( $base ) );
	}

	/**
	 * Public URL to bundled SVG icon.
	 *
	 * @param string $slug  Icon slug.
	 * @param string $color white|black.
	 * @return string
	 */
	public static function icon_url( $slug, $color = 'white' ) {
		$slug  = sanitize_key( $slug );
		$color = in_array( $color, array( 'white', 'black' ), true ) ? $color : 'white';
		if ( 'none' === $slug || ! in_array( $slug, self::all_slugs(), true ) ) {
			return '';
		}
		if ( class_exists( 'PCKZ_Icon_Library' ) ) {
			$bundled = PCKZ_Icon_Library::bundled_url( $slug );
			if ( $bundled ) {
				return $bundled;
			}
		}
		$file = PCKZCE_PLUGIN_URL . 'public/images/icons/' . $slug . '-' . $color . '.svg';
		return esc_url_raw( $file );
	}

	/**
	 * Icon map for JavaScript (preview badges + canvas).
	 *
	 * @return array
	 */
	public static function registry_for_js() {
		$registry = array();
		foreach ( self::all_slugs() as $slug ) {
			if ( 'none' === $slug ) {
				continue;
			}
			$registry[ $slug ] = array(
				'white' => self::icon_url( $slug, 'white' ),
				'black' => self::icon_url( $slug, 'black' ),
			);
		}
		return $registry;
	}

	/**
	 * Human label for slug.
	 *
	 * @param string $slug Icon slug.
	 * @return string
	 */
	public static function label_for_slug( $slug ) {
		foreach ( self::symbol_choices() as $choice ) {
			if ( ( $choice['value'] ?? '' ) === $slug ) {
				return $choice['label'] ?? $slug;
			}
		}
		if ( class_exists( 'PCKZ_Icon_Library' ) ) {
			$bundled = PCKZ_Icon_Library::bundled_manifest();
			if ( isset( $bundled[ $slug ] ) ) {
				return $bundled[ $slug ];
			}
		}
		if ( 'lines' === $slug ) {
			return 'Linien';
		}
		return ucfirst( $slug );
	}
}
