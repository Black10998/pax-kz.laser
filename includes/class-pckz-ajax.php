<?php
/**
 * AJAX handlers for uploads, saves, exports.
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PCKZ_Ajax
 */
class PCKZ_Ajax {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$actions = array(
			'pckzce_upload_image',
			'pckzce_upload_customer_artwork',
			'pckzce_save_design',
			'pckzce_export_design',
			'pckzce_add_to_cart',
			'pckzce_create_paypal_order',
			'pckzce_create_payment_order',
			'pckzce_export_validate',
			'pckzce_font_file',
			'pckzce_runtime_config',
			'pckzce_resolve_option_asset',
			'pckzce_resolve_option_assets',
			'pckzce_secure_asset',
		);

		foreach ( $actions as $action ) {
			add_action( 'wp_ajax_' . $action, array( $this, str_replace( 'pckzce_', 'handle_', $action ) ) );
			add_action( 'wp_ajax_nopriv_' . $action, array( $this, str_replace( 'pckzce_', 'handle_', $action ) ) );
		}

		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Verify nonce for AJAX requests.
	 *
	 * @return bool
	 */
	private function verify_nonce() {
		$nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : '';
		return (bool) wp_verify_nonce( $nonce, 'pckzce_creator' );
	}

	/**
	 * Structured JSON error payload for export failures.
	 *
	 * @param string          $message User-facing message.
	 * @param int             $status  HTTP status.
	 * @param array           $extra   Extra fields.
	 * @param Throwable|null  $error   Optional exception.
	 */
	private function send_export_error( $message, $status = 500, $extra = array(), $error = null ) {
		$payload = array_merge(
			array( 'message' => $message ),
			$extra
		);
		if ( ! empty( $extra['validation']['errors'] ) && is_array( $extra['validation']['errors'] ) ) {
			$payload['errors'] = $extra['validation']['errors'];
		} elseif ( ! empty( $extra['errors'] ) && is_array( $extra['errors'] ) ) {
			$payload['errors'] = $extra['errors'];
		}
		$allow_debug_details = ( defined( 'WP_DEBUG' ) && WP_DEBUG && current_user_can( 'manage_options' ) );
		if ( $allow_debug_details && $error instanceof \Throwable ) {
			$payload['exception'] = get_class( $error );
			$payload['file']      = $error->getFile();
			$payload['line']      = $error->getLine();
		}
		wp_send_json_error( $payload, $status );
	}

	/**
	 * Enforce licensing for protected export actions (phase 1).
	 */
	private function enforce_export_license( $operation = 'export', $context = array() ) {
		if ( ! class_exists( 'PCKZ_Licensing' ) ) {
			return true;
		}
		$auth = PCKZ_Licensing::authorize_export_operation(
			array_merge(
				array( 'operation' => $operation ),
				is_array( $context ) ? $context : array()
			)
		);
		if ( is_wp_error( $auth ) ) {
			wp_send_json_error(
				array(
					'message' => $auth->get_error_message(),
					'code'    => $auth->get_error_code(),
				),
				403
			);
		}
		return $auth;
	}


