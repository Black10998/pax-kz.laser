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
	 * Critical runtime files participating in anti-tamper fingerprinting.
	 *
	 * @return array
	 */
	private static function integrity_targets() {
		$targets = array(
			'pckz-canonical-engine.php',
			'includes/class-pckz-licensing.php',
			'includes/class-pckz-security.php',
			'includes/class-pckz-ajax.php',
			'includes/class-pckz-asset-sync.php',
			'includes/class-pckz-export-engine.php',
			'includes/class-pckz-production-geometry.php',
			'includes/class-pckz-production-lbrn2.php',
			'public/js/preview-engine.js',
			'public/js/creator.js',
		);
		return apply_filters( 'pckzce_integrity_targets', $targets );
	}

	/**
	 * Build integrity fingerprint from critical runtime files.
	 *
	 * @return string
	 */
	public static function integrity_fingerprint() {
		$parts = array();
		foreach ( self::integrity_targets() as $relative ) {
			$path = PCKZCE_PLUGIN_DIR . $relative;
			if ( is_readable( $path ) ) {
				$parts[] = $relative . ':' . hash_file( 'sha256', $path );
			} else {
				$parts[] = $relative . ':missing';
			}
		}
		return hash( 'sha256', implode( '|', $parts ) );
	}

	/**
	 * Parse packaged release manifest from plugin root if present.
	 *
	 * @return array|null
	 */
	private static function release_manifest() {
		$manifest_path = trailingslashit( PCKZCE_PLUGIN_DIR ) . 'RELEASE_MANIFEST.json';
		if ( ! is_readable( $manifest_path ) ) {
			return null;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$raw = file_get_contents( $manifest_path );
		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			return null;
		}
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : null;
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
		$plugin_main = trailingslashit( PCKZCE_PLUGIN_DIR ) . 'pckz-canonical-engine.php';
		if ( is_writable( PCKZCE_PLUGIN_DIR ) ) {
			$signals[] = 'plugin_dir_writable';
		}
		if ( ! is_readable( $plugin_main ) ) {
			$signals[] = 'plugin_main_missing';
		}
		$missing_critical = false;
		foreach ( self::integrity_targets() as $relative ) {
			if ( ! is_readable( PCKZCE_PLUGIN_DIR . $relative ) ) {
				$missing_critical = true;
				break;
			}
		}
		if ( $missing_critical ) {
			$signals[] = 'critical_runtime_file_missing';
		}

		$manifest = self::release_manifest();
		if ( null === $manifest ) {
			$signals[] = 'release_manifest_unavailable';
		} elseif ( ! empty( $manifest['files'] ) && is_array( $manifest['files'] ) ) {
			$mismatch_found = false;
			foreach ( self::integrity_targets() as $relative ) {
				if ( empty( $manifest['files'][ $relative ] ) ) {
					continue;
				}
				$path = PCKZCE_PLUGIN_DIR . $relative;
				if ( ! is_readable( $path ) ) {
					$mismatch_found = true;
					break;
				}
				$actual = hash_file( 'sha256', $path );
				$expect = (string) $manifest['files'][ $relative ];
				if ( ! hash_equals( $expect, $actual ) ) {
					$mismatch_found = true;
					break;
				}
			}
			if ( $mismatch_found ) {
				$signals[] = 'release_manifest_mismatch';
			}
		} else {
			$signals[] = 'release_manifest_invalid';
		}

		return $signals;
	}
}
