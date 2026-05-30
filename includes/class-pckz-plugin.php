<?php
/**
 * Main plugin bootstrap.
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PCKZ_Plugin
 */
class PCKZ_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var PCKZ_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get singleton.
	 *
	 * @return PCKZ_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->load_textdomain();
		$this->init_components();
	}

	/**
	 * Load translations.
	 */
	private function load_textdomain() {
		load_plugin_textdomain(
			'pckz-canonical-engine',
			false,
			dirname( PCKZCE_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Initialize plugin components.
	 */
	private function init_components() {
		add_action( 'init', array( 'PCKZ_Activator', 'maybe_upgrade' ), 5 );
		new PCKZ_Post_Type();
		new PCKZ_Admin();
		new PCKZ_Public();
		new PCKZ_Ajax();
		new PCKZ_Shortcode();
		new PCKZ_Order_Tracking();

		if ( class_exists( 'WooCommerce' ) ) {
			new PCKZ_WooCommerce();
		}
	}
}
