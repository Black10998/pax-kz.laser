<?php
/**
 * Admin area hooks and menus.
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PCKZ_Admin
 */
class PCKZ_Admin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_notices', array( $this, 'render_asset_protection_notice' ) );
		add_action( 'admin_post_pckz_update_order_status', array( $this, 'handle_update_order_status' ) );
		add_action( 'admin_post_pckz_update_order_notes', array( $this, 'handle_update_order_notes' ) );
		add_action( 'admin_post_pckz_download_customer_artwork', array( $this, 'handle_download_customer_artwork' ) );
	}

	/**
	 * Download customer-provided artwork for a commerce order (admin only).
	 */
	public function handle_download_customer_artwork() {
		if ( class_exists( 'PCKZ_Customer_Artwork' ) ) {
			PCKZ_Customer_Artwork::handle_admin_download();
		}
		wp_die( esc_html__( 'Kundendatei nicht verfügbar.', 'pckz-canonical-engine' ), '', array( 'response' => 404 ) );
	}

	/**
	 * Register admin menus.
	 */
	public function register_menus() {
		add_menu_page(
			__( 'Product Creator', 'pckz-canonical-engine' ),
			__( 'Product Creator', 'pckz-canonical-engine' ),
			'manage_options',
			'pckz-canonical-engine',
			array( $this, 'render_dashboard' ),
			'dashicons-art',
			56
		);

		add_submenu_page(
			'pckz-canonical-engine',
			__( 'Dashboard', 'pckz-canonical-engine' ),
			__( 'Dashboard', 'pckz-canonical-engine' ),
			'manage_options',
			'pckz-canonical-engine',
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			'pckz-canonical-engine',
			__( 'Creator Products', 'pckz-canonical-engine' ),
			__( 'Products', 'pckz-canonical-engine' ),
			'manage_options',
			'edit.php?post_type=' . PCKZ_Post_Type::POST_TYPE
		);

		add_submenu_page(
			'pckz-canonical-engine',
			__( 'Icon Library', 'pckz-canonical-engine' ),
			__( 'Icon Library', 'pckz-canonical-engine' ),
			'manage_options',
			'pckz-icon-library',
			array( $this, 'render_icon_library' )
		);

		add_submenu_page(
			'pckz-canonical-engine',
			__( 'Line Library', 'pckz-canonical-engine' ),
			__( 'Line Library', 'pckz-canonical-engine' ),
			'manage_options',
			'pckz-line-library',
			array( $this, 'render_line_library' )
		);

		add_submenu_page(
			'pckz-canonical-engine',
			__( 'Font Library', 'pckz-canonical-engine' ),
			__( 'Font Library', 'pckz-canonical-engine' ),
			'manage_options',
			'pckz-font-library',
			array( $this, 'render_font_library' )
		);

		add_submenu_page(
			'pckz-canonical-engine',
			__( 'Settings', 'pckz-canonical-engine' ),
			__( 'Settings', 'pckz-canonical-engine' ),
			'manage_options',
			'pckz-settings',
			array( $this, 'render_settings' )
		);

		add_submenu_page(
			'pckz-canonical-engine',
			__( 'About the Developer', 'pckz-canonical-engine' ),
			__( 'Über den Entwickler', 'pckz-canonical-engine' ),
			'manage_options',
			'pckz-about',
			array( $this, 'render_about_developer' )
		);

		add_submenu_page(
			'pckz-canonical-engine',
			__( 'Saved Designs', 'pckz-canonical-engine' ),
			__( 'Designs', 'pckz-canonical-engine' ),
			'manage_options',
			'pckz-designs',
			array( $this, 'render_designs' )
		);

		add_submenu_page(
			'pckz-canonical-engine',
			__( 'Orders', 'pckz-canonical-engine' ),
			__( 'Orders', 'pckz-canonical-engine' ),
			'manage_options',
			'pckz-orders',
			array( $this, 'render_orders' )
		);
	}

	/**
	 * Show admin warning when protected asset mode is enabled but artifacts are missing.
	 */
	public function render_asset_protection_notice() {
		if ( ! current_user_can( 'manage_options' ) || ! class_exists( 'PCKZ_Assets' ) ) {
			return;
		}

		$settings = PCKZ_Settings::get_all();
		if ( ! PCKZ_Assets::prefer_protected_assets( $settings ) ) {
			return;
		}

		$missing = PCKZ_Assets::missing_production_assets( $settings );
		if ( empty( $missing ) ) {
			return;
		}
		?>
		<div class="notice notice-warning">
			<p><strong><?php esc_html_e( 'PCKZ asset protection mode is enabled, but production assets are missing.', 'pckz-canonical-engine' ); ?></strong></p>
			<p><?php esc_html_e( 'Public pages will keep working with fallback source files until production artifacts are generated. Build protected assets before deploying to production.', 'pckz-canonical-engine' ); ?></p>
			<ul style="margin-left:20px;list-style:disc;">
				<?php foreach ( $missing as $entry ) : ?>
					<li>
						<code><?php echo esc_html( (string) $entry['source'] ); ?></code>
						&rarr;
						<code><?php echo esc_html( implode( ', ', array_map( 'strval', (array) ( $entry['expected'] ?? array() ) ) ) ); ?></code>
					</li>
				<?php endforeach; ?>
			</ul>
			<p><code>php tools/build-js-protection.php</code></p>
		</div>
		<?php
	}

	/**
	 * Save production workflow status from admin.
	 */
	public function handle_update_order_notes() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'pckz-canonical-engine' ) );
		}
		check_admin_referer( 'pckz_update_order_notes', 'pckz_order_notes_nonce' );
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$notes    = isset( $_POST['admin_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['admin_notes'] ) ) : '';
		$redirect = isset( $_POST['redirect'] ) ? esc_url_raw( wp_unslash( $_POST['redirect'] ) ) : admin_url( 'admin.php?page=pckz-orders' );
		if ( $order_id && class_exists( 'PCKZ_Commerce' ) ) {
			PCKZ_Commerce::update_order( $order_id, array( 'admin_notes' => $notes ) );
		}
		wp_safe_redirect( add_query_arg( 'pckz_notes_updated', '1', $redirect ) );
		exit;
	}

	/**
	 * Save production workflow status from admin.
	 */
	public function handle_update_order_status() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'pckz-canonical-engine' ) );
		}
		check_admin_referer( 'pckz_update_order_status', 'pckz_order_status_nonce' );
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$status   = isset( $_POST['workflow_status'] ) ? sanitize_key( wp_unslash( $_POST['workflow_status'] ) ) : '';
		$redirect = isset( $_POST['redirect'] ) ? esc_url_raw( wp_unslash( $_POST['redirect'] ) ) : admin_url( 'admin.php?page=pckz-orders' );
		if ( $order_id && class_exists( 'PCKZ_Commerce' ) ) {
			$result = PCKZ_Commerce::set_workflow_status( $order_id, $status );
			if ( is_wp_error( $result ) ) {
				wp_die( esc_html( $result->get_error_message() ) );
			}
		}
		wp_safe_redirect( add_query_arg( 'pckz_status_updated', '1', $redirect ) );
		exit;
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		register_setting(
			'pckz_settings_group',
			PCKZ_Settings::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);

		register_setting(
			'pckz_icon_library_group',
			PCKZ_Icon_Library::OPTION_DISABLED,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_icon_disabled' ),
			)
		);

		register_setting(
			'pckz_line_library_group',
			PCKZ_Line_Library::OPTION_DISABLED,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_line_disabled' ),
			)
		);
	}

	/**
	 * Sanitize disabled icon slugs from admin form.
	 *
	 * @param mixed $input Posted value.
	 * @return string[]
	 */
	public function sanitize_icon_disabled( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}
		$out = array();
		foreach ( $input as $slug ) {
			$s = sanitize_key( $slug );
			if ( $s && 'none' !== $s ) {
				$out[] = $s;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Sanitize disabled line slugs from admin form.
	 *
	 * @param mixed $input Posted value.
	 * @return string[]
	 */
	public function sanitize_line_disabled( $input ) {
		return $this->sanitize_icon_disabled( $input );
	}

	/**
	 * Parse JSON slug list from library bulk-delete form.
	 *
	 * @param mixed $json Raw JSON string.
	 * @return string[]
	 */
	private function parse_library_bulk_slugs( $json ) {
		$raw = is_string( $json ) ? trim( $json ) : '';
		if ( '' === $raw ) {
			return array();
		}
		$decoded = json_decode( wp_unslash( $raw ), true );
		if ( ! is_array( $decoded ) ) {
			return array();
		}
		$out = array();
		foreach ( $decoded as $slug ) {
			$s = sanitize_key( (string) $slug );
			if ( $s ) {
				$out[] = $s;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Normalize the configured master URL to a base site URL.
	 *
	 * Admin users sometimes paste endpoint URLs like `/wp-admin/pckz-license-server`
	 * or `/wp-json/pckzce-license/...`. Clients append REST routes themselves, so
	 * we keep only the site base URL here.
	 *
	 * @param string $raw Raw setting value.
	 * @return string
	 */
	private function normalize_master_base_url( $raw ) {
		$url = esc_url_raw( trim( (string) $raw ) );
		if ( '' === $url ) {
			return '';
		}
		$parts = wp_parse_url( $url );
		if ( empty( $parts['host'] ) ) {
			return rtrim( $url, '/' );
		}
		$scheme = ! empty( $parts['scheme'] ) ? strtolower( (string) $parts['scheme'] ) : 'https';
		$host   = strtolower( (string) $parts['host'] );
		$port   = isset( $parts['port'] ) ? ':' . absint( $parts['port'] ) : '';
		$path   = (string) ( $parts['path'] ?? '' );
		$path   = preg_replace( '#/(wp-admin|wp-json)(/.*)?$#i', '', $path );
		$path   = preg_replace( '#/admin\.php$#i', '', $path );
		$path   = preg_replace( '#/pckz-license-server/?$#i', '', $path );
		$path   = rtrim( (string) $path, '/' );
		return esc_url_raw( $scheme . '://' . $host . $port . $path );
	}

	/**
	 * Sanitize global settings on save.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		if ( ! is_array( $input ) ) {
			return PCKZ_Settings::default_options();
		}

		$defaults = PCKZ_Settings::default_options();
		$current  = PCKZ_Settings::get_all();
		$output   = array(
			'primary_color'        => sanitize_hex_color( $input['primary_color'] ?? $defaults['primary_color'] ) ?: $defaults['primary_color'],
			'accent_color'         => sanitize_hex_color( $input['accent_color'] ?? $defaults['accent_color'] ) ?: $defaults['accent_color'],
			'ui_theme'             => in_array( $input['ui_theme'] ?? '', array( 'dark', 'light' ), true ) ? $input['ui_theme'] : 'dark',
			'default_dpi'          => max( 72, min( 600, intval( $input['default_dpi'] ?? 300 ) ) ),
			'default_origin'       => in_array( $input['default_origin'] ?? '', array( 'top-left', 'bottom-left' ), true ) ? $input['default_origin'] : 'bottom-left',
			'fabric_cdn'           => esc_url_raw( $input['fabric_cdn'] ?? $defaults['fabric_cdn'] ),
			'google_fonts_url'     => esc_url_raw( $input['google_fonts_url'] ?? $defaults['google_fonts_url'] ),
			'enable_woocommerce'   => ! empty( $input['enable_woocommerce'] ),
			'cart_meta_key'        => sanitize_key( $input['cart_meta_key'] ?? '_pckz_design' ),
			'require_design'       => ! empty( $input['require_design'] ),
			'admin_email_notify'   => ! empty( $input['admin_email_notify'] ),
			'max_designs_per_user' => max( 1, intval( $input['max_designs_per_user'] ?? 50 ) ),
			'default_creator_product_id' => absint( $input['default_creator_product_id'] ?? 0 ),
			'enable_dxf_export'    => ! empty( $input['enable_dxf_export'] ),
			'fonts'                => $defaults['fonts'],
			'color_palette'        => $defaults['color_palette'],
			'price_show_enabled'   => ! empty( $input['price_show_enabled'] ),
			'price_base'           => max( 0, (float) ( $input['price_base'] ?? 0 ) ),
			'price_setup_fee'      => max( 0, (float) ( $input['price_setup_fee'] ?? 0 ) ),
			'price_currency_code'  => strtoupper( sanitize_text_field( $input['price_currency_code'] ?? 'EUR' ) ),
			'price_currency_symbol'=> sanitize_text_field( $input['price_currency_symbol'] ?? '€' ),
			'price_default_currency' => strtoupper( sanitize_text_field( $input['price_default_currency'] ?? 'EUR' ) ),
			'price_allow_currency_switch' => ! empty( $input['price_allow_currency_switch'] ),
			'checkout_notice_enabled' => ! empty( $input['checkout_notice_enabled'] ),
			'checkout_notice_message' => wp_kses_post( $input['checkout_notice_message'] ?? '' ),
			'paypal_enabled'       => ! empty( $input['paypal_enabled'] ),
			'paypal_test_mode'     => ! empty( $input['paypal_test_mode'] ),
			'paypal_sandbox_client_id' => sanitize_text_field( $input['paypal_sandbox_client_id'] ?? '' ),
			'paypal_sandbox_secret'    => sanitize_text_field( $input['paypal_sandbox_secret'] ?? '' ),
			'paypal_live_client_id'    => sanitize_text_field( $input['paypal_live_client_id'] ?? '' ),
			'paypal_live_secret'       => sanitize_text_field( $input['paypal_live_secret'] ?? '' ),
			'paypal_success_url'   => esc_url_raw( $input['paypal_success_url'] ?? '' ),
			'creator_page_id'      => absint( $input['creator_page_id'] ?? 0 ),
			'paypal_cancel_url'    => esc_url_raw( $input['paypal_cancel_url'] ?? '' ),
			'licensing_master_mode' => false,
			'licensing_master_url'  => array_key_exists( 'licensing_master_url', $input ) ? $this->normalize_master_base_url( $input['licensing_master_url'] ) : $this->normalize_master_base_url( (string) ( $current['licensing_master_url'] ?? 'https://paxdesign.at' ) ),
			'licensing_key'         => array_key_exists( 'licensing_key', $input ) ? sanitize_text_field( $input['licensing_key'] ) : sanitize_text_field( (string) ( $current['licensing_key'] ?? '' ) ),
			'licensing_install_uuid' => array_key_exists( 'licensing_install_uuid', $input ) ? sanitize_text_field( $input['licensing_install_uuid'] ) : sanitize_text_field( (string) ( $current['licensing_install_uuid'] ?? '' ) ),
			'licensing_enforce'     => array_key_exists( 'licensing_enforce', $input ) ? ! empty( $input['licensing_enforce'] ) : ! empty( $current['licensing_enforce'] ),
			'licensing_grace_minutes' => array_key_exists( 'licensing_grace_minutes', $input ) ? max( 5, min( 1440, absint( $input['licensing_grace_minutes'] ?? 120 ) ) ) : max( 5, min( 1440, absint( $current['licensing_grace_minutes'] ?? 120 ) ) ),
			'licensing_require_signed_requests' => array_key_exists( 'licensing_require_signed_requests', $input ) ? ! empty( $input['licensing_require_signed_requests'] ) : ! empty( $current['licensing_require_signed_requests'] ),
			'licensing_export_authorize' => array_key_exists( 'licensing_export_authorize', $input ) ? ! empty( $input['licensing_export_authorize'] ) : ! empty( $current['licensing_export_authorize'] ),
			'licensing_export_remote_mode' => array_key_exists( 'licensing_export_remote_mode', $input ) ? ! empty( $input['licensing_export_remote_mode'] ) : ! empty( $current['licensing_export_remote_mode'] ),
			'licensing_export_remote_strict' => array_key_exists( 'licensing_export_remote_strict', $input ) ? ! empty( $input['licensing_export_remote_strict'] ) : ! empty( $current['licensing_export_remote_strict'] ),
			'licensing_strict_integrity' => array_key_exists( 'licensing_strict_integrity', $input ) ? ! empty( $input['licensing_strict_integrity'] ) : ! empty( $current['licensing_strict_integrity'] ),
			'licensing_master_api_key' => array_key_exists( 'licensing_master_api_key', $input ) ? sanitize_text_field( $input['licensing_master_api_key'] ) : sanitize_text_field( (string) ( $current['licensing_master_api_key'] ?? '' ) ),
			'security_prefer_protected_assets' => array_key_exists( 'security_prefer_protected_assets', $input )
				? ! empty( $input['security_prefer_protected_assets'] )
				: (
					array_key_exists( 'security_prefer_minified_js', $input )
						? ! empty( $input['security_prefer_minified_js'] )
						: (
							! empty( $current['security_prefer_protected_assets'] )
							|| ! empty( $current['security_prefer_minified_js'] )
						)
				),
			'payments_primary_provider' => in_array( sanitize_key( $input['payments_primary_provider'] ?? 'paypal' ), array( 'paypal', 'stripe' ), true )
				? sanitize_key( $input['payments_primary_provider'] ?? 'paypal' )
				: 'paypal',
			'payments_enable_stripe' => ! empty( $input['payments_enable_stripe'] ),
			'payments_stripe_test_mode' => ! empty( $input['payments_stripe_test_mode'] ),
			'payments_stripe_publishable_key' => sanitize_text_field( $input['payments_stripe_publishable_key'] ?? '' ),
			'payments_stripe_secret_key' => sanitize_text_field( $input['payments_stripe_secret_key'] ?? '' ),
			'payments_stripe_webhook_secret' => sanitize_text_field( $input['payments_stripe_webhook_secret'] ?? '' ),
			'payments_stripe_success_url' => esc_url_raw( $input['payments_stripe_success_url'] ?? '' ),
			'payments_stripe_cancel_url' => esc_url_raw( $input['payments_stripe_cancel_url'] ?? '' ),
			'payments_stripe_webhook_tolerance' => max( 60, min( 1800, absint( $input['payments_stripe_webhook_tolerance'] ?? 300 ) ) ),
		);
		$output['security_prefer_minified_js'] = ! empty( $output['security_prefer_protected_assets'] );

		if ( empty( $output['licensing_master_api_key'] ) ) {
			$output['licensing_master_api_key'] = 'pckz-msk-' . wp_generate_password( 40, false, false );
		}

		if ( empty( $output['licensing_install_uuid'] ) ) {
			$output['licensing_install_uuid'] = wp_generate_uuid4();
		}

		if ( ! empty( $input['color_palette'] ) && is_string( $input['color_palette'] ) ) {
			$colors = array_filter( array_map( 'trim', explode( ',', $input['color_palette'] ) ) );
			$output['color_palette'] = array();
			foreach ( $colors as $color ) {
				$sanitized = sanitize_hex_color( $color );
				if ( $sanitized ) {
					$output['color_palette'][] = $sanitized;
				}
			}
		}

		if ( ! empty( $input['fonts_json'] ) ) {
			$fonts = json_decode( wp_unslash( $input['fonts_json'] ), true );
			if ( is_array( $fonts ) ) {
				$output['fonts'] = array();
				foreach ( $fonts as $font ) {
					if ( ! empty( $font['family'] ) ) {
						$output['fonts'][] = array(
							'family' => sanitize_text_field( $font['family'] ),
							'label'  => sanitize_text_field( $font['label'] ?? $font['family'] ),
						);
					}
				}
			}
		}

		$catalog = class_exists( 'PCKZ_Commerce' ) ? PCKZ_Commerce::currency_catalog() : array();
		$enabled = array();
		if ( ! empty( $input['price_currencies_enabled'] ) && is_array( $input['price_currencies_enabled'] ) ) {
			foreach ( $input['price_currencies_enabled'] as $code ) {
				$code = strtoupper( sanitize_text_field( $code ) );
				if ( isset( $catalog[ $code ] ) ) {
					$enabled[] = $code;
				}
			}
		}
		$output['price_currencies_enabled'] = ! empty( $enabled ) ? array_values( array_unique( $enabled ) ) : array( 'EUR' );

		$default = strtoupper( sanitize_text_field( $input['price_default_currency'] ?? 'EUR' ) );
		if ( ! isset( $catalog[ $default ] ) || ! in_array( $default, $output['price_currencies_enabled'], true ) ) {
			$default = $output['price_currencies_enabled'][0];
		}
		$output['price_default_currency'] = $default;
		$output['price_currency_code']    = $default;

		$output['price_by_currency'] = array();
		if ( ! empty( $input['price_by_currency'] ) && is_array( $input['price_by_currency'] ) ) {
			foreach ( $input['price_by_currency'] as $code => $amount ) {
				$code = strtoupper( sanitize_text_field( $code ) );
				if ( isset( $catalog[ $code ] ) && in_array( $code, $output['price_currencies_enabled'], true ) ) {
					$output['price_by_currency'][ $code ] = max( 0, (float) $amount );
				}
			}
		}

		return $output;
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		$screen = get_current_screen();
		$is_pckz = (
			strpos( $hook, 'pckz-canonical-engine' ) !== false
			|| strpos( $hook, 'pckz-' ) !== false
			|| ( $screen && PCKZ_Post_Type::POST_TYPE === $screen->post_type )
		);

		if ( ! $is_pckz ) {
			return;
		}

		wp_enqueue_style(
			'pckz-admin',
			PCKZCE_PLUGIN_URL . 'admin/css/admin.css',
			array(),
			PCKZ_Assets::version( 'admin/css/admin.css' )
		);

		if ( class_exists( 'PCKZ_Font_Library' ) && ( false !== strpos( $hook, 'pckz-designs' ) || false !== strpos( $hook, 'pckz-orders' ) ) ) {
			$google_url = PCKZ_Font_Library::google_fonts_css_url();
			if ( $google_url ) {
				wp_enqueue_style( 'pckz-admin-google-fonts', $google_url, array(), null );
			}
		}

		wp_enqueue_media();
		wp_enqueue_script(
			'pckz-admin',
			PCKZCE_PLUGIN_URL . 'admin/js/admin.js',
			array( 'jquery' ),
			PCKZ_Assets::version( 'admin/js/admin.js' ),
			true
		);

		if ( false !== strpos( $hook, 'pckz-icon-library' ) ) {
			wp_enqueue_script(
				'pckz-library-admin',
				PCKZCE_PLUGIN_URL . 'admin/js/library-admin.js',
				array(),
				PCKZ_Assets::version( 'admin/js/library-admin.js' ),
				true
			);
			wp_localize_script(
				'pckz-library-admin',
				'pckzLibraryAdmin',
				array(
					'icon' => array(
						'tableSelector'      => '.pckz-icon-library-table',
						'formId'             => 'pckz-icon-library-save-form',
						'payloadId'          => 'pckz-icon-library-payload',
						'bulkInputId'        => 'pckz-icon-bulk-slugs',
						'bulkActionId'       => 'pckz-icon-bulk-delete-flag',
						'rowSlugAttr'        => 'data-icon-slug',
						'payloadKey'         => 'icons',
						'enabledClass'       => 'pckz-icon-enabled',
						'labelClass'         => 'pckz-icon-label',
						'bulkCheckboxClass'  => 'pckz-library-bulk-select',
						'enableAllId'        => 'pckz-icon-enable-all',
						'disableAllId'       => 'pckz-icon-disable-all',
						'selectAllId'        => 'pckz-icon-select-all-custom',
						'deselectAllId'      => 'pckz-icon-deselect-all-custom',
						'headerSelectId'     => 'pckz-icon-header-select',
						'bulkDeleteId'       => 'pckz-icon-bulk-delete',
						'singleDeleteName'   => 'pckz_icon_delete',
						'messages'           => array(
							'emptyPayload'       => __( 'Icon library save payload is empty. Please reload the page and try again.', 'pckz-canonical-engine' ),
							'selectItems'        => __( 'Select one or more custom icons to delete.', 'pckz-canonical-engine' ),
							'confirmBulkDelete'  => __( 'Delete {count} selected icon(s)? This cannot be undone.', 'pckz-canonical-engine' ),
						),
					),
				)
			);
		}

		if ( false !== strpos( $hook, 'pckz-line-library' ) ) {
			wp_enqueue_script(
				'pckz-library-admin',
				PCKZCE_PLUGIN_URL . 'admin/js/library-admin.js',
				array(),
				PCKZ_Assets::version( 'admin/js/library-admin.js' ),
				true
			);
			wp_localize_script(
				'pckz-library-admin',
				'pckzLibraryAdmin',
				array(
					'line' => array(
						'tableSelector'      => '.pckz-line-library-table',
						'formId'             => 'pckz-line-library-save-form',
						'payloadId'          => 'pckz-line-library-payload',
						'bulkInputId'        => 'pckz-line-bulk-slugs',
						'bulkActionId'       => 'pckz-line-bulk-delete-flag',
						'rowSlugAttr'        => 'data-line-slug',
						'payloadKey'         => 'lines',
						'enabledClass'       => 'pckz-line-enabled',
						'labelClass'         => 'pckz-line-label',
						'connectedClass'     => 'pckz-line-connected-right',
						'adminVisibleClass'  => 'pckz-line-admin-visible',
						'activeClass'        => 'pckz-line-active',
						'bulkCheckboxClass'  => 'pckz-library-bulk-select',
						'bulkAllRows'        => true,
						'enableAllId'        => 'pckz-line-enable-all',
						'disableAllId'       => 'pckz-line-disable-all',
						'selectAllId'        => 'pckz-line-select-all-custom',
						'deselectAllId'      => 'pckz-line-deselect-all-custom',
						'headerSelectId'     => 'pckz-line-header-select',
						'bulkDeleteId'       => 'pckz-line-bulk-delete',
						'singleDeleteName'   => 'pckz_line_delete',
						'orderEnabled'       => true,
						'messages'           => array(
							'emptyPayload'       => __( 'Line library save payload is empty. Please reload the page and try again.', 'pckz-canonical-engine' ),
							'selectItems'        => __( 'Select one or more line models.', 'pckz-canonical-engine' ),
							'confirmBulkDelete'  => __( 'Permanently delete {count} selected model(s)? Built-in SVG files are removed from disk and disappear from admin, customer preview, and export.', 'pckz-canonical-engine' ),
						),
					),
				)
			);
		}
	}

	/**
	 * Render dashboard page.
	 */
	public function render_dashboard() {
		$products_count = wp_count_posts( PCKZ_Post_Type::POST_TYPE );
		$published      = isset( $products_count->publish ) ? (int) $products_count->publish : 0;
		include PCKZCE_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	/**
	 * Render icon library visibility admin.
	 */
	public function render_icon_library() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_POST['pckz_icon_library_upload'] ) && check_admin_referer( 'pckz_icon_library_upload', 'pckz_icon_library_upload_nonce' ) ) {
			$result = PCKZ_Icon_Library::handle_upload( $_FILES['pckz_icon_file'] ?? array() );
			if ( is_wp_error( $result ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
			} else {
				echo '<div class="notice notice-success"><p>' . esc_html__( 'Icon uploaded.', 'pckz-canonical-engine' ) . '</p></div>';
			}
		}

		if ( isset( $_POST['pckz_icon_library_url_import'] ) && check_admin_referer( 'pckz_icon_library_url', 'pckz_icon_library_url_nonce' ) ) {
			$url   = isset( $_POST['icon_import_url'] ) ? esc_url_raw( wp_unslash( $_POST['icon_import_url'] ) ) : '';
			$label = isset( $_POST['icon_import_label'] ) ? sanitize_text_field( wp_unslash( $_POST['icon_import_label'] ) ) : '';
			$result = PCKZ_Icon_Library::handle_url_import( $url, $label );
			if ( is_wp_error( $result ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
			} else {
				echo '<div class="notice notice-success"><p>' . esc_html__( 'Icon imported from URL.', 'pckz-canonical-engine' ) . '</p></div>';
			}
		}

		if ( ! empty( $_POST['pckz_icon_library_bulk_delete'] ) && check_admin_referer( 'pckz_icon_library_save', 'pckz_icon_library_nonce' ) ) {
			$slugs  = $this->parse_library_bulk_slugs( $_POST['pckz_icon_bulk_slugs_json'] ?? '' );
			$result = PCKZ_Icon_Library::delete_custom_bulk( $slugs );
			if ( is_wp_error( $result ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
			} else {
				/* translators: %d: number of deleted icons */
				echo '<div class="notice notice-success"><p>' . esc_html( sprintf( __( '%d icon(s) deleted.', 'pckz-canonical-engine' ), (int) $result['deleted'] ) ) . '</p></div>';
			}
		} elseif ( isset( $_POST['pckz_icon_delete'] ) && check_admin_referer( 'pckz_icon_library_save', 'pckz_icon_library_nonce' ) ) {
			$del = PCKZ_Icon_Library::delete_custom( sanitize_key( wp_unslash( $_POST['pckz_icon_delete'] ) ) );
			if ( is_wp_error( $del ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html( $del->get_error_message() ) . '</p></div>';
			} else {
				echo '<div class="notice notice-success"><p>' . esc_html__( 'Icon deleted.', 'pckz-canonical-engine' ) . '</p></div>';
			}
		} elseif ( isset( $_POST['pckz_icon_library_save'] ) && check_admin_referer( 'pckz_icon_library_save', 'pckz_icon_library_nonce' ) ) {
			$result = PCKZ_Icon_Library::save_admin_state_from_post( wp_unslash( $_POST ) );
			if ( is_wp_error( $result ) ) {
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
			} else {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Icon library updated.', 'pckz-canonical-engine' ) . '</p></div>';
			}
		}

		$catalog  = PCKZ_Icon_Library::admin_catalog_entries();
		$disabled = PCKZ_Icon_Library::disabled_slugs();
		$payload  = PCKZ_Icon_Library::build_admin_save_payload();
		include PCKZCE_PLUGIN_DIR . 'admin/views/icon-library.php';
	}

	/**
	 * Render line library visibility admin.
	 */
	public function render_line_library() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_POST['pckz_line_library_upload'] ) && check_admin_referer( 'pckz_line_library_upload', 'pckz_line_library_upload_nonce' ) ) {
			$result = PCKZ_Line_Library::handle_upload( $_FILES['pckz_line_file'] ?? array() );
			if ( is_wp_error( $result ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
			} else {
				$slug = is_array( $result ) && ! empty( $result['slug'] ) ? $result['slug'] : '';
				if ( $slug && PCKZ_Line_Library::is_line_in_catalog( $slug, false ) ) {
					echo '<div class="notice notice-success"><p>' . esc_html(
						sprintf(
							/* translators: %s: line slug e.g. type_102 */
							__( 'Line design uploaded as %s and added to the library.', 'pckz-canonical-engine' ),
							$slug
						)
					) . '</p></div>';
				} else {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'Upload could not be registered in the line library.', 'pckz-canonical-engine' ) . '</p></div>';
				}
			}
		}

		if ( isset( $_POST['pckz_line_library_vector_import'] ) && check_admin_referer( 'pckz_line_library_vector_import', 'pckz_line_library_vector_import_nonce' ) ) {
			$result = PCKZ_Line_Library::handle_vector_import( $_FILES['pckz_line_vector_file'] ?? array() );
			if ( is_wp_error( $result ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
			} else {
				$slug = is_array( $result ) && ! empty( $result['slug'] ) ? $result['slug'] : '';
				if ( $slug ) {
					echo '<div class="notice notice-success"><p>' . esc_html(
						sprintf(
							/* translators: %s: line slug e.g. type_102 */
							__( 'Line model imported as %s. It is now available in the library and customer preview.', 'pckz-canonical-engine' ),
							$slug
						)
					) . '</p></div>';
				} else {
					echo '<div class="notice notice-success"><p>' . esc_html__( 'Line model imported.', 'pckz-canonical-engine' ) . '</p></div>';
				}
			}
		}

		if ( isset( $_POST['pckz_line_library_url_import'] ) && check_admin_referer( 'pckz_line_library_url', 'pckz_line_library_url_nonce' ) ) {
			$url   = isset( $_POST['line_import_url'] ) ? esc_url_raw( wp_unslash( $_POST['line_import_url'] ) ) : '';
			$label = isset( $_POST['line_import_label'] ) ? sanitize_text_field( wp_unslash( $_POST['line_import_label'] ) ) : '';
			$result = PCKZ_Line_Library::handle_url_import( $url, $label );
			if ( is_wp_error( $result ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
			} else {
				$slug = is_array( $result ) && ! empty( $result['slug'] ) ? $result['slug'] : '';
				if ( $slug && PCKZ_Line_Library::is_line_in_catalog( $slug, false ) ) {
					echo '<div class="notice notice-success"><p>' . esc_html(
						sprintf(
							/* translators: %s: line slug */
							__( 'Line design imported from URL as %s.', 'pckz-canonical-engine' ),
							$slug
						)
					) . '</p></div>';
				} else {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'URL import could not be registered in the line library.', 'pckz-canonical-engine' ) . '</p></div>';
				}
			}
		}

		if ( ! empty( $_POST['pckz_line_library_bulk_delete'] ) && check_admin_referer( 'pckz_line_library_save', 'pckz_line_library_nonce' ) ) {
			$slugs  = $this->parse_library_bulk_slugs( $_POST['pckz_line_bulk_slugs_json'] ?? '' );
			$result = PCKZ_Line_Library::delete_selected_bulk( $slugs );
			if ( is_wp_error( $result ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
			} else {
				$parts = array();
				if ( ! empty( $result['deleted'] ) ) {
					/* translators: %d: number of deleted custom lines */
					$parts[] = sprintf( __( '%d custom upload(s) deleted.', 'pckz-canonical-engine' ), (int) $result['deleted'] );
				}
				if ( empty( $parts ) ) {
					$parts[] = __( 'Selection processed.', 'pckz-canonical-engine' );
				}
				echo '<div class="notice notice-success"><p>' . esc_html( implode( ' ', $parts ) ) . '</p></div>';
			}
		} elseif ( isset( $_POST['pckz_line_delete'] ) && check_admin_referer( 'pckz_line_library_save', 'pckz_line_library_nonce' ) ) {
			$slug = sanitize_key( wp_unslash( $_POST['pckz_line_delete'] ) );
			$del  = PCKZ_Line_Library::is_custom( $slug )
				? PCKZ_Line_Library::delete_custom( $slug )
				: PCKZ_Line_Library::delete_bundled_permanent( $slug );
			if ( is_wp_error( $del ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html( $del->get_error_message() ) . '</p></div>';
			} else {
				echo '<div class="notice notice-success"><p>' . esc_html__( 'Line design deleted permanently.', 'pckz-canonical-engine' ) . '</p></div>';
			}
		} elseif ( isset( $_POST['pckz_line_library_save'] ) && check_admin_referer( 'pckz_line_library_save', 'pckz_line_library_nonce' ) ) {
			$result = PCKZ_Line_Library::save_admin_state_from_post( wp_unslash( $_POST ) );
			if ( is_wp_error( $result ) ) {
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
			} else {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Line library updated.', 'pckz-canonical-engine' ) . '</p></div>';
			}
		}

		$catalog  = PCKZ_Line_Library::admin_catalog_entries();
		$disabled = PCKZ_Line_Library::disabled_slugs();
		$payload  = PCKZ_Line_Library::build_admin_save_payload();
		include PCKZCE_PLUGIN_DIR . 'admin/views/line-library.php';
	}

	/**
	 * Render font library admin.
	 */
	public function render_font_library() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_POST['pckz_font_library_upload'] ) && check_admin_referer( 'pckz_font_library_upload', 'pckz_font_library_upload_nonce' ) ) {
			$result = PCKZ_Font_Library::handle_upload( $_FILES['pckz_font_file'] ?? array() );
			if ( is_wp_error( $result ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
			} else {
				echo '<div class="notice notice-success"><p>' . esc_html__( 'Font uploaded.', 'pckz-canonical-engine' ) . '</p></div>';
			}
		}

		if ( isset( $_POST['pckz_font_delete'] ) && check_admin_referer( 'pckz_font_library_save', 'pckz_font_library_nonce' ) ) {
			$del = PCKZ_Font_Library::delete_custom( sanitize_key( wp_unslash( $_POST['pckz_font_delete'] ) ) );
			if ( is_wp_error( $del ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html( $del->get_error_message() ) . '</p></div>';
			} else {
				echo '<div class="notice notice-success"><p>' . esc_html__( 'Font deleted.', 'pckz-canonical-engine' ) . '</p></div>';
			}
		}

		if ( isset( $_POST['pckz_font_library_save'] ) && check_admin_referer( 'pckz_font_library_save', 'pckz_font_library_nonce' ) && ! isset( $_POST['pckz_font_delete'] ) ) {
			$enabled = isset( $_POST['pckz_font_enabled'] ) && is_array( $_POST['pckz_font_enabled'] )
				? array_map( 'sanitize_key', wp_unslash( $_POST['pckz_font_enabled'] ) )
				: array();
			$labels  = isset( $_POST['pckz_font_labels'] ) && is_array( $_POST['pckz_font_labels'] )
				? wp_unslash( $_POST['pckz_font_labels'] )
				: array();
			PCKZ_Font_Library::save_admin_state( $enabled, $labels );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Font library updated.', 'pckz-canonical-engine' ) . '</p></div>';
		}

		$entries    = PCKZ_Font_Library::all_entries();
		$disabled   = PCKZ_Font_Library::disabled_ids();
		$categories = PCKZ_Font_Library::categories();
		include PCKZCE_PLUGIN_DIR . 'admin/views/font-library.php';
	}

	/**
	 * Render settings page.
	 */
	public function render_settings() {
		$settings = PCKZ_Settings::get_all();
		include PCKZCE_PLUGIN_DIR . 'admin/views/settings.php';
	}

	/**
	 * Render about-the-developer page (PAXDesign).
	 */
	public function render_about_developer() {
		PCKZ_Branding::render_about_page();
	}

	/**
	 * Render saved designs list.
	 */
	public function render_designs() {
		$design_id = isset( $_GET['design_id'] ) ? absint( $_GET['design_id'] ) : 0;

		if ( $design_id ) {
			$this->render_design_detail( $design_id );
			return;
		}

		global $wpdb;
		$table   = $wpdb->prefix . 'pckz_designs';
		$designs = array();

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
			$designs = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 100" );
		}

		include PCKZCE_PLUGIN_DIR . 'admin/views/designs.php';
	}

	/**
	 * Render single design with full LightBurn production data.
	 *
	 * @param int $design_id Design ID.
	 */
	private function render_design_detail( $design_id ) {
		$design  = PCKZ_Design_Storage::get_design( $design_id );
		$package = array();

		if ( $design ) {
			$meta = array();
			if ( ! empty( $design['meta_json'] ) ) {
				$meta = json_decode( $design['meta_json'], true );
			}
			if ( ! empty( $meta['production'] ) && is_array( $meta['production'] ) ) {
				$package = $meta['production'];
			}
			if ( ! empty( $package ) && empty( $package['production_lbrn2_url'] ) && ! empty( $package['layout']['objects'] ) ) {
				$package = PCKZ_Production::persist_export_files( $package, $design_id );
				PCKZ_Design_Storage::update_meta(
					$design_id,
					array_merge(
						$meta,
						array(
							'production'          => $package,
							'lightburn_json_url'  => $package['lightburn_json_url'] ?? '',
							'canvas_json_url'     => $package['canvas_json_url'] ?? '',
							'production_svg_url'   => $package['production_svg_url'] ?? '',
							'production_lbrn2_url' => $package['production_lbrn2_url'] ?? '',
							'production_lbrn_url'  => $package['production_lbrn_url'] ?? '',
							'production_dxf_url'   => $package['production_dxf_url'] ?? '',
						)
					)
				);
			}
			if ( empty( $package ) ) {
				$config = PCKZ_Post_Type::get_product_config( (int) ( $design['product_id'] ?? 0 ) );
				$package = PCKZ_Production::build_package(
					array(
						'selections'  => $meta['selections'] ?? array(),
						'canvas_json' => $design['canvas_json'] ?? '',
						'config'      => $config,
						'preview_url' => $design['preview_url'] ?? '',
						'export_url'  => $design['export_url'] ?? '',
						'std_spec'    => $meta['std_spec'] ?? array(),
						'design_id'   => $design_id,
					)
				);
				$package = PCKZ_Production::persist_export_files( $package, $design_id );
				PCKZ_Design_Storage::update_meta(
					$design_id,
					array_merge(
						$meta,
						array(
							'production'         => $package,
							'lightburn_json_url'  => $package['lightburn_json_url'] ?? '',
							'canvas_json_url'     => $package['canvas_json_url'] ?? '',
							'production_svg_url'   => $package['production_svg_url'] ?? '',
							'production_lbrn2_url' => $package['production_lbrn2_url'] ?? '',
							'production_lbrn_url'  => $package['production_lbrn_url'] ?? '',
							'production_dxf_url'   => $package['production_dxf_url'] ?? '',
						)
					)
				);
			}
		}

		include PCKZCE_PLUGIN_DIR . 'admin/views/design-detail.php';
	}

	/**
	 * Render commerce orders list or single order detail.
	 */
	public function render_orders() {
		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$orders   = class_exists( 'PCKZ_Commerce' )
			? PCKZ_Commerce::list_orders( 200, array( 'search' => $search ) )
			: array();
		$order         = null;
		$order_package = array();
		$order_design  = null;
		$order_meta    = array();

		if ( $order_id && class_exists( 'PCKZ_Commerce' ) ) {
			$order = PCKZ_Commerce::get_order( $order_id );
			if ( $order && ! empty( $order['design_id'] ) ) {
				$order_design = PCKZ_Design_Storage::get_design( (int) $order['design_id'] );
				if ( $order_design ) {
					if ( ! empty( $order_design['meta_json'] ) ) {
						$order_meta = json_decode( $order_design['meta_json'], true );
					}
					if ( is_array( $order_meta ) && ! empty( $order_meta['production'] ) ) {
						$order_package = $order_meta['production'];
					}
				}
			}
		}

		include PCKZCE_PLUGIN_DIR . 'admin/views/orders.php';
	}
}
