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
		if ( class_exists( 'PCKZ_Licensing' ) ) {
			PCKZ_Licensing::report_plugin_deactivated();
		}
		wp_clear_scheduled_hook( PCKZ_Licensing::HEARTBEAT_HOOK );
		wp_clear_scheduled_hook( PCKZ_Licensing::UPDATE_POLL_HOOK );
		wp_clear_scheduled_hook( 'pckz_shipment_tracking_sync' );
		flush_rewrite_rules();
	}
}
