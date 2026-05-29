<?php
/**
 * Official PAXDesign developer branding for admin and plugin metadata.
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PCKZ_Branding
 */
class PCKZ_Branding {

	public const AUTHOR_NAME     = 'PAXDesign';
	public const DEVELOPER_LABEL = 'PAXDesign – Ahmad Alkhalaf';
	public const WEBSITE_URL     = 'https://paxdesign.at';
	public const SUPPORT_EMAIL   = 'info@paxdesign.at';

	/**
	 * Plugin version string for admin display.
	 *
	 * @return string
	 */
	public static function version_label() {
		$version = defined( 'PCKZCE_VERSION' ) ? PCKZCE_VERSION : '';
		$build   = defined( 'PCKZCE_BUILD' ) ? PCKZCE_BUILD : '';

		if ( $version && $build && strpos( $build, $version ) === 0 ) {
			return $version . ' (' . $build . ')';
		}

		return $version ?: $build;
	}

	/**
	 * Render compact branding block (settings sidebar / dashboard).
	 *
	 * @param bool $show_version Show installed version.
	 */
	public static function render_settings_panel( $show_version = true ) {
		include PCKZCE_PLUGIN_DIR . 'admin/views/partials/developer-branding.php';
	}

	/**
	 * Render full "Über den Entwickler" admin page.
	 */
	public static function render_about_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		include PCKZCE_PLUGIN_DIR . 'admin/views/about-developer.php';
	}
}
