<?php
/**
 * Plugin Name:       PCKZ Canonical Engine
 * Plugin URI:        https://paxdesign.at
 * Description:       Canonical preview-to-LightBurn export engine with server-side SVG/LBRN2 generation and mm-accurate parity validation.
 * Version:           2.27.2
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            PAXDesign
 * Author URI:        https://paxdesign.at
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pckz-canonical-engine
 * Domain Path:       /languages
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

define( 'PCKZCE_VERSION', '2.27.2' );
define( 'PCKZCE_BUILD', '2.27.2.20260531-icon-library-fix' );
define( 'PCKZCE_PLUGIN_FILE', __FILE__ );
define( 'PCKZCE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PCKZCE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PCKZCE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Legacy aliases — prevents fatals if an old copy is referenced; do not use in new code.
if ( ! defined( 'PCKZ_PLUGIN_DIR' ) ) {
	define( 'PCKZ_PLUGIN_DIR', PCKZCE_PLUGIN_DIR );
	define( 'PCKZ_PLUGIN_URL', PCKZCE_PLUGIN_URL );
	define( 'PCKZ_PLUGIN_FILE', PCKZCE_PLUGIN_FILE );
	define( 'PCKZ_PLUGIN_BASENAME', PCKZCE_PLUGIN_BASENAME );
	define( 'PCKZ_VERSION', PCKZCE_VERSION );
}

require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-autoloader.php';
PCKZ_Autoloader::register();

/**
 * Returns the main plugin instance.
 *
 * @return PCKZ_Plugin
 */
function pckzce() {
	return PCKZ_Plugin::instance();
}

/**
 * Back-compat wrapper.
 *
 * @return PCKZ_Plugin
 */
function pckz() {
	return pckzce();
}

/**
 * Plugin activation.
 */
function pckzce_activate() {
	PCKZ_Activator::activate();
}
register_activation_hook( __FILE__, 'pckzce_activate' );

/**
 * Plugin deactivation.
 */
function pckzce_deactivate() {
	PCKZ_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'pckzce_deactivate' );

pckzce();
