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
	 * Safe option getter for CLI smoke environments.
	 *
	 * @param string $key Option key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	private static function option_get( $key, $default = null ) {
		if ( function_exists( 'get_option' ) ) {
			return get_option( $key, $default );
		}
		return $default;
	}

	/**
	 * Safe option setter for CLI smoke environments.
	 *
	 * @param string $key   Option key.
	 * @param mixed  $value Option value.
	 */
	private static function option_update( $key, $value ) {
		if ( function_exists( 'update_option' ) ) {
			update_option( $key, $value );
		}
	}

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
	 * @return array<string,array{label:string,file:string,customer_visible:bool}>
	 */
	public static function custom_manifest() {
		$raw = self::option_get( self::OPTION_CUSTOM, array() );
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$disabled = self::disabled_slugs();
		$out      = array();
		foreach ( $raw as $slug => $row ) {
			$slug = sanitize_key( $slug );
			if ( ! $slug || ! is_array( $row ) || empty( $row['file'] ) ) {
				continue;
			}
			if ( ! is_readable( self::upload_dir() . '/' . sanitize_file_name( $row['file'] ) ) ) {
				continue;
			}
			if ( array_key_exists( 'customer_visible', $row ) ) {
				$visible = (bool) $row['customer_visible'];
			} else {
				$visible = ! in_array( $slug, $disabled, true );
			}
			$out[ $slug ] = array(
				'label'            => sanitize_text_field( $row['label'] ?? $slug ),
				'file'             => sanitize_file_name( $row['file'] ),
				'customer_visible' => $visible,
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
		$raw = self::option_get( self::OPTION_LABELS, array() );
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
	 * Color handling mode for a custom icon slug.
	 *
	 * @param string $slug Icon slug.
	 * @return string preserve|tintable
	 */
	public static function color_mode_for_slug( $slug ) {
		$raw = self::option_get( self::OPTION_CUSTOM, array() );
		$slug = sanitize_key( $slug );
		if ( is_array( $raw ) && ! empty( $raw[ $slug ]['color_mode'] ) ) {
			$mode = sanitize_key( (string) $raw[ $slug ]['color_mode'] );
			if ( in_array( $mode, array( 'preserve', 'tintable' ), true ) ) {
				return $mode;
			}
		}
		if ( is_array( $raw ) && ! empty( $raw[ $slug ]['file'] ) ) {
			$path = self::upload_dir() . '/' . sanitize_file_name( $raw[ $slug ]['file'] );
			if ( is_readable( $path ) ) {
				$svg = file_get_contents( $path );
				if ( is_string( $svg ) && class_exists( 'PCKZ_Svg_Library' ) ) {
					return PCKZ_Svg_Library::icon_color_mode_for_svg( $svg );
				}
			}
		}
		return 'tintable';
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
		$raw = self::option_get( self::OPTION_DISABLED, array() );
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
		$slug = sanitize_key( $slug );
		if ( self::is_custom( $slug ) ) {
			$custom = self::custom_manifest();
			if ( isset( $custom[ $slug ] ) ) {
				return ! empty( $custom[ $slug ]['customer_visible'] );
			}
			return true;
		}
		return ! in_array( $slug, self::disabled_slugs(), true );
	}

	/**
	 * Build compact admin save payload from the current catalog state.
	 *
	 * @return array{icons:array<string,array{enabled:bool,label:string}>}
	 */
	public static function build_admin_save_payload() {
		$icons = array();
		foreach ( self::admin_catalog_entries() as $slug => $data ) {
			if ( 'none' === $slug ) {
				continue;
			}
			$icons[ $slug ] = array(
				'enabled' => self::is_visible( $slug ),
				'label'   => (string) ( $data['label'] ?? self::label_for_slug( $slug, $slug ) ),
			);
		}
		return array( 'icons' => $icons );
	}

	/**
	 * Customer-facing icon picker choices (live catalog, not stored product snapshot).
	 *
	 * @return array<int,array{value:string,label:string,img:string}>
	 */
	public static function get_customer_icon_choices() {
		if ( ! class_exists( 'PCKZ_Ledos_Preview' ) ) {
			return array();
		}
		$choices = array();
		foreach ( PCKZ_Ledos_Preview::icon_catalog( true ) as $slug => $data ) {
			$thumb = ! empty( $data['preview'] ) ? $data['preview'] : ( $data['url'] ?? '' );
			$choices[] = array(
				'value' => $slug,
				'label' => $data['label'] ?? ( class_exists( 'PCKZ_Icons' ) ? PCKZ_Icons::label_for_slug( $slug ) : $slug ),
				'img'   => $thumb ? esc_url_raw( $thumb ) : '',
			);
		}
		return $choices;
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
		$label = isset( $_POST['icon_upload_label'] ) ? sanitize_text_field( wp_unslash( $_POST['icon_upload_label'] ) ) : ucwords( str_replace( '-', ' ', $base ) );
		return self::store_custom_svg( $contents, $slug, $label, 'upload' );
	}

	/**
	 * Import SVG from a remote URL and store locally.
	 *
	 * @param string $url   Remote SVG URL.
	 * @param string $label Optional display label.
	 * @return array|WP_Error
	 */
	public static function handle_url_import( $url, $label = '' ) {
		$contents = PCKZ_Svg_Library::fetch_from_url( $url );
		if ( is_wp_error( $contents ) ) {
			return $contents;
		}
		if ( ! self::is_safe_svg( $contents ) ) {
			return new WP_Error( 'unsafe_svg', __( 'SVG enthält nicht erlaubte Inhalte.', 'pckz-canonical-engine' ) );
		}
		$base = PCKZ_Svg_Library::basename_from_url( $url );
		$slug = sanitize_key( $base );
		if ( ! $slug ) {
			$slug = 'icon-' . substr( wp_generate_uuid4(), 0, 8 );
		}
		if ( self::is_bundled( $slug ) || self::is_custom( $slug ) ) {
			$slug .= '-' . substr( wp_generate_uuid4(), 0, 6 );
		}
		if ( '' === $label ) {
			$label = ucwords( str_replace( '-', ' ', $base ) );
			if ( '' === trim( $label ) ) {
				$label = $slug;
			}
		} else {
			$label = sanitize_text_field( $label );
		}
		return self::store_custom_svg( $contents, $slug, $label, 'url' );
	}

	/**
	 * Persist validated custom SVG and register manifest entry.
	 *
	 * @param string $contents SVG source.
	 * @param string $slug     Unique slug.
	 * @param string $label    Display label.
	 * @param string $source   upload|url.
	 * @return array|WP_Error
	 */
	private static function store_custom_svg( $contents, $slug, $label, $source = 'upload' ) {
		$slug     = sanitize_key( $slug );
		$filename = $slug . '.svg';
		$dest     = self::upload_dir() . '/' . $filename;
		if ( ! file_put_contents( $dest, $contents ) ) {
			return new WP_Error( 'write_fail', __( 'SVG konnte nicht gespeichert werden.', 'pckz-canonical-engine' ) );
		}
		$custom = self::option_get( self::OPTION_CUSTOM, array() );
		if ( ! is_array( $custom ) ) {
			$custom = array();
		}
		$custom[ $slug ] = array(
			'label'            => sanitize_text_field( $label ),
			'file'             => $filename,
			'customer_visible' => true,
			'source'           => in_array( $source, array( 'upload', 'url' ), true ) ? $source : 'upload',
			'color_mode'       => PCKZ_Svg_Library::icon_color_mode_for_svg( $contents ),
		);
		self::option_update( self::OPTION_CUSTOM, $custom );

		$disabled = array_values(
			array_diff( self::disabled_slugs(), array( $slug ) )
		);
		self::option_update( self::OPTION_DISABLED, $disabled );

		do_action( 'pckzce_asset_catalog_changed' );

		return array( 'slug' => $slug );
	}

	/**
	 * Basic SVG safety check.
	 *
	 * @param string $svg SVG source.
	 * @return bool
	 */
	public static function is_safe_svg( $svg ) {
		return class_exists( 'PCKZ_Svg_Library' )
			? PCKZ_Svg_Library::is_safe_svg( $svg )
			: ( ( stripos( $svg, '<script' ) === false && stripos( $svg, '<?php' ) === false ) && (bool) preg_match( '/<svg[\s>]/i', $svg ) );
	}

	/**
	 * Delete custom icon.
	 *
	 * @param string $slug Slug.
	 * @return bool|WP_Error
	 */
	public static function delete_custom( $slug ) {
		$slug   = sanitize_key( $slug );
		$custom = self::option_get( self::OPTION_CUSTOM, array() );
		if ( ! is_array( $custom ) || empty( $custom[ $slug ] ) ) {
			return new WP_Error( 'not_custom', __( 'Nur hochgeladene Icons können gelöscht werden.', 'pckz-canonical-engine' ) );
		}
		$file = $custom[ $slug ]['file'] ?? '';
		unset( $custom[ $slug ] );
		self::option_update( self::OPTION_CUSTOM, $custom );
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
			self::option_update( self::OPTION_LABELS, $labels );
		}
		return true;
	}

	/**
	 * Delete multiple custom icons (built-in items are skipped).
	 *
	 * @param array $slugs Slugs to delete.
	 * @return array{deleted:int,failed:int,skipped:int}|WP_Error
	 */
	public static function delete_custom_bulk( $slugs ) {
		if ( ! is_array( $slugs ) || empty( $slugs ) ) {
			return new WP_Error( 'bulk_empty', __( 'Keine Icons zum Löschen ausgewählt.', 'pckz-canonical-engine' ) );
		}
		$deleted = 0;
		$failed  = 0;
		$skipped = 0;
		foreach ( $slugs as $slug ) {
			$slug = sanitize_key( (string) $slug );
			if ( ! $slug ) {
				continue;
			}
			if ( ! self::is_custom( $slug ) ) {
				++$skipped;
				continue;
			}
			$result = self::delete_custom( $slug );
			if ( is_wp_error( $result ) ) {
				++$failed;
			} else {
				++$deleted;
			}
		}
		if ( 0 === $deleted && 0 === $failed ) {
			return new WP_Error( 'bulk_none', __( 'Keine benutzerdefinierten Icons konnten gelöscht werden.', 'pckz-canonical-engine' ) );
		}
		return array(
			'deleted' => $deleted,
			'failed'  => $failed,
			'skipped' => $skipped,
		);
	}

	/**
	 * Parse compact JSON payload from icon library save form.
	 *
	 * Large inventories exceed PHP max_input_vars when each icon is a separate POST
	 * field; the admin UI submits one JSON blob instead.
	 *
	 * @param mixed $payload Decoded or raw payload.
	 * @return array{enabled:string[],labels:array<string,string>}|null
	 */
	public static function parse_admin_save_payload( $payload ) {
		if ( is_string( $payload ) ) {
			$payload = trim( $payload );
			if ( '' === $payload ) {
				return null;
			}
			$payload = json_decode( wp_unslash( $payload ), true );
			if ( JSON_ERROR_NONE !== json_last_error() ) {
				return null;
			}
		}
		if ( ! is_array( $payload ) || empty( $payload['icons'] ) || ! is_array( $payload['icons'] ) ) {
			return null;
		}

		$known   = self::admin_catalog_entries();
		$custom  = array_keys( self::custom_manifest() );
		$enabled = array();
		$labels  = array();

		foreach ( $payload['icons'] as $slug => $row ) {
			$slug = sanitize_key( (string) $slug );
			if ( ! $slug || 'none' === $slug ) {
				continue;
			}
			if ( ! isset( $known[ $slug ] ) && ! in_array( $slug, $custom, true ) ) {
				continue;
			}
			if ( ! empty( $row['enabled'] ) ) {
				$enabled[] = $slug;
			}
			if ( isset( $row['label'] ) ) {
				$label = sanitize_text_field( (string) $row['label'] );
				if ( '' !== $label ) {
					$labels[ $slug ] = $label;
				}
			}
		}

		return array(
			'enabled' => array_values( array_unique( $enabled ) ),
			'labels'  => $labels,
		);
	}

	/**
	 * Save icon library admin form submission.
	 *
	 * @param array $post Raw $_POST.
	 * @return true|WP_Error
	 */
	public static function save_admin_state_from_post( $post ) {
		$parsed = null;
		if ( ! empty( $post['pckz_icon_library_payload'] ) ) {
			$parsed = self::parse_admin_save_payload( $post['pckz_icon_library_payload'] );
		}

		if ( ! is_array( $parsed ) ) {
			return new WP_Error(
				'icon_library_empty_payload',
				__( 'Icon library save failed: no icon state was received. Reload the page and try again.', 'pckz-canonical-engine' )
			);
		}

		self::save_admin_state( $parsed['enabled'], $parsed['labels'] );
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
		$enabled  = array();
		foreach ( (array) $enabled_slugs as $slug ) {
			$slug = sanitize_key( (string) $slug );
			if ( $slug && 'none' !== $slug ) {
				$enabled[] = $slug;
			}
		}
		$enabled  = array_values( array_unique( $enabled ) );
		$disabled = array();
		foreach ( $all as $slug ) {
			if ( 'none' === $slug ) {
				continue;
			}
			if ( ! in_array( $slug, $enabled, true ) ) {
				$disabled[] = $slug;
			}
		}
		self::option_update( self::OPTION_DISABLED, $disabled );
		$clean = array();
		if ( is_array( $labels ) ) {
			foreach ( $labels as $slug => $label ) {
				$slug  = sanitize_key( (string) $slug );
				$label = sanitize_text_field( (string) $label );
				if ( $slug && 'none' !== $slug && '' !== $label ) {
					$clean[ $slug ] = $label;
				}
			}
		}
		self::option_update( self::OPTION_LABELS, $clean );

		$custom_raw = self::option_get( self::OPTION_CUSTOM, array() );
		if ( is_array( $custom_raw ) ) {
			foreach ( $custom_raw as $slug => $row ) {
				$slug = sanitize_key( (string) $slug );
				if ( ! $slug || ! is_array( $row ) || empty( $row['file'] ) ) {
					continue;
				}
				$custom_raw[ $slug ]['customer_visible'] = in_array( $slug, $enabled, true );
				if ( isset( $clean[ $slug ] ) ) {
					$custom_raw[ $slug ]['label'] = $clean[ $slug ];
				}
			}
			self::option_update( self::OPTION_CUSTOM, $custom_raw );
		}
	}
}
