<?php
/**
 * Font library: defaults, uploads, visibility, labels (CMS).
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PCKZ_Font_Library
 */
class PCKZ_Font_Library {

	const OPTION_DISABLED = 'pckz_font_disabled_ids';
	const OPTION_CUSTOM   = 'pckz_font_custom';
	const OPTION_LABELS   = 'pckz_font_labels';
	const UPLOAD_SUBDIR   = 'pckz-canonical-engine/fonts';

	/**
	 * Category labels for admin / UI.
	 *
	 * @return array<string,string>
	 */
	public static function categories() {
		return array(
			'luxury'    => __( 'Luxus', 'pckz-canonical-engine' ),
			'elegant'   => __( 'Elegant', 'pckz-canonical-engine' ),
			'premium'   => __( 'Premium', 'pckz-canonical-engine' ),
			'modern'    => __( 'Modern', 'pckz-canonical-engine' ),
			'classic'   => __( 'Klassisch', 'pckz-canonical-engine' ),
			'sport'     => __( 'Sport', 'pckz-canonical-engine' ),
			'technical' => __( 'Technisch', 'pckz-canonical-engine' ),
			'display'   => __( 'Display', 'pckz-canonical-engine' ),
			'custom'    => __( 'Eigene Uploads', 'pckz-canonical-engine' ),
		);
	}