	/**
	 * Register REST routes.
	 */
	public function register_rest_routes() {
		register_rest_route(
			'pckzce/v1',
			'/design/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_design' ),
				'permission_callback' => array( $this, 'rest_design_permission' ),
				'args'                => array(
					'id' => array(
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
				),
			)
		);
	}

	/**
	 * Permission callback for public design REST endpoint.
	 *
	 * Backward compatible by default; strict mode can be enabled in settings.
	 *
	 * @return bool
	 */
	public function rest_design_permission( $request = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		if ( ! class_exists( 'PCKZ_Settings' ) ) {
			return true;
		}
		$settings = PCKZ_Settings::get_all();
		if ( empty( $settings['licensing_harden_design_rest'] ) ) {
			return true;
		}
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		$nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : '';
		return (bool) wp_verify_nonce( $nonce, 'pckzce_creator' );
	}

	/**
	 * REST: get design by ID.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function rest_get_design( $request ) {
		$design = PCKZ_Design_Storage::get_design( (int) $request['id'] );
		if ( ! $design ) {
			return new WP_Error( 'not_found', __( 'Design not found.', 'pckz-canonical-engine' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( $design );
	}

	/**
	 * Stream export-safe font binary for OpenType.js (same-origin; avoids stale gstatic woff2).
	 */
	public function handle_font_file() {
		$nonce = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'pckzce_font_file' ) ) {
			status_header( 403 );
			exit;
		}

		$font_id = isset( $_GET['font_id'] ) ? sanitize_key( wp_unslash( $_GET['font_id'] ) ) : '';
		if ( ! $font_id || ! class_exists( 'PCKZ_Font_Library' ) ) {
			status_header( 404 );
			exit;
		}

		$result = PCKZ_Font_Library::stream_font_binary( $font_id );
		if ( is_wp_error( $result ) ) {
			$status = (int) ( $result->get_error_data()['status'] ?? 404 );
			status_header( $status );
			exit;
		}
		exit;
	}

	/**
	 * Runtime-only creator config payload (keeps inline config minimal).
	 */
	public function handle_runtime_config() {
		if ( ! $this->verify_nonce() ) {
			wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'pckz-canonical-engine' ) ), 403 );
		}

		$product_id = isset( $_REQUEST['product_id'] ) ? absint( $_REQUEST['product_id'] ) : 0;
		if ( ! $product_id || ! class_exists( 'PCKZ_Post_Type' ) || ! PCKZ_Post_Type::is_publishable_product( $product_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Creator product not found.', 'pckz-canonical-engine' ) ), 404 );
		}

		$default_family = class_exists( 'PCKZ_Font_Library' )
			? PCKZ_Font_Library::default_customer_font_family()
			: 'Ubuntu';

		$payload = array(
			'defaultFontFamily' => $default_family,
		);

		wp_send_json_success( $payload );
	}

	/**
	 * Resolve a single selected option asset into a short-lived signed token URL.
	 */
	public function handle_resolve_option_asset() {
		if ( ! $this->verify_nonce() ) {
			wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'pckz-canonical-engine' ) ), 403 );
		}

		$product_id = isset( $_REQUEST['product_id'] ) ? absint( $_REQUEST['product_id'] ) : 0;
		if ( ! $product_id || ! class_exists( 'PCKZ_Post_Type' ) || ! PCKZ_Post_Type::is_publishable_product( $product_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Creator product not found.', 'pckz-canonical-engine' ) ), 404 );
		}

		$kind  = isset( $_REQUEST['asset_kind'] ) ? sanitize_key( wp_unslash( $_REQUEST['asset_kind'] ) ) : '';
		$value = isset( $_REQUEST['asset_value'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['asset_value'] ) ) : '';
		$data  = $this->resolve_option_asset_data( $product_id, $kind, $value );
		if ( is_wp_error( $data ) ) {
			wp_send_json_error( array( 'message' => $data->get_error_message() ), 404 );
		}

		wp_send_json_success( $data );
	}

	/**
	 * Resolve all currently selected preview assets in one request.
	 */
	public function handle_resolve_option_assets() {
		if ( ! $this->verify_nonce() ) {
			wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'pckz-canonical-engine' ) ), 403 );
		}

		$product_id = isset( $_REQUEST['product_id'] ) ? absint( $_REQUEST['product_id'] ) : 0;
		if ( ! $product_id || ! class_exists( 'PCKZ_Post_Type' ) || ! PCKZ_Post_Type::is_publishable_product( $product_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Creator product not found.', 'pckz-canonical-engine' ) ), 404 );
		}

		$selections = array();
		if ( ! empty( $_REQUEST['selections'] ) ) {
			$decoded = json_decode( wp_unslash( $_REQUEST['selections'] ), true );
			if ( is_array( $decoded ) ) {
				$selections = $decoded;
			}
		}

		$line_slug  = sanitize_key( (string) ( $selections['line'] ?? 'none' ) );
		$left_slug  = sanitize_key( (string) ( $selections['icon_left'] ?? 'none' ) );
		$right_slug = sanitize_key( (string) ( $selections['icon_right'] ?? 'none' ) );
		$font_value = sanitize_text_field( (string) ( $selections['font'] ?? '' ) );

		$payload = array(
			'line'       => $this->resolve_option_asset_data( $product_id, 'line', $line_slug ),
			'icon_left'  => $this->resolve_option_asset_data( $product_id, 'icon', $left_slug ),
			'icon_right' => $this->resolve_option_asset_data( $product_id, 'icon', $right_slug ),
			'font'       => $font_value ? $this->resolve_option_asset_data( $product_id, 'font', $font_value ) : array(),
		);

		foreach ( $payload as $row ) {
			if ( is_wp_error( $row ) ) {
				wp_send_json_error( array( 'message' => $row->get_error_message() ), 404 );
			}
		}

		wp_send_json_success( $payload );
	}

	/**
	 * Resolve one creator option asset payload.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $kind       Asset kind.
	 * @param string $value      Asset value.
	 * @return array|WP_Error
	 */
	private function resolve_option_asset_data( $product_id, $kind, $value ) {
		if ( 'icon' === $kind ) {
			$slug = sanitize_key( $value );
			if ( ! $slug || 'none' === $slug ) {
				return array( 'kind' => 'icon', 'slug' => 'none', 'url' => '', 'bg_url' => '' );
			}
			$catalog = class_exists( 'PCKZ_Ledos_Preview' ) ? PCKZ_Ledos_Preview::icon_catalog( false ) : array();
			if ( empty( $catalog[ $slug ] ) ) {
				return new WP_Error( 'icon_not_found', __( 'Icon not found.', 'pckz-canonical-engine' ) );
			}
			$row = is_array( $catalog[ $slug ] ) ? $catalog[ $slug ] : array();
			return array(
				'kind'            => 'icon',
				'slug'            => $slug,
				'tintable'        => array_key_exists( 'tintable', $row ) ? ! empty( $row['tintable'] ) : true,
				'preserve_colors' => ! empty( $row['preserve_colors'] ),
				'url'             => $this->secure_creator_asset_url( $product_id, 'icon', $slug ),
				'bg_url'          => $this->secure_creator_asset_url( $product_id, 'icon_bg', 'default' ),
			);
		}

		if ( 'line' === $kind ) {
			$slug = sanitize_key( $value );
			if ( ! $slug || 'none' === $slug ) {
				return array( 'kind' => 'line', 'slug' => 'none', 'url' => '' );
			}
			$catalog = class_exists( 'PCKZ_Ledos_Preview' ) ? PCKZ_Ledos_Preview::line_catalog_for_js() : array();
			if ( empty( $catalog[ $slug ] ) ) {
				return new WP_Error( 'line_not_found', __( 'Line not found.', 'pckz-canonical-engine' ) );
			}
			$row = is_array( $catalog[ $slug ] ) ? $catalog[ $slug ] : array();
			return array(
				'kind'            => 'line',
				'slug'            => $slug,
				'connected_right' => ! empty( $row['connected_right'] ),
				'tintable'        => array_key_exists( 'tintable', $row ) ? ! empty( $row['tintable'] ) : true,
				'preserve_colors' => ! empty( $row['preserve_colors'] ),
				'url'             => $this->secure_creator_asset_url( $product_id, 'line', $slug ),
			);
		}

		if ( 'font' === $kind ) {
			$font_id = $this->resolve_font_id_by_family( $value );
			if ( '' === $font_id ) {
				return new WP_Error( 'font_not_found', __( 'Font not found.', 'pckz-canonical-engine' ) );
			}
			$family   = sanitize_text_field( $value );
			$entries  = class_exists( 'PCKZ_Font_Library' ) ? PCKZ_Font_Library::all_entries() : array();
			$font_row = isset( $entries[ $font_id ] ) && is_array( $entries[ $font_id ] ) ? $entries[ $font_id ] : array();
			$font_url = class_exists( 'PCKZ_Font_Library' )
				? PCKZ_Font_Library::export_url_for_frontend( $font_id, $font_row )
				: '';
			if ( '' === $font_url ) {
				$font_url = $this->secure_creator_asset_url( $product_id, 'font', $font_id );
			}
			return array(
				'kind'        => 'font',
				'font_id'     => $font_id,
				'font_family' => $family,
				'family_key'  => strtolower( $family ),
				'url'         => $font_url,
			);
		}

		return new WP_Error( 'unsupported_asset', __( 'Unsupported asset type.', 'pckz-canonical-engine' ) );
	}

	/**
	 * Stream secure creator asset by signed token.
	 */
	public function handle_secure_asset() {
		$token = isset( $_REQUEST['token'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['token'] ) ) : '';
		$data  = $this->verify_creator_asset_token( $token );
		if ( is_wp_error( $data ) ) {
			status_header( 403 );
			exit;
		}

		$product_id = isset( $data['prd'] ) ? absint( $data['prd'] ) : 0;
		if ( ! $product_id || ! class_exists( 'PCKZ_Post_Type' ) || ! PCKZ_Post_Type::is_publishable_product( $product_id ) ) {
			status_header( 404 );
			exit;
		}

		$kind  = sanitize_key( (string) ( $data['kind'] ?? '' ) );
		$value = sanitize_text_field( (string) ( $data['val'] ?? '' ) );

		if ( 'line' === $kind ) {
			$this->serve_line_asset( sanitize_key( $value ) );
			return;
		}
		if ( 'icon' === $kind ) {
			$this->serve_icon_asset( sanitize_key( $value ) );
			return;
		}
		if ( 'icon_bg' === $kind ) {
			$this->serve_icon_background_asset();
			return;
		}
		if ( 'font' === $kind ) {
			$this->serve_font_asset( sanitize_key( $value ) );
			return;
		}

		status_header( 404 );
		exit;
	}

	/**
	 * Resolve font ID by family (case-insensitive).
	 *
	 * @param string $family Font family.
	 * @return string
	 */
	private function resolve_font_id_by_family( $family ) {
		if ( ! class_exists( 'PCKZ_Font_Library' ) ) {
			return '';
		}
		$target = strtolower( trim( (string) $family ) );
		if ( '' === $target ) {
			return '';
		}
		foreach ( PCKZ_Font_Library::get_customer_fonts() as $row ) {
			$row_family = strtolower( trim( (string) ( $row['family'] ?? '' ) ) );
			$row_id     = sanitize_key( (string) ( $row['id'] ?? '' ) );
			if ( '' !== $row_family && $row_family === $target && '' !== $row_id ) {
				return $row_id;
			}
		}
		return '';
	}

	/**
	 * Build secure asset URL backed by a signed token.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $kind       Asset kind.
	 * @param string $value      Asset value.
	 * @return string
	 */
	private function secure_creator_asset_url( $product_id, $kind, $value ) {
		$token = $this->create_creator_asset_token(
			array(
				'typ'  => 'creator_asset',
				'prd'  => (int) $product_id,
				'kind' => sanitize_key( (string) $kind ),
				'val'  => sanitize_text_field( (string) $value ),
				'exp'  => time() + 900,
			)
		);
		return add_query_arg(
			array(
				'action' => 'pckzce_secure_asset',
				'token'  => $token,
			),
			admin_url( 'admin-ajax.php' )
		);
	}

	/**
	 * Create signed token for creator asset.
	 *
	 * @param array $payload Payload.
	 * @return string
	 */
	private function create_creator_asset_token( $payload ) {
		if ( class_exists( 'PCKZ_Licensing' ) && method_exists( 'PCKZ_Licensing', 'build_signed_token_public' ) ) {
			return PCKZ_Licensing::build_signed_token_public( $payload );
		}
		$json = wp_json_encode( $payload );
		$body = rtrim( strtr( base64_encode( (string) $json ), '+/', '-_' ), '=' );
		$sig  = hash_hmac( 'sha256', $body, wp_salt( 'auth' ) );
		return $body . '.' . $sig;
	}

	/**
	 * Verify creator asset token.
	 *
	 * @param string $token Token.
	 * @return array|WP_Error
	 */
	private function verify_creator_asset_token( $token ) {
		if ( '' === $token ) {
			return new WP_Error( 'missing_token', __( 'Missing token.', 'pckz-canonical-engine' ) );
		}
		if ( class_exists( 'PCKZ_Licensing' ) && method_exists( 'PCKZ_Licensing', 'verify_signed_token_public' ) ) {
			$decoded = PCKZ_Licensing::verify_signed_token_public( $token );
		} else {
			$parts = explode( '.', $token, 2 );
			if ( 2 !== count( $parts ) ) {
				return new WP_Error( 'invalid_token', __( 'Invalid token.', 'pckz-canonical-engine' ) );
			}
			$body = (string) $parts[0];
			$sig  = (string) $parts[1];
			$calc = hash_hmac( 'sha256', $body, wp_salt( 'auth' ) );
			if ( ! hash_equals( $calc, $sig ) ) {
				return new WP_Error( 'invalid_token', __( 'Invalid token signature.', 'pckz-canonical-engine' ) );
			}
			$raw = base64_decode( strtr( $body, '-_', '+/' ) );
			$decoded = json_decode( (string) $raw, true );
		}
		if ( is_wp_error( $decoded ) || ! is_array( $decoded ) ) {
			return new WP_Error( 'invalid_token', __( 'Invalid token payload.', 'pckz-canonical-engine' ) );
		}
		if ( 'creator_asset' !== (string) ( $decoded['typ'] ?? '' ) ) {
			return new WP_Error( 'invalid_token', __( 'Unexpected token type.', 'pckz-canonical-engine' ) );
		}
		$exp = isset( $decoded['exp'] ) ? (int) $decoded['exp'] : 0;
		if ( $exp > 0 && time() > $exp ) {
			return new WP_Error( 'expired_token', __( 'Token expired.', 'pckz-canonical-engine' ) );
		}
		return $decoded;
	}

	/**
	 * Send SVG response body.
	 *
	 * @param string $svg SVG markup.
	 */
	private function send_svg_response( $svg ) {
		if ( '' === trim( (string) $svg ) ) {
			status_header( 404 );
			exit;
		}
		header( 'Content-Type: image/svg+xml; charset=utf-8' );
		header( 'Cache-Control: private, max-age=300' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- serving SVG payload.
		echo $svg;
		exit;
	}

	/**
	 * Serve line SVG by slug.
	 *
	 * @param string $slug Line slug.
	 */
	private function serve_line_asset( $slug ) {
		$slug = sanitize_key( $slug );
		if ( ! $slug || 'none' === $slug || ! class_exists( 'PCKZ_Line_Library' ) ) {
			status_header( 404 );
			exit;
		}
		$svg = PCKZ_Line_Library::read_source_svg_for_slug( $slug );
		if ( ! is_string( $svg ) || '' === $svg ) {
			status_header( 404 );
			exit;
		}
		if ( class_exists( 'PCKZ_Svg_Library' ) ) {
			$svg = PCKZ_Line_Library::normalize_line_svg_for_display( $slug, $svg, PCKZ_Line_Library::connected_right_for_slug( $slug ) );
		}
		$this->send_svg_response( $svg );
	}

	/**
	 * Serve icon SVG by slug.
	 *
	 * @param string $slug Icon slug.
	 */
	private function serve_icon_asset( $slug ) {
		$slug = sanitize_key( $slug );
		if ( ! $slug || 'none' === $slug ) {
			status_header( 404 );
			exit;
		}

		$local_path = '';
		if ( class_exists( 'PCKZ_Icon_Library' ) ) {
			if ( PCKZ_Icon_Library::is_custom( $slug ) ) {
				$custom = PCKZ_Icon_Library::custom_manifest();
				$file   = ! empty( $custom[ $slug ]['file'] ) ? sanitize_file_name( (string) $custom[ $slug ]['file'] ) : '';
				if ( '' !== $file ) {
					$candidate = trailingslashit( PCKZ_Icon_Library::upload_dir() ) . $file;
					if ( is_readable( $candidate ) ) {
						$local_path = $candidate;
					}
				}
			} else {
				$local_path = PCKZ_Icon_Library::bundled_file_path( $slug );
			}
		}

		if ( '' !== $local_path && is_readable( $local_path ) ) {
			$svg = (string) file_get_contents( $local_path );
			$this->send_svg_response( $svg );
		}

		$catalog = class_exists( 'PCKZ_Ledos_Preview' ) ? PCKZ_Ledos_Preview::icon_catalog( false ) : array();
		$url     = ! empty( $catalog[ $slug ]['url'] ) ? esc_url_raw( (string) $catalog[ $slug ]['url'] ) : '';
		if ( '' === $url ) {
			status_header( 404 );
			exit;
		}
		$response = wp_remote_get( $url, array( 'timeout' => 20, 'redirection' => 2 ) );
		if ( is_wp_error( $response ) ) {
			status_header( 404 );
			exit;
		}
		$body = (string) wp_remote_retrieve_body( $response );
		$this->send_svg_response( $body );
	}

	/**
	 * Serve icon background SVG.
	 */
	private function serve_icon_background_asset() {
		$layer_refs = class_exists( 'PCKZ_Ledos_Preview' ) ? PCKZ_Ledos_Preview::layer_refs() : array();
		$url = isset( $layer_refs['iconBgLeft']['url'] ) ? esc_url_raw( (string) $layer_refs['iconBgLeft']['url'] ) : '';
		if ( '' === $url ) {
			status_header( 404 );
			exit;
		}
		$response = wp_remote_get( $url, array( 'timeout' => 20, 'redirection' => 2 ) );
		if ( is_wp_error( $response ) ) {
			status_header( 404 );
			exit;
		}
		$body = (string) wp_remote_retrieve_body( $response );
		$this->send_svg_response( $body );
	}

	/**
	 * Serve font binary by font ID.
	 *
	 * @param string $font_id Font ID.
	 */
	private function serve_font_asset( $font_id ) {
		if ( '' === $font_id || ! class_exists( 'PCKZ_Font_Library' ) ) {
			status_header( 404 );
			exit;
		}
		$result = PCKZ_Font_Library::stream_font_binary( $font_id );
		if ( is_wp_error( $result ) ) {
			$status = (int) ( $result->get_error_data()['status'] ?? 404 );
			status_header( $status );
		}
		exit;
	}

	/**
	 * Optional customer artwork upload (checkout — logo, vector, etc.).
	 */
	public function handle_upload_customer_artwork() {
		if ( ! $this->verify_nonce() ) {
			wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'pckz-canonical-engine' ) ), 403 );
		}
		if ( empty( $_FILES['file'] ) || ! class_exists( 'PCKZ_Customer_Artwork' ) ) {
			wp_send_json_error( array( 'message' => __( 'No file uploaded.', 'pckz-canonical-engine' ) ), 400 );
		}
		$design_id = isset( $_POST['design_id'] ) ? absint( $_POST['design_id'] ) : 0;
		$result    = PCKZ_Customer_Artwork::handle_upload( $_FILES['file'], $design_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}
		wp_send_json_success( $result );
	}

	/**
	 * Handle image upload.
	 */
	public function handle_upload_image() {
		if ( ! $this->verify_nonce() ) {
			wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'pckz-canonical-engine' ) ), 403 );
		}

		if ( empty( $_FILES['file'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file uploaded.', 'pckz-canonical-engine' ) ), 400 );
		}

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$config     = PCKZ_Post_Type::get_product_config( $product_id );
		$max_mb     = (int) ( $config['max_upload_mb'] ?? 5 );

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$overrides = array(
			'test_form' => false,
			'mimes'     => array(
				'jpg|jpeg|jpe' => 'image/jpeg',
				'gif'          => 'image/gif',
				'png'          => 'image/png',
				'svg'          => 'image/svg+xml',
				'webp'         => 'image/webp',
			),
		);

		$file = $_FILES['file'];
		if ( $file['size'] > $max_mb * 1024 * 1024 ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %d: max MB */
						__( 'File exceeds maximum size of %d MB.', 'pckz-canonical-engine' ),
						$max_mb
					),
				),
				400
			);
		}

		$upload = wp_handle_upload( $file, $overrides );

		if ( isset( $upload['error'] ) ) {
			wp_send_json_error( array( 'message' => $upload['error'] ), 400 );
		}

		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => $upload['type'],
				'post_title'     => sanitize_file_name( basename( $upload['file'] ) ),
				'post_content'   => '',
				'post_status'    => 'inherit',
			),
			$upload['file']
		);

		if ( ! is_wp_error( $attachment_id ) ) {
			wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );
		}

		wp_send_json_success(
			array(
				'url'           => $upload['url'],
				'attachment_id' => $attachment_id,
			)
		);
	}

	/**
	 * Handle design save.
	 */
	public function handle_save_design() {
		if ( ! $this->verify_nonce() ) {
			wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'pckz-canonical-engine' ) ), 403 );
		}
		$license_auth = $this->enforce_export_license( 'save-design' );

		$product_id   = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$canvas_json  = isset( $_POST['canvas_json'] ) ? wp_unslash( $_POST['canvas_json'] ) : '';
		$preview_data = isset( $_POST['preview_png'] ) ? wp_unslash( $_POST['preview_png'] ) : '';
		$design_meta  = isset( $_POST['design_meta'] ) ? wp_unslash( $_POST['design_meta'] ) : '';

		if ( empty( $canvas_json ) ) {
			wp_send_json_error( array( 'message' => __( 'Empty design data.', 'pckz-canonical-engine' ) ), 400 );
		}

		$meta = array();
		if ( ! empty( $design_meta ) ) {
			$decoded = json_decode( $design_meta, true );
			if ( is_array( $decoded ) ) {
				$meta = $decoded;
			}
		}

		$selections = array();
		if ( ! empty( $_POST['selections'] ) ) {
			$decoded_sel = json_decode( wp_unslash( $_POST['selections'] ), true );
			if ( is_array( $decoded_sel ) ) {
				$selections = $decoded_sel;
			}
		}
		$meta['selections'] = $selections;

		if ( class_exists( 'PCKZ_Commerce' ) ) {
			$email_in = isset( $_POST['customer_email'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_email'] ) ) : '';
			if ( $email_in ) {
				$valid_email = PCKZ_Commerce::validate_email( $email_in );
				if ( ! is_wp_error( $valid_email ) ) {
					$meta['customer_email']              = $valid_email;
					$meta['selections']['customer_email'] = $valid_email;
				}
			}
			$wishes = isset( $_POST['customer_wishes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['customer_wishes'] ) ) : '';
			if ( $wishes ) {
				$meta['customer_note']               = $wishes;
				$meta['selections']['customer_wishes'] = $wishes;
			}
		}

		$canonical_scene_json = '';
		if ( ! empty( $_POST['canonical_scene_json'] ) ) {
			$canonical_scene_json = wp_unslash( $_POST['canonical_scene_json'] );
		}

		$production_vector_svg = '';
		if ( ! empty( $_POST['production_vector_svg'] ) ) {
			$production_vector_svg = wp_unslash( $_POST['production_vector_svg'] );
		}

		$text_plate_paths = PCKZ_Export_Diagnostics::decode_text_plate_paths_from_request(
			! empty( $_POST['text_plate_paths'] ) ? wp_unslash( $_POST['text_plate_paths'] ) : '',
			! empty( $_POST['text_plate_paths_b64'] ) ? wp_unslash( $_POST['text_plate_paths_b64'] ) : ''
		);

		if ( ! empty( $_POST['design_meta'] ) ) {
			$extra = json_decode( wp_unslash( $_POST['design_meta'] ), true );
			if ( is_array( $extra ) ) {
				$meta = array_merge( $meta, $extra );
			}
		}

		$config = PCKZ_Post_Type::get_product_config( $product_id );

		$design_id = PCKZ_Design_Storage::save_design(
			array(
				'product_id'  => $product_id,
				'user_id'     => get_current_user_id(),
				'canvas_json' => $canvas_json,
				'preview_png' => $preview_data,
				'meta'        => $meta,
			)
		);

		if ( is_wp_error( $design_id ) ) {
			wp_send_json_error( array( 'message' => $design_id->get_error_message() ), 500 );
		}

		$export_payload = array();
		$design = PCKZ_Design_Storage::get_design( $design_id );
		if ( $design ) {
			$layout = $meta['layout'] ?? array();
			if ( empty( $layout ) ) {
				$parsed = json_decode( $canvas_json, true );
				if ( is_array( $parsed ) && ! empty( $parsed['pckzMeta']['layout'] ) ) {
					$layout = $parsed['pckzMeta']['layout'];
				}
			}
			if ( $production_vector_svg ) {
				$layout['production_vector_svg']         = $production_vector_svg;
				$meta['production_vector_svg']           = $production_vector_svg;
				$meta['layout']['production_vector_svg'] = $production_vector_svg;
			}
			if ( $text_plate_paths ) {
				$layout['text_plate_paths']         = $text_plate_paths;
				$meta['text_plate_paths']           = $text_plate_paths;
				$meta['layout']['text_plate_paths'] = $text_plate_paths;
			}
			$export_args = array(
				'selections'  => $selections,
				'canvas_json' => $canvas_json,
				'config'      => $config,
				'preview_url' => $design['preview_url'] ?? '',
				'export_url'  => $design['export_url'] ?? '',
				'std_spec'    => $meta['std_spec'] ?? array(),
				'design_id'   => (int) $design_id,
				'layout'      => $layout,
			);

			try {
				$lic_settings = PCKZ_Settings::get_all();
				$use_remote_export = class_exists( 'PCKZ_Licensing' ) && ! empty( $lic_settings['licensing_export_remote_mode'] );
				$strict_remote = ! empty( $lic_settings['licensing_export_remote_strict'] );
				if ( $use_remote_export ) {
					$remote_payload = array(
						'product_id'           => $product_id,
						'config'               => $config,
						'selections'           => $selections,
						'canvas_json'          => $canvas_json,
						'canonical_scene_json' => $canonical_scene_json,
						'production_vector_svg'=> $production_vector_svg,
						'text_plate_paths'     => $text_plate_paths,
						'design_id'            => (int) $design_id,
						'std_spec'             => $meta['std_spec'] ?? array(),
					);
					$package = PCKZ_Licensing::remote_generate_export( $remote_payload, $license_auth );
					if ( is_wp_error( $package ) ) {
						if ( $strict_remote ) {
							$this->send_export_error(
								$package->get_error_message(),
								403,
								array(
									'code' => $package->get_error_code(),
									'stage'=> 'remote_export',
								)
							);
						}
						$use_remote_export = false;
					}
				}
				if ( ! $use_remote_export ) {
					if ( $canonical_scene_json ) {
						$export_args['canonical_scene'] = $canonical_scene_json;
						if ( $production_vector_svg ) {
							$export_args['production_vector_svg'] = $production_vector_svg;
						}
						if ( $text_plate_paths ) {
							$export_args['text_plate_paths'] = $text_plate_paths;
						}
						$package = PCKZ_Export_Engine::run( $export_args );
						if ( is_wp_error( $package ) ) {
							$data   = $package->get_error_data();
							$status = 422;
							if ( is_array( $data ) && ! empty( $data['http_status'] ) ) {
								$status = (int) $data['http_status'];
							}
							$font_family = (string) ( $selections['font_family'] ?? '' );
							$font_url    = self::export_font_url_for_family( $font_family );
							$summary     = PCKZ_Export_Diagnostics::summarize_payload( $text_plate_paths, $production_vector_svg, $font_family, $font_url );
							$probe       = is_array( $data ) && ! empty( $data['parse_probe'] ) ? $data['parse_probe'] : array();
							if ( empty( $probe ) ) {
								$canvas = PCKZ_Export_Diagnostics::canvas_mm_from_request();
								$probe  = PCKZ_Export_Diagnostics::probe_text_fragment_parse(
									$text_plate_paths,
									$canvas['width'],
									$canvas['height'],
									$layout
								);
							}
							$debug = PCKZ_Export_Diagnostics::format_debug_suffix( $summary, $probe );
							$this->send_export_error(
								$package->get_error_message() . ' ' . $debug,
								$status,
								array(
									'code'       => $package->get_error_code(),
									'validation' => $data,
									'errors'     => is_array( $data ) ? ( $data['errors'] ?? array() ) : array(),
									'parity'     => is_array( $data ) ? ( $data['parity'] ?? null ) : null,
									'export_debug' => array_merge( $summary, $probe ),
								)
							);
						}
					} else {
						$export_args['production_vector_svg'] = $production_vector_svg;
						$package = PCKZ_Production::build_package( $export_args );
					}
				}

				$package = PCKZ_Production::persist_export_files( $package, $design_id );
				if ( $canonical_scene_json && empty( $package['production_lbrn2_url'] ) ) {
					$lbrn2_probe = PCKZ_Export_Diagnostics::probe_lbrn2_generation( $package );
					$font_family = (string) ( $selections['font_family'] ?? '' );
					$font_url    = self::export_font_url_for_family( $font_family );
					$summary     = PCKZ_Export_Diagnostics::summarize_payload( $text_plate_paths, $production_vector_svg, $font_family, $font_url );
					$this->send_export_error(
						__( 'LightBurn LBRN2 export file was not created.', 'pckz-canonical-engine' ) . ' ' . PCKZ_Export_Diagnostics::format_debug_suffix( $summary, $lbrn2_probe ),
						422,
						array(
							'code'         => 'lbrn2_missing',
							'export_debug' => array_merge( $summary, $lbrn2_probe ),
						)
					);
				}
				if ( $canonical_scene_json && '' !== trim( (string) ( $selections['custom_text'] ?? '' ) ) ) {
					$lbrn2_probe = PCKZ_Export_Diagnostics::probe_lbrn2_generation( $package );
					$svg_probe   = PCKZ_Export_Diagnostics::probe_svg_generation( $package );
					if ( empty( $lbrn2_probe['lbrn2_text_shape_count'] ) ) {
						$font_family = (string) ( $selections['font_family'] ?? '' );
						$font_url    = self::export_font_url_for_family( $font_family );
						$summary     = PCKZ_Export_Diagnostics::summarize_payload( $text_plate_paths, $production_vector_svg, $font_family, $font_url );
						$this->send_export_error(
							__( 'LightBurn LBRN2 export is missing customer text vector paths.', 'pckz-canonical-engine' ) . ' ' . PCKZ_Export_Diagnostics::format_debug_suffix( $summary, $lbrn2_probe ),
							422,
							array(
								'code'         => 'lbrn2_text_missing',
								'export_debug' => array_merge( $summary, $lbrn2_probe, $svg_probe ),
							)
						);
					}
					if ( empty( $svg_probe['svg_text_path_count'] ) && empty( $svg_probe['svg_text_group_count'] ) ) {
						$font_family = (string) ( $selections['font_family'] ?? '' );
						$font_url    = self::export_font_url_for_family( $font_family );
						$summary     = PCKZ_Export_Diagnostics::summarize_payload( $text_plate_paths, $production_vector_svg, $font_family, $font_url );
						$this->send_export_error(
							__( 'Production SVG export is missing customer text vector paths.', 'pckz-canonical-engine' ) . ' ' . PCKZ_Export_Diagnostics::format_debug_suffix( $summary, array_merge( $lbrn2_probe, $svg_probe ) ),
							422,
							array(
								'code'         => 'svg_text_missing',
								'export_debug' => array_merge( $summary, $lbrn2_probe, $svg_probe ),
							)
						);
					}
				}
			} catch ( \Throwable $export_error ) {
				$this->send_export_error(
					$export_error->getMessage(),
					500,
					array( 'stage' => 'export' ),
					$export_error
				);
			}

			PCKZ_Design_Storage::update_meta(
				$design_id,
				array_merge(
					$meta,
					array(
						'canonical_scene'      => $package['canonical_scene'] ?? ( $canonical_scene_json ? json_decode( $canonical_scene_json, true ) : null ),
						'validation'           => $package['validation'] ?? null,
						'parity'               => $package['parity'] ?? null,
						'production'           => $package,
						'lightburn_json_url'   => $package['lightburn_json_url'] ?? '',
						'canvas_json_url'      => $package['canvas_json_url'] ?? '',
						'production_svg_url'   => $package['production_svg_url'] ?? '',
						'production_lbrn2_url' => $package['production_lbrn2_url'] ?? '',
						'production_lbrn_url'  => $package['production_lbrn_url'] ?? '',
						'production_dxf_url'   => $package['production_dxf_url'] ?? '',
					)
				)
			);

			$export_payload = array(
				'validation'           => $package['validation'] ?? null,
				'parity'               => $package['parity'] ?? null,
				'lightburn_json_url'   => $package['lightburn_json_url'] ?? '',
				'canvas_json_url'      => $package['canvas_json_url'] ?? '',
				'production_svg_url'   => $package['production_svg_url'] ?? '',
				'production_lbrn2_url' => $package['production_lbrn2_url'] ?? '',
			);
		}

		wp_send_json_success(
			array_merge(
				array(
					'design_id' => $design_id,
					'message'   => __( 'Design saved.', 'pckz-canonical-engine' ),
				),
				$export_payload
			)
		);
	}

	/**
	 * Handle print-ready export.
	 */
	public function handle_export_design() {
		if ( ! $this->verify_nonce() ) {
			wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'pckz-canonical-engine' ) ), 403 );
		}
		$this->enforce_export_license( 'export-design' );

		$png_data   = isset( $_POST['export_png'] ) ? wp_unslash( $_POST['export_png'] ) : '';
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$format     = isset( $_POST['format'] ) ? sanitize_text_field( wp_unslash( $_POST['format'] ) ) : 'png';

		if ( empty( $png_data ) ) {
			wp_send_json_error( array( 'message' => __( 'No export data.', 'pckz-canonical-engine' ) ), 400 );
		}

		$url = PCKZ_Design_Storage::save_export_file( $png_data, $product_id, $format );

		if ( is_wp_error( $url ) ) {
			wp_send_json_error( array( 'message' => $url->get_error_message() ), 500 );
		}

		wp_send_json_success( array( 'url' => $url ) );
	}

	/**
	 * Handle add to cart (WooCommerce).
	 */
	public function handle_add_to_cart() {
		if ( ! $this->verify_nonce() ) {
			wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'pckz-canonical-engine' ) ), 403 );
		}
		$this->enforce_export_license( 'add-to-cart' );

		if ( ! class_exists( 'WooCommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'WooCommerce is not active.', 'pckz-canonical-engine' ) ), 400 );
		}

		if ( class_exists( 'PCKZ_Commerce' ) && PCKZ_Commerce::checkout_paypal_only() ) {
			wp_send_json_error(
				array(
					'message' => __( 'Bitte schließen Sie die Bestellung ausschließlich über PayPal ab.', 'pckz-canonical-engine' ),
				),
				400
			);
		}

		$woo_id    = isset( $_POST['woo_product_id'] ) ? absint( $_POST['woo_product_id'] ) : 0;
		$design_id = isset( $_POST['design_id'] ) ? absint( $_POST['design_id'] ) : 0;
		$quantity  = isset( $_POST['quantity'] ) ? max( 1, absint( $_POST['quantity'] ) ) : 1;

		if ( class_exists( 'PCKZ_Commerce' ) ) {
			$details = PCKZ_Commerce::parse_customer_details_from_request();
			$valid   = PCKZ_Commerce::validate_customer_details( $details );
			if ( is_wp_error( $valid ) ) {
				wp_send_json_error( array( 'message' => $valid->get_error_message() ), 400 );
			}
			$email  = $details['email'];
			$wishes = $details['wishes'];
			if ( $design_id ) {
				PCKZ_Commerce::attach_customer_meta_to_design( $design_id, $email, $wishes, $details );
			}
		}

		if ( ! $woo_id ) {
			wp_send_json_error( array( 'message' => __( 'No WooCommerce product linked.', 'pckz-canonical-engine' ) ), 400 );
		}

		$cart_item_data = array();
		if ( class_exists( 'PCKZ_Commerce' ) ) {
			$currency = PCKZ_Commerce::get_default_currency_code();
			if ( isset( $_POST['currency'] ) ) {
				$currency = PCKZ_Commerce::sanitize_currency_code( sanitize_text_field( wp_unslash( $_POST['currency'] ) ) );
			}
			$cart_item_data['pckz_currency'] = $currency;
		}
		if ( $design_id ) {
			$design = PCKZ_Design_Storage::get_design( $design_id );
			if ( $design ) {
				$meta = array();
				if ( ! empty( $design['meta_json'] ) ) {
					$meta = json_decode( $design['meta_json'], true );
				}
				$cart_item_data[ PCKZ_Settings::get( 'cart_meta_key', '_pckz_design' ) ] = array(
					'design_id'        => $design_id,
					'preview_url'      => $design['preview_url'] ?? '',
					'export_url'       => $design['export_url'] ?? '',
					'product_id'       => $design['product_id'] ?? 0,
					'selections'       => $meta['selections'] ?? array(),
					'production'       => $meta['production'] ?? array(),
					'customer_email'   => $meta['customer_email'] ?? ( $meta['selections']['customer_email'] ?? '' ),
					'customer_wishes'  => $meta['customer_note'] ?? ( $meta['selections']['customer_wishes'] ?? '' ),
				);
			}
		}

		$added = WC()->cart->add_to_cart( $woo_id, $quantity, 0, array(), $cart_item_data );

		if ( ! $added ) {
			wp_send_json_error( array( 'message' => __( 'Could not add to cart.', 'pckz-canonical-engine' ) ), 500 );
		}

		wp_send_json_success(
			array(
				'cart_url'    => wc_get_cart_url(),
				'cart_count'  => WC()->cart->get_cart_contents_count(),
				'message'     => __( 'Added to cart.', 'pckz-canonical-engine' ),
			)
		);
	}

	/**
	 * Resolve export font URL for diagnostics.
	 *
	 * @param string $font_family Font family.
	 * @return string
	 */
	private static function export_font_url_for_family( $font_family ) {
		if ( ! class_exists( 'PCKZ_Font_Library' ) || '' === trim( (string) $font_family ) ) {
			return '';
		}
		$key = strtolower( trim( (string) $font_family ) );
		$map = PCKZ_Font_Library::font_files_for_js();
		if ( ! empty( $map[ $key ] ) ) {
			return (string) $map[ $key ];
		}
		foreach ( PCKZ_Font_Library::default_catalog() as $id => $row ) {
			if ( strtolower( (string) ( $row['family'] ?? '' ) ) === $key ) {
				$by_id = PCKZ_Font_Library::font_files_by_id_for_js();
				return (string) ( $by_id[ $id ] ?? '' );
			}
		}
		return '';
	}

	/**
	 * Server-side export preflight (same pipeline as save, no design persist).
	 */
	public function handle_export_validate() {
		if ( ! $this->verify_nonce() ) {
			wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'pckz-canonical-engine' ) ), 403 );
		}
		$this->enforce_export_license( 'export-validate' );

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$config     = PCKZ_Post_Type::get_product_config( $product_id );
		$std_spec   = class_exists( 'PCKZ_Std_Spec' ) ? PCKZ_Std_Spec::for_product( $config ) : array();

		$selections = array();
		if ( ! empty( $_POST['selections'] ) ) {
			$decoded = json_decode( wp_unslash( $_POST['selections'] ), true );
			if ( is_array( $decoded ) ) {
				$selections = $decoded;
			}
		}

		$canonical_scene_json = ! empty( $_POST['canonical_scene_json'] ) ? wp_unslash( $_POST['canonical_scene_json'] ) : '';
		$production_vector_svg = ! empty( $_POST['production_vector_svg'] ) ? wp_unslash( $_POST['production_vector_svg'] ) : '';
		$text_plate_paths      = PCKZ_Export_Diagnostics::decode_text_plate_paths_from_request(
			! empty( $_POST['text_plate_paths'] ) ? wp_unslash( $_POST['text_plate_paths'] ) : '',
			! empty( $_POST['text_plate_paths_b64'] ) ? wp_unslash( $_POST['text_plate_paths_b64'] ) : ''
		);

		$font_family = (string) ( $selections['font_family'] ?? '' );
		$font_url    = self::export_font_url_for_family( $font_family );
		$summary     = PCKZ_Export_Diagnostics::summarize_payload( $text_plate_paths, $production_vector_svg, $font_family, $font_url );

		if ( '' === trim( $production_vector_svg ) ) {
			wp_send_json_error(
				array(
					'message'      => __( 'Fabric production SVG missing. Re-save after preview fully loads.', 'pckz-canonical-engine' ) . ' ' . PCKZ_Export_Diagnostics::format_debug_suffix( $summary ),
					'code'         => 'missing_fabric_export',
					'export_debug' => $summary,
				),
				422
			);
		}

		$canvas  = PCKZ_Export_Diagnostics::canvas_mm_from_request();
		$plate_w = $canvas['width'];
		$plate_h = $canvas['height'];
		$probe   = PCKZ_Export_Diagnostics::probe_text_fragment_parse( $text_plate_paths, $plate_w, $plate_h, array() );

		if ( '' !== trim( (string) ( $selections['custom_text'] ?? '' ) ) && empty( $probe['lbrn2_parse_ok'] ) ) {
			wp_send_json_error(
				array(
					'message'      => __( 'Vector text paths failed to parse for LBRN2 export.', 'pckz-canonical-engine' ) . ' ' . PCKZ_Export_Diagnostics::format_debug_suffix( $summary, $probe ),
					'code'         => 'vector_text_invalid',
					'export_debug' => array_merge( $summary, $probe ),
				),
				422
			);
		}

		if ( '' === $canonical_scene_json ) {
			wp_send_json_success(
				array(
					'ok'           => true,
					'export_debug' => array_merge( $summary, $probe ),
				)
			);
		}

		$export_args = array(
			'canonical_scene'       => $canonical_scene_json,
			'production_vector_svg' => $production_vector_svg,
			'text_plate_paths'      => $text_plate_paths,
			'config'                => $config,
			'std_spec'              => $std_spec,
			'selections'            => $selections,
			'canvas_json'           => '{}',
			'design_id'             => 0,
		);

		$package = PCKZ_Export_Engine::run( $export_args );
		if ( is_wp_error( $package ) ) {
			$data  = $package->get_error_data();
			$probe = is_array( $data ) && ! empty( $data['parse_probe'] ) ? $data['parse_probe'] : $probe;
			wp_send_json_error(
				array(
					'message'      => $package->get_error_message() . ' ' . PCKZ_Export_Diagnostics::format_debug_suffix( $summary, $probe ),
					'code'         => $package->get_error_code(),
					'export_debug' => array_merge( $summary, is_array( $probe ) ? $probe : array() ),
				),
				422
			);
		}

		$lbrn2_probe = PCKZ_Export_Diagnostics::probe_lbrn2_generation( $package );
		$svg_probe   = PCKZ_Export_Diagnostics::probe_svg_generation( $package );
		if ( empty( $lbrn2_probe['lbrn2_generated'] ) ) {
			wp_send_json_error(
				array(
					'message'      => __( 'LightBurn LBRN2 document could not be built from export scene.', 'pckz-canonical-engine' ) . ' ' . PCKZ_Export_Diagnostics::format_debug_suffix( $summary, array_merge( $probe, $lbrn2_probe, $svg_probe ) ),
					'code'         => 'lbrn2_build_failed',
					'export_debug' => array_merge( $summary, $probe, $lbrn2_probe, $svg_probe ),
				),
				422
			);
		}

		wp_send_json_success(
			array(
				'ok'           => true,
				'export_debug' => array_merge( $summary, $probe, $lbrn2_probe, $svg_probe ),
			)
		);
	}

	/**
	 * Create PayPal checkout session (payment required before order completion).
	 */
	public function handle_create_paypal_order() {
		if ( ! $this->verify_nonce() ) {
			wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'pckz-canonical-engine' ) ), 403 );
		}
		$this->enforce_export_license( 'create-paypal-order' );

		if ( ! class_exists( 'PCKZ_Commerce' ) || ! PCKZ_Commerce::payment_checkout_enabled() ) {
			wp_send_json_error( array( 'message' => __( 'Es ist kein aktiver Zahlungsanbieter konfiguriert.', 'pckz-canonical-engine' ) ), 400 );
		}
		$provider = isset( $_POST['payment_provider'] ) ? sanitize_key( wp_unslash( $_POST['payment_provider'] ) ) : '';
		if ( '' === $provider ) {
			$provider = PCKZ_Commerce::active_payment_provider();
		}
		if ( ! in_array( $provider, array( 'paypal', 'stripe' ), true ) ) {
			$provider = 'paypal';
		}
		if ( 'paypal' === $provider && ! PCKZ_Commerce::paypal_enabled() ) {
			wp_send_json_error( array( 'message' => __( 'PayPal ist derzeit nicht verfügbar.', 'pckz-canonical-engine' ) ), 400 );
		}
		if ( 'stripe' === $provider && ( ! class_exists( 'PCKZ_Payments' ) || ! PCKZ_Commerce::payment_checkout_enabled() ) ) {
			wp_send_json_error( array( 'message' => __( 'Stripe ist derzeit nicht verfügbar.', 'pckz-canonical-engine' ) ), 400 );
		}

		$design_id  = isset( $_POST['design_id'] ) ? absint( $_POST['design_id'] ) : 0;
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$quantity   = isset( $_POST['quantity'] ) ? max( 1, absint( $_POST['quantity'] ) ) : 1;
		$details = PCKZ_Commerce::parse_customer_details_from_request();
		$valid   = PCKZ_Commerce::validate_customer_details( $details );
		if ( is_wp_error( $valid ) ) {
			wp_send_json_error( array( 'message' => $valid->get_error_message() ), 400 );
		}
		$email  = PCKZ_Commerce::validate_email( $details['email'] );
		$wishes = $details['wishes'];

		if ( ! $design_id ) {
			wp_send_json_error( array( 'message' => __( 'Bitte personalisieren Sie zuerst Ihr Produkt.', 'pckz-canonical-engine' ) ), 400 );
		}

		$design = PCKZ_Design_Storage::get_design( $design_id );
		if ( ! $design ) {
			wp_send_json_error( array( 'message' => __( 'Design nicht gefunden.', 'pckz-canonical-engine' ) ), 404 );
		}
		$lbrn2_url = PCKZ_Design_Storage::get_production_lbrn2_url( $design );
		if ( '' === $lbrn2_url ) {
			$meta      = is_array( $design['meta'] ?? null ) ? $design['meta'] : array();
			$package   = is_array( $meta['production'] ?? null ) ? $meta['production'] : array();
			$lbrn2_dbg = class_exists( 'PCKZ_Export_Diagnostics' )
				? PCKZ_Export_Diagnostics::probe_lbrn2_generation( $package )
				: array();
			$lbrn2_dbg['lbrn2_attached_to_request'] = false;
			wp_send_json_error(
				array(
					'message'      => __( 'LightBurn LBRN2 file missing for saved design. Re-save after preview loads.', 'pckz-canonical-engine' ) . ' ' . PCKZ_Export_Diagnostics::format_debug_suffix(
						array( 'pckz_version' => PCKZCE_VERSION ),
						$lbrn2_dbg
					),
					'code'         => 'lbrn2_missing',
					'export_debug' => $lbrn2_dbg,
				),
				422
			);
		}

		PCKZ_Commerce::attach_customer_meta_to_design( $design_id, $email, $wishes, $details );

		$currency_in = isset( $_POST['currency'] ) ? sanitize_text_field( wp_unslash( $_POST['currency'] ) ) : '';
		$currency    = PCKZ_Commerce::sanitize_currency_code( $currency_in );
		$pricing     = PCKZ_Commerce::get_frontend_pricing( $product_id, $currency );
		$amount      = PCKZ_Commerce::calculate_total( $quantity, $product_id, $currency );

		if ( $amount <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Produktpreis ist nicht konfiguriert. Bitte kontaktieren Sie uns.', 'pckz-canonical-engine' ) ), 400 );
		}

		$page_url = isset( $_POST['page_url'] ) ? esc_url_raw( wp_unslash( $_POST['page_url'] ) ) : '';
		if ( ! $page_url ) {
			$page_url = wp_get_referer() ?: '';
		}
		if ( $page_url ) {
			$page_url = remove_query_arg( array( 'pckz_paypal', 'pckz_paid', 'token', 'PayerID' ), $page_url );
		}

		$commerce_id = PCKZ_Commerce::insert_order(
			array(
				'design_id'        => $design_id,
				'product_id'       => $product_id,
				'customer_email'   => $email,
				'customer_note'    => $wishes,
				'customer_details' => $details,
				'quantity'         => $quantity,
				'amount'           => $amount,
				'currency'         => $currency,
				'payment_provider' => $provider,
				'status'           => 'pending',
				'return_url'       => $page_url,
			)
		);

		$checkout_args = array(
			'amount'      => $amount,
			'currency'    => $currency,
			'description' => get_the_title( $product_id ) ?: __( 'Personalisiertes Produkt', 'pckz-canonical-engine' ),
			'return_url'  => PCKZ_Commerce::paypal_return_url( $commerce_id ),
			'cancel_url'  => PCKZ_Commerce::paypal_cancel_url( $commerce_id ),
			'page_url'    => $page_url,
			'commerce_id' => $commerce_id,
			'design_id'   => $design_id,
			'product_id'  => $product_id,
		);
		$checkout = 'stripe' === $provider
			? PCKZ_Payments::create_checkout( $checkout_args, 'stripe' )
			: PCKZ_Payments::create_checkout( $checkout_args, 'paypal' );

		if ( is_wp_error( $checkout ) ) {
			PCKZ_Commerce::update_order( $commerce_id, array( 'status' => 'failed' ) );
			wp_send_json_error( array( 'message' => $checkout->get_error_message() ), 500 );
		}
		$external_order_id = sanitize_text_field( (string) ( $checkout['paypal_order_id'] ?? $checkout['stripe_session_id'] ?? '' ) );
		PCKZ_Commerce::update_order(
			$commerce_id,
			array(
				'payment_provider'=> $provider,
				'paypal_order_id' => $external_order_id,
				'status'          => 'stripe' === $provider ? 'stripe_created' : 'paypal_created',
			)
		);

		$message = 'stripe' === $provider
			? __( 'Weiterleitung zu Stripe…', 'pckz-canonical-engine' )
			: __( 'Weiterleitung zu PayPal…', 'pckz-canonical-engine' );
		wp_send_json_success(
			array(
				'approve_url'      => esc_url_raw( (string) ( $checkout['approve_url'] ?? '' ) ),
				'commerce_id'      => $commerce_id,
				'paypal_order_id'  => $external_order_id,
				'payment_provider' => $provider,
				'message'          => $message,
			)
		);
	}

	/**
	 * Backward-compatible alias for provider-aware payment order creation.
	 */
	public function handle_create_payment_order() {
		$this->handle_create_paypal_order();
	}
}
