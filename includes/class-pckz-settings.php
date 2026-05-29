<?php
/**
 * Plugin settings API.
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PCKZ_Settings
 */
class PCKZ_Settings {

	const OPTION_KEY = 'pckz_settings';

	/**
	 * Default plugin options.
	 *
	 * @return array
	 */
	public static function default_options() {
		return array(
			'primary_color'       => '#334fb4',
			'accent_color'        => '#242833',
			'ui_theme'            => 'light',
			'default_dpi'         => 300,
			'default_origin'      => 'top-left',
			'fabric_cdn'          => 'https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js',
			'google_fonts_url'    => 'https://fonts.googleapis.com/css2?family=Russo+One&family=Ubuntu:wght@400;700&family=Roboto:wght@400;700&family=Oswald:wght@400;700&family=Montserrat:wght@400;700&family=Bebas+Neue&family=Anton&family=Orbitron:wght@400;700&family=Rajdhani:wght@500;700&family=Exo+2:wght@400;700&family=Audiowide&family=Black+Ops+One&display=swap',
			'fonts'               => array(
				array( 'family' => 'Russo One', 'label' => 'Russo One' ),
				array( 'family' => 'Bebas Neue', 'label' => 'Bebas Neue' ),
				array( 'family' => 'Anton', 'label' => 'Anton' ),
				array( 'family' => 'Black Ops One', 'label' => 'Black Ops One' ),
				array( 'family' => 'Orbitron', 'label' => 'Orbitron' ),
				array( 'family' => 'Audiowide', 'label' => 'Audiowide' ),
				array( 'family' => 'Rajdhani', 'label' => 'Rajdhani' ),
				array( 'family' => 'Exo 2', 'label' => 'Exo 2' ),
				array( 'family' => 'Oswald', 'label' => 'Oswald' ),
				array( 'family' => 'Montserrat', 'label' => 'Montserrat' ),
				array( 'family' => 'Roboto', 'label' => 'Roboto' ),
				array( 'family' => 'Ubuntu', 'label' => 'Ubuntu' ),
				array( 'family' => 'Arial', 'label' => 'Arial' ),
				array( 'family' => 'Helvetica', 'label' => 'Helvetica' ),
			),
			'color_palette'       => array(
				'#FFFFFF', '#F5F5F5', '#E0E0E0', '#CCCCCC', '#999999',
				'#666666', '#333333', '#000000',
			),
			'monochrome_ui'       => true,
			'enable_woocommerce'  => true,
			'cart_meta_key'       => '_pckz_design',
			'require_design'      => true,
			'admin_email_notify'  => false,
			'max_designs_per_user'=> 50,
			'default_creator_product_id' => 0,
			'enable_dxf_export'   => false,
		);
	}

	/**
	 * Get all settings merged with defaults.
	 *
	 * @return array
	 */
	public static function get_all() {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return wp_parse_args( $stored, self::default_options() );
	}

	/**
	 * Get single setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$all = self::get_all();
		return $all[ $key ] ?? $default;
	}

	/**
	 * Update settings.
	 *
	 * @param array $options Options to save.
	 */
	public static function update( $options ) {
		$current = self::get_all();
		$merged  = wp_parse_args( $options, $current );
		update_option( self::OPTION_KEY, $merged );
	}

	/**
	 * Get font list for creator UI.
	 *
	 * @return array
	 */
	public static function get_fonts() {
		return self::get( 'fonts', self::default_options()['fonts'] );
	}

	/**
	 * Get color palette.
	 *
	 * @return array
	 */
	public static function get_color_palette() {
		return self::get_gray_palette();
	}

	/**
	 * Grayscale palette for customer UI (black & white only).
	 *
	 * @return array
	 */
	public static function get_gray_palette() {
		return array(
			'#FFFFFF',
			'#EEEEEE',
			'#CCCCCC',
			'#999999',
			'#666666',
			'#333333',
			'#000000',
		);
	}
}
