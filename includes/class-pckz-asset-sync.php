<?php
/**
 * Master → client asset synchronization (custom lines, icons, presets).
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PCKZ_Asset_Sync
 */
class PCKZ_Asset_Sync {

	const OPTION_MANIFEST     = 'pckzce_master_asset_manifest';
	const OPTION_CLIENT_STATE = 'pckzce_client_asset_sync';
	const SYNC_INTERVAL_SEC   = 21600;
	const PRESET_SUBDIR       = 'pckz-canonical-engine/presets';

	/**
	 * Register REST + catalog hooks.
	 */
	public static function register_hooks() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
		if ( class_exists( 'PCKZ_Master_Control' ) ) {
			PCKZ_Master_Control::register_hooks();
		}
	}

	/**
	 * REST routes (master serves; clients consume).
	 */
	public static function register_rest_routes() {
		if ( ! class_exists( 'PCKZ_Licensing' ) || ! PCKZ_Licensing::is_master_mode() ) {
			return;
		}
		register_rest_route(
			'pckzce-license/v1',
			'/client/asset-manifest',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest_client_asset_manifest' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			'pckzce-license/v1',
			'/client/asset-file',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest_client_asset_file' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Bump manifest revision on master.
	 */
	public static function bump_manifest_revision() {
		if ( ! PCKZ_Licensing::is_master_mode() ) {
			return;
		}
		$manifest = self::build_manifest( true );
		update_option( self::OPTION_MANIFEST, $manifest, false );
	}

	/**
	 * Build asset manifest from master uploads (custom assets only).
	 *
	 * @param bool $force Rebuild.
	 * @return array
	 */
	public static function build_manifest( $force = false ) {
		$cached = get_option( self::OPTION_MANIFEST, array() );
		if ( ! $force && is_array( $cached ) && ! empty( $cached['revision'] ) && ! empty( $cached['assets'] ) ) {
			return $cached;
		}
		$assets = array();
		if ( class_exists( 'PCKZ_Line_Library' ) ) {
			foreach ( PCKZ_Line_Library::custom_manifest() as $slug => $meta ) {
				$file = sanitize_file_name( (string) ( $meta['file'] ?? $slug . '.svg' ) );
				$path = PCKZ_Line_Library::upload_dir() . '/' . $file;
				if ( ! is_readable( $path ) ) {
					continue;
				}
				$assets[] = self::asset_row( 'line', $slug, $path, $file, $meta );
			}
		}
		if ( class_exists( 'PCKZ_Icon_Library' ) ) {
			foreach ( PCKZ_Icon_Library::custom_manifest() as $slug => $meta ) {
				$file = sanitize_file_name( (string) ( $meta['file'] ?? $slug . '.svg' ) );
				$path = PCKZ_Icon_Library::upload_dir() . '/' . $file;
				if ( ! is_readable( $path ) ) {
					continue;
				}
				$assets[] = self::asset_row( 'icon', $slug, $path, $file, $meta );
			}
		}
		$presets_dir = self::presets_dir();
		if ( is_dir( $presets_dir ) ) {
			$files = glob( $presets_dir . '/*.{json,svg,png,jpg,jpeg,webp}', GLOB_BRACE );
			if ( is_array( $files ) ) {
				foreach ( $files as $path ) {
					$basename = basename( $path );
					$slug     = sanitize_key( pathinfo( $basename, PATHINFO_FILENAME ) );
					if ( ! $slug ) {
						continue;
					}
					$assets[] = self::asset_row( 'preset', $slug, $path, $basename, array() );
				}
			}
		}
		$revision = self::hash_assets( $assets );
		return array(
			'revision'     => $revision,
			'generated_at' => gmdate( 'c' ),
			'assets'       => $assets,
		);
	}

	/**
	 * @param string $type   Asset type.
	 * @param string $slug   Slug.
	 * @param string $path   Absolute path.
	 * @param string $file   Filename.
	 * @param array  $meta   Catalog meta.
	 * @return array
	 */
	private static function asset_row( $type, $slug, $path, $file, $meta ) {
		return array(
			'type'     => sanitize_key( $type ),
			'slug'     => sanitize_key( $slug ),
			'file'     => sanitize_file_name( $file ),
			'sha256'   => hash_file( 'sha256', $path ),
			'size'     => (int) filesize( $path ),
			'meta'     => is_array( $meta ) ? $meta : array(),
		);
	}

	/**
	 * @param array $assets Asset rows.
	 * @return string
	 */
	private static function hash_assets( $assets ) {
		$parts = array();
		foreach ( $assets as $row ) {
			$parts[] = implode(
				'|',
				array(
					$row['type'] ?? '',
					$row['slug'] ?? '',
					$row['sha256'] ?? '',
				)
			);
		}
		sort( $parts );
		return substr( hash( 'sha256', implode( "\n", $parts ) ), 0, 32 );
	}

	/**
	 * Presets directory on master.
	 *
	 * @return string
	 */
	public static function presets_dir() {
		$upload = wp_upload_dir();
		$dir    = trailingslashit( $upload['basedir'] ) . self::PRESET_SUBDIR;
		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		return $dir;
	}

	/**
	 * Client asset manifest endpoint.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function rest_client_asset_manifest( WP_REST_Request $request ) {
		if ( ! PCKZ_Licensing::is_master_mode() ) {
			return rest_ensure_response( array( 'ok' => false, 'reason' => 'master_mode_disabled' ) );
		}
		$licensing = new PCKZ_Licensing();
		$body_raw  = (string) $request->get_body();
		$payload   = json_decode( $body_raw, true );
		if ( ! is_array( $payload ) ) {
			$payload = array();
		}
		$validated = $licensing->validate_client_payload_public( $payload, $request, $body_raw );
		if ( is_wp_error( $validated ) ) {
			return rest_ensure_response( array( 'ok' => false, 'reason' => $validated->get_error_message() ) );
		}
		if ( empty( $validated['permissions']['asset_sync'] ) && empty( $validated['permissions']['updates'] ) ) {
			return rest_ensure_response( array( 'ok' => false, 'reason' => 'asset_sync_not_allowed' ) );
		}
		$manifest = self::build_manifest( false );
		$client_rev = sanitize_text_field( (string) ( $payload['asset_revision'] ?? '' ) );
		if ( $client_rev && $client_rev === (string) ( $manifest['revision'] ?? '' ) ) {
			return rest_ensure_response(
				array(
					'ok'       => true,
					'current'  => true,
					'revision' => $client_rev,
					'assets'   => array(),
				)
			);
		}
		return rest_ensure_response(
			array(
				'ok'       => true,
				'current'  => false,
				'revision' => (string) ( $manifest['revision'] ?? '' ),
				'assets'   => $manifest['assets'] ?? array(),
			)
		);
	}

	/**
	 * Stream one asset file to licensed client.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public static function rest_client_asset_file( WP_REST_Request $request ) {
		if ( ! PCKZ_Licensing::is_master_mode() ) {
			status_header( 403 );
			exit;
		}
		$token = sanitize_text_field( (string) $request->get_param( 'token' ) );
		$decoded = PCKZ_Licensing::verify_signed_token_public( $token );
		if ( is_wp_error( $decoded ) || 'asset_file' !== (string) ( $decoded['typ'] ?? '' ) ) {
			status_header( 403 );
			exit;
		}
		$type = sanitize_key( (string) ( $decoded['asset_type'] ?? '' ) );
		$slug = sanitize_key( (string) ( $decoded['asset_slug'] ?? '' ) );
		$file = sanitize_file_name( (string) ( $decoded['asset_file'] ?? '' ) );
		$path = self::resolve_master_asset_path( $type, $slug, $file );
		if ( ! $path || ! is_readable( $path ) ) {
			status_header( 404 );
			exit;
		}
		$mime = 'application/octet-stream';
		if ( preg_match( '/\.svg$/i', $file ) ) {
			$mime = 'image/svg+xml';
		} elseif ( preg_match( '/\.json$/i', $file ) ) {
			$mime = 'application/json';
		}
		header( 'Content-Type: ' . $mime );
		header( 'Content-Disposition: attachment; filename="' . $file . '"' );
		header( 'Content-Length: ' . (string) filesize( $path ) );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		readfile( $path );
		exit;
	}

	/**
	 * @param string $type Asset type.
	 * @param string $slug Slug.
	 * @param string $file Filename.
	 * @return string
	 */
	private static function resolve_master_asset_path( $type, $slug, $file ) {
		if ( ! $slug || ! $file ) {
			return '';
		}
		if ( 'line' === $type && class_exists( 'PCKZ_Line_Library' ) ) {
			$path = PCKZ_Line_Library::upload_dir() . '/' . $file;
			return is_readable( $path ) ? $path : '';
		}
		if ( 'icon' === $type && class_exists( 'PCKZ_Icon_Library' ) ) {
			$path = PCKZ_Icon_Library::upload_dir() . '/' . $file;
			return is_readable( $path ) ? $path : '';
		}
		if ( 'preset' === $type ) {
			$path = self::presets_dir() . '/' . $file;
			return is_readable( $path ) ? $path : '';
		}
		return '';
	}

	/**
	 * Build download token for one asset.
	 *
	 * @param array $asset Asset row from manifest.
	 * @return string
	 */
	public static function build_asset_download_token( $asset ) {
		return PCKZ_Licensing::build_signed_token_public(
			array(
				'typ'        => 'asset_file',
				'asset_type' => (string) ( $asset['type'] ?? '' ),
				'asset_slug' => (string) ( $asset['slug'] ?? '' ),
				'asset_file' => (string) ( $asset['file'] ?? '' ),
				'exp'        => time() + ( 15 * MINUTE_IN_SECONDS ),
			)
		);
	}

	/**
	 * Client: sync assets if due (after successful license check-in).
	 */
	public static function maybe_sync_client() {
		if ( PCKZ_Licensing::is_master_mode() ) {
			return;
		}
		$state = get_option( self::OPTION_CLIENT_STATE, array() );
		if ( ! is_array( $state ) ) {
			$state = array();
		}
		$last = (int) ( $state['last_sync_at'] ?? 0 );
		if ( $last && ( time() - $last ) < self::SYNC_INTERVAL_SEC ) {
			return;
		}
		$license_state = PCKZ_Licensing::get_client_state();
		if ( empty( $license_state['authorized'] ) ) {
			return;
		}
		$result = self::run_client_sync();
		$state['last_sync_at'] = time();
		$state['last_result']  = is_wp_error( $result ) ? $result->get_error_message() : 'ok';
		if ( ! is_wp_error( $result ) && ! empty( $result['revision'] ) ) {
			$state['revision'] = $result['revision'];
		}
		update_option( self::OPTION_CLIENT_STATE, $state, false );
	}

	/**
	 * Pull manifest and apply missing/changed assets.
	 *
	 * @return array|WP_Error
	 */
	public static function run_client_sync() {
		$settings = PCKZ_Settings::get_all();
		$master   = trailingslashit( (string) ( $settings['licensing_master_url'] ?? '' ) );
		$key      = trim( (string) ( $settings['licensing_key'] ?? '' ) );
		if ( '' === $master || '' === $key ) {
			return new WP_Error( 'unconfigured', __( 'License not configured.', 'pckz-canonical-engine' ) );
		}
		$client_state = get_option( self::OPTION_CLIENT_STATE, array() );
		$payload      = array(
			'license_key'    => $key,
			'domain'         => PCKZ_Licensing::normalized_domain_public(),
			'install_uuid'   => PCKZ_Licensing::get_install_uuid(),
			'plugin_version' => PCKZCE_VERSION,
			'asset_revision' => (string) ( $client_state['revision'] ?? '' ),
		);
		$body    = wp_json_encode( $payload );
		$headers = PCKZ_Licensing::build_signed_request_headers_public( $body );
		$resp    = wp_remote_post(
			$master . 'wp-json/pckzce-license/v1/client/asset-manifest',
			array(
				'timeout' => 30,
				'headers' => $headers,
				'body'    => $body,
			)
		);
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $resp ), true );
		if ( ! is_array( $data ) || empty( $data['ok'] ) ) {
			return new WP_Error( 'sync_failed', sanitize_text_field( (string) ( $data['reason'] ?? 'Asset sync failed.' ) ) );
		}
		if ( ! empty( $data['current'] ) ) {
			return array( 'revision' => (string) ( $data['revision'] ?? '' ), 'applied' => 0 );
		}
		$applied = 0;
		foreach ( (array) ( $data['assets'] ?? array() ) as $asset ) {
			if ( ! is_array( $asset ) ) {
				continue;
			}
			$ok = self::apply_client_asset( $asset, $master );
			if ( ! is_wp_error( $ok ) ) {
				++$applied;
			}
		}
		self::merge_catalog_meta_from_manifest( (array) ( $data['assets'] ?? array() ) );
		$revision = (string) ( $data['revision'] ?? '' );
		PCKZ_Licensing::report_asset_sync_complete_public( $revision );
		return array( 'revision' => $revision, 'applied' => $applied );
	}

	/**
	 * Download and store one asset on client.
	 *
	 * @param array  $asset  Manifest row.
	 * @param string $master Master base URL.
	 * @return true|WP_Error
	 */
	private static function apply_client_asset( $asset, $master ) {
		$type = sanitize_key( (string) ( $asset['type'] ?? '' ) );
		$slug = sanitize_key( (string) ( $asset['slug'] ?? '' ) );
		$file = sanitize_file_name( (string) ( $asset['file'] ?? '' ) );
		if ( ! $type || ! $slug || ! $file ) {
			return new WP_Error( 'invalid_asset', 'Invalid asset row.' );
		}
		$dest = self::client_dest_path( $type, $file );
		if ( ! $dest ) {
			return new WP_Error( 'invalid_dest', 'Invalid destination.' );
		}
		if ( is_readable( $dest ) ) {
			$local_hash = hash_file( 'sha256', $dest );
			if ( ! empty( $asset['sha256'] ) && hash_equals( (string) $asset['sha256'], $local_hash ) ) {
				return true;
			}
		}
		$token = self::build_asset_download_token( $asset );
		$url   = add_query_arg( 'token', rawurlencode( $token ), $master . 'wp-json/pckzce-license/v1/client/asset-file' );
		$resp  = wp_remote_get( $url, array( 'timeout' => 45 ) );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$code = (int) wp_remote_retrieve_response_code( $resp );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'download_failed', 'Asset download failed: ' . $code );
		}
		$body = wp_remote_retrieve_body( $resp );
		if ( '' === $body ) {
			return new WP_Error( 'empty_file', 'Empty asset file.' );
		}
		$dir = dirname( $dest );
		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( false === file_put_contents( $dest, $body ) ) {
			return new WP_Error( 'write_failed', 'Could not write asset.' );
		}
		if ( ! empty( $asset['sha256'] ) ) {
			$written = hash_file( 'sha256', $dest );
			if ( ! hash_equals( (string) $asset['sha256'], $written ) ) {
				unlink( $dest );
				return new WP_Error( 'hash_mismatch', 'Asset integrity check failed.' );
			}
		}
		return true;
	}

	/**
	 * @param string $type Asset type.
	 * @param string $file Filename.
	 * @return string
	 */
	private static function client_dest_path( $type, $file ) {
		$upload = wp_upload_dir();
		$base   = trailingslashit( $upload['basedir'] );
		if ( 'line' === $type ) {
			return $base . 'pckz-canonical-engine/lines/' . $file;
		}
		if ( 'icon' === $type ) {
			return $base . 'pckz-canonical-engine/icons/' . $file;
		}
		if ( 'preset' === $type ) {
			return $base . self::PRESET_SUBDIR . '/' . $file;
		}
		return '';
	}

	/**
	 * Merge manifest meta into local line/icon custom options (non-destructive).
	 *
	 * @param array $assets Manifest assets.
	 */
	private static function merge_catalog_meta_from_manifest( $assets ) {
		$lines = array();
		$icons = array();
		foreach ( $assets as $asset ) {
			if ( ! is_array( $asset ) || empty( $asset['meta'] ) || ! is_array( $asset['meta'] ) ) {
				continue;
			}
			$slug = sanitize_key( (string) ( $asset['slug'] ?? '' ) );
			if ( ! $slug ) {
				continue;
			}
			if ( 'line' === ( $asset['type'] ?? '' ) ) {
				$lines[ $slug ] = $asset['meta'];
			} elseif ( 'icon' === ( $asset['type'] ?? '' ) ) {
				$icons[ $slug ] = $asset['meta'];
			}
		}
		if ( ! empty( $lines ) && class_exists( 'PCKZ_Line_Library' ) ) {
			self::merge_custom_option( 'pckz_line_custom', $lines );
		}
		if ( ! empty( $icons ) && class_exists( 'PCKZ_Icon_Library' ) ) {
			self::merge_custom_option( 'pckz_icon_custom', $icons );
		}
	}

	/**
	 * @param string $option Option name.
	 * @param array  $incoming Incoming slug => meta.
	 */
	private static function merge_custom_option( $option, $incoming ) {
		$current = get_option( $option, array() );
		if ( ! is_array( $current ) ) {
			$current = array();
		}
		foreach ( $incoming as $slug => $meta ) {
			if ( ! is_array( $meta ) ) {
				continue;
			}
			$slug = sanitize_key( $slug );
			if ( ! isset( $current[ $slug ] ) ) {
				$current[ $slug ] = $meta;
				continue;
			}
			$current[ $slug ] = array_merge( $current[ $slug ], $meta );
			if ( ! empty( $meta['file'] ) ) {
				$current[ $slug ]['file'] = sanitize_file_name( $meta['file'] );
			}
		}
		update_option( $option, $current, false );
	}
}
