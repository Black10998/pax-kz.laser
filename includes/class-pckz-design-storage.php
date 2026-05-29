<?php
/**
 * Design persistence (database + files).
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PCKZ_Design_Storage
 */
class PCKZ_Design_Storage {

	/**
	 * Get table name.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'pckz_designs';
	}

	/**
	 * Create database table.
	 */
	public static function create_table() {
		global $wpdb;

		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			product_id bigint(20) unsigned NOT NULL DEFAULT 0,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			canvas_json longtext NOT NULL,
			preview_url varchar(500) DEFAULT '',
			export_url varchar(500) DEFAULT '',
			meta_json longtext,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY product_id (product_id),
			KEY user_id (user_id)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Save design to database and disk.
	 *
	 * @param array $data Design data.
	 * @return int|WP_Error Design ID.
	 */
	public static function save_design( $data ) {
		global $wpdb;

		self::create_table();

		$upload_dir = wp_upload_dir();
		$base_dir   = $upload_dir['basedir'] . '/pckz-canonical-engine/designs';
		wp_mkdir_p( $base_dir );

		$preview_url = '';
		if ( ! empty( $data['preview_png'] ) ) {
			$preview_url = self::save_base64_image( $data['preview_png'], $base_dir, 'preview' );
			if ( is_wp_error( $preview_url ) ) {
				$preview_url = '';
			}
		}

		$meta_json = ! empty( $data['meta'] ) ? wp_json_encode( $data['meta'] ) : '';

		$inserted = $wpdb->insert(
			self::table_name(),
			array(
				'product_id'  => absint( $data['product_id'] ?? 0 ),
				'user_id'     => absint( $data['user_id'] ?? 0 ),
				'canvas_json' => $data['canvas_json'],
				'preview_url' => is_string( $preview_url ) ? $preview_url : '',
				'meta_json'   => $meta_json,
			),
			array( '%d', '%d', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'db_error', __( 'Failed to save design.', 'pckz-canonical-engine' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Get design by ID.
	 *
	 * @param int $design_id Design ID.
	 * @return array|null
	 */
	public static function get_design( $design_id ) {
		global $wpdb;

		$table = self::table_name();
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return null;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $design_id ),
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		if ( ! empty( $row['meta_json'] ) ) {
			$row['meta'] = json_decode( $row['meta_json'], true );
		}

		return $row;
	}

	/**
	 * Update design meta JSON.
	 *
	 * @param int   $design_id Design ID.
	 * @param array $meta      Meta array.
	 */
	public static function update_meta( $design_id, $meta ) {
		global $wpdb;
		$wpdb->update(
			self::table_name(),
			array( 'meta_json' => wp_json_encode( $meta ) ),
			array( 'id' => $design_id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Save base64 PNG to uploads.
	 *
	 * @param string $data_url Base64 data URL.
	 * @param string $dir      Target directory.
	 * @param string $prefix   Filename prefix.
	 * @return string|WP_Error Public URL.
	 */
	public static function save_base64_image( $data_url, $dir, $prefix = 'img' ) {
		if ( ! preg_match( '/^data:image\/(\w+);base64,/', $data_url, $matches ) ) {
			return new WP_Error( 'invalid_image', __( 'Invalid image data.', 'pckz-canonical-engine' ) );
		}

		$extension = strtolower( $matches[1] );
		if ( 'jpeg' === $extension ) {
			$extension = 'jpg';
		}

		$raw = base64_decode( substr( $data_url, strpos( $data_url, ',' ) + 1 ) );
		if ( false === $raw ) {
			return new WP_Error( 'decode_error', __( 'Could not decode image.', 'pckz-canonical-engine' ) );
		}

		$filename = $prefix . '-' . wp_generate_uuid4() . '.' . $extension;
		$filepath = trailingslashit( $dir ) . $filename;

		if ( false === file_put_contents( $filepath, $raw ) ) {
			return new WP_Error( 'write_error', __( 'Could not write file.', 'pckz-canonical-engine' ) );
		}

		$upload_dir = wp_upload_dir();
		$url        = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $filepath );

		return $url;
	}

	/**
	 * Save export file from base64 PNG.
	 *
	 * @param string $png_data   Base64 PNG.
	 * @param int    $product_id Product ID.
	 * @param string $format     File format.
	 * @return string|WP_Error URL.
	 */
	public static function save_export_file( $png_data, $product_id, $format = 'png' ) {
		$upload_dir = wp_upload_dir();
		$dir        = $upload_dir['basedir'] . '/pckz-canonical-engine/exports';
		wp_mkdir_p( $dir );

		$url = self::save_base64_image( $png_data, $dir, 'export-p' . $product_id );

		if ( ! is_wp_error( $url ) ) {
			global $wpdb;
			// Store latest export URL on most recent design if provided via POST.
			$design_id = isset( $_POST['design_id'] ) ? absint( $_POST['design_id'] ) : 0;
			if ( $design_id && $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', self::table_name() ) ) === self::table_name() ) {
				$wpdb->update(
					self::table_name(),
					array( 'export_url' => $url ),
					array( 'id' => $design_id ),
					array( '%s' ),
					array( '%d' )
				);
			}
		}

		return $url;
	}
}
