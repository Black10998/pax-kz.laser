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

	const OPTION_DISABLED = 'pckz_line_disabled_slugs';
	const OPTION_CUSTOM   = 'pckz_line_custom';
	const OPTION_LABELS   = 'pckz_line_labels';
	const OPTION_ORDER    = 'pckz_line_display_order';
	const UPLOAD_SUBDIR   = 'pckz-canonical-engine/lines';

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
			$out[ $slug ] = array(
				'label'            => sanitize_text_field( $row['label'] ?? $slug ),
				'file'             => sanitize_file_name( $row['file'] ),
				'customer_visible' => $visible,
				'connected_right'  => ! empty( $row['connected_right'] ),
				'source'           => in_array( $row['source'] ?? '', array( 'upload', 'url' ), true ) ? $row['source'] : 'upload',
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
	 * Default label for a line slug (Typ N for type_N).
	 *
	 * @param string $slug Line slug.
	 * @return string
	 */
	public static function default_label_for_slug( $slug ) {
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
				'enabled' => self::is_visible( $slug ),
				'label'   => (string) ( $data['label'] ?? self::label_for_slug( $slug, $slug ) ),
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
			$thumb = ! empty( $data['preview'] ) ? $data['preview'] : ( $data['url'] ?? '' );
			$choices[] = array(
				'value' => $slug,
				'label' => $data['label'] ?? self::label_for_slug( $slug, $slug ),
				'img'   => $thumb ? esc_url_raw( $thumb ) : '',
			);
		}
		return $choices;
	}

	/**
	 * Filter catalog entries for customer UI.
	 *
	 * @param array<string,array> $items Line catalog.
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
		$slug  = self::next_upload_slug();
		$label = isset( $_POST['line_upload_label'] ) ? sanitize_text_field( wp_unslash( $_POST['line_upload_label'] ) ) : self::default_label_for_slug( $slug );
		if ( '' === $label ) {
			$label = self::default_label_for_slug( $slug );
		}
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
			'connected_right'  => false,
			'source'           => in_array( $source, array( 'upload', 'url' ), true ) ? $source : 'upload',
		);
		self::option_update( self::OPTION_CUSTOM, $custom );

		$disabled = array_values(
			array_diff( self::disabled_slugs(), array( $slug ) )
		);
		self::option_update( self::OPTION_DISABLED, $disabled );

		self::append_to_display_order( $slug );

		self::regenerate_preview_svg( $slug );

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
	 * Delete multiple custom lines (built-in items are skipped).
	 *
	 * @param array $slugs Slugs to delete.
	 * @return array{deleted:int,failed:int,skipped:int}|WP_Error
	 */
	public static function delete_custom_bulk( $slugs ) {
		if ( ! is_array( $slugs ) || empty( $slugs ) ) {
			return new WP_Error( 'bulk_empty', __( 'Keine Linien zum Löschen ausgewählt.', 'pckz-canonical-engine' ) );
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
			return new WP_Error( 'bulk_none', __( 'Keine benutzerdefinierten Linien konnten gelöscht werden.', 'pckz-canonical-engine' ) );
		}
		return array(
			'deleted' => $deleted,
			'failed'  => $failed,
			'skipped' => $skipped,
		);
	}

	/**
	 * Parse compact JSON payload from line library save form.
	 *
	 * @param mixed $payload Decoded or raw payload.
	 * @return array{enabled:string[],labels:array<string,string>,connected:array<string,bool>,order:string[]}|null
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

		$known   = self::admin_catalog_entries();
		$custom  = array_keys( self::custom_manifest() );
		$enabled = array();
		$labels  = array();
		$connected = array();

		foreach ( $payload['lines'] as $slug => $row ) {
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
			'enabled'   => array_values( array_unique( $enabled ) ),
			'labels'    => $labels,
			'connected' => $connected,
			'order'     => array_values( array_unique( $order ) ),
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

		self::save_admin_state( $parsed['enabled'], $parsed['labels'], $parsed['connected'] ?? array(), $parsed['order'] ?? array() );
		return true;
	}

	/**
	 * Save visibility + labels from admin.
	 *
	 * @param array $enabled_slugs Enabled slugs.
	 * @param array $labels        Slug => label.
	 * @param array $connected     Slug => connected_right bool (custom only).
	 * @param array $order         Ordered slug list.
	 */
	public static function save_admin_state( $enabled_slugs, $labels, $connected = array(), $order = array() ) {
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
}
