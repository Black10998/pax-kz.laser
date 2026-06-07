<?php
/**
 * Custom line uploads and admin visibility controls for Linien picker.
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PCKZ_Line_Library
 */
class PCKZ_Line_Library {

	const OPTION_DISABLED     = 'pckz_line_disabled_slugs';
	const OPTION_ADMIN_HIDDEN = 'pckz_line_admin_hidden_slugs';
	const OPTION_INACTIVE     = 'pckz_line_inactive_slugs';
	const OPTION_CUSTOM       = 'pckz_line_custom';
	const OPTION_LABELS       = 'pckz_line_labels';
	const OPTION_ORDER        = 'pckz_line_display_order';
	const OPTION_DELETED      = 'pckz_line_permanently_deleted_slugs';
	const UPLOAD_SUBDIR       = 'pckz-canonical-engine/lines';

	/** Bundled line types permanently retired from admin and customer UIs (assets remain for legacy orders). */
	const RETIRED_BUNDLED_TYPE_MIN = 21;
	const RETIRED_BUNDLED_TYPE_MAX = 40;

	/** Incorrect procedural red lines type_112+ (removed; type_102–111 are official bundled Naruto eye models). */
	const REMOVED_RED_LINE_TYPE_MIN = 112;
	const REMOVED_RED_LINE_TYPE_MAX = 121;

