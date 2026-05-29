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
		flush_rewrite_rules();
	}
}
