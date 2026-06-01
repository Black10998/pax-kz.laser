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
		add_action( 'admin_post_pckz_update_order_status', array( $this, 'handle_update_order_status' ) );
		add_action( 'admin_post_pckz_update_order_notes', array( $this, 'handle_update_order_notes' ) );
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
			'licensing_master_url'  => array_key_exists( 'licensing_master_url', $input ) ? esc_url_raw( $input['licensing_master_url'] ) : esc_url_raw( (string) ( $current['licensing_master_url'] ?? 'https://paxdesign.at' ) ),
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
				'pckz-icon-library',
				PCKZCE_PLUGIN_URL . 'admin/js/icon-library.js',
				array(),
				PCKZ_Assets::version( 'admin/js/icon-library.js' ),
				true
			);
			wp_localize_script(
				'pckz-icon-library',
				'pckzIconLibrary',
				array(
					'emptyPayloadMessage' => __( 'Icon library save payload is empty. Please reload the page and try again.', 'pckz-canonical-engine' ),
				)
			);
		}

		if ( false !== strpos( $hook, 'pckz-line-library' ) ) {
			wp_enqueue_script(
				'pckz-line-library',
				PCKZCE_PLUGIN_URL . 'admin/js/line-library.js',
				array(),
				PCKZ_Assets::version( 'admin/js/line-library.js' ),
				true
			);
			wp_localize_script(
				'pckz-line-library',
				'pckzLineLibrary',
				array(
					'emptyPayloadMessage' => __( 'Line library save payload is empty. Please reload the page and try again.', 'pckz-canonical-engine' ),
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

		if ( isset( $_POST['pckz_icon_delete'] ) && check_admin_referer( 'pckz_icon_library_save', 'pckz_icon_library_nonce' ) ) {
			$del = PCKZ_Icon_Library::delete_custom( sanitize_key( wp_unslash( $_POST['pckz_icon_delete'] ) ) );
			if ( is_wp_error( $del ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html( $del->get_error_message() ) . '</p></div>';
			} else {
				echo '<div class="notice notice-success"><p>' . esc_html__( 'Icon deleted.', 'pckz-canonical-engine' ) . '</p></div>';
			}
		}

		if ( isset( $_POST['pckz_icon_library_save'] ) && check_admin_referer( 'pckz_icon_library_save', 'pckz_icon_library_nonce' ) && ! isset( $_POST['pckz_icon_delete'] ) ) {
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
				echo '<div class="notice notice-success"><p>' . esc_html__( 'Line design uploaded.', 'pckz-canonical-engine' ) . '</p></div>';
			}
		}

		if ( isset( $_POST['pckz_line_delete'] ) && check_admin_referer( 'pckz_line_library_save', 'pckz_line_library_nonce' ) ) {
			$del = PCKZ_Line_Library::delete_custom( sanitize_key( wp_unslash( $_POST['pckz_line_delete'] ) ) );
			if ( is_wp_error( $del ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html( $del->get_error_message() ) . '</p></div>';
			} else {
				echo '<div class="notice notice-success"><p>' . esc_html__( 'Line design deleted.', 'pckz-canonical-engine' ) . '</p></div>';
			}
		}

		if ( isset( $_POST['pckz_line_library_save'] ) && check_admin_referer( 'pckz_line_library_save', 'pckz_line_library_nonce' ) && ! isset( $_POST['pckz_line_delete'] ) ) {
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