	/**
	 * Built-in default fonts.
	 *
	 * @return array<string,array>
	 */
	public static function default_catalog() {
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}
		$file  = PCKZCE_PLUGIN_DIR . 'includes/font-library-defaults.php';
		$cache = is_readable( $file ) ? include $file : array();
		if ( ! is_array( $cache ) ) {
			$cache = array();
		}
		foreach ( $cache as $id => $row ) {
			$cache[ $id ]['id']       = $id;
			$cache[ $id ]['builtin']  = true;
			$cache[ $id ]['source']   = $row['source'] ?? 'google';
			$cache[ $id ]['category'] = $row['category'] ?? 'modern';
		}
		return $cache;
	}

	/**
	 * Custom uploaded fonts from options.
	 *
	 * @return array<string,array>
	 */
	public static function custom_catalog() {
		$raw = get_option( self::OPTION_CUSTOM, array() );
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out = array();
		foreach ( $raw as $id => $row ) {
			$id = sanitize_key( $id );
			if ( ! $id || ! is_array( $row ) ) {
				continue;
			}
			$file = $row['file'] ?? '';
			if ( ! $file || ! is_readable( self::upload_dir() . '/' . $file ) ) {
				continue;
			}
			$family = ! empty( $row['family'] ) ? sanitize_text_field( $row['family'] ) : $id;
			$out[ $id ] = array(
				'id'       => $id,
				'label'    => sanitize_text_field( $row['label'] ?? $family ),
				'category' => 'custom',
				'family'   => $family,
				'source'   => 'upload',
				'file'     => sanitize_file_name( $file ),
				'sample'   => sanitize_text_field( $row['sample'] ?? 'ABC 123' ),
				'builtin'  => false,
			);
		}
		return $out;
	}

	/**
	 * Label overrides.
	 *
	 * @return array<string,string>
	 */
	public static function label_overrides() {
		$raw = get_option( self::OPTION_LABELS, array() );
		return is_array( $raw ) ? $raw : array();
	}

	/**
	 * Disabled font IDs.
	 *
	 * @return string[]
	 */
	public static function disabled_ids() {
		$raw = get_option( self::OPTION_DISABLED, array() );
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out = array();
		foreach ( $raw as $id ) {
			$id = sanitize_key( $id );
			if ( $id ) {
				$out[] = $id;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Full merged catalog (defaults + custom + label overrides).
	 *
	 * @return array<string,array>
	 */
	public static function all_entries() {
		$entries = array_merge( self::default_catalog(), self::custom_catalog() );
		$labels  = self::label_overrides();
		foreach ( $entries as $id => $row ) {
			if ( ! empty( $labels[ $id ] ) ) {
				$entries[ $id ]['label'] = sanitize_text_field( $labels[ $id ] );
			}
		}
		return $entries;
	}

	/**
	 * @param string $id Font ID.
	 * @return bool
	 */
	public static function is_visible( $id ) {
		return ! in_array( sanitize_key( $id ), self::disabled_ids(), true );
	}

	/**
	 * Customer-facing fonts (legacy shape: family + label + meta).
	 *
	 * @return array<int,array>
	 */
	public static function get_customer_fonts() {
		$list = array();
		foreach ( self::all_entries() as $id => $row ) {
			if ( ! self::is_visible( $id ) ) {
				continue;
			}
			$list[] = array(
				'id'       => $id,
				'family'   => $row['family'] ?? '',
				'label'    => $row['label'] ?? $row['family'] ?? $id,
				'category' => $row['category'] ?? 'modern',
				'sample'   => $row['sample'] ?? 'Aa Bb 123',
				'source'   => $row['source'] ?? 'google',
			);
		}
		usort(
			$list,
			function ( $a, $b ) {
				$cat = strcmp( $a['category'] ?? '', $b['category'] ?? '' );
				return 0 !== $cat ? $cat : strcmp( $a['label'] ?? '', $b['label'] ?? '' );
			}
		);
		return $list;
	}

	/**
	 * Upload directory (creates if needed).
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
	 * Public URL base for uploaded fonts.
	 *
	 * @return string
	 */
	public static function upload_url_base() {
		$upload = wp_upload_dir();
		return trailingslashit( $upload['baseurl'] ) . self::UPLOAD_SUBDIR;
	}

	/**
	 * URL for uploaded font file.
	 *
	 * @param string $file Filename.
	 * @return string
	 */
	public static function font_file_url( $file ) {
		$file = sanitize_file_name( $file );
		if ( ! $file ) {
			return '';
		}
		return esc_url_raw( self::upload_url_base() . '/' . $file );
	}

	/**
	 * Build Google Fonts CSS URL for enabled google-source fonts.
	 *
	 * @return string
	 */
	public static function google_fonts_css_url() {
		$ids = array();
		foreach ( self::all_entries() as $id => $row ) {
			if ( ! self::is_visible( $id ) ) {
				continue;
			}
			if ( 'google' !== ( $row['source'] ?? '' ) || empty( $row['google_id'] ) ) {
				continue;
			}
			$ids[] = $row['google_id'];
		}
		$ids = array_values( array_unique( $ids ) );
		if ( empty( $ids ) ) {
			return '';
		}
		return 'https://fonts.googleapis.com/css2?family=' . implode( '&family=', $ids ) . '&display=swap';
	}

	/**
	 * Inline @font-face CSS for uploaded fonts.
	 *
	 * @return string
	 */
	public static function uploaded_fonts_css() {
		$css = '';
		foreach ( self::custom_catalog() as $id => $row ) {
			if ( ! self::is_visible( $id ) ) {
				continue;
			}
			$url    = self::font_file_url( $row['file'] ?? '' );
			$family = $row['family'] ?? $id;
			if ( ! $url ) {
				continue;
			}
			$format = 'woff2';
			$ext    = strtolower( pathinfo( $row['file'], PATHINFO_EXTENSION ) );
			if ( 'ttf' === $ext ) {
				$format = 'truetype';
			} elseif ( 'otf' === $ext ) {
				$format = 'opentype';
			} elseif ( 'woff' === $ext ) {
				$format = 'woff';
			}
			$css .= '@font-face{font-family:' . self::css_family( $family ) . ';src:url("' . esc_url( $url ) . '") format("' . $format . '");font-display:swap;}';
		}
		return $css;
	}

	/**
	 * @param string $family Font family.
	 * @return string
	 */
	private static function css_family( $family ) {
		return '"' . str_replace( '"', '\\"', $family ) . '"';
	}

	/**
	 * Font files map for opentype.js (lowercase family => url).
	 *
	 * @return array<string,string>
	 */
	public static function font_files_for_js() {
		$map = array();
		foreach ( self::custom_catalog() as $row ) {
			if ( empty( $row['file'] ) || empty( $row['family'] ) ) {
				continue;
			}
			$url = self::font_file_url( $row['file'] );
			if ( $url ) {
				$map[ strtolower( $row['family'] ) ] = $url;
			}
		}
		return $map;
	}

	/**
	 * Upload font file from $_FILES.
	 *
	 * @param array $file File array.
	 * @return array|WP_Error
	 */
	public static function handle_upload( $file ) {
		if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error( 'no_file', __( 'Keine Schriftdatei empfangen.', 'pckz-canonical-engine' ) );
		}
		$allowed = array(
			'woff2' => 'font/woff2',
			'woff'  => 'font/woff',
			'ttf'   => 'font/ttf',
			'otf'   => 'font/otf',
		);
		$check   = wp_check_filetype( $file['name'], $allowed );
		if ( empty( $check['ext'] ) ) {
			return new WP_Error( 'bad_type', __( 'Erlaubt: WOFF2, WOFF, TTF, OTF.', 'pckz-canonical-engine' ) );
		}
		$base     = sanitize_title( pathinfo( $file['name'], PATHINFO_FILENAME ) );
		$slug     = sanitize_key( $base );
		if ( ! $slug ) {
			$slug = 'font-' . wp_generate_password( 6, false, false );
		}
		$existing = self::all_entries();
		if ( isset( $existing[ $slug ] ) ) {
			$slug .= '-' . substr( wp_generate_uuid4(), 0, 6 );
		}
		$filename = $slug . '.' . $check['ext'];
		$dest     = self::upload_dir() . '/' . $filename;
		if ( ! move_uploaded_file( $file['tmp_name'], $dest ) ) {
			return new WP_Error( 'move_fail', __( 'Upload fehlgeschlagen.', 'pckz-canonical-engine' ) );
		}
		$label  = isset( $_POST['font_upload_label'] ) ? sanitize_text_field( wp_unslash( $_POST['font_upload_label'] ) ) : ucwords( str_replace( '-', ' ', $base ) );
		$family = isset( $_POST['font_upload_family'] ) ? sanitize_text_field( wp_unslash( $_POST['font_upload_family'] ) ) : $label;
		$custom = get_option( self::OPTION_CUSTOM, array() );
		if ( ! is_array( $custom ) ) {
			$custom = array();
		}
		$custom[ $slug ] = array(
			'label'  => $label,
			'family' => $family,
			'file'   => $filename,
			'sample' => 'ABC 123',
		);
		update_option( self::OPTION_CUSTOM, $custom );
		return array( 'id' => $slug, 'slug' => $slug );
	}

	/**
	 * Delete custom uploaded font.
	 *
	 * @param string $id Font ID.
	 * @return bool|WP_Error
	 */
	public static function delete_custom( $id ) {
		$id = sanitize_key( $id );
		$custom = get_option( self::OPTION_CUSTOM, array() );
		if ( ! is_array( $custom ) || empty( $custom[ $id ] ) ) {
			return new WP_Error( 'not_custom', __( 'Nur hochgeladene Schriften können gelöscht werden.', 'pckz-canonical-engine' ) );
		}
		$file = $custom[ $id ]['file'] ?? '';
		unset( $custom[ $id ] );
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
		return true;
	}

	/**
	 * Save admin form: disabled list + labels.
	 *
	 * @param array $enabled_ids Enabled font IDs.
	 * @param array $labels      ID => label.
	 */
	public static function save_admin_state( $enabled_ids, $labels ) {
		$all      = array_keys( self::all_entries() );
		$enabled  = array_map( 'sanitize_key', (array) $enabled_ids );
		$disabled = array();
		foreach ( $all as $id ) {
			if ( ! in_array( $id, $enabled, true ) ) {
				$disabled[] = $id;
			}
		}
		update_option( self::OPTION_DISABLED, $disabled );
		$clean_labels = array();
		if ( is_array( $labels ) ) {
			foreach ( $labels as $id => $label ) {
				$id = sanitize_key( $id );
				$label = sanitize_text_field( $label );
				if ( $id && $label ) {
					$clean_labels[ $id ] = $label;
				}
			}
		}
		update_option( self::OPTION_LABELS, $clean_labels );
	}
}
