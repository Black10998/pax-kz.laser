<?php
/**
 * Bundled premium icon assets, custom uploads, and admin visibility controls.
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PCKZ_Icon_Library
 */
class PCKZ_Icon_Library {

	const OPTION_DISABLED = 'pckz_icon_disabled_slugs';
	const OPTION_CUSTOM   = 'pckz_icon_custom';
	const OPTION_LABELS   = 'pckz_icon_labels';
	const BUNDLED_SUBDIR  = 'public/images/icons/bundled/';
	const UPLOAD_SUBDIR   = 'pckz-canonical-engine/icons';

	/**
	 * Bundled icon slugs => labels (manifest).
	 *
	 * @return array<string,string>
	 */
	public static function bundled_manifest() {
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}
		$file  = PCKZCE_PLUGIN_DIR . 'includes/bundled-premium-icons.php';
		$cache = is_readable( $file ) ? include $file : array();
		if ( ! is_array( $cache ) ) {
			$cache = array();
		}
		return $cache;
	}

	/**
	 * Custom uploaded icons.
	 *
	 * @return array<string,array{label:string,file:string}>
	 */
	public static function custom_manifest() {
		$raw = get_option( self::OPTION_CUSTOM, array() );
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out = array();
		foreach ( $raw as $slug => $row ) {
			$slug = sanitize_key( $slug );
			if ( ! $slug || ! is_array( $row ) || empty( $row['file'] ) ) {
				continue;
			}
			if ( ! is_readable( self::upload_dir() . '/' . sanitize_file_name( $row['file'] ) ) ) {
				continue;
			}
			$out[ $slug ] = array(
				'label' => sanitize_text_field( $row['label'] ?? $slug ),
				'file'  => sanitize_file_name( $row['file'] ),
			);
		}
		return $out;
	}

	/**
	 * Label overrides for any slug.
	 *
	 * @return array<string,string>
	 */
	public static function label_overrides() {
		$raw = get_option( self::OPTION_LABELS, array() );
		return is_array( $raw ) ? $raw : array();
	}

	/**
	 * Display label for slug.
	 *
	 * @param string $slug Icon slug.
	 * @param string $fallback Fallback.
	 * @return string
	 */
	public static function label_for_slug( $slug, $fallback = '' ) {
		$overrides = self::label_overrides();
		if ( ! empty( $overrides[ $slug ] ) ) {
			return $overrides[ $slug ];
		}
		$custom = self::custom_manifest();
		if ( isset( $custom[ $slug ]['label'] ) ) {
			return $custom[ $slug ]['label'];
		}
		$bundled = self::bundled_manifest();
		if ( isset( $bundled[ $slug ] ) ) {
			return $bundled[ $slug ];
		}
		return $fallback ?: $slug;
	}

	/**
	 * Whether slug is a bundled premium icon.
	 *
	 * @param string $slug Icon slug.
	 * @return bool
	 */
	public static function is_bundled( $slug ) {
		return isset( self::bundled_manifest()[ $slug ] );
	}

	/**
	 * Whether slug is custom upload.
	 *
	 * @param string $slug Icon slug.
	 * @return bool
	 */
	public static function is_custom( $slug ) {
		return isset( self::custom_manifest()[ $slug ] );
	}

	/**
	 * Upload directory.
	 *
	 * @return string
	 */
	public static function upload_dir() {
		$upload = wp_upload_dir();
		$dir    = trailingslashit( $upload['basedir'] ) . self::UPLOAD_SUBDIR;
		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		return $dir;
	}

	/**
	 * Upload base URL.
	 *
	 * @return string
	 */
	public static function upload_url_base() {
		$upload = wp_upload_dir();
		return trailingslashit( $upload['baseurl'] ) . self::UPLOAD_SUBDIR;
	}

	/**
	 * Absolute path to bundled SVG (preserved artwork).
	 *
	 * @param string $slug Icon slug.
	 * @return string
	 */
	public static function bundled_file_path( $slug ) {
		$slug = sanitize_key( $slug );
		if ( ! self::is_bundled( $slug ) ) {
			return '';
		}
		$path = PCKZCE_PLUGIN_DIR . self::BUNDLED_SUBDIR . $slug . '.svg';
		return is_readable( $path ) ? $path : '';
	}

	/**
	 * Public URL for icon SVG (custom, then bundled).
	 *
	 * @param string $slug Icon slug.
	 * @return string
	 */
	public static function icon_url( $slug ) {
		$custom = self::custom_url( $slug );
		if ( $custom ) {
			return $custom;
		}
		return self::bundled_url( $slug );
	}

	/**
	 * @param string $slug Slug.
	 * @return string
	 */
	public static function custom_url( $slug ) {
		$slug   = sanitize_key( $slug );
		$custom = self::custom_manifest();
		if ( empty( $custom[ $slug ]['file'] ) ) {
			return '';
		}
		return esc_url_raw( self::upload_url_base() . '/' . $custom[ $slug ]['file'] );
	}

	/**
	 * Public URL for bundled SVG.
	 *
	 * @param string $slug Icon slug.
	 * @return string
	 */
	public static function bundled_url( $slug ) {
		if ( ! self::bundled_file_path( $slug ) ) {
			return '';
		}
		return esc_url_raw( PCKZCE_PLUGIN_URL . self::BUNDLED_SUBDIR . sanitize_key( $slug ) . '.svg' );
	}

	/**
	 * All known slugs (bundled + custom).
	 *
	 * @return string[]
	 */
	public static function all_slugs() {
		return array_values(
			array_unique(
				array_merge(
					array_keys( self::bundled_manifest() ),
					array_keys( self::custom_manifest() )
				)
			)
		);
	}

	/**
	 * Slugs disabled in admin (hidden from customer selector).
	 *
	 * @return string[]
	 */
	public static function disabled_slugs() {
		$raw = get_option( self::OPTION_DISABLED, array() );
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out = array();
		foreach ( $raw as $slug ) {
			$s = sanitize_key( $slug );
			if ( $s && 'none' !== $s ) {
				$out[] = $s;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Whether icon appears in customer-facing catalog/selector.
	 *
	 * @param string $slug Icon slug.
	 * @return bool
	 */
	public static function is_visible( $slug ) {
		if ( 'none' === $slug || '' === $slug ) {
			return true;
		}
		return ! in_array( sanitize_key( $slug ), self::disabled_slugs(), true );
	}

	/**
	 * Filter catalog entries for customer UI.
	 *
	 * @param array<string,array> $items Icon catalog.
	 * @return array<string,array>
	 */
	public static function filter_visible_catalog( $items ) {
		foreach ( $items as $slug => $data ) {
			if ( ! self::is_visible( $slug ) ) {
				unset( $items[ $slug ] );
			}
		}
		return $items;
	}

	/**
	 * All catalog slugs for admin (unfiltered).
	 *
	 * @return array<string,array>
	 */
	public static function admin_catalog_entries() {
		if ( ! class_exists( 'PCKZ_Ledos_Preview' ) ) {
			return array();
		}
		return PCKZ_Ledos_Preview::icon_catalog( false );
	}

	/**
	 * Handle SVG upload.
	 *
	 * @param array $file $_FILES row.
	 * @return array|WP_Error
	 */
	public static function handle_upload( $file ) {
		if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error( 'no_file', __( 'Keine SVG-Datei empfangen.', 'pckz-canonical-engine' ) );
		}
		$check = wp_check_filetype( $file['name'], array( 'svg' => 'image/svg+xml' ) );
		if ( empty( $check['ext'] ) || 'svg' !== $check['ext'] ) {
			return new WP_Error( 'bad_type', __( 'Nur SVG-Dateien sind erlaubt.', 'pckz-canonical-engine' ) );
		}
		$contents = file_get_contents( $file['tmp_name'] );
		if ( false === $contents || ! self::is_safe_svg( $contents ) ) {
			return new WP_Error( 'unsafe_svg', __( 'SVG enthält nicht erlaubte Inhalte.', 'pckz-canonical-engine' ) );
		}
		$base = sanitize_title( pathinfo( $file['name'], PATHINFO_FILENAME ) );
		$slug = sanitize_key( $base );
		if ( ! $slug ) {
			$slug = 'icon-' . substr( wp_generate_uuid4(), 0, 8 );
		}
		if ( self::is_bundled( $slug ) || self::is_custom( $slug ) ) {
			$slug .= '-' . substr( wp_generate_uuid4(), 0, 6 );
		}
		$filename = $slug . '.svg';
		$dest     = self::upload_dir() . '/' . $filename;
		if ( ! file_put_contents( $dest, $contents ) ) {
			return new WP_Error( 'write_fail', __( 'SVG konnte nicht gespeichert werden.', 'pckz-canonical-engine' ) );
		}
		$label  = isset( $_POST['icon_upload_label'] ) ? sanitize_text_field( wp_unslash( $_POST['icon_upload_label'] ) ) : ucwords( str_replace( '-', ' ', $base ) );
		$custom = get_option( self::OPTION_CUSTOM, array() );
		if ( ! is_array( $custom ) ) {
			$custom = array();
		}
		$custom[ $slug ] = array(
			'label' => $label,
			'file'  => $filename,
		);
		update_option( self::OPTION_CUSTOM, $custom );
		return array( 'slug' => $slug );
	}

	/**
	 * Basic SVG safety check.
	 *
	 * @param string $svg SVG source.
	 * @return bool
	 */
	public static function is_safe_svg( $svg ) {
		if ( stripos( $svg, '<script' ) !== false || stripos( $svg, '<?php' ) !== false ) {
			return false;
		}
		return (bool) preg_match( '/<svg[\s>]/i', $svg );
	}

	/**
	 * Delete custom icon.
	 *
	 * @param string $slug Slug.
	 * @return bool|WP_Error
	 */
	public static function delete_custom( $slug ) {
		$slug   = sanitize_key( $slug );
		$custom = get_option( self::OPTION_CUSTOM, array() );
		if ( ! is_array( $custom ) || empty( $custom[ $slug ] ) ) {
			return new WP_Error( 'not_custom', __( 'Nur hochgeladene Icons können gelöscht werden.', 'pckz-canonical-engine' ) );
		}
		$file = $custom[ $slug ]['file'] ?? '';
		unset( $custom[ $slug ] );
		update_option( self::OPTION_CUSTOM, $custom );
		if ( $file ) {
			$path = self::upload_dir() . '/' . sanitize_file_name( $file );
			if ( is_readable( $path ) ) {
				if ( function_exists( 'wp_delete_file' ) ) {
					wp_delete_file( $path );
				} else {
					unlink( $path );
				}
			}
		}
		$labels = self::label_overrides();
		if ( isset( $labels[ $slug ] ) ) {
			unset( $labels[ $slug ] );
			update_option( self::OPTION_LABELS, $labels );
		}
		return true;
	}

	/**
	 * Save visibility + labels from admin.
	 *
	 * @param array $enabled_slugs Enabled slugs.
	 * @param array $labels        Slug => label.
	 */
	public static function save_admin_state( $enabled_slugs, $labels ) {
		$all      = array_keys( self::admin_catalog_entries() );
		$enabled  = array_map( 'sanitize_key', (array) $enabled_slugs );
		$disabled = array();
		foreach ( $all as $slug ) {
			if ( 'none' === $slug ) {
				continue;
			}
			if ( ! in_array( $slug, $enabled, true ) ) {
				$disabled[] = $slug;
			}
		}
		update_option( self::OPTION_DISABLED, $disabled );
		$clean = array();
		if ( is_array( $labels ) ) {
			foreach ( $labels as $slug => $label ) {
				$slug  = sanitize_key( $slug );
				$label = sanitize_text_field( $label );
				if ( $slug && 'none' !== $slug && $label ) {
					$clean[ $slug ] = $label;
				}
			}
		}
		update_option( self::OPTION_LABELS, $clean );
	}
}
