<?php
/**
 * Security helper utilities (anti-tamper telemetry scaffolding).
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PCKZ_Security
 */
class PCKZ_Security {

	/**
	 * Build integrity fingerprint from critical runtime files.
	 *
	 * @return string
	 */
	public static function integrity_fingerprint() {
		$targets = array(
			'pckz-canonical-engine.php',
			'includes/class-pckz-ajax.php',
			'includes/class-pckz-production-geometry.php',
			'includes/class-pckz-production-lbrn2.php',
			'public/js/preview-engine.js',
			'public/js/creator.js',
		);
		$parts = array();
		foreach ( $targets as $relative ) {
			$path = PCKZCE_PLUGIN_DIR . $relative;
			if ( is_readable( $path ) ) {
				$parts[] = $relative . ':' . hash_file( 'sha256', $path );
			}
		}
		return hash( 'sha256', implode( '|', $parts ) );
	}

	/**
	 * Lightweight tamper signal list for telemetry dashboards.
	 *
	 * @return array
	 */
	public static function tamper_signals() {
		$signals = array();
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$signals[] = 'wp_debug_enabled';
		}
		if ( file_exists( WP_CONTENT_DIR . '/mu-plugins' ) ) {
			$signals[] = 'mu_plugins_present';
		}
		if ( ! function_exists( 'hash_hmac' ) ) {
			$signals[] = 'hash_hmac_missing';
		}
		return $signals;
	}
}
