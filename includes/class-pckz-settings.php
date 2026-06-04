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
			'price_show_enabled'  => true,
			'price_base'          => 0,
			'price_setup_fee'     => 0,
			'price_currency_code' => 'EUR',
			'price_currency_symbol' => '€',
			'price_default_currency' => 'EUR',
			'price_currencies_enabled' => array( 'EUR' ),
			'price_allow_currency_switch' => false,
			'price_by_currency'   => array(),
			'checkout_notice_enabled' => true,
			'checkout_notice_message' => self::default_checkout_notice_message(),
			'paypal_enabled'      => false,
			'paypal_test_mode'    => true,
			'paypal_sandbox_client_id' => '',
			'paypal_sandbox_secret'    => '',
			'paypal_live_client_id'    => '',
			'paypal_live_secret'       => '',
			'paypal_success_url'  => '',
			'paypal_cancel_url'   => '',
			'creator_page_id'     => 0,
			'licensing_master_mode' => false,
			'licensing_master_url'  => 'https://paxdesign.at',
			'licensing_key'         => '',
			'licensing_install_uuid' => '',
			'licensing_enforce'     => false,
			'licensing_grace_minutes' => 120,
			'licensing_require_signed_requests' => true,
			'licensing_export_authorize' => false,
			'licensing_export_remote_mode' => false,
			'licensing_export_remote_strict' => false,
			'licensing_strict_integrity' => false,
			'licensing_master_api_key' => '',
			'security_prefer_protected_assets' => false,
			'security_prefer_minified_js' => false,
			'payments_primary_provider' => 'paypal',
			'payments_enable_stripe' => false,
			'payments_stripe_test_mode' => true,
			'payments_stripe_publishable_key' => '',
			'payments_stripe_secret_key' => '',
			'payments_stripe_webhook_secret' => '',
			'payments_stripe_success_url' => '',
			'payments_stripe_cancel_url' => '',
			'payments_stripe_webhook_tolerance' => 300,
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
		if ( class_exists( 'PCKZ_Font_Library' ) ) {
			$from_library = PCKZ_Font_Library::get_customer_fonts();
			if ( ! empty( $from_library ) ) {
				return $from_library;
			}
		}
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
	/**
	 * Default customer reassurance message (checkout).
	 *
	 * @return string
	 */
	public static function default_checkout_notice_message() {
		return '<p><strong>Keine Sorge</strong> – Ihr Entwurf wird nach der Bestellung zusätzlich von unserem Team geprüft und für die bestmögliche Produktionsqualität optimiert. Das finale Ergebnis entspricht der Vorschau und wird für eine optimale Laserproduktion professionell vorbereitet.</p>';
	}

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
