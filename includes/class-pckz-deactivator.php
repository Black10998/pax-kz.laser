<?php
/**
 * Deactivation routines.
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PCKZ_Deactivator
 */
class PCKZ_Deactivator {

	/**
	 * Run on plugin deactivation.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( PCKZ_Licensing::HEARTBEAT_HOOK );
		flush_rewrite_rules();
	}
}