	/** Official bundled Naruto anime eye models shipped with the plugin. */
	const NARUTO_EYE_TYPE_MIN = 102;
	const NARUTO_EYE_TYPE_MAX = 111;

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
			update_option( $key, $value, false );
			if ( function_exists( 'wp_cache_delete' ) ) {
				wp_cache_delete( $key, 'options' );
			}
		}
	}

	/**
	 * Custom uploaded lines.
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
			$source = (string) ( $row['source'] ?? 'upload' );
			if ( ! self::is_valid_custom_source( $source ) ) {
				$source = 'upload';
			}
			$out[ $slug ] = array(
				'label'            => sanitize_text_field( $row['label'] ?? $slug ),
				'file'             => sanitize_file_name( $row['file'] ),
				'customer_visible' => $visible,
				'connected_right'  => ! empty( $row['connected_right'] ),
				'source'           => $source,
				'source_file'      => sanitize_file_name( $row['source_file'] ?? '' ),
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
	 * Bundled line slug => label manifest (type_102+ named models).
	 *
	 * @return array<string,string>
	 */
	public static function bundled_labels() {
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}
		$file  = PCKZCE_PLUGIN_DIR . 'includes/bundled-line-labels.php';
		$cache = is_readable( $file ) ? include $file : array();
		if ( ! is_array( $cache ) ) {
			$cache = array();
		}
		return $cache;
	}

	/**
	 * Default label for a line slug (Typ N for type_N).
	 *
	 * @param string $slug Line slug.
	 * @return string
	 */
	public static function default_label_for_slug( $slug ) {
		$bundled = self::bundled_labels();
		if ( ! empty( $bundled[ $slug ] ) ) {
			return $bundled[ $slug ];
		}
		if ( preg_match( '/^type_(\d+)$/', $slug, $m ) ) {
			return 'Typ ' . $m[1];
		}
		return $slug;
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
		return $fallback ?: self::default_label_for_slug( $slug );
	}

	/**
	 * Whether slug is custom upload.
	 *
	 * @param string $slug Line slug.
	 * @return bool
	 */
	public static function is_custom( $slug ) {
		return isset( self::custom_manifest()[ $slug ] );
	}

	/**
	 * Bundled type_21–type_40: hidden everywhere except internal line_types() for legacy designs.
	 *
	 * @param string $slug Line slug.
	 * @return bool
	 */
	public static function is_retired_bundled_slug( $slug ) {
		if ( ! preg_match( '/^type_(\d+)$/', (string) $slug, $m ) ) {
			return false;
		}
		$n = (int) $m[1];
		return $n >= self::RETIRED_BUNDLED_TYPE_MIN && $n <= self::RETIRED_BUNDLED_TYPE_MAX;
	}

	/**
	 * Slugs permanently deleted from disk and all catalogs (not legacy-retained).
	 *
	 * @return string[]
	 */
	public static function permanently_deleted_slugs() {
		return self::sanitize_slug_list( self::option_get( self::OPTION_DELETED, array() ) );
	}

	/**
	 * Whether a bundled slug was permanently removed from the plugin library.
	 *
	 * @param string $slug Line slug.
	 * @return bool
	 */
	public static function is_permanently_deleted_bundled( $slug ) {
		$slug = sanitize_key( (string) $slug );
		if ( ! $slug || 'none' === $slug ) {
			return false;
		}
		if ( ! in_array( $slug, self::permanently_deleted_slugs(), true ) ) {
			return false;
		}
		// Custom upload at this slug overrides a bundled permanent-delete marker.
		return ! self::has_stored_custom_line( $slug );
	}

	/**
	 * Whether a custom line exists on disk (raw manifest, not filtered by delete list).
	 *
	 * @param string $slug Line slug.
	 * @return bool
	 */
	public static function has_stored_custom_line( $slug ) {
		$slug = sanitize_key( (string) $slug );
		if ( ! $slug ) {
			return false;
		}
		$raw = self::option_get( self::OPTION_CUSTOM, array() );
		if ( ! is_array( $raw ) || empty( $raw[ $slug ]['file'] ) ) {
			return false;
		}
		$path = self::upload_dir() . '/' . sanitize_file_name( $raw[ $slug ]['file'] );
		return is_readable( $path );
	}

	/**
	 * Clear permanent-delete marker (e.g. after re-importing a corrected SVG).
	 *
	 * @param string $slug Line slug.
	 */
	public static function clear_permanently_deleted( $slug ) {
		$slug = sanitize_key( (string) $slug );
		if ( ! $slug ) {
			return;
		}
		$list = self::permanently_deleted_slugs();
		if ( ! in_array( $slug, $list, true ) ) {
			return;
		}
		self::option_update(
			self::OPTION_DELETED,
			array_values( array_diff( $list, array( $slug ) ) )
		);
	}

	/**
	 * Remove slug from visibility, order, and label options.
	 *
	 * @param string $slug Line slug.
	 */
	public static function purge_slug_from_options( $slug ) {
		$slug = sanitize_key( (string) $slug );
		if ( ! $slug || 'none' === $slug ) {
			return;
		}
		self::remove_from_display_order( $slug );
		foreach (
			array(
				self::OPTION_DISABLED,
				self::OPTION_ADMIN_HIDDEN,
				self::OPTION_INACTIVE,
			) as $key
		) {
			$raw = self::sanitize_slug_list( self::option_get( $key, array() ) );
			if ( in_array( $slug, $raw, true ) ) {
				self::option_update( $key, array_values( array_diff( $raw, array( $slug ) ) ) );
			}
		}
		$labels = self::label_overrides();
		if ( isset( $labels[ $slug ] ) ) {
			unset( $labels[ $slug ] );
			self::option_update( self::OPTION_LABELS, $labels );
		}
	}

	/**
	 * Bundled SVG path for a type slug (plugin assets only).
	 *
	 * @param string $slug Line slug.
	 * @return string Empty when not a bundled asset file.
	 */
	public static function bundled_asset_path( $slug ) {
		if ( ! preg_match( '/^type_(\d+)$/', sanitize_key( (string) $slug ), $m ) ) {
			return '';
		}
		if ( ! class_exists( 'PCKZ_Ledos_Preview' ) ) {
			return '';
		}
		$path = PCKZ_Ledos_Preview::line_assets_dir() . 'type_' . (int) $m[1] . '.svg';
		return is_readable( $path ) ? $path : '';
	}

	/**
	 * Permanently delete a built-in bundled line SVG and purge all library references.
	 *
	 * @param string $slug Line slug (type_N).
	 * @return bool|WP_Error
	 */
	public static function delete_bundled_permanent( $slug ) {
		$slug = sanitize_key( (string) $slug );
		if ( ! $slug || 'none' === $slug ) {
			return new WP_Error( 'invalid_slug', __( 'Ungültiger Linien-Slug.', 'pckz-canonical-engine' ) );
		}
		if ( self::is_custom( $slug ) ) {
			return new WP_Error( 'is_custom', __( 'Benutzerdefinierte Linien bitte über „Löschen“ im Upload-Bereich entfernen.', 'pckz-canonical-engine' ) );
		}
		if ( self::is_retired_bundled_slug( $slug ) ) {
			return new WP_Error( 'retired', __( 'Dieses Modell ist dauerhaft aus dem Katalog entfernt.', 'pckz-canonical-engine' ) );
		}
		if ( ! preg_match( '/^type_(\d+)$/', $slug, $m ) ) {
			return new WP_Error( 'not_bundled', __( 'Nur eingebaute Linienmodelle können hier gelöscht werden.', 'pckz-canonical-engine' ) );
		}
		$n = (int) $m[1];
		if ( $n < PCKZ_Ledos_Preview::BUNDLED_LINE_TYPE_MIN ) {
			return new WP_Error( 'cdn_line', __( 'CDN-Linien (Typ 1–20) können nicht gelöscht werden.', 'pckz-canonical-engine' ) );
		}
		$path = self::bundled_asset_path( $slug );
		if ( $path ) {
			if ( function_exists( 'wp_delete_file' ) ) {
				wp_delete_file( $path );
			} else {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				@unlink( $path );
			}
		}
		self::purge_slug_from_options( $slug );
		$deleted = self::permanently_deleted_slugs();
		if ( ! in_array( $slug, $deleted, true ) ) {
			$deleted[] = $slug;
			self::option_update( self::OPTION_DELETED, array_values( array_unique( $deleted ) ) );
		}
		return true;
	}

	/**
	 * Remove incorrect red line models type_112–type_121 from disk and options.
	 *
	 * @return int Number of slugs processed.
	 */
	public static function purge_removed_red_line_models() {
		$count = 0;
		for ( $i = self::REMOVED_RED_LINE_TYPE_MIN; $i <= self::REMOVED_RED_LINE_TYPE_MAX; $i++ ) {
			$slug = 'type_' . $i;
			$result = self::delete_bundled_permanent( $slug );
			if ( ! is_wp_error( $result ) ) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * After bundled type_102–type_111 ship or import, re-enable them in catalogs.
	 *
	 * Clears stale permanent-delete markers from older purge builds (v2.27.31–2.27.34
	 * removed type_102–121) and restores customer visibility + display order.
	 *
	 * @return int Number of slugs re-enabled.
	 */
	public static function register_imported_customer_red_lines() {
		$count = 0;
		for ( $i = self::NARUTO_EYE_TYPE_MIN; $i <= self::NARUTO_EYE_TYPE_MAX; $i++ ) {
			$slug = 'type_' . $i;
			if ( ! self::bundled_asset_path( $slug ) ) {
				continue;
			}
			self::clear_permanently_deleted( $slug );
			self::enable_bundled_slug_for_customers( $slug );
			self::append_to_display_order( $slug );
			++$count;
		}
		return $count;
	}

	/**
	 * Ensure bundled Naruto eye models are visible when SVG assets exist on disk.
	 *
	 * Self-heals sites that upgraded before SVGs shipped or still carry stale delete flags.
	 */
	public static function ensure_bundled_naruto_lines_visible() {
		if ( ! self::bundled_asset_path( 'type_' . self::NARUTO_EYE_TYPE_MIN ) ) {
			return;
		}
		$needs_fix = false;
		for ( $i = self::NARUTO_EYE_TYPE_MIN; $i <= self::NARUTO_EYE_TYPE_MAX; $i++ ) {
			$slug = 'type_' . $i;
			if ( ! self::bundled_asset_path( $slug ) ) {
				continue;
			}
			if (
				self::is_permanently_deleted_bundled( $slug )
				|| ! self::is_active( $slug )
				|| ! self::is_visible( $slug )
			) {
				$needs_fix = true;
				break;
			}
		}
		if ( $needs_fix ) {
			self::register_imported_customer_red_lines();
		}
	}

	/**
	 * Re-enable a bundled slug in customer/admin catalogs (remove hide flags).
	 *
	 * @param string $slug Line slug.
	 */
	public static function enable_bundled_slug_for_customers( $slug ) {
		$slug = sanitize_key( (string) $slug );
		if ( ! $slug || 'none' === $slug ) {
			return;
		}
		foreach (
			array(
				self::OPTION_DISABLED,
				self::OPTION_INACTIVE,
				self::OPTION_ADMIN_HIDDEN,
			) as $key
		) {
			$raw = self::sanitize_slug_list( self::option_get( $key, array() ) );
			if ( in_array( $slug, $raw, true ) ) {
				self::option_update( $key, array_values( array_diff( $raw, array( $slug ) ) ) );
			}
		}
	}

	/**
	 * Slugs hidden from admin line inventory (persisted).
	 *
	 * @return string[]
	 */
	public static function admin_hidden_slugs() {
		return self::sanitize_slug_list( self::option_get( self::OPTION_ADMIN_HIDDEN, array() ) );
	}

	/**
	 * Slugs marked inactive (no new orders; hidden from admin + customer catalogs).
	 *
	 * @return string[]
	 */
	public static function inactive_slugs() {
		return self::sanitize_slug_list( self::option_get( self::OPTION_INACTIVE, array() ) );
	}

	/**
	 * Sanitize a list of line slugs from option storage.
	 *
	 * @param mixed $raw Raw option value.
	 * @return string[]
	 */
	private static function sanitize_slug_list( $raw ) {
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out = array();
		foreach ( $raw as $slug ) {
			$s = sanitize_key( (string) $slug );
			if ( $s && 'none' !== $s ) {
				$out[] = $s;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Whether slug is active (inactive models are hidden from all pickers and admin inventory).
	 *
	 * @param string $slug Line slug.
	 * @return bool
	 */
	public static function is_active( $slug ) {
		if ( self::is_permanently_deleted_bundled( $slug ) ) {
			return false;
		}
		if ( 'none' === $slug || '' === $slug || self::is_retired_bundled_slug( $slug ) ) {
			return true;
		}
		return ! in_array( sanitize_key( $slug ), self::inactive_slugs(), true );
	}

	/**
	 * Whether slug is flagged visible in admin (inventory toggle; independent of active state).
	 *
	 * @param string $slug Line slug.
	 * @return bool
	 */
	public static function admin_visible_flag( $slug ) {
		if ( 'none' === $slug || '' === $slug || self::is_retired_bundled_slug( $slug ) ) {
			return false;
		}
		return ! in_array( sanitize_key( $slug ), self::admin_hidden_slugs(), true );
	}

	/**
	 * Whether slug appears in admin-only pickers/lists (active + admin visible).
	 *
	 * @param string $slug Line slug.
	 * @return bool
	 */
	public static function is_admin_visible( $slug ) {
		if ( 'none' === $slug || '' === $slug ) {
			return true;
		}
		return self::is_active( $slug ) && self::admin_visible_flag( $slug );
	}

	/**
	 * All manageable slugs (for save validation), excluding retired bundled types.
	 *
	 * @return string[]
	 */
	public static function known_line_slugs() {
		$slugs = array();
		if ( class_exists( 'PCKZ_Ledos_Preview' ) ) {
			foreach ( PCKZ_Ledos_Preview::line_types() as $slug => $url ) {
				unset( $url );
				if ( 'none' === $slug || self::is_retired_bundled_slug( $slug ) || self::is_permanently_deleted_bundled( $slug ) ) {
					continue;
				}
				$slugs[] = sanitize_key( $slug );
			}
		}
		return array_values( array_unique( array_filter( $slugs ) ) );
	}

	/**
	 * Saved admin display order (slug list).
	 *
	 * @return string[]
	 */
	public static function display_order() {
		$raw = self::option_get( self::OPTION_ORDER, array() );
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out = array();
		foreach ( $raw as $slug ) {
			$s = sanitize_key( (string) $slug );
			if ( $s && 'none' !== $s ) {
				$out[] = $s;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Whether a custom line uses connected left/right continuation.
	 *
	 * @param string $slug Line slug.
	 * @return bool
	 */
	public static function connected_right_for_slug( $slug ) {
		$slug   = sanitize_key( $slug );
		$custom = self::custom_manifest();
		return ! empty( $custom[ $slug ]['connected_right'] );
	}

	/**
	 * Color mode for a custom line slug.
	 *
	 * @param string $slug Line slug.
	 * @return string preserve|tintable
	 */
	public static function color_mode_for_slug( $slug ) {
		$slug   = sanitize_key( $slug );
		$custom = self::custom_manifest();
		if ( empty( $custom[ $slug ] ) ) {
			return 'tintable';
		}
		$mode = $custom[ $slug ]['color_mode'] ?? '';
		if ( in_array( $mode, array( 'preserve', 'tintable' ), true ) ) {
			return $mode;
		}
		$file = sanitize_file_name( $custom[ $slug ]['file'] ?? '' );
		$path = $file ? self::upload_dir() . '/' . $file : '';
		if ( $path && is_readable( $path ) && class_exists( 'PCKZ_Svg_Library' ) ) {
			return PCKZ_Svg_Library::line_color_mode_for_svg( (string) file_get_contents( $path ) );
		}
		return 'tintable';
	}

	/**
	 * Sort catalog items by admin-defined display order.
	 *
	 * @param array<string,array> $items Catalog entries keyed by slug.
	 * @return array<string,array>
	 */
	public static function sort_catalog_items( $items ) {
		if ( ! is_array( $items ) || empty( $items ) ) {
			return is_array( $items ) ? $items : array();
		}
		$order   = self::display_order();
		$sorted  = array();
		$seen    = array();
		if ( isset( $items['none'] ) ) {
			$sorted['none'] = $items['none'];
			$seen['none']   = true;
		}
		foreach ( $order as $slug ) {
			if ( isset( $items[ $slug ] ) ) {
				$sorted[ $slug ] = $items[ $slug ];
				$seen[ $slug ]   = true;
			}
		}
		foreach ( $items as $slug => $data ) {
			if ( ! empty( $seen[ $slug ] ) ) {
				continue;
			}
			$sorted[ $slug ] = $data;
		}
		return $sorted;
	}

	/**
	 * Persist admin display order.
	 *
	 * @param array $slugs Ordered slug list.
	 */
	public static function save_display_order( $slugs ) {
		$clean = array();
		foreach ( (array) $slugs as $slug ) {
			$s = sanitize_key( (string) $slug );
			if ( $s && 'none' !== $s ) {
				$clean[] = $s;
			}
		}
		self::option_update( self::OPTION_ORDER, array_values( array_unique( $clean ) ) );
	}

	/**
	 * Remove slug from saved display order.
	 *
	 * @param string $slug Line slug.
	 */
	public static function remove_from_display_order( $slug ) {
		$slug = sanitize_key( $slug );
		if ( ! $slug ) {
			return;
		}
		self::save_display_order(
			array_values(
				array_diff( self::display_order(), array( $slug ) )
			)
		);
	}

	/**
	 * Append slug to display order if missing.
	 *
	 * @param string $slug Line slug.
	 */
	public static function append_to_display_order( $slug ) {
		$slug = sanitize_key( $slug );
		if ( ! $slug || 'none' === $slug ) {
			return;
		}
		$order = self::display_order();
		if ( in_array( $slug, $order, true ) ) {
			return;
		}
		$order[] = $slug;
		self::save_display_order( $order );
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
	 * Public URL for custom line SVG.
	 *
	 * @param string $slug Line slug.
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
	 * Preview filename for a custom line slug.
	 *
	 * @param string $slug Line slug.
	 * @return string
	 */
	public static function preview_filename( $slug ) {
		return sanitize_key( $slug ) . '-preview.svg';
	}

	/**
	 * Public URL for normalized picker/admin preview SVG.
	 *
	 * @param string $slug Line slug.
	 * @return string
	 */
	public static function preview_url( $slug ) {
		$slug = sanitize_key( $slug );
		if ( ! self::is_custom( $slug ) ) {
			return '';
		}
		$file = self::upload_dir() . '/' . self::preview_filename( $slug );
		if ( ! is_readable( $file ) ) {
			self::regenerate_preview_svg( $slug );
		}
		if ( ! is_readable( $file ) ) {
			return self::custom_url( $slug );
		}
		return esc_url_raw( self::upload_url_base() . '/' . self::preview_filename( $slug ) );
	}

	/**
	 * Write normalized preview SVG for picker/admin thumbnails.
	 *
	 * @param string $slug Line slug.
	 * @return bool
	 */
	public static function regenerate_preview_svg( $slug ) {
		$slug   = sanitize_key( $slug );
		$custom = self::option_get( self::OPTION_CUSTOM, array() );
		if ( ! is_array( $custom ) || empty( $custom[ $slug ]['file'] ) ) {
			return false;
		}
		$source = self::upload_dir() . '/' . sanitize_file_name( $custom[ $slug ]['file'] );
		if ( ! is_readable( $source ) ) {
			return false;
		}
		$svg = file_get_contents( $source );
		if ( ! is_string( $svg ) || ! class_exists( 'PCKZ_Svg_Library' ) ) {
			return false;
		}
		$connected = ! empty( $custom[ $slug ]['connected_right'] );
		$preview   = PCKZ_Svg_Library::normalize_line_svg_for_preview( $svg, $connected );
		$dest      = self::upload_dir() . '/' . self::preview_filename( $slug );
		return (bool) file_put_contents( $dest, $preview );
	}

	/**
	 * Slugs disabled in admin (hidden from customer selector).
	 *
	 * @return string[]
	 */
	public static function disabled_slugs() {
		return self::sanitize_slug_list( self::option_get( self::OPTION_DISABLED, array() ) );
	}

	/**
	 * Whether line appears in customer-facing catalog/selector.
	 *
	 * @param string $slug Line slug.
	 * @return bool
	 */
	public static function is_visible( $slug ) {
		if ( 'none' === $slug || '' === $slug ) {
			return true;
		}
		$slug = sanitize_key( $slug );
		if ( self::is_permanently_deleted_bundled( $slug ) || self::is_retired_bundled_slug( $slug ) || ! self::is_active( $slug ) ) {
			return false;
		}
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
	 * @return array{lines:array<string,array{enabled:bool,label:string}>}
	 */
	public static function build_admin_save_payload() {
		$catalog = self::admin_catalog_entries();
		if ( class_exists( 'PCKZ_Ledos_Preview' ) ) {
			$catalog = PCKZ_Ledos_Preview::line_catalog( false, true );
		}
		$lines = array();
		foreach ( $catalog as $slug => $data ) {
			if ( 'none' === $slug ) {
				continue;
			}
			$row = array(
				'enabled'       => self::is_visible( $slug ),
				'admin_visible' => self::admin_visible_flag( $slug ),
				'active'        => self::is_active( $slug ),
				'label'         => (string) ( $data['label'] ?? self::label_for_slug( $slug, $slug ) ),
			);
			if ( ! empty( $data['custom'] ) ) {
				$row['connected_right'] = self::connected_right_for_slug( $slug );
			}
			$lines[ $slug ] = $row;
		}
		$order = array();
		foreach ( array_keys( $catalog ) as $slug ) {
			if ( 'none' !== $slug ) {
				$order[] = $slug;
			}
		}
		return array(
			'lines' => $lines,
			'order' => $order,
		);
	}

	/**
	 * Customer-facing Linien picker choices (live catalog, not stored product snapshot).
	 *
	 * @return array<int,array{value:string,label:string,img:string}>
	 */
	public static function get_customer_line_choices() {
		if ( ! class_exists( 'PCKZ_Ledos_Preview' ) ) {
			return array();
		}
		$choices = array();
		foreach ( PCKZ_Ledos_Preview::line_catalog( true ) as $slug => $data ) {
			// Picker thumbnails use display-only scaled previews; plate/export keep lineTypes URLs.
			$thumb = self::picker_preview_url( $slug );
			if ( '' === $thumb ) {
				$thumb = ! empty( $data['preview'] ) ? $data['preview'] : ( $data['url'] ?? '' );
			}
			$preserve = ! empty( $data['preserve_colors'] );
			$choices[] = array(
				'value'           => $slug,
				'label'           => $data['label'] ?? self::label_for_slug( $slug, $slug ),
				'img'             => $thumb ? esc_url_raw( $thumb ) : '',
				'preserve_colors' => $preserve,
			);
		}
		return $choices;
	}

	/**
	 * First line shown in the customer Linien picker (after "Keine Linien").
	 *
	 * @return string Line slug, or empty when no lines are available.
	 */
	public static function default_customer_line_slug() {
		foreach ( self::get_customer_line_choices() as $choice ) {
			$slug = sanitize_key( (string) ( $choice['value'] ?? '' ) );
			if ( $slug && 'none' !== $slug ) {
				return $slug;
			}
		}
		if ( class_exists( 'PCKZ_Ledos_Preview' ) ) {
			foreach ( PCKZ_Ledos_Preview::line_catalog( true, true ) as $slug => $data ) {
				unset( $data );
				if ( 'none' !== $slug ) {
					return sanitize_key( (string) $slug );
				}
			}
		}
		return 'type_1';
	}

	/**
	 * Filter catalog entries for customer UI.
	 *
	 * @param array<string,array> $items Line catalog.
	 * @return array<string,array>
	 */
	public static function filter_visible_catalog( $items ) {
		foreach ( $items as $slug => $data ) {
			if ( self::is_retired_bundled_slug( $slug ) || self::is_permanently_deleted_bundled( $slug ) || ! self::is_visible( $slug ) ) {
				unset( $items[ $slug ] );
			}
		}
		return $items;
	}

	/**
	 * Filter catalog entries for admin line inventory.
	 *
	 * @param array<string,array> $items Line catalog.
	 * @return array<string,array>
	 */
	public static function filter_admin_catalog( $items ) {
		foreach ( $items as $slug => $data ) {
			if ( self::is_retired_bundled_slug( $slug ) || self::is_permanently_deleted_bundled( $slug ) || ! self::is_admin_visible( $slug ) ) {
				unset( $items[ $slug ] );
			}
		}
		return $items;
	}

	/**
	 * Strip retired bundled types from a catalog map.
	 *
	 * @param array<string,array> $items Line catalog.
	 * @return array<string,array>
	 */
	public static function strip_retired_from_catalog( $items ) {
		foreach ( $items as $slug => $data ) {
			if ( self::is_retired_bundled_slug( $slug ) || self::is_permanently_deleted_bundled( $slug ) ) {
				unset( $items[ $slug ] );
			}
		}
		return $items;
	}

	/**
	 * Catalog entries for admin line inventory (retired types excluded; all visibility states editable).
	 *
	 * @return array<string,array>
	 */
	public static function admin_catalog_entries() {
		if ( ! class_exists( 'PCKZ_Ledos_Preview' ) ) {
			return array();
		}
		return PCKZ_Ledos_Preview::line_catalog( false, true );
	}

	/**
	 * Next available type_N slug for a new upload.
	 *
	 * @return string
	 */
	public static function next_upload_slug() {
		$max = class_exists( 'PCKZ_Ledos_Preview' ) ? PCKZ_Ledos_Preview::BUNDLED_LINE_TYPE_MAX : 71;
		if ( class_exists( 'PCKZ_Ledos_Preview' ) ) {
			foreach ( PCKZ_Ledos_Preview::base_line_types() as $slug => $url ) {
				unset( $url );
				if ( preg_match( '/^type_(\d+)$/', $slug, $m ) ) {
					$max = max( $max, (int) $m[1] );
				}
			}
		}
		foreach ( array_keys( self::custom_manifest() ) as $slug ) {
			if ( preg_match( '/^type_(\d+)$/', $slug, $m ) ) {
				$max = max( $max, (int) $m[1] );
			}
		}
		return 'type_' . ( $max + 1 );
	}

	/**
	 * Allowed manifest source values for custom lines.
	 *
	 * @param string $source Source key.
	 * @return bool
	 */
	public static function is_valid_custom_source( $source ) {
		return in_array(
			$source,
			array(
				'upload',
				'url',
				'import_svg',
				'import_lbrn2',
				'import_ai',
				'import_eps',
				'import_dxf',
				'import_pdf',
				'import_vector',
			),
			true
		);
	}

	/**
	 * Whether an upload temp path is allowed (HTTP upload or CLI smoke test).
	 *
	 * @param string $path Temp file path.
	 * @return bool
	 */
	private static function is_valid_line_upload_tmp( $path ) {
		if ( is_uploaded_file( $path ) ) {
			return true;
		}
		return defined( 'PCKZ_SMOKE_TEST' ) && PCKZ_SMOKE_TEST && is_readable( $path );
	}

	/**
	 * Handle SVG upload (direct store, no conversion).
	 *
	 * @param array $file $_FILES row.
	 * @return array|WP_Error
	 */
	public static function handle_upload( $file ) {
		if ( empty( $file['tmp_name'] ) || ! self::is_valid_line_upload_tmp( $file['tmp_name'] ) ) {
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
		$slug  = self::next_upload_slug();
		$label = isset( $_POST['line_upload_label'] ) ? sanitize_text_field( wp_unslash( $_POST['line_upload_label'] ) ) : self::default_label_for_slug( $slug );
		if ( '' === $label ) {
			$label = self::default_label_for_slug( $slug );
		}
		return self::store_custom_svg( $contents, $slug, $label, 'upload' );
	}

	/**
	 * Optional vector import (LBRN2, AI, EPS, DXF, PDF, or SVG via converter).
	 *
	 * @param array $file $_FILES row.
	 * @return array|WP_Error
	 */
	public static function handle_vector_import( $file ) {
		if ( ! class_exists( 'PCKZ_Line_Importer' ) ) {
			return new WP_Error( 'missing_importer', __( 'Line import module is not available.', 'pckz-canonical-engine' ) );
		}
		$args = array(
			'label'           => isset( $_POST['line_vector_label'] ) ? sanitize_text_field( wp_unslash( $_POST['line_vector_label'] ) ) : '',
			'preserve_colors' => ! empty( $_POST['line_import_preserve_colors'] ),
			'fill_color'      => isset( $_POST['line_import_fill_color'] ) ? sanitize_text_field( wp_unslash( $_POST['line_import_fill_color'] ) ) : '',
			'connected_right' => ! empty( $_POST['line_import_connected_right'] ),
			'source_file'     => isset( $file['name'] ) ? sanitize_file_name( wp_unslash( $file['name'] ) ) : '',
		);
		return PCKZ_Line_Importer::import_upload( $file, $args );
	}

	/**
	 * Persist validated custom line SVG and register manifest entry (public for importer).
	 *
	 * @param string $contents SVG source.
	 * @param string $slug     Unique slug.
	 * @param string $label    Display label.
	 * @param string $source   Manifest source key.
	 * @param array  $args     Optional preserve_colors, connected_right, source_file, fill_color.
	 * @return array|WP_Error
	 */
	public static function store_custom_line_svg( $contents, $slug, $label, $source = 'upload', $args = array() ) {
		$extra = array();
		if ( ! empty( $args['preserve_colors'] ) ) {
			$extra['color_mode'] = 'preserve';
		} elseif ( class_exists( 'PCKZ_Svg_Library' ) ) {
			$extra['color_mode'] = PCKZ_Svg_Library::line_color_mode_for_svg( $contents );
		}
		if ( array_key_exists( 'connected_right', $args ) ) {
			$extra['connected_right'] = (bool) $args['connected_right'];
		}
		if ( ! empty( $args['source_file'] ) ) {
			$extra['source_file'] = sanitize_file_name( $args['source_file'] );
		}
		return self::store_custom_svg( $contents, $slug, $label, $source, $extra );
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
		$slug = self::next_upload_slug();
		if ( '' === $label ) {
			$base  = PCKZ_Svg_Library::basename_from_url( $url );
			$label = $base ? ucwords( str_replace( '-', ' ', $base ) ) : self::default_label_for_slug( $slug );
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
	 * @param string $source   upload|url|import_* .
	 * @param array  $extra    Optional color_mode, connected_right, source_file.
	 * @return array|WP_Error
	 */
	private static function store_custom_svg( $contents, $slug, $label, $source = 'upload', $extra = array() ) {
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
		$color_mode = 'tintable';
		if ( ! empty( $extra['color_mode'] ) && in_array( $extra['color_mode'], array( 'preserve', 'tintable' ), true ) ) {
			$color_mode = $extra['color_mode'];
		} elseif ( class_exists( 'PCKZ_Svg_Library' ) ) {
			$color_mode = PCKZ_Svg_Library::line_color_mode_for_svg( $contents );
		}
		if ( ! self::is_valid_custom_source( $source ) ) {
			$source = 'upload';
		}
		$custom[ $slug ] = array(
			'label'            => sanitize_text_field( $label ),
			'file'             => $filename,
			'customer_visible' => true,
			'connected_right'  => ! empty( $extra['connected_right'] ),
			'color_mode'       => $color_mode,
			'source'           => $source,
			'source_file'      => ! empty( $extra['source_file'] ) ? sanitize_file_name( $extra['source_file'] ) : '',
		);
		self::option_update( self::OPTION_CUSTOM, $custom );

		self::clear_permanently_deleted( $slug );
		self::ensure_custom_line_visibility_flags( $slug );

		$disabled = array_values(
			array_diff( self::disabled_slugs(), array( $slug ) )
		);
		self::option_update( self::OPTION_DISABLED, $disabled );

		self::append_to_display_order( $slug );

		self::regenerate_preview_svg( $slug );

		if ( ! self::is_line_in_catalog( $slug, false ) ) {
			return new WP_Error(
				'register_fail',
				__(
					'Die Linie wurde gespeichert, konnte aber nicht in der Bibliothek registriert werden. Bitte Seite neu laden oder Support kontaktieren.',
					'pckz-canonical-engine'
				)
			);
		}

		do_action( 'pckzce_asset_catalog_changed' );

		return array( 'slug' => $slug );
	}

	/**
	 * Ensure a new custom line is visible in admin and customer catalogs.
	 *
	 * @param string $slug Line slug.
	 */
	private static function ensure_custom_line_visibility_flags( $slug ) {
		$slug = sanitize_key( $slug );
		if ( ! $slug ) {
			return;
		}
		$inactive = array_values( array_diff( self::inactive_slugs(), array( $slug ) ) );
		self::option_update( self::OPTION_INACTIVE, $inactive );
		$hidden = array_values( array_diff( self::admin_hidden_slugs(), array( $slug ) ) );
		self::option_update( self::OPTION_ADMIN_HIDDEN, $hidden );
	}

	/**
	 * Whether slug appears in the live line catalog after filters.
	 *
	 * @param string $slug          Line slug.
	 * @param bool   $for_customer  When true, use customer visibility filters.
	 * @return bool
	 */
	public static function is_line_in_catalog( $slug, $for_customer = false ) {
		$slug = sanitize_key( (string) $slug );
		if ( ! $slug || ! class_exists( 'PCKZ_Ledos_Preview' ) ) {
			return false;
		}
		$catalog = PCKZ_Ledos_Preview::line_catalog( $for_customer, true );
		return isset( $catalog[ $slug ] );
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
	 * Delete custom line.
	 *
	 * @param string $slug Slug.
	 * @return bool|WP_Error
	 */
	public static function delete_custom( $slug ) {
		$slug   = sanitize_key( $slug );
		$custom = self::option_get( self::OPTION_CUSTOM, array() );
		if ( ! is_array( $custom ) || empty( $custom[ $slug ] ) ) {
			return new WP_Error( 'not_custom', __( 'Nur hochgeladene Linien können gelöscht werden.', 'pckz-canonical-engine' ) );
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
		$preview = self::upload_dir() . '/' . self::preview_filename( $slug );
		if ( is_readable( $preview ) ) {
			if ( function_exists( 'wp_delete_file' ) ) {
				wp_delete_file( $preview );
			} else {
				unlink( $preview );
			}
		}
		$labels = self::label_overrides();
		if ( isset( $labels[ $slug ] ) ) {
			unset( $labels[ $slug ] );
			self::option_update( self::OPTION_LABELS, $labels );
		}
		self::remove_from_display_order( $slug );
		return true;
	}

	/**
	 * Hide built-in line slugs from admin and customer (files remain on disk).
	 *
	 * @param string[] $slugs Line slugs.
	 */
	public static function hide_bundled_slugs( $slugs ) {
		$admin_hidden = self::admin_hidden_slugs();
		$inactive     = self::inactive_slugs();
		$disabled     = self::disabled_slugs();
		foreach ( (array) $slugs as $slug ) {
			$slug = sanitize_key( (string) $slug );
			if ( ! $slug || 'none' === $slug || self::is_retired_bundled_slug( $slug ) || self::is_custom( $slug ) ) {
				continue;
			}
			if ( ! in_array( $slug, $admin_hidden, true ) ) {
				$admin_hidden[] = $slug;
			}
			if ( ! in_array( $slug, $inactive, true ) ) {
				$inactive[] = $slug;
			}
			if ( ! in_array( $slug, $disabled, true ) ) {
				$disabled[] = $slug;
			}
		}
		self::option_update( self::OPTION_ADMIN_HIDDEN, array_values( array_unique( $admin_hidden ) ) );
		self::option_update( self::OPTION_INACTIVE, array_values( array_unique( $inactive ) ) );
		self::option_update( self::OPTION_DISABLED, array_values( array_unique( $disabled ) ) );
	}

	/**
	 * Delete custom uploads and hide built-in models from the library.
	 *
	 * @param array $slugs Slugs to process.
	 * @return array{deleted:int,hidden:int,failed:int,skipped:int}|WP_Error
	 */
	public static function delete_selected_bulk( $slugs ) {
		if ( ! is_array( $slugs ) || empty( $slugs ) ) {
			return new WP_Error( 'bulk_empty', __( 'Keine Linien zum Löschen ausgewählt.', 'pckz-canonical-engine' ) );
		}
		$deleted = 0;
		$failed  = 0;
		$skipped = 0;
		foreach ( $slugs as $slug ) {
			$slug = sanitize_key( (string) $slug );
			if ( ! $slug || 'none' === $slug || self::is_retired_bundled_slug( $slug ) ) {
				++$skipped;
				continue;
			}
			if ( self::is_custom( $slug ) ) {
				$result = self::delete_custom( $slug );
			} else {
				$result = self::delete_bundled_permanent( $slug );
			}
			if ( is_wp_error( $result ) ) {
				++$failed;
			} else {
				++$deleted;
			}
		}
		if ( 0 === $deleted && 0 === $failed ) {
			return new WP_Error( 'bulk_none', __( 'Keine Linien konnten verarbeitet werden.', 'pckz-canonical-engine' ) );
		}
		return array(
			'deleted' => $deleted,
			'hidden'  => 0,
			'failed'  => $failed,
			'skipped' => $skipped,
		);
	}

	/**
	 * Delete multiple custom lines (built-in items are skipped).
	 *
	 * @param array $slugs Slugs to delete.
	 * @return array{deleted:int,failed:int,skipped:int}|WP_Error
	 * @deprecated Use delete_selected_bulk().
	 */
	public static function delete_custom_bulk( $slugs ) {
		$result = self::delete_selected_bulk( $slugs );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return array(
			'deleted' => (int) $result['deleted'],
			'failed'  => (int) $result['failed'],
			'skipped' => (int) $result['skipped'] + (int) ( $result['hidden'] ?? 0 ),
		);
	}

	/**
	 * Parse compact JSON payload from line library save form.
	 *
	 * @param mixed $payload Decoded or raw payload.
	 * @return array{enabled:string[],admin_visible:string[],active:string[],labels:array<string,string>,connected:array<string,bool>,order:string[]}|null
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
		if ( ! is_array( $payload ) || empty( $payload['lines'] ) || ! is_array( $payload['lines'] ) ) {
			return null;
		}

		$known         = array_fill_keys( self::known_line_slugs(), true );
		$custom        = array_keys( self::custom_manifest() );
		$enabled       = array();
		$admin_visible = array();
		$active        = array();
		$labels        = array();
		$connected     = array();

		foreach ( $payload['lines'] as $slug => $row ) {
			$slug = sanitize_key( (string) $slug );
			if ( ! $slug || 'none' === $slug || self::is_retired_bundled_slug( $slug ) ) {
				continue;
			}
			if ( ! isset( $known[ $slug ] ) && ! in_array( $slug, $custom, true ) ) {
				continue;
			}
			if ( ! empty( $row['enabled'] ) ) {
				$enabled[] = $slug;
			}
			if ( ! empty( $row['admin_visible'] ) ) {
				$admin_visible[] = $slug;
			}
			if ( ! empty( $row['active'] ) ) {
				$active[] = $slug;
			}
			if ( isset( $row['label'] ) ) {
				$label = sanitize_text_field( (string) $row['label'] );
				if ( '' !== $label ) {
					$labels[ $slug ] = $label;
				}
			}
			if ( in_array( $slug, $custom, true ) ) {
				$connected[ $slug ] = ! empty( $row['connected_right'] );
			}
		}

		$order = array();
		if ( ! empty( $payload['order'] ) && is_array( $payload['order'] ) ) {
			foreach ( $payload['order'] as $slug ) {
				$s = sanitize_key( (string) $slug );
				if ( $s && 'none' !== $s ) {
					$order[] = $s;
				}
			}
		}

		return array(
			'enabled'       => array_values( array_unique( $enabled ) ),
			'admin_visible' => array_values( array_unique( $admin_visible ) ),
			'active'        => array_values( array_unique( $active ) ),
			'labels'        => $labels,
			'connected'     => $connected,
			'order'         => array_values( array_unique( $order ) ),
		);
	}

	/**
	 * Save line library admin form submission.
	 *
	 * @param array $post Raw $_POST.
	 * @return true|WP_Error
	 */
	public static function save_admin_state_from_post( $post ) {
		$parsed = null;
		if ( ! empty( $post['pckz_line_library_payload'] ) ) {
			$parsed = self::parse_admin_save_payload( $post['pckz_line_library_payload'] );
		}

		if ( ! is_array( $parsed ) ) {
			return new WP_Error(
				'line_library_empty_payload',
				__( 'Line library save failed: no line state was received. Reload the page and try again.', 'pckz-canonical-engine' )
			);
		}

		self::save_admin_state(
			$parsed['enabled'],
			$parsed['labels'],
			$parsed['connected'] ?? array(),
			$parsed['order'] ?? array(),
			$parsed['admin_visible'] ?? array(),
			$parsed['active'] ?? array()
		);
		return true;
	}

	/**
	 * Save visibility + labels from admin.
	 *
	 * @param array $enabled_slugs       Customer-visible slugs.
	 * @param array $labels              Slug => label.
	 * @param array $connected           Slug => connected_right bool (custom only).
	 * @param array $order               Ordered slug list.
	 * @param array $admin_visible_slugs Slugs shown in admin inventory.
	 * @param array $active_slugs        Active slugs.
	 */
	public static function save_admin_state( $enabled_slugs, $labels, $connected = array(), $order = array(), $admin_visible_slugs = array(), $active_slugs = array() ) {
		$all_slugs = self::known_line_slugs();
		$enabled   = array();
		foreach ( (array) $enabled_slugs as $slug ) {
			$slug = sanitize_key( (string) $slug );
			if ( $slug && 'none' !== $slug && ! self::is_retired_bundled_slug( $slug ) ) {
				$enabled[] = $slug;
			}
		}
		$enabled = array_values( array_unique( $enabled ) );

		$admin_visible = array();
		foreach ( (array) $admin_visible_slugs as $slug ) {
			$slug = sanitize_key( (string) $slug );
			if ( $slug && 'none' !== $slug && ! self::is_retired_bundled_slug( $slug ) ) {
				$admin_visible[] = $slug;
			}
		}
		$admin_visible = array_values( array_unique( $admin_visible ) );

		$active = array();
		foreach ( (array) $active_slugs as $slug ) {
			$slug = sanitize_key( (string) $slug );
			if ( $slug && 'none' !== $slug && ! self::is_retired_bundled_slug( $slug ) ) {
				$active[] = $slug;
			}
		}
		$active = array_values( array_unique( $active ) );

		$disabled = array();
		$admin_hidden = array();
		$inactive = array();
		foreach ( $all_slugs as $slug ) {
			if ( ! in_array( $slug, $enabled, true ) ) {
				$disabled[] = $slug;
			}
			if ( ! in_array( $slug, $admin_visible, true ) ) {
				$admin_hidden[] = $slug;
			}
			if ( ! in_array( $slug, $active, true ) ) {
				$inactive[] = $slug;
			}
		}
		self::option_update( self::OPTION_DISABLED, $disabled );
		self::option_update( self::OPTION_ADMIN_HIDDEN, $admin_hidden );
		self::option_update( self::OPTION_INACTIVE, $inactive );
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
				if ( is_array( $connected ) && array_key_exists( $slug, $connected ) ) {
					$custom_raw[ $slug ]['connected_right'] = ! empty( $connected[ $slug ] );
				}
			}
			self::option_update( self::OPTION_CUSTOM, $custom_raw );
		}

		foreach ( array_keys( self::custom_manifest() ) as $slug ) {
			self::regenerate_preview_svg( $slug );
		}

		if ( is_array( $order ) && ! empty( $order ) ) {
			self::save_display_order( $order );
		}
	}

	/**
	 * Register frontend hooks (picker preview SVG endpoint).
	 */
	public static function register_hooks() {
		add_action( 'init', array( __CLASS__, 'ensure_bundled_naruto_lines_visible' ), 6 );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_serve_picker_preview' ), 0 );
	}

	/**
	 * Public URL for picker-only SVG preview (display scaling; source assets unchanged).
	 *
	 * @param string $slug Line slug.
	 * @return string
	 */
	public static function picker_preview_url( $slug ) {
		$slug = sanitize_key( $slug );
		if ( ! $slug || 'none' === $slug ) {
			return '';
		}
		$base = home_url( '/' );
		if ( ! function_exists( 'add_query_arg' ) ) {
			return $base . '?pckz_line_picker=' . rawurlencode( $slug ) . '&pckz_v=' . rawurlencode( (string) self::picker_preview_version( $slug ) );
		}
		return add_query_arg(
			array(
				'pckz_line_picker' => $slug,
				'pckz_v'           => self::picker_preview_version( $slug ),
			),
			$base
		);
	}

	/**
	 * Cache-buster for picker preview responses.
	 *
	 * @param string $slug Line slug.
	 * @return string
	 */
	public static function picker_preview_version( $slug ) {
		$slug = sanitize_key( $slug );
		if ( self::is_custom( $slug ) ) {
			$custom = self::custom_manifest();
			$file   = ! empty( $custom[ $slug ]['file'] ) ? self::upload_dir() . '/' . sanitize_file_name( $custom[ $slug ]['file'] ) : '';
			return ( $file && is_readable( $file ) ) ? (string) filemtime( $file ) : '1';
		}
		if ( class_exists( 'PCKZ_Ledos_Preview' ) && preg_match( '/^type_(\d+)$/', $slug, $m ) ) {
			$path = PCKZ_Ledos_Preview::line_assets_dir() . $slug . '.svg';
			if ( is_readable( $path ) ) {
				return (string) filemtime( $path );
			}
		}
		$url = class_exists( 'PCKZ_Ledos_Preview' ) ? ( PCKZ_Ledos_Preview::line_types()[ $slug ] ?? '' ) : '';
		return $url ? substr( md5( $url ), 0, 12 ) : '1';
	}

	/**
	 * Serve dynamic picker preview SVG when ?pckz_line_picker=slug is requested.
	 */
	public static function maybe_serve_picker_preview() {
		if ( empty( $_GET['pckz_line_picker'] ) ) {
			return;
		}
		$slug = sanitize_key( wp_unslash( $_GET['pckz_line_picker'] ) );
		if ( ! $slug || 'none' === $slug || self::is_retired_bundled_slug( $slug ) || self::is_permanently_deleted_bundled( $slug ) ) {
			status_header( 404 );
			exit;
		}
		$svg = self::read_source_svg_for_slug( $slug );
		if ( ! $svg || ! class_exists( 'PCKZ_Svg_Library' ) ) {
			status_header( 404 );
			exit;
		}
		$connected = self::connected_right_for_slug( $slug );
		$body      = PCKZ_Svg_Library::normalize_line_svg_for_picker_preview( $svg, $connected );
		if ( function_exists( 'nocache_headers' ) ) {
			nocache_headers();
		}
		header( 'Content-Type: image/svg+xml; charset=utf-8' );
		header( 'Cache-Control: public, max-age=86400' );
		if ( ! empty( $_GET['pckz_v'] ) ) {
			header( 'ETag: "' . sanitize_text_field( wp_unslash( $_GET['pckz_v'] ) ) . '"' );
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG served as image payload.
		echo $body;
		exit;
	}

	/**
	 * Load original line SVG source for a slug (export assets are not modified).
	 *
	 * @param string $slug Line slug.
	 * @return string|false
	 */
	public static function read_source_svg_for_slug( $slug ) {
		$slug = sanitize_key( $slug );
		if ( ! $slug || 'none' === $slug || ! class_exists( 'PCKZ_Ledos_Preview' ) ) {
			return false;
		}
		$url = PCKZ_Ledos_Preview::line_types()[ $slug ] ?? '';
		if ( ! $url ) {
			return false;
		}
		if ( self::is_custom( $slug ) ) {
			$custom = self::custom_manifest();
			$file   = ! empty( $custom[ $slug ]['file'] ) ? self::upload_dir() . '/' . sanitize_file_name( $custom[ $slug ]['file'] ) : '';
			if ( $file && is_readable( $file ) ) {
				$body = file_get_contents( $file );
				return is_string( $body ) ? $body : false;
			}
		}
		$plugin_url = trailingslashit( PCKZCE_PLUGIN_URL );
		if ( 0 === strpos( $url, $plugin_url ) ) {
			$path = PCKZCE_PLUGIN_DIR . ltrim( substr( $url, strlen( $plugin_url ) ), '/' );
			if ( is_readable( $path ) ) {
				$body = file_get_contents( $path );
				return is_string( $body ) ? $body : false;
			}
		}
		if ( preg_match( '#^https?://#i', $url ) && class_exists( 'PCKZ_Svg_Library' ) ) {
			$fetched = PCKZ_Svg_Library::fetch_from_url( $url );
			if ( is_string( $fetched ) ) {
				return $fetched;
			}
		}
		return false;
	}
}
