<?php
/**
 * Optional customer-provided artwork uploads (checkout), attached to orders for production.
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PCKZ_Customer_Artwork
 */
class PCKZ_Customer_Artwork {

	const UPLOAD_SUBDIR = 'pckz-canonical-engine/customer-artwork';
	const MAX_BYTES     = 5242880;
	const META_KEY      = 'customer_artwork';

	/**
	 * Allowed extensions => mime hints for wp_check_filetype.
	 *
	 * @return array<string,string>
	 */
	public static function allowed_mimes() {
		return array(
			'svg'  => 'image/svg+xml',
			'png'  => 'image/png',
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'webp' => 'image/webp',
		);
	}

	/**
	 * Storage directory (not web-public; served via admin download only).
	 *
	 * @return string
	 */
	public static function storage_dir() {
		$upload = wp_upload_dir();
		$dir    = trailingslashit( $upload['basedir'] ) . self::UPLOAD_SUBDIR;
		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		$htaccess = $dir . '/.htaccess';
		if ( ! is_readable( $htaccess ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $htaccess, "Deny from all\n" );
		}
		$index = $dir . '/index.php';
		if ( ! is_readable( $index ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}
		return $dir;
	}

	/**
	 * Handle checkout artwork upload.
	 *
	 * @param array $file $_FILES row.
	 * @param int   $design_id Optional design to bind immediately.
	 * @return array|WP_Error { token, filename, size, mime }
	 */
	public static function handle_upload( $file, $design_id = 0 ) {
		$tmp = $file['tmp_name'] ?? '';
		$valid_upload = is_string( $tmp ) && '' !== $tmp && is_readable( $tmp )
			&& ( is_uploaded_file( $tmp ) || ( defined( 'PCKZ_SMOKE_TEST' ) && PCKZ_SMOKE_TEST ) );
		if ( ! $valid_upload ) {
			return new WP_Error( 'no_file', __( 'Keine Datei empfangen.', 'pckz-canonical-engine' ) );
		}
		if ( ! empty( $file['size'] ) && (int) $file['size'] > self::MAX_BYTES ) {
			return new WP_Error(
				'too_large',
				sprintf(
					/* translators: %d: max MB */
					__( 'Die Datei ist zu groß (max. %d MB).', 'pckz-canonical-engine' ),
					(int) ( self::MAX_BYTES / 1048576 )
				)
			);
		}

		$name = isset( $file['name'] ) ? sanitize_file_name( wp_unslash( $file['name'] ) ) : 'upload';
		$check = wp_check_filetype( $name, self::allowed_mimes() );
		if ( empty( $check['ext'] ) || ! isset( self::allowed_mimes()[ $check['ext'] ] ) ) {
			return new WP_Error(
				'bad_type',
				__( 'Erlaubt sind SVG, PNG, JPG, JPEG und WEBP.', 'pckz-canonical-engine' )
			);
		}

		if ( 'svg' === $check['ext'] ) {
			$contents = file_get_contents( $file['tmp_name'] );
			if ( false === $contents || ! self::is_safe_svg( $contents ) ) {
				return new WP_Error( 'unsafe_svg', __( 'SVG enthält nicht erlaubte Inhalte.', 'pckz-canonical-engine' ) );
			}
		}

		$token    = 'art_' . wp_generate_password( 16, false, false );
		$filename = $token . '.' . $check['ext'];
		$dest     = self::storage_dir() . '/' . $filename;
		$moved = false;
		if ( defined( 'PCKZ_SMOKE_TEST' ) && PCKZ_SMOKE_TEST ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
			$moved = copy( $tmp, $dest );
		} else {
			$moved = move_uploaded_file( $tmp, $dest );
		}
		if ( ! $moved ) {
			return new WP_Error( 'write_fail', __( 'Datei konnte nicht gespeichert werden.', 'pckz-canonical-engine' ) );
		}

		$meta = array(
			'token'      => $token,
			'filename'   => $name,
			'stored'     => $filename,
			'mime'       => $check['type'] ?: self::allowed_mimes()[ $check['ext'] ],
			'size'       => (int) ( $file['size'] ?? 0 ),
			'design_id'  => 0,
			'uploaded'   => current_time( 'mysql' ),
		);

		self::save_meta( $token, $meta );

		if ( $design_id > 0 ) {
			self::bind_to_design( $token, $design_id );
			$meta = self::get_meta( $token );
		}

		return array(
			'token'    => $token,
			'filename' => $meta['filename'] ?? $name,
			'size'     => $meta['size'] ?? 0,
			'mime'     => $meta['mime'] ?? '',
		);
	}

	/**
	 * Basic SVG safety check (customer uploads).
	 *
	 * @param string $svg SVG source.
	 * @return bool
	 */
	private static function is_safe_svg( $svg ) {
		if ( class_exists( 'PCKZ_Svg_Library' ) ) {
			return PCKZ_Svg_Library::is_safe_svg( $svg );
		}
		return ( stripos( $svg, '<script' ) === false && stripos( $svg, '<?php' ) === false ) && (bool) preg_match( '/<svg[\s>]/i', $svg );
	}

	/**
	 * @param string $token Artwork token.
	 * @return array|null
	 */
	public static function get_meta( $token ) {
		$token = self::sanitize_token( $token );
		if ( ! $token ) {
			return null;
		}
		$all = get_option( self::option_key( $token ), array() );
		return is_array( $all ) ? $all : null;
	}

	/**
	 * @param string $token Token.
	 * @param array  $meta  Meta row.
	 */
	private static function save_meta( $token, $meta ) {
		update_option( self::option_key( $token ), $meta, false );
	}

	/**
	 * @param string $token Token.
	 * @return string
	 */
	private static function option_key( $token ) {
		return 'pckz_customer_artwork_' . $token;
	}

	/**
	 * @param string $token Token.
	 * @return string
	 */
	public static function sanitize_token( $token ) {
		$token = sanitize_key( (string) $token );
		if ( 0 !== strpos( $token, 'art_' ) ) {
			return '';
		}
		return $token;
	}

	/**
	 * Link pending upload to a saved design.
	 *
	 * @param string $token     Upload token.
	 * @param int    $design_id Design ID.
	 * @return array|null Updated meta.
	 */
	public static function bind_to_design( $token, $design_id ) {
		$token     = self::sanitize_token( $token );
		$design_id = absint( $design_id );
		if ( ! $token || ! $design_id ) {
			return null;
		}
		$meta = self::get_meta( $token );
		if ( ! $meta || empty( $meta['stored'] ) ) {
			return null;
		}
		$meta['design_id'] = $design_id;
		self::save_meta( $token, $meta );
		self::persist_on_design( $design_id, $meta );
		return $meta;
	}

	/**
	 * Store artwork reference on design meta_json.
	 *
	 * @param int   $design_id Design ID.
	 * @param array $meta      Artwork meta.
	 */
	public static function persist_on_design( $design_id, $meta ) {
		if ( ! class_exists( 'PCKZ_Design_Storage' ) || empty( $meta['stored'] ) ) {
			return;
		}
		$design = PCKZ_Design_Storage::get_design( $design_id );
		if ( ! $design ) {
			return;
		}
		$decoded = array();
		if ( ! empty( $design['meta_json'] ) ) {
			$decoded = json_decode( $design['meta_json'], true );
		}
		if ( ! is_array( $decoded ) ) {
			$decoded = array();
		}
		$decoded[ self::META_KEY ] = self::public_meta( $meta );
		PCKZ_Design_Storage::update_meta( $design_id, $decoded );
	}

	/**
	 * @param array $meta Raw meta.
	 * @return array
	 */
	public static function public_meta( $meta ) {
		return array(
			'token'    => sanitize_key( $meta['token'] ?? '' ),
			'filename' => sanitize_file_name( $meta['filename'] ?? '' ),
			'stored'   => sanitize_file_name( $meta['stored'] ?? '' ),
			'mime'     => sanitize_text_field( $meta['mime'] ?? '' ),
			'size'     => absint( $meta['size'] ?? 0 ),
		);
	}

	/**
	 * @param int $design_id Design ID.
	 * @return array|null
	 */
	public static function get_for_design( $design_id ) {
		$design_id = absint( $design_id );
		if ( ! $design_id || ! class_exists( 'PCKZ_Design_Storage' ) ) {
			return null;
		}
		$design = PCKZ_Design_Storage::get_design( $design_id );
		if ( ! $design || empty( $design['meta_json'] ) ) {
			return null;
		}
		$meta = json_decode( $design['meta_json'], true );
		if ( ! is_array( $meta ) || empty( $meta[ self::META_KEY ] ) ) {
			return null;
		}
		$row = $meta[ self::META_KEY ];
		return self::resolve_file_meta( $row );
	}

	/**
	 * @param array $row Artwork meta row.
	 * @return array|null
	 */
	public static function resolve_file_meta( $row ) {
		if ( ! is_array( $row ) || empty( $row['stored'] ) ) {
			return null;
		}
		$path = self::storage_dir() . '/' . sanitize_file_name( $row['stored'] );
		if ( ! is_readable( $path ) ) {
			return null;
		}
		$row['path'] = $path;
		return $row;
	}

	/**
	 * Merge artwork into customer details array.
	 *
	 * @param array $details Customer details.
	 * @return array
	 */
	/**
	 * Attach pending upload token to sanitized customer details.
	 *
	 * @param array  $details Customer details.
	 * @param string $token   Artwork token from checkout.
	 * @return array
	 */
	public static function apply_token_to_details( $details, $token ) {
		if ( ! is_array( $details ) ) {
			$details = array();
		}
		$token = self::sanitize_token( $token );
		if ( ! $token ) {
			return $details;
		}
		$meta = self::get_meta( $token );
		if ( ! $meta ) {
			return $details;
		}
		$details['customer_artwork'] = self::public_meta( $meta );
		return $details;
	}

	/**
	 * Read artwork token from POST and merge into details.
	 *
	 * @param array $details Customer details.
	 * @return array
	 */
	public static function apply_token_from_request( $details ) {
		$token = isset( $_POST['customer_artwork_token'] )
			? sanitize_key( wp_unslash( $_POST['customer_artwork_token'] ) )
			: '';
		if ( '' === $token && ! empty( $_POST['customer_details'] ) ) {
			$decoded = json_decode( wp_unslash( $_POST['customer_details'] ), true );
			if ( is_array( $decoded ) && ! empty( $decoded['customer_artwork_token'] ) ) {
				$token = sanitize_key( $decoded['customer_artwork_token'] );
			}
		}
		return self::apply_token_to_details( $details, $token );
	}

	/**
	 * Bind artwork from customer details to a saved design.
	 *
	 * @param int   $design_id Design ID.
	 * @param array $details   Customer details (may include customer_artwork).
	 */
	public static function sync_with_design( $design_id, $details ) {
		$design_id = absint( $design_id );
		if ( ! $design_id || empty( $details['customer_artwork'] ) || ! is_array( $details['customer_artwork'] ) ) {
			return;
		}
		$row = $details['customer_artwork'];
		if ( ! empty( $row['token'] ) ) {
			self::bind_to_design( $row['token'], $design_id );
			return;
		}
		if ( ! empty( $row['stored'] ) ) {
			self::persist_on_design( $design_id, $row );
		}
	}

	/**
	 * Sanitize artwork meta for storage on orders.
	 *
	 * @param array $raw Raw artwork row.
	 * @return array|null
	 */
	public static function sanitize_artwork_for_storage( $raw ) {
		if ( ! is_array( $raw ) ) {
			return null;
		}
		if ( ! empty( $raw['token'] ) ) {
			$meta = self::get_meta( $raw['token'] );
			if ( $meta ) {
				return self::public_meta( $meta );
			}
		}
		$resolved = self::resolve_file_meta( $raw );
		if ( $resolved ) {
			return self::public_meta( $resolved );
		}
		if ( empty( $raw['stored'] ) ) {
			return null;
		}
		return self::public_meta( $raw );
	}

	/**
	 * Admin-post handler entry.
	 */
	public static function handle_admin_download() {
		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		if ( ! $order_id || ! wp_verify_nonce(
			isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '',
			'pckz_download_customer_artwork_' . $order_id
		) ) {
			wp_die( esc_html__( 'Ungültige Anfrage.', 'pckz-canonical-engine' ), 403 );
		}
		$result = self::serve_admin_download( $order_id );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 404 ) );
		}
	}

	public static function merge_into_customer_details( $details ) {
		if ( ! is_array( $details ) ) {
			$details = array();
		}
		if ( ! empty( $details['customer_artwork'] ) && is_array( $details['customer_artwork'] ) ) {
			$resolved = self::resolve_file_meta( $details['customer_artwork'] );
			if ( $resolved ) {
				$details['customer_artwork'] = self::public_meta( $resolved );
			}
		}
		return $details;
	}

	/**
	 * Absolute path for stored file.
	 *
	 * @param array $artwork Artwork meta with stored key.
	 * @return string
	 */
	public static function file_path( $artwork ) {
		if ( empty( $artwork['stored'] ) ) {
			return '';
		}
		return self::storage_dir() . '/' . sanitize_file_name( $artwork['stored'] );
	}

	/**
	 * Admin download URL for an order's customer artwork.
	 *
	 * @param int $order_id Commerce order ID.
	 * @return string
	 */
	public static function admin_download_url( $order_id ) {
		$order_id = absint( $order_id );
		if ( ! $order_id ) {
			return '';
		}
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=pckz_download_customer_artwork&order_id=' . $order_id ),
			'pckz_download_customer_artwork_' . $order_id
		);
	}

	/**
	 * Stream artwork file to browser (admin only).
	 *
	 * @param int $order_id Commerce order ID.
	 * @return true|WP_Error
	 */
	public static function serve_admin_download( $order_id ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'forbidden', __( 'Keine Berechtigung.', 'pckz-canonical-engine' ) );
		}
		$order_id = absint( $order_id );
		if ( ! $order_id || ! class_exists( 'PCKZ_Commerce' ) ) {
			return new WP_Error( 'not_found', __( 'Bestellung nicht gefunden.', 'pckz-canonical-engine' ) );
		}
		$order = PCKZ_Commerce::get_order( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'not_found', __( 'Bestellung nicht gefunden.', 'pckz-canonical-engine' ) );
		}
		$details = PCKZ_Commerce::decode_customer_details( $order['customer_details'] ?? '' );
		$artwork = ! empty( $details['customer_artwork'] ) ? self::resolve_file_meta( $details['customer_artwork'] ) : null;
		if ( ! $artwork && ! empty( $order['design_id'] ) ) {
			$artwork = self::get_for_design( (int) $order['design_id'] );
		}
		if ( ! $artwork || empty( $artwork['path'] ) || ! is_readable( $artwork['path'] ) ) {
			return new WP_Error( 'not_found', __( 'Kundendatei nicht gefunden.', 'pckz-canonical-engine' ) );
		}
		$filename = ! empty( $artwork['filename'] ) ? $artwork['filename'] : basename( $artwork['path'] );
		$mime     = ! empty( $artwork['mime'] ) ? $artwork['mime'] : 'application/octet-stream';
		header( 'Content-Type: ' . $mime );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		header( 'Content-Length: ' . (string) filesize( $artwork['path'] ) );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		readfile( $artwork['path'] );
		exit;
	}
}
