<?php
/**
 * Licensing, master-control APIs, update delivery, and export authorization.
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PCKZ_Licensing
 */
class PCKZ_Licensing {

	const OPTION_CLIENT_STATE   = 'pckzce_license_state';
	const OPTION_INSTALL_SECRET = 'pckzce_license_install_secret';
	const OPTION_RELEASE_META   = 'pckzce_master_release_meta';
	const OPTION_CLIENT_PACKAGE_HASH = 'pckzce_client_package_hash';
	const OPTION_CLIENT_BOUND_DOMAINS = 'pckzce_client_bound_domains';
	const OPTION_REPLAY_PREFIX  = 'pckzce_license_replay_';
	const HEARTBEAT_HOOK        = 'pckzce_license_heartbeat';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'bootstrap' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( self::HEARTBEAT_HOOK, array( $this, 'heartbeat_task' ) );

		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_notices', array( $this, 'maybe_show_admin_notice' ) );

		if ( self::is_master_mode() ) {
			add_action( 'admin_post_pckzce_create_license', array( $this, 'handle_create_license' ) );
			add_action( 'admin_post_pckzce_update_license_status', array( $this, 'handle_update_license_status' ) );
			add_action( 'admin_post_pckzce_update_installation_status', array( $this, 'handle_update_installation_status' ) );
			add_action( 'admin_post_pckzce_save_release_meta', array( $this, 'handle_save_release_meta' ) );
			add_action( 'admin_post_pckzce_save_license_detail', array( $this, 'handle_save_license_detail' ) );
			add_action( 'admin_post_pckzce_generate_customer_package', array( $this, 'handle_generate_customer_package' ) );
		}

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_plugin_update' ) );
		add_filter( 'plugins_api', array( $this, 'inject_plugin_info' ), 10, 3 );
	}

	/**
	 * Activation/upgrade table create.
	 */
	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();

		$licenses = $wpdb->prefix . 'pckz_license_keys';
		$installs = $wpdb->prefix . 'pckz_license_installations';
		$downloads = $wpdb->prefix . 'pckz_license_downloads';

		$sql_licenses = "CREATE TABLE {$licenses} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			license_key VARCHAR(191) NOT NULL,
			label VARCHAR(191) NOT NULL DEFAULT '',
			status VARCHAR(32) NOT NULL DEFAULT 'active',
			domains LONGTEXT NULL,
			permissions LONGTEXT NULL,
			max_installs INT UNSIGNED NOT NULL DEFAULT 1,
			expires_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY license_key (license_key)
		) {$charset};";

		$sql_installs = "CREATE TABLE {$installs} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			license_id BIGINT UNSIGNED NOT NULL,
			install_uuid VARCHAR(96) NOT NULL,
			domain VARCHAR(191) NOT NULL,
			status VARCHAR(32) NOT NULL DEFAULT 'active',
			plugin_version VARCHAR(64) NOT NULL DEFAULT '',
			plugin_build VARCHAR(128) NOT NULL DEFAULT '',
			wp_version VARCHAR(32) NOT NULL DEFAULT '',
			php_version VARCHAR(32) NOT NULL DEFAULT '',
			integrity_hash VARCHAR(128) NOT NULL DEFAULT '',
			tamper_signals LONGTEXT NULL,
			last_check_in DATETIME NULL,
			heartbeat_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
			last_ip VARCHAR(64) NOT NULL DEFAULT '',
			last_user_agent VARCHAR(255) NOT NULL DEFAULT '',
			last_error TEXT NULL,
			install_secret VARCHAR(191) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY license_install_domain (license_id, install_uuid, domain),
			KEY license_id (license_id),
			KEY domain (domain)
		) {$charset};";
		$sql_downloads = "CREATE TABLE {$downloads} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			license_id BIGINT UNSIGNED NOT NULL,
			installation_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			domain VARCHAR(191) NOT NULL,
			install_uuid VARCHAR(96) NOT NULL,
			requested_version VARCHAR(64) NOT NULL DEFAULT '',
			package_url TEXT NULL,
			last_ip VARCHAR(64) NOT NULL DEFAULT '',
			last_user_agent VARCHAR(255) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY license_id (license_id),
			KEY installation_id (installation_id),
			KEY domain (domain),
			KEY created_at (created_at)
		) {$charset};";

		dbDelta( $sql_licenses );
		dbDelta( $sql_installs );
		dbDelta( $sql_downloads );
	}

	/**
	 * Core bootstrapping.
	 */
	public function bootstrap() {
		$this->ensure_install_uuid();
		$this->enforce_master_host_lock();
		$this->apply_embedded_client_package_config();
		$this->schedule_heartbeat();
	}

	/**
	 * Force master mode off when host is not allowed.
	 */
	private function enforce_master_host_lock() {
		if ( ! self::master_mode_enabled() || self::is_master_host_allowed() ) {
			return;
		}
		$settings = PCKZ_Settings::get_all();
		if ( empty( $settings['licensing_master_mode'] ) ) {
			return;
		}
		$settings['licensing_master_mode'] = false;
		update_option( PCKZ_Settings::OPTION_KEY, $settings, false );
	}

	/**
	 * Register master-control admin page.
	 */
	public function register_admin_menu() {
		$master_mode = self::is_master_mode();
		$page_title = $master_mode ? __( 'Master Control', 'pckz-canonical-engine' ) : __( 'License Dashboard', 'pckz-canonical-engine' );
		$menu_title = $master_mode ? __( 'Master Control', 'pckz-canonical-engine' ) : __( 'License Dashboard', 'pckz-canonical-engine' );
		add_submenu_page(
			'pckz-canonical-engine',
			$page_title,
			$menu_title,
			'manage_options',
			'pckz-license-server',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Show admin notice if enforcement is active and license is invalid.
	 */
	public function maybe_show_admin_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( self::master_mode_enabled() && ! self::is_master_host_allowed() ) {
			echo '<div class="notice notice-error"><p><strong>PCKZ Master Control:</strong> ' . esc_html__( 'Master mode is restricted to paxdesign.at. This installation runs in client mode only.', 'pckz-canonical-engine' ) . '</p></div>';
		}
		$settings = PCKZ_Settings::get_all();
		if ( empty( $settings['licensing_enforce'] ) ) {
			return;
		}
		if ( self::can_run_feature( 'export' ) ) {
			return;
		}
		$state = self::get_client_state();
		$reason = ! empty( $state['reason'] ) ? (string) $state['reason'] : __( 'License validation failed.', 'pckz-canonical-engine' );
		echo '<div class="notice notice-error"><p><strong>PCKZ Licensing:</strong> ' . esc_html( $reason ) . '</p></div>';
	}

	/**
	 * Render license server admin page.
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings     = PCKZ_Settings::get_all();
		$master_mode  = self::is_master_mode();
		$generated    = get_transient( 'pckzce_last_created_license' );
		$package_notice = get_transient( 'pckzce_customer_package_notice' );
		$package_error  = get_transient( 'pckzce_customer_package_error' );
		$release_meta = get_option(
			self::OPTION_RELEASE_META,
			array(
				'version'             => '',
				'package_url'         => '',
				'changelog'           => '',
				'requires'            => '6.0',
				'requires_php'        => '7.4',
				'tested'              => '',
				'min_client_build'    => '',
				'allow_remote_export' => false,
			)
		);
		$client_state = self::get_client_state();
		$client_summary = $this->client_dashboard_summary( $settings, $client_state );

		global $wpdb;
		$licenses       = array();
		$installs       = array();
		$downloads      = array();
		$recent_errors  = array();
		$stats          = array(
			'licenses_total'        => 0,
			'licenses_active'       => 0,
			'installations_total'   => 0,
			'installations_active'  => 0,
			'installations_blocked' => 0,
			'downloads_total'       => 0,
			'downloads_24h'         => 0,
		);
		if ( $master_mode ) {
			$licenses = $wpdb->get_results( 'SELECT * FROM ' . $wpdb->prefix . 'pckz_license_keys ORDER BY id DESC LIMIT 300', ARRAY_A );
			$where    = '1=1';
			$params   = array();
			$search   = isset( $_GET['pckz_install_s'] ) ? sanitize_text_field( wp_unslash( $_GET['pckz_install_s'] ) ) : '';
			$status   = isset( $_GET['pckz_install_status'] ) ? sanitize_key( wp_unslash( $_GET['pckz_install_status'] ) ) : '';
			if ( $search ) {
				$where    .= ' AND (domain LIKE %s OR install_uuid LIKE %s)';
				$params[] = '%' . $wpdb->esc_like( $search ) . '%';
				$params[] = '%' . $wpdb->esc_like( $search ) . '%';
			}
			if ( in_array( $status, array( 'active', 'blocked' ), true ) ) {
				$where    .= ' AND status = %s';
				$params[] = $status;
			}
			$sql = 'SELECT * FROM ' . $wpdb->prefix . 'pckz_license_installations WHERE ' . $where . ' ORDER BY updated_at DESC LIMIT 1000';
			if ( ! empty( $params ) ) {
				$sql = $wpdb->prepare( $sql, ...$params );
			}
			$installs = $wpdb->get_results( $sql, ARRAY_A );
			$downloads = $wpdb->get_results( 'SELECT * FROM ' . $wpdb->prefix . 'pckz_license_downloads ORDER BY id DESC LIMIT 300', ARRAY_A );
			$recent_errors = $wpdb->get_results( 'SELECT domain, install_uuid, last_error, updated_at FROM ' . $wpdb->prefix . "pckz_license_installations WHERE last_error <> '' ORDER BY updated_at DESC LIMIT 50", ARRAY_A );

			$stats['licenses_total'] = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'pckz_license_keys' );
			$stats['licenses_active'] = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'pckz_license_keys WHERE status = %s', 'active' ) );
			$stats['installations_total'] = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'pckz_license_installations' );
			$stats['installations_active'] = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'pckz_license_installations WHERE status = %s', 'active' ) );
			$stats['installations_blocked'] = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'pckz_license_installations WHERE status = %s', 'blocked' ) );
			$stats['downloads_total'] = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'pckz_license_downloads' );
			$stats['downloads_24h'] = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'pckz_license_downloads WHERE created_at >= %s',
					gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS )
				)
			);
		}
		$customer_packages = self::list_customer_packages();
		include PCKZCE_PLUGIN_DIR . 'admin/views/licensing-dashboard.php';
		if ( $generated ) {
			delete_transient( 'pckzce_last_created_license' );
		}
		if ( $package_notice ) {
			delete_transient( 'pckzce_customer_package_notice' );
		}
		if ( $package_error ) {
			delete_transient( 'pckzce_customer_package_error' );
		}
	}

	/**
	 * Build summary payload for the restricted client dashboard.
	 *
	 * @param array $settings     Saved plugin settings.
	 * @param array $client_state Cached client entitlement state.
	 * @return array
	 */
	private function client_dashboard_summary( $settings, $client_state ) {
		$status       = sanitize_key( (string) ( $client_state['status'] ?? 'unknown' ) );
		$reason       = sanitize_text_field( (string) ( $client_state['reason'] ?? '' ) );
		$authorized   = ! empty( $client_state['authorized'] );
		$checked_at   = (int) ( $client_state['checked_at'] ?? 0 );
		$connected_to = self::normalize_master_url( (string) ( $client_state['master_url'] ?? $settings['licensing_master_url'] ?? '' ) );
		$permissions  = is_array( $client_state['permissions'] ?? null ) ? $client_state['permissions'] : array();

		$license_status = 'unknown';
		if ( $authorized ) {
			$license_status = 'active';
		} elseif ( false !== strpos( strtolower( $reason ), 'expired' ) ) {
			$license_status = 'expired';
		} elseif ( false !== strpos( strtolower( $reason ), 'revoked' ) || false !== strpos( strtolower( $reason ), 'blocked' ) ) {
			$license_status = 'blocked';
		} elseif ( in_array( $status, array( 'denied', 'network_error', 'unconfigured' ), true ) ) {
			$license_status = $status;
		}

		$license_type = __( 'Restricted', 'pckz-canonical-engine' );
		if ( ! empty( $permissions['export'] ) && ! empty( $permissions['updates'] ) ) {
			$license_type = __( 'Full Access', 'pckz-canonical-engine' );
		} elseif ( ! empty( $permissions['export'] ) ) {
			$license_type = __( 'Export Access', 'pckz-canonical-engine' );
		} elseif ( ! empty( $permissions['updates'] ) ) {
			$license_type = __( 'Updates Access', 'pckz-canonical-engine' );
		}

		$update_label  = __( 'Unknown', 'pckz-canonical-engine' );
		$update_status = 'unknown';
		if ( empty( $permissions['updates'] ) ) {
			$update_label  = __( 'Not permitted by license', 'pckz-canonical-engine' );
			$update_status = 'blocked';
		} else {
			$remote_meta = $this->fetch_remote_update_meta();
			if ( ! empty( $remote_meta['update_available'] ) ) {
				$version      = sanitize_text_field( (string) ( $remote_meta['version'] ?? '' ) );
				$update_label = $version ? sprintf( __( 'Update available: %s', 'pckz-canonical-engine' ), $version ) : __( 'Update available', 'pckz-canonical-engine' );
				$update_status = 'available';
			} elseif ( ! empty( $remote_meta['ok'] ) || ! empty( $remote_meta['version'] ) ) {
				$update_label  = __( 'Up to date', 'pckz-canonical-engine' );
				$update_status = 'ok';
			}
		}

		return array(
			'license_status'      => $license_status,
			'license_reason'      => $reason,
			'connected_server'    => $connected_to ? $connected_to : __( 'Not configured', 'pckz-canonical-engine' ),
			'domain'              => self::normalized_domain(),
			'license_type'        => $license_type,
			'installed_version'   => PCKZCE_VERSION,
			'installed_build'     => defined( 'PCKZCE_BUILD' ) ? PCKZCE_BUILD : PCKZCE_VERSION,
			'last_check_in_time'  => $checked_at ? gmdate( 'Y-m-d H:i:s', $checked_at ) . ' UTC' : __( 'Never', 'pckz-canonical-engine' ),
			'last_check_in_human' => $checked_at ? sprintf( __( '%s ago', 'pckz-canonical-engine' ), human_time_diff( $checked_at, time() ) ) : __( 'No heartbeat yet', 'pckz-canonical-engine' ),
			'update_status'       => $update_status,
			'update_label'        => $update_label,
			'license_hint'        => sanitize_text_field( (string) ( $client_state['license_hint'] ?? '' ) ),
			'install_uuid'        => self::get_install_uuid(),
		);
	}

	/**
	 * Build customer-bound package from master dashboard.
	 */
	public function handle_generate_customer_package() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'pckz-canonical-engine' ) );
		}
		check_admin_referer( 'pckzce_generate_customer_package', 'pckzce_package_nonce' );
		if ( ! self::is_master_mode() ) {
			wp_safe_redirect( admin_url( 'admin.php?page=pckz-license-server' ) );
			exit;
		}

		global $wpdb;
		$license_id = absint( $_POST['license_id'] ?? 0 );
		$license    = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . $wpdb->prefix . 'pckz_license_keys WHERE id = %d LIMIT 1', $license_id ),
			ARRAY_A
		);
		if ( ! $license ) {
			set_transient( 'pckzce_customer_package_error', __( 'Please choose a valid license before generating a package.', 'pckz-canonical-engine' ), 10 * MINUTE_IN_SECONDS );
			wp_safe_redirect( admin_url( 'admin.php?page=pckz-license-server' ) );
			exit;
		}

		$domains = self::parse_domains( wp_unslash( $_POST['domains'] ?? '' ) );
		if ( empty( $domains ) ) {
			$domains = self::decode_json_array( (string) ( $license['domains'] ?? '' ) );
		}
		if ( empty( $domains ) ) {
			set_transient( 'pckzce_customer_package_error', __( 'At least one allowed domain is required.', 'pckz-canonical-engine' ), 10 * MINUTE_IN_SECONDS );
			wp_safe_redirect( admin_url( 'admin.php?page=pckz-license-server' ) );
			exit;
		}

		$stored_permissions = self::decode_json_assoc( (string) ( $license['permissions'] ?? '' ), array( 'export' => true, 'updates' => true ) );
		$has_export_field   = array_key_exists( 'perm_export', $_POST );
		$has_update_field   = array_key_exists( 'perm_updates', $_POST );
		$permissions = array(
			'export'  => $has_export_field ? ! empty( $_POST['perm_export'] ) : ! empty( $stored_permissions['export'] ),
			'updates' => $has_update_field ? ! empty( $_POST['perm_updates'] ) : ! empty( $stored_permissions['updates'] ),
		);

		$settings    = PCKZ_Settings::get_all();
		$master_url  = self::normalize_master_url( (string) ( $settings['licensing_master_url'] ?? home_url() ) );
		$license_key = sanitize_text_field( (string) ( $license['license_key'] ?? '' ) );

		$config_payload = array(
			'master_url'                        => $master_url,
			'license_key'                       => $license_key,
			'domains'                           => array_values( $domains ),
			'permissions'                       => $permissions,
			'licensing_master_mode'             => false,
			'licensing_enforce'                 => true,
			'licensing_grace_minutes'           => max( 5, min( 1440, absint( $_POST['grace_minutes'] ?? 120 ) ) ),
			'licensing_require_signed_requests' => true,
			'licensing_export_authorize'        => ! empty( $_POST['export_authorize'] ),
			'licensing_export_remote_mode'      => ! empty( $_POST['export_remote_mode'] ),
			'licensing_export_remote_strict'    => ! empty( $_POST['export_remote_strict'] ),
			'generated_at'                      => gmdate( 'c' ),
		);
		$binding_payload = array(
			'issued_at'         => gmdate( 'c' ),
			'master_url'        => $master_url,
			'license_key_mask'  => self::mask_key( $license_key ),
			'domains'           => array_values( $domains ),
			'permissions'       => $permissions,
			'license_label'     => sanitize_text_field( (string) ( $license['label'] ?? '' ) ),
			'license_id'        => (int) ( $license['id'] ?? 0 ),
			'workflow'          => 'master-generated-customer-package',
			'generated_by_user' => sanitize_user( wp_get_current_user()->user_login ),
		);

		$storage = self::customer_package_storage();
		if ( is_wp_error( $storage ) ) {
			set_transient( 'pckzce_customer_package_error', $storage->get_error_message(), 10 * MINUTE_IN_SECONDS );
			wp_safe_redirect( admin_url( 'admin.php?page=pckz-license-server' ) );
			exit;
		}

		$label_slug   = sanitize_title( (string) ( $license['label'] ?? '' ) );
		$domain_slug  = sanitize_title( str_replace( '.', '-', (string) $domains[0] ) );
		$slug         = trim( $label_slug . '-' . $domain_slug, '-' );
		$slug         = $slug ? $slug : 'license-' . (int) $license['id'];
		$package_name = 'pckz-canonical-engine-client-' . $slug . '-' . gmdate( 'Ymd-His' ) . '.zip';
		$package_path = trailingslashit( $storage['dir'] ) . $package_name;

		$generated = self::build_customer_package_zip( $package_path, $config_payload, $binding_payload );
		if ( is_wp_error( $generated ) ) {
			set_transient( 'pckzce_customer_package_error', $generated->get_error_message(), 10 * MINUTE_IN_SECONDS );
			wp_safe_redirect( admin_url( 'admin.php?page=pckz-license-server' ) );
			exit;
		}

		set_transient(
			'pckzce_customer_package_notice',
			array(
				'filename' => $package_name,
				'url'      => trailingslashit( $storage['url'] ) . rawurlencode( $package_name ),
				'size'     => (int) @filesize( $package_path ),
			),
			10 * MINUTE_IN_SECONDS
		);
		wp_safe_redirect( admin_url( 'admin.php?page=pckz-license-server' ) );
		exit;
	}

	/**
	 * Resolve customer package storage directory.
	 *
	 * @return array|WP_Error
	 */
	private static function customer_package_storage() {
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return new WP_Error( 'upload_error', sanitize_text_field( (string) $uploads['error'] ) );
		}
		$dir = trailingslashit( $uploads['basedir'] ) . 'pckz-customer-packages';
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return new WP_Error( 'mkdir_failed', __( 'Could not create customer package directory.', 'pckz-canonical-engine' ) );
		}
		return array(
			'dir' => $dir,
			'url' => trailingslashit( $uploads['baseurl'] ) . 'pckz-customer-packages',
		);
	}

	/**
	 * Build and write a customer ZIP package.
	 *
	 * @param string $destination_zip Destination zip path.
	 * @param array  $config_payload  Embedded client config payload.
	 * @param array  $binding_payload Embedded license binding payload.
	 * @return true|WP_Error
	 */
	private static function build_customer_package_zip( $destination_zip, $config_payload, $binding_payload ) {
		$plugin_root = untrailingslashit( PCKZCE_PLUGIN_DIR );
		$plugin_slug = basename( $plugin_root );
		$temp_base   = trailingslashit( get_temp_dir() ) . 'pckz-customer-' . wp_generate_uuid4();
		$temp_root   = trailingslashit( $temp_base ) . $plugin_slug;

		if ( ! wp_mkdir_p( $temp_root ) ) {
			return new WP_Error( 'temp_dir_failed', __( 'Could not create temporary package workspace.', 'pckz-canonical-engine' ) );
		}

		$copied = self::copy_directory_recursive(
			$plugin_root,
			$temp_root,
			array(
				'.git',
				'.github',
				'.cursor',
				'node_modules',
				'release-packages',
				'dist',
				'vendor/bin',
			)
		);
		if ( is_wp_error( $copied ) ) {
			self::delete_directory_recursive( $temp_base );
			return $copied;
		}

		$config_file  = trailingslashit( $temp_root ) . 'CLIENT_LICENSE_CONFIG.json';
		$binding_file = trailingslashit( $temp_root ) . 'LICENSE_BINDING.json';
		$config_json  = wp_json_encode( $config_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		$binding_json = wp_json_encode( $binding_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		if ( ! is_string( $config_json ) || ! is_string( $binding_json ) ) {
			self::delete_directory_recursive( $temp_base );
			return new WP_Error( 'json_encode_failed', __( 'Could not encode customer package metadata.', 'pckz-canonical-engine' ) );
		}
		if ( false === @file_put_contents( $config_file, $config_json ) || false === @file_put_contents( $binding_file, $binding_json ) ) {
			self::delete_directory_recursive( $temp_base );
			return new WP_Error( 'metadata_write_failed', __( 'Could not write customer package metadata files.', 'pckz-canonical-engine' ) );
		}

		$zip_ok = self::zip_directory( $temp_base, $destination_zip );
		self::delete_directory_recursive( $temp_base );
		return $zip_ok;
	}

	/**
	 * Recursively copy directory content while excluding selected paths.
	 *
	 * @param string $source  Source absolute path.
	 * @param string $target  Target absolute path.
	 * @param array  $exclude Relative path prefixes to skip.
	 * @return true|WP_Error
	 */
	private static function copy_directory_recursive( $source, $target, $exclude = array() ) {
		$source = untrailingslashit( (string) $source );
		$target = untrailingslashit( (string) $target );
		$iter   = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $source, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iter as $item ) {
			$path     = $item->getPathname();
			$relative = ltrim( str_replace( $source, '', $path ), DIRECTORY_SEPARATOR );
			if ( '' === $relative ) {
				continue;
			}
			$relative_normalized = str_replace( '\\', '/', $relative );
			$skip = false;
			foreach ( $exclude as $skip_path ) {
				$prefix = trim( str_replace( '\\', '/', (string) $skip_path ), '/' );
				if ( '' !== $prefix && ( 0 === strpos( $relative_normalized, $prefix . '/' ) || $relative_normalized === $prefix ) ) {
					$skip = true;
					break;
				}
			}
			if ( $skip ) {
				continue;
			}
			$dest = $target . DIRECTORY_SEPARATOR . $relative;
			if ( $item->isDir() ) {
				if ( ! is_dir( $dest ) && ! wp_mkdir_p( $dest ) ) {
					return new WP_Error( 'copy_dir_failed', __( 'Could not create a directory while building customer package.', 'pckz-canonical-engine' ) );
				}
			} elseif ( ! copy( $path, $dest ) ) {
				return new WP_Error( 'copy_file_failed', sprintf( __( 'Could not copy file while building package: %s', 'pckz-canonical-engine' ), $relative_normalized ) );
			}
		}
		return true;
	}

	/**
	 * Archive a directory to zip.
	 *
	 * @param string $source_root Source directory.
	 * @param string $zip_path    Zip path.
	 * @return true|WP_Error
	 */
	private static function zip_directory( $source_root, $zip_path ) {
		if ( class_exists( 'ZipArchive' ) ) {
			$zip = new ZipArchive();
			$open = $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE );
			if ( true !== $open ) {
				return new WP_Error( 'zip_open_failed', __( 'Could not open destination ZIP archive.', 'pckz-canonical-engine' ) );
			}
			$iter = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $source_root, FilesystemIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::LEAVES_ONLY
			);
			foreach ( $iter as $file_info ) {
				$path = $file_info->getPathname();
				$rel  = ltrim( str_replace( $source_root, '', $path ), DIRECTORY_SEPARATOR );
				if ( '' !== $rel ) {
					$zip->addFile( $path, $rel );
				}
			}
			$zip->close();
			return true;
		}

		$zip_bin = trim( (string) shell_exec( 'command -v zip' ) );
		if ( '' === $zip_bin ) {
			return new WP_Error( 'zip_binary_missing', __( 'ZipArchive and system zip utility are unavailable on this server.', 'pckz-canonical-engine' ) );
		}
		$command = sprintf(
			'cd %s && %s -qr %s .',
			escapeshellarg( $source_root ),
			escapeshellarg( $zip_bin ),
			escapeshellarg( $zip_path )
		);
		$output = array();
		$code   = 0;
		exec( $command, $output, $code );
		if ( 0 !== (int) $code ) {
			return new WP_Error( 'zip_command_failed', __( 'Could not create package using system zip utility.', 'pckz-canonical-engine' ) );
		}
		return true;
	}

	/**
	 * Delete directory recursively.
	 *
	 * @param string $directory Absolute directory path.
	 */
	private static function delete_directory_recursive( $directory ) {
		if ( ! is_dir( $directory ) ) {
			return;
		}
		$iter = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iter as $file_info ) {
			if ( $file_info->isDir() ) {
				@rmdir( $file_info->getPathname() );
			} else {
				@unlink( $file_info->getPathname() );
			}
		}
		@rmdir( $directory );
	}

	/**
	 * List generated customer packages for master dashboard.
	 *
	 * @return array
	 */
	private static function list_customer_packages() {
		$storage = self::customer_package_storage();
		if ( is_wp_error( $storage ) ) {
			return array();
		}

		$entries = @scandir( $storage['dir'] );
		if ( ! is_array( $entries ) ) {
			return array();
		}
		$packages = array();
		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry || ! preg_match( '/\.zip$/i', $entry ) ) {
				continue;
			}
			$path = trailingslashit( $storage['dir'] ) . $entry;
			if ( ! is_file( $path ) ) {
				continue;
			}
			$packages[] = array(
				'filename' => $entry,
				'path'     => $path,
				'url'      => trailingslashit( $storage['url'] ) . rawurlencode( $entry ),
				'size'     => (int) @filesize( $path ),
				'modified' => (int) @filemtime( $path ),
			);
		}

		usort(
			$packages,
			static function ( $a, $b ) {
				return (int) $b['modified'] <=> (int) $a['modified'];
			}
		);

		return array_slice( $packages, 0, 50 );
	}

	/**
	 * Create license action.
	 */
	public function handle_create_license() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'pckz-canonical-engine' ) );
		}
		check_admin_referer( 'pckzce_create_license', 'pckzce_license_nonce' );
		if ( ! self::is_master_mode() ) {
			wp_safe_redirect( admin_url( 'admin.php?page=pckz-license-server' ) );
			exit;
		}
		global $wpdb;
		$key       = self::generate_license_key();
		$domains   = self::parse_domains( isset( $_POST['domains'] ) ? wp_unslash( $_POST['domains'] ) : '' );
		$perm      = array(
			'export'  => ! empty( $_POST['perm_export'] ),
			'updates' => ! empty( $_POST['perm_updates'] ),
		);
		$max       = isset( $_POST['max_installs'] ) ? max( 1, absint( $_POST['max_installs'] ) ) : 1;
		$label     = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';
		$now       = current_time( 'mysql' );
		$wpdb->insert(
			$wpdb->prefix . 'pckz_license_keys',
			array(
				'license_key'  => $key,
				'label'        => $label,
				'status'       => 'active',
				'domains'      => wp_json_encode( $domains ),
				'permissions'  => wp_json_encode( $perm ),
				'max_installs' => $max,
				'created_at'   => $now,
				'updated_at'   => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);
		set_transient( 'pckzce_last_created_license', $key, 10 * MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=pckz-license-server' ) );
		exit;
	}

	/**
	 * Update license status.
	 */
	public function handle_update_license_status() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'pckz-canonical-engine' ) );
		}
		check_admin_referer( 'pckzce_update_license_status', 'pckzce_license_status_nonce' );
		if ( ! self::is_master_mode() ) {
			wp_safe_redirect( admin_url( 'admin.php?page=pckz-license-server' ) );
			exit;
		}
		global $wpdb;
		$id        = isset( $_POST['license_id'] ) ? absint( $_POST['license_id'] ) : 0;
		$new_state = isset( $_POST['new_status'] ) ? sanitize_key( wp_unslash( $_POST['new_status'] ) ) : 'revoked';
		if ( $id ) {
			$new_state = in_array( $new_state, array( 'active', 'revoked', 'disabled' ), true ) ? $new_state : 'revoked';
			$wpdb->update(
				$wpdb->prefix . 'pckz_license_keys',
				array(
					'status'     => $new_state,
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'id' => $id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
			if ( in_array( $new_state, array( 'revoked', 'disabled' ), true ) ) {
				$wpdb->update(
					$wpdb->prefix . 'pckz_license_installations',
					array(
						'status'     => 'blocked',
						'updated_at' => current_time( 'mysql' ),
						'last_error' => 'license_' . $new_state,
					),
					array( 'license_id' => $id ),
					array( '%s', '%s', '%s' ),
					array( '%d' )
				);
			}
		}
		wp_safe_redirect( admin_url( 'admin.php?page=pckz-license-server' ) );
		exit;
	}

	/**
	 * Update installation status.
	 */
	public function handle_update_installation_status() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'pckz-canonical-engine' ) );
		}
		check_admin_referer( 'pckzce_update_installation_status', 'pckzce_install_status_nonce' );
		if ( ! self::is_master_mode() ) {
			wp_safe_redirect( admin_url( 'admin.php?page=pckz-license-server' ) );
			exit;
		}
		global $wpdb;
		$id        = isset( $_POST['installation_id'] ) ? absint( $_POST['installation_id'] ) : 0;
		$new_state = isset( $_POST['new_status'] ) ? sanitize_key( wp_unslash( $_POST['new_status'] ) ) : 'blocked';
		if ( $id ) {
			$wpdb->update(
				$wpdb->prefix . 'pckz_license_installations',
				array(
					'status'     => in_array( $new_state, array( 'active', 'blocked' ), true ) ? $new_state : 'blocked',
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'id' => $id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
		}
		wp_safe_redirect( admin_url( 'admin.php?page=pckz-license-server' ) );
		exit;
	}

	/**
	 * Save update/release metadata.
	 */
	public function handle_save_release_meta() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'pckz-canonical-engine' ) );
		}
		check_admin_referer( 'pckzce_save_release_meta', 'pckzce_release_nonce' );
		if ( ! self::is_master_mode() ) {
			wp_safe_redirect( admin_url( 'admin.php?page=pckz-license-server' ) );
			exit;
		}
		$meta = array(
			'version'          => sanitize_text_field( wp_unslash( $_POST['version'] ?? '' ) ),
			'package_url'      => esc_url_raw( wp_unslash( $_POST['package_url'] ?? '' ) ),
			'changelog'        => wp_kses_post( wp_unslash( $_POST['changelog'] ?? '' ) ),
			'requires'         => sanitize_text_field( wp_unslash( $_POST['requires'] ?? '6.0' ) ),
			'requires_php'     => sanitize_text_field( wp_unslash( $_POST['requires_php'] ?? '7.4' ) ),
			'tested'           => sanitize_text_field( wp_unslash( $_POST['tested'] ?? '' ) ),
			'min_client_build' => sanitize_text_field( wp_unslash( $_POST['min_client_build'] ?? '' ) ),
			'allow_remote_export' => ! empty( $_POST['allow_remote_export'] ),
		);
		update_option( self::OPTION_RELEASE_META, $meta );
		wp_safe_redirect( admin_url( 'admin.php?page=pckz-license-server' ) );
		exit;
	}

	/**
	 * Save editable license fields.
	 */
	public function handle_save_license_detail() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'pckz-canonical-engine' ) );
		}
		check_admin_referer( 'pckzce_save_license_detail', 'pckzce_license_detail_nonce' );
		if ( ! self::is_master_mode() ) {
			wp_safe_redirect( admin_url( 'admin.php?page=pckz-license-server' ) );
			exit;
		}
		global $wpdb;
		$id = isset( $_POST['license_id'] ) ? absint( $_POST['license_id'] ) : 0;
		if ( ! $id ) {
			wp_safe_redirect( admin_url( 'admin.php?page=pckz-license-server' ) );
			exit;
		}
		$domains = self::parse_domains( isset( $_POST['domains'] ) ? wp_unslash( $_POST['domains'] ) : '' );
		$perm    = array(
			'export'  => ! empty( $_POST['perm_export'] ),
			'updates' => ! empty( $_POST['perm_updates'] ),
		);
		$expires_raw = sanitize_text_field( wp_unslash( $_POST['expires_at'] ?? '' ) );
		$expires     = '';
		if ( $expires_raw ) {
			$ts = strtotime( $expires_raw );
			if ( $ts ) {
				$expires = gmdate( 'Y-m-d H:i:s', $ts );
			}
		}
		$wpdb->update(
			$wpdb->prefix . 'pckz_license_keys',
			array(
				'label'        => sanitize_text_field( wp_unslash( $_POST['label'] ?? '' ) ),
				'max_installs' => max( 1, absint( $_POST['max_installs'] ?? 1 ) ),
				'domains'      => wp_json_encode( $domains ),
				'permissions'  => wp_json_encode( $perm ),
				'expires_at'   => $expires ? $expires : null,
				'updated_at'   => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%s', '%d', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);
		wp_safe_redirect( admin_url( 'admin.php?page=pckz-license-server' ) );
		exit;
	}

	/**
	 * Register REST API routes for master and update services.
	 */
	public function register_rest_routes() {
		if ( ! self::is_master_mode() ) {
			return;
		}
		register_rest_route(
			'pckzce-license/v1',
			'/client/check-in',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_client_check_in' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'pckzce-license/v1',
			'/client/update-meta',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_client_update_meta' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'pckzce-license/v1',
			'/client/download',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_client_download' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			'pckzce-license/v1',
			'/client/export-authorize',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_client_export_authorize' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			'pckzce-license/v1',
			'/client/export-generate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_client_export_generate' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			'pckzce-license/v1',
			'/master/health',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_master_health' ),
				'permission_callback' => array( $this, 'rest_master_permission' ),
			)
		);
		register_rest_route(
			'pckzce-license/v1',
			'/master/licenses',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_master_list_licenses' ),
				'permission_callback' => array( $this, 'rest_master_permission' ),
			)
		);
		register_rest_route(
			'pckzce-license/v1',
			'/master/licenses/(?P<id>\d+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_master_update_license' ),
				'permission_callback' => array( $this, 'rest_master_permission' ),
			)
		);
		register_rest_route(
			'pckzce-license/v1',
			'/master/installations',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_master_list_installations' ),
				'permission_callback' => array( $this, 'rest_master_permission' ),
			)
		);
		register_rest_route(
			'pckzce-license/v1',
			'/master/downloads',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_master_list_downloads' ),
				'permission_callback' => array( $this, 'rest_master_permission' ),
			)
		);
		register_rest_route(
			'pckzce-license/v1',
			'/master/validate-release',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_master_validate_release' ),
				'permission_callback' => array( $this, 'rest_master_permission' ),
			)
		);
	}

	/**
	 * Master endpoint: entitlement + heartbeat check-in.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function rest_client_check_in( WP_REST_Request $request ) {
		if ( ! self::is_master_mode() ) {
			return rest_ensure_response(
				array(
					'authorized' => false,
					'reason'     => 'master_mode_disabled',
				)
			);
		}
		$body_raw = (string) $request->get_body();
		$payload = json_decode( $body_raw, true );
		if ( ! is_array( $payload ) ) {
			$payload = array();
		}
		$validated = $this->server_validate_client_payload( $payload, $request, $body_raw );
		if ( is_wp_error( $validated ) ) {
			return rest_ensure_response(
				array(
					'authorized' => false,
					'reason'     => $validated->get_error_message(),
				)
			);
		}

		$token_ttl = 20 * MINUTE_IN_SECONDS;
		$heartbeat = 5 * MINUTE_IN_SECONDS;
		$permissions = $validated['permissions'];
		$token = self::build_signed_token(
			array(
				'license_id'   => (int) $validated['license']['id'],
				'domain'       => $validated['domain'],
				'install_uuid' => $validated['install_uuid'],
				'permissions'  => $permissions,
				'exp'          => time() + $token_ttl,
			)
		);

		return rest_ensure_response(
			array(
				'authorized'         => true,
				'status'             => 'active',
				'token'              => $token,
				'token_ttl'          => $token_ttl,
				'heartbeat_interval' => $heartbeat,
				'permissions'        => $permissions,
				'install_secret'     => $validated['install_secret'],
				'server_time'        => time(),
				'license_id'         => (int) $validated['license']['id'],
				'min_client_build'   => (string) ( get_option( self::OPTION_RELEASE_META, array() )['min_client_build'] ?? '' ),
			)
		);
	}

	/**
	 * Master endpoint: update metadata for a licensed installation.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function rest_client_update_meta( WP_REST_Request $request ) {
		if ( ! self::is_master_mode() ) {
			return rest_ensure_response( array( 'ok' => false, 'reason' => 'master_mode_disabled' ) );
		}
		$body_raw = (string) $request->get_body();
		$payload = json_decode( $body_raw, true );
		if ( ! is_array( $payload ) ) {
			$payload = array();
		}
		$validated = $this->server_validate_client_payload( $payload, $request, $body_raw );
		if ( is_wp_error( $validated ) ) {
			return rest_ensure_response( array( 'ok' => false, 'reason' => $validated->get_error_message() ) );
		}
		if ( empty( $validated['permissions']['updates'] ) ) {
			return rest_ensure_response( array( 'ok' => false, 'reason' => 'updates_not_allowed' ) );
		}
		$meta = get_option( self::OPTION_RELEASE_META, array() );
		$latest = sanitize_text_field( (string) ( $meta['version'] ?? '' ) );
		if ( '' === $latest ) {
			return rest_ensure_response( array( 'ok' => false, 'reason' => 'no_release' ) );
		}
		$current = sanitize_text_field( (string) ( $payload['current_version'] ?? '' ) );
		if ( $current && version_compare( $latest, $current, '<=' ) ) {
			return rest_ensure_response( array( 'ok' => true, 'update_available' => false ) );
		}
		$token = self::build_signed_token(
			array(
				'typ'         => 'download',
				'license_key' => (string) ( $payload['license_key'] ?? '' ),
				'domain'      => (string) ( $payload['domain'] ?? '' ),
				'install_uuid'=> (string) ( $payload['install_uuid'] ?? '' ),
				'version'     => $latest,
				'exp'         => time() + ( 10 * MINUTE_IN_SECONDS ),
			)
		);
		$query = array( 'token' => $token );
		$download = add_query_arg( $query, rest_url( 'pckzce-license/v1/client/download' ) );
		return rest_ensure_response(
			array(
				'ok'               => true,
				'update_available' => true,
				'version'          => $latest,
				'package'          => esc_url_raw( $download ),
				'requires'         => sanitize_text_field( (string) ( $meta['requires'] ?? '6.0' ) ),
				'requires_php'     => sanitize_text_field( (string) ( $meta['requires_php'] ?? '7.4' ) ),
				'tested'           => sanitize_text_field( (string) ( $meta['tested'] ?? '' ) ),
				'changelog'        => (string) ( $meta['changelog'] ?? '' ),
				'min_client_build' => sanitize_text_field( (string) ( $meta['min_client_build'] ?? '' ) ),
			)
		);
	}

	/**
	 * Master endpoint: protected package delivery.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function rest_client_download( WP_REST_Request $request ) {
		if ( ! self::is_master_mode() ) {
			return new WP_REST_Response( array( 'ok' => false, 'reason' => 'master_mode_disabled' ), 403 );
		}
		$token   = sanitize_text_field( (string) $request->get_param( 'token' ) );
		$payload = array();
		if ( $token ) {
			$decoded = self::verify_signed_token( $token );
			if ( is_wp_error( $decoded ) || 'download' !== (string) ( $decoded['typ'] ?? '' ) ) {
				return new WP_REST_Response( array( 'ok' => false, 'reason' => 'invalid_download_token' ), 403 );
			}
			$payload = array(
				'license_key'   => sanitize_text_field( (string) ( $decoded['license_key'] ?? '' ) ),
				'domain'        => sanitize_text_field( (string) ( $decoded['domain'] ?? '' ) ),
				'install_uuid'  => sanitize_text_field( (string) ( $decoded['install_uuid'] ?? '' ) ),
				'current_version' => sanitize_text_field( (string) ( $decoded['version'] ?? '' ) ),
			);
		} else {
			// Backward-compatible fallback query mode.
			$payload = array(
				'license_key'  => sanitize_text_field( (string) $request->get_param( 'license_key' ) ),
				'domain'       => sanitize_text_field( (string) $request->get_param( 'domain' ) ),
				'install_uuid' => sanitize_text_field( (string) $request->get_param( 'install_uuid' ) ),
				'current_version' => sanitize_text_field( (string) $request->get_param( 'version' ) ),
			);
		}
		$validated = $this->server_validate_client_payload( $payload, $request, '', true );
		if ( is_wp_error( $validated ) || empty( $validated['permissions']['updates'] ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'reason' => 'not_authorized' ), 403 );
		}
		$meta    = get_option( self::OPTION_RELEASE_META, array() );
		$package = esc_url_raw( (string) ( $meta['package_url'] ?? '' ) );
		if ( ! $package ) {
			return new WP_REST_Response( array( 'ok' => false, 'reason' => 'package_not_configured' ), 404 );
		}
		$this->record_download_event( $validated, $payload, $package );
		wp_redirect( $package, 302 );
		exit;
	}

	/**
	 * Master endpoint: authorize export operations on clients.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function rest_client_export_authorize( WP_REST_Request $request ) {
		if ( ! self::is_master_mode() ) {
			return rest_ensure_response( array( 'authorized' => false, 'reason' => 'master_mode_disabled' ) );
		}
		$body_raw = (string) $request->get_body();
		$payload  = json_decode( $body_raw, true );
		if ( ! is_array( $payload ) ) {
			$payload = array();
		}
		$validated = $this->server_validate_client_payload( $payload, $request, $body_raw );
		if ( is_wp_error( $validated ) ) {
			return rest_ensure_response( array( 'authorized' => false, 'reason' => $validated->get_error_message() ) );
		}
		if ( empty( $validated['permissions']['export'] ) ) {
			return rest_ensure_response( array( 'authorized' => false, 'reason' => 'export_not_permitted' ) );
		}
		$release_meta = get_option( self::OPTION_RELEASE_META, array() );
		$permit = self::build_signed_token(
			array(
				'typ'         => 'export_permit',
				'license_key' => (string) ( $payload['license_key'] ?? '' ),
				'domain'      => (string) ( $payload['domain'] ?? '' ),
				'install_uuid'=> (string) ( $payload['install_uuid'] ?? '' ),
				'operation'   => sanitize_key( (string) ( $payload['operation'] ?? 'export' ) ),
				'payload_hash'=> sanitize_text_field( (string) ( $payload['payload_hash'] ?? '' ) ),
				'exp'         => time() + ( 5 * MINUTE_IN_SECONDS ),
			)
		);
		return rest_ensure_response(
			array(
				'authorized'         => true,
				'permit'             => $permit,
				'permit_ttl'         => 300,
				'remote_export_mode' => ! empty( $release_meta['allow_remote_export'] ),
			)
		);
	}

	/**
	 * Master endpoint: optional remote export generation.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function rest_client_export_generate( WP_REST_Request $request ) {
		if ( ! self::is_master_mode() ) {
			return rest_ensure_response( array( 'ok' => false, 'reason' => 'master_mode_disabled' ) );
		}
		$release_meta = get_option( self::OPTION_RELEASE_META, array() );
		if ( empty( $release_meta['allow_remote_export'] ) ) {
			return rest_ensure_response( array( 'ok' => false, 'reason' => 'remote_export_disabled' ) );
		}
		$body_raw = (string) $request->get_body();
		$data     = json_decode( $body_raw, true );
		if ( ! is_array( $data ) ) {
			$data = array();
		}
		$permit = sanitize_text_field( (string) ( $data['permit'] ?? '' ) );
		$export_payload = is_array( $data['export_payload'] ?? null ) ? $data['export_payload'] : array();
		$permit_payload = self::verify_signed_token( $permit );
		if ( is_wp_error( $permit_payload ) || 'export_permit' !== (string) ( $permit_payload['typ'] ?? '' ) ) {
			return rest_ensure_response( array( 'ok' => false, 'reason' => 'invalid_permit' ) );
		}
		$validation_payload = array(
			'license_key'   => sanitize_text_field( (string) ( $permit_payload['license_key'] ?? '' ) ),
			'domain'        => sanitize_text_field( (string) ( $permit_payload['domain'] ?? '' ) ),
			'install_uuid'  => sanitize_text_field( (string) ( $permit_payload['install_uuid'] ?? '' ) ),
			'plugin_version'=> sanitize_text_field( (string) ( $export_payload['plugin_version'] ?? '' ) ),
			'plugin_build'  => sanitize_text_field( (string) ( $export_payload['plugin_build'] ?? '' ) ),
			'wp_version'    => sanitize_text_field( (string) ( $export_payload['wp_version'] ?? '' ) ),
			'php_version'   => sanitize_text_field( (string) ( $export_payload['php_version'] ?? '' ) ),
			'integrity_hash'=> sanitize_text_field( (string) ( $export_payload['integrity_hash'] ?? '' ) ),
		);
		$validated = $this->server_validate_client_payload( $validation_payload, $request, $body_raw );
		if ( is_wp_error( $validated ) || empty( $validated['permissions']['export'] ) ) {
			return rest_ensure_response( array( 'ok' => false, 'reason' => 'not_authorized' ) );
		}
		$computed_hash = self::hash_export_payload( $export_payload );
		if ( ! empty( $permit_payload['payload_hash'] ) && ! hash_equals( (string) $permit_payload['payload_hash'], $computed_hash ) ) {
			return rest_ensure_response( array( 'ok' => false, 'reason' => 'payload_mismatch' ) );
		}
		$result = self::run_remote_export_generation( $export_payload );
		if ( is_wp_error( $result ) ) {
			return rest_ensure_response( array( 'ok' => false, 'reason' => $result->get_error_message() ) );
		}
		return rest_ensure_response( array( 'ok' => true, 'package' => $result ) );
	}

	/**
	 * Permission callback for master-control API routes.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function rest_master_permission( WP_REST_Request $request ) {
		if ( ! self::is_master_mode() ) {
			return false;
		}
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		$settings = PCKZ_Settings::get_all();
		$master_key = trim( (string) ( $settings['licensing_master_api_key'] ?? '' ) );
		if ( '' === $master_key ) {
			return false;
		}
		$header_key = sanitize_text_field( (string) $request->get_header( 'x-pckz-master-key' ) );
		return '' !== $header_key && hash_equals( $master_key, $header_key );
	}

	/**
	 * Master API: health summary.
	 *
	 * @return WP_REST_Response
	 */
	public function rest_master_health() {
		global $wpdb;
		$licenses_table  = $wpdb->prefix . 'pckz_license_keys';
		$installs_table  = $wpdb->prefix . 'pckz_license_installations';
		$downloads_table = $wpdb->prefix . 'pckz_license_downloads';
		$meta = get_option( self::OPTION_RELEASE_META, array() );
		$summary = array(
			'active_licenses'    => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$licenses_table} WHERE status = %s", 'active' ) ),
			'revoked_licenses'   => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$licenses_table} WHERE status = %s", 'revoked' ) ),
			'active_installations' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$installs_table} WHERE status = %s", 'active' ) ),
			'blocked_installations' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$installs_table} WHERE status = %s", 'blocked' ) ),
			'downloads_24h'      => (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$downloads_table} WHERE created_at >= %s",
					gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS )
				)
			),
			'latest_version'     => sanitize_text_field( (string) ( $meta['version'] ?? '' ) ),
			'release_configured' => ! empty( $meta['version'] ) && ! empty( $meta['package_url'] ),
			'allow_remote_export'=> ! empty( $meta['allow_remote_export'] ),
			'server_time'        => time(),
		);
		return rest_ensure_response( array( 'ok' => true, 'summary' => $summary ) );
	}

	/**
	 * Master API: list licenses.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function rest_master_list_licenses( WP_REST_Request $request ) {
		global $wpdb;
		$status = sanitize_key( (string) $request->get_param( 'status' ) );
		$search = sanitize_text_field( (string) $request->get_param( 'search' ) );
		$limit  = max( 1, min( 1000, absint( $request->get_param( 'limit' ) ?: 200 ) ) );
		$where  = '1=1';
		$params = array();
		if ( in_array( $status, array( 'active', 'revoked', 'disabled' ), true ) ) {
			$where .= ' AND status = %s';
			$params[] = $status;
		}
		if ( '' !== $search ) {
			$where .= ' AND (label LIKE %s OR license_key LIKE %s)';
			$params[] = '%' . $wpdb->esc_like( $search ) . '%';
			$params[] = '%' . $wpdb->esc_like( $search ) . '%';
		}
		$sql = "SELECT * FROM {$wpdb->prefix}pckz_license_keys WHERE {$where} ORDER BY id DESC LIMIT %d";
		$params[] = $limit;
		$query = $wpdb->prepare( $sql, ...$params );
		$rows = $wpdb->get_results( $query, ARRAY_A );
		foreach ( $rows as &$row ) {
			$row['license_key'] = self::mask_key( (string) ( $row['license_key'] ?? '' ) );
			$row['domains'] = self::decode_json_array( (string) ( $row['domains'] ?? '' ) );
			$row['permissions'] = self::decode_json_assoc( (string) ( $row['permissions'] ?? '' ), array( 'export' => true, 'updates' => true ) );
		}
		return rest_ensure_response( array( 'ok' => true, 'licenses' => $rows ) );
	}

	/**
	 * Master API: update license attributes.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function rest_master_update_license( WP_REST_Request $request ) {
		global $wpdb;
		$license_id = absint( $request->get_param( 'id' ) );
		if ( ! $license_id ) {
			return rest_ensure_response( array( 'ok' => false, 'reason' => 'missing_license_id' ) );
		}
		$payload = json_decode( (string) $request->get_body(), true );
		if ( ! is_array( $payload ) ) {
			$payload = array();
		}
		$update = array(
			'updated_at' => current_time( 'mysql' ),
		);
		$format = array( '%s' );
		if ( array_key_exists( 'label', $payload ) ) {
			$update['label'] = sanitize_text_field( (string) $payload['label'] );
			$format[] = '%s';
		}
		if ( array_key_exists( 'status', $payload ) ) {
			$update['status'] = in_array( sanitize_key( (string) $payload['status'] ), array( 'active', 'revoked', 'disabled' ), true )
				? sanitize_key( (string) $payload['status'] )
				: 'revoked';
			$format[] = '%s';
		}
		if ( array_key_exists( 'max_installs', $payload ) ) {
			$update['max_installs'] = max( 1, absint( $payload['max_installs'] ) );
			$format[] = '%d';
		}
		if ( array_key_exists( 'domains', $payload ) ) {
			$domains = is_array( $payload['domains'] ) ? implode( "\n", $payload['domains'] ) : (string) $payload['domains'];
			$update['domains'] = wp_json_encode( self::parse_domains( $domains ) );
			$format[] = '%s';
		}
		if ( array_key_exists( 'permissions', $payload ) ) {
			$perm_payload = is_array( $payload['permissions'] ) ? $payload['permissions'] : array();
			$update['permissions'] = wp_json_encode(
				array(
					'export'  => ! empty( $perm_payload['export'] ),
					'updates' => ! empty( $perm_payload['updates'] ),
				)
			);
			$format[] = '%s';
		}
		if ( array_key_exists( 'expires_at', $payload ) ) {
			$expires = sanitize_text_field( (string) $payload['expires_at'] );
			$ts = $expires ? strtotime( $expires ) : 0;
			$update['expires_at'] = $ts ? gmdate( 'Y-m-d H:i:s', $ts ) : null;
			$format[] = '%s';
		}
		$wpdb->update(
			$wpdb->prefix . 'pckz_license_keys',
			$update,
			array( 'id' => $license_id ),
			$format,
			array( '%d' )
		);
		if ( isset( $update['status'] ) && in_array( $update['status'], array( 'revoked', 'disabled' ), true ) ) {
			$wpdb->update(
				$wpdb->prefix . 'pckz_license_installations',
				array(
					'status'     => 'blocked',
					'updated_at' => current_time( 'mysql' ),
					'last_error' => 'license_' . $update['status'],
				),
				array( 'license_id' => $license_id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);
		}
		return rest_ensure_response( array( 'ok' => true ) );
	}

	/**
	 * Master API: list installations.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function rest_master_list_installations( WP_REST_Request $request ) {
		global $wpdb;
		$status = sanitize_key( (string) $request->get_param( 'status' ) );
		$search = sanitize_text_field( (string) $request->get_param( 'search' ) );
		$limit  = max( 1, min( 2000, absint( $request->get_param( 'limit' ) ?: 500 ) ) );
		$where  = '1=1';
		$params = array();
		if ( in_array( $status, array( 'active', 'blocked' ), true ) ) {
			$where .= ' AND status = %s';
			$params[] = $status;
		}
		if ( '' !== $search ) {
			$where .= ' AND (domain LIKE %s OR install_uuid LIKE %s)';
			$params[] = '%' . $wpdb->esc_like( $search ) . '%';
			$params[] = '%' . $wpdb->esc_like( $search ) . '%';
		}
		$sql = "SELECT * FROM {$wpdb->prefix}pckz_license_installations WHERE {$where} ORDER BY updated_at DESC LIMIT %d";
		$params[] = $limit;
		$query = $wpdb->prepare( $sql, ...$params );
		$rows = $wpdb->get_results( $query, ARRAY_A );
		foreach ( $rows as &$row ) {
			$row['tamper_signals'] = self::decode_json_array( (string) ( $row['tamper_signals'] ?? '' ) );
		}
		return rest_ensure_response( array( 'ok' => true, 'installations' => $rows ) );
	}

	/**
	 * Master API: list protected package download events.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function rest_master_list_downloads( WP_REST_Request $request ) {
		global $wpdb;
		$domain = self::normalize_domain_value( (string) $request->get_param( 'domain' ) );
		$limit  = max( 1, min( 2000, absint( $request->get_param( 'limit' ) ?: 500 ) ) );
		$where  = '1=1';
		$params = array();
		if ( '' !== $domain ) {
			$where .= ' AND domain = %s';
			$params[] = $domain;
		}
		$sql = "SELECT * FROM {$wpdb->prefix}pckz_license_downloads WHERE {$where} ORDER BY id DESC LIMIT %d";
		$params[] = $limit;
		$query = $wpdb->prepare( $sql, ...$params );
		$rows = $wpdb->get_results( $query, ARRAY_A );
		return rest_ensure_response( array( 'ok' => true, 'downloads' => $rows ) );
	}

	/**
	 * Master API: validate update delivery configuration.
	 *
	 * @return WP_REST_Response
	 */
	public function rest_master_validate_release() {
		$meta = get_option( self::OPTION_RELEASE_META, array() );
		$package_url = esc_url_raw( (string) ( $meta['package_url'] ?? '' ) );
		$result = array(
			'version'           => sanitize_text_field( (string) ( $meta['version'] ?? '' ) ),
			'package_url'       => $package_url,
			'has_version'       => ! empty( $meta['version'] ),
			'has_package_url'   => '' !== $package_url,
			'package_reachable' => false,
			'package_status'    => 0,
			'errors'            => array(),
		);
		if ( '' === $package_url ) {
			$result['errors'][] = 'package_url_missing';
			return rest_ensure_response( array( 'ok' => false, 'validation' => $result ) );
		}
		$resp = wp_remote_get(
			$package_url,
			array(
				'method'  => 'HEAD',
				'timeout' => 12,
			)
		);
		if ( is_wp_error( $resp ) ) {
			$result['errors'][] = $resp->get_error_message();
			return rest_ensure_response( array( 'ok' => false, 'validation' => $result ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $resp );
		$result['package_status'] = $code;
		$result['package_reachable'] = $code >= 200 && $code < 400;
		if ( ! $result['package_reachable'] ) {
			$result['errors'][] = 'package_unreachable_' . $code;
		}
		return rest_ensure_response(
			array(
				'ok'         => $result['package_reachable'] && $result['has_version'],
				'validation' => $result,
			)
		);
	}

	/**
	 * Schedule recurring license heartbeat.
	 */
	private function schedule_heartbeat() {
		if ( ! wp_next_scheduled( self::HEARTBEAT_HOOK ) ) {
			wp_schedule_event( time() + 60, 'five_minutes', self::HEARTBEAT_HOOK );
		}
	}

	/**
	 * Heartbeat task.
	 */
	public function heartbeat_task() {
		self::refresh_entitlement( false );
	}

	/**
	 * Refresh license entitlement from the master URL.
	 *
	 * @param bool $force Force network check.
	 * @return array
	 */
	public static function refresh_entitlement( $force = false ) {
		$settings = PCKZ_Settings::get_all();
		$master   = self::normalize_master_url( (string) ( $settings['licensing_master_url'] ?? '' ) );
		$key      = trim( (string) ( $settings['licensing_key'] ?? '' ) );
		$enforce  = ! empty( $settings['licensing_enforce'] );
		$state    = self::get_client_state();

		if ( '' === $master || '' === $key ) {
			$state = array(
				'authorized' => false,
				'status'     => 'unconfigured',
				'reason'     => __( 'License key or master URL is missing.', 'pckz-canonical-engine' ),
				'checked_at' => time(),
			);
			update_option( self::OPTION_CLIENT_STATE, $state, false );
			return $state;
		}

		if ( ! $force && ! empty( $state['checked_at'] ) && ( time() - (int) $state['checked_at'] ) < 120 ) {
			return $state;
		}

		$payload = array(
			'license_key'    => $key,
			'domain'         => self::normalized_domain(),
			'install_uuid'   => self::get_install_uuid(),
			'plugin_version' => PCKZCE_VERSION,
			'plugin_build'   => defined( 'PCKZCE_BUILD' ) ? PCKZCE_BUILD : PCKZCE_VERSION,
			'wp_version'     => get_bloginfo( 'version' ),
			'php_version'    => PHP_VERSION,
			'integrity_hash' => self::client_integrity_hash(),
			'tamper_signals' => class_exists( 'PCKZ_Security' ) ? PCKZ_Security::tamper_signals() : array(),
			'site_name'      => get_bloginfo( 'name' ),
		);
		$body    = wp_json_encode( $payload );
		$headers = self::build_signed_request_headers( $body );

		$url  = $master . '/wp-json/pckzce-license/v1/client/check-in';
		$resp = wp_remote_post(
			$url,
			array(
				'method'  => 'POST',
				'timeout' => 15,
				'headers' => $headers,
				'body'    => $body,
			)
		);

		$grace_minutes = max( 5, (int) ( $settings['licensing_grace_minutes'] ?? 120 ) );
		$grace_until   = time() + ( $grace_minutes * MINUTE_IN_SECONDS );
		if ( is_wp_error( $resp ) ) {
			$state['checked_at'] = time();
			$state['status']     = 'network_error';
			$state['reason']     = $resp->get_error_message();
			if ( ! empty( $state['authorized'] ) ) {
				$state['grace_until'] = max( (int) ( $state['grace_until'] ?? 0 ), $grace_until );
			}
			update_option( self::OPTION_CLIENT_STATE, $state, false );
			return $state;
		}

		$data = json_decode( (string) wp_remote_retrieve_body( $resp ), true );
		if ( ! is_array( $data ) ) {
			$data = array();
		}
		$authorized = ! empty( $data['authorized'] );
		$ttl        = max( 60, (int) ( $data['token_ttl'] ?? 300 ) );
		$state      = array(
			'authorized'   => $authorized,
			'status'       => $authorized ? 'authorized' : 'denied',
			'reason'       => (string) ( $data['reason'] ?? '' ),
			'checked_at'   => time(),
			'expires_at'   => time() + $ttl,
			'grace_until'  => $grace_until,
			'token'        => (string) ( $data['token'] ?? '' ),
			'permissions'  => is_array( $data['permissions'] ?? null ) ? $data['permissions'] : array(),
			'master_url'   => $master,
			'license_hint' => self::mask_key( $key ),
			'server_time'  => (int) ( $data['server_time'] ?? 0 ),
		);
		if ( ! empty( $data['install_secret'] ) ) {
			update_option( self::OPTION_INSTALL_SECRET, sanitize_text_field( (string) $data['install_secret'] ), false );
		}
		update_option( self::OPTION_CLIENT_STATE, $state, false );
		if ( $enforce && ! $authorized ) {
			$state['grace_until'] = 0;
			update_option( self::OPTION_CLIENT_STATE, $state, false );
		}
		return $state;
	}

	/**
	 * Return whether protected features are currently allowed.
	 *
	 * @param string $feature Feature key.
	 * @return bool
	 */
	public static function can_run_feature( $feature = 'export' ) {
		$settings = PCKZ_Settings::get_all();
		if ( empty( $settings['licensing_enforce'] ) ) {
			return true;
		}
		$bound_domains = get_option( self::OPTION_CLIENT_BOUND_DOMAINS, array() );
		if ( is_array( $bound_domains ) && ! empty( $bound_domains ) && ! self::domain_allowed( self::normalized_domain(), $bound_domains ) ) {
			$state = self::get_client_state();
			$state['authorized'] = false;
			$state['status']     = 'denied';
			$state['reason']     = __( 'Current domain is not permitted for this customer package.', 'pckz-canonical-engine' );
			$state['checked_at'] = time();
			update_option( self::OPTION_CLIENT_STATE, $state, false );
			return false;
		}
		$state = self::refresh_entitlement( false );
		if ( ! empty( $state['authorized'] ) ) {
			$perms = is_array( $state['permissions'] ?? null ) ? $state['permissions'] : array();
			if ( isset( $perms[ $feature ] ) && ! $perms[ $feature ] ) {
				return false;
			}
			if ( ! empty( $state['expires_at'] ) && time() > (int) $state['expires_at'] && time() > (int) ( $state['grace_until'] ?? 0 ) ) {
				return false;
			}
			return true;
		}
		if ( ! empty( $state['grace_until'] ) && time() <= (int) $state['grace_until'] ) {
			return true;
		}
		return false;
	}

	/**
	 * Guard protected path and return WP_Error when denied.
	 *
	 * @param string $feature Feature key.
	 * @return true|WP_Error
	 */
	public static function guard_or_error( $feature = 'export' ) {
		if ( self::can_run_feature( $feature ) ) {
			return true;
		}
		$state = self::get_client_state();
		$reason = ! empty( $state['reason'] ) ? (string) $state['reason'] : __( 'License authorization failed.', 'pckz-canonical-engine' );
		return new WP_Error( 'license_denied', $reason );
	}

	/**
	 * Remote authorization call for protected export operations.
	 *
	 * @param array $context Export context.
	 * @return true|WP_Error|array
	 */
	public static function authorize_export_operation( $context = array() ) {
		$guard = self::guard_or_error( 'export' );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}
		$settings = PCKZ_Settings::get_all();
		if ( empty( $settings['licensing_export_authorize'] ) ) {
			return true;
		}
		$master = self::normalize_master_url( (string) ( $settings['licensing_master_url'] ?? '' ) );
		$key    = trim( (string) ( $settings['licensing_key'] ?? '' ) );
		if ( ! $master || ! $key ) {
			return new WP_Error( 'license_unconfigured', __( 'Licensing master URL or license key is missing.', 'pckz-canonical-engine' ) );
		}
		$payload = array(
			'license_key'   => $key,
			'domain'        => self::normalized_domain(),
			'install_uuid'  => self::get_install_uuid(),
			'plugin_version'=> PCKZCE_VERSION,
			'plugin_build'  => defined( 'PCKZCE_BUILD' ) ? PCKZCE_BUILD : PCKZCE_VERSION,
			'wp_version'    => get_bloginfo( 'version' ),
			'php_version'   => PHP_VERSION,
			'integrity_hash'=> self::client_integrity_hash(),
			'tamper_signals'=> class_exists( 'PCKZ_Security' ) ? PCKZ_Security::tamper_signals() : array(),
			'operation'     => sanitize_key( (string) ( $context['operation'] ?? 'export' ) ),
			'payload_hash'  => self::hash_export_payload( $context ),
		);
		$body = wp_json_encode( $payload );
		$resp = wp_remote_post(
			$master . '/wp-json/pckzce-license/v1/client/export-authorize',
			array(
				'timeout' => 12,
				'headers' => self::build_signed_request_headers( $body ),
				'body'    => $body,
			)
		);
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $resp ), true );
		if ( ! is_array( $data ) || empty( $data['authorized'] ) ) {
			return new WP_Error( 'export_not_authorized', sanitize_text_field( (string) ( $data['reason'] ?? __( 'Export authorization denied.', 'pckz-canonical-engine' ) ) ) );
		}
		return $data;
	}

	/**
	 * Optional remote export generation.
	 *
	 * @param array      $export_payload Export payload.
	 * @param array|bool $auth_data      Authorization response.
	 * @return array|WP_Error
	 */
	public static function remote_generate_export( $export_payload, $auth_data = true ) {
		$settings = PCKZ_Settings::get_all();
		if ( empty( $settings['licensing_export_remote_mode'] ) ) {
			return new WP_Error( 'remote_export_disabled', __( 'Remote export mode disabled.', 'pckz-canonical-engine' ) );
		}
		if ( ! is_array( $auth_data ) ) {
			$auth_data = self::authorize_export_operation(
				array(
					'operation' => 'export-generate',
					'payload'   => $export_payload,
				)
			);
			if ( is_wp_error( $auth_data ) ) {
				return $auth_data;
			}
		}
		$permit = sanitize_text_field( (string) ( $auth_data['permit'] ?? '' ) );
		if ( ! $permit ) {
			return new WP_Error( 'missing_export_permit', __( 'Export permit missing.', 'pckz-canonical-engine' ) );
		}
		$master = self::normalize_master_url( (string) ( $settings['licensing_master_url'] ?? '' ) );
		$payload = array(
			'permit'        => $permit,
			'export_payload'=> array_merge(
				(array) $export_payload,
				array(
					'plugin_version' => PCKZCE_VERSION,
					'plugin_build'   => defined( 'PCKZCE_BUILD' ) ? PCKZCE_BUILD : PCKZCE_VERSION,
					'wp_version'     => get_bloginfo( 'version' ),
					'php_version'    => PHP_VERSION,
					'integrity_hash' => self::client_integrity_hash(),
				)
			),
		);
		$body = wp_json_encode( $payload );
		$resp = wp_remote_post(
			$master . '/wp-json/pckzce-license/v1/client/export-generate',
			array(
				'timeout' => 30,
				'headers' => self::build_signed_request_headers( $body ),
				'body'    => $body,
			)
		);
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $resp ), true );
		if ( ! is_array( $data ) || empty( $data['ok'] ) || ! is_array( $data['package'] ?? null ) ) {
			return new WP_Error( 'remote_export_failed', sanitize_text_field( (string) ( $data['reason'] ?? __( 'Remote export failed.', 'pckz-canonical-engine' ) ) ) );
		}
		return $data['package'];
	}

	/**
	 * Inject WordPress plugin update metadata from master server.
	 *
	 * @param stdClass $transient Update transient.
	 * @return stdClass
	 */
	public function inject_plugin_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}
		$meta = $this->fetch_remote_update_meta();
		if ( empty( $meta['update_available'] ) ) {
			return $transient;
		}
		$item = (object) array(
			'slug'        => 'pckz-canonical-engine',
			'plugin'      => PCKZCE_PLUGIN_BASENAME,
			'new_version' => (string) ( $meta['version'] ?? '' ),
			'package'     => (string) ( $meta['package'] ?? '' ),
			'url'         => self::normalize_master_url( (string) ( PCKZ_Settings::get_all()['licensing_master_url'] ?? '' ) ),
			'tested'      => (string) ( $meta['tested'] ?? '' ),
			'requires'    => (string) ( $meta['requires'] ?? '6.0' ),
			'requires_php'=> (string) ( $meta['requires_php'] ?? '7.4' ),
		);
		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
			$transient->response = array();
		}
		$transient->response[ PCKZCE_PLUGIN_BASENAME ] = $item;
		return $transient;
	}

	/**
	 * Inject plugin info pop-up data.
	 *
	 * @param false|object|array $result Existing result.
	 * @param string             $action Action.
	 * @param object             $args   Args.
	 * @return object|false|array
	 */
	public function inject_plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || 'pckz-canonical-engine' !== $args->slug ) {
			return $result;
		}
		$meta = $this->fetch_remote_update_meta();
		if ( empty( $meta['version'] ) ) {
			return $result;
		}
		return (object) array(
			'name'          => 'PCKZ Canonical Engine',
			'slug'          => 'pckz-canonical-engine',
			'version'       => (string) $meta['version'],
			'author'        => '<a href="https://paxdesign.at">PAXDesign</a>',
			'homepage'      => 'https://paxdesign.at',
			'requires'      => (string) ( $meta['requires'] ?? '6.0' ),
			'requires_php'  => (string) ( $meta['requires_php'] ?? '7.4' ),
			'tested'        => (string) ( $meta['tested'] ?? '' ),
			'download_link' => (string) ( $meta['package'] ?? '' ),
			'sections'      => array(
				'description' => 'Protected distribution package delivered by paxdesign.at.',
				'changelog'   => (string) ( $meta['changelog'] ?? '' ),
			),
		);
	}

	/**
	 * Fetch update metadata from master.
	 *
	 * @return array
	 */
	private function fetch_remote_update_meta() {
		$cache = get_transient( 'pckzce_update_meta_cache' );
		if ( is_array( $cache ) ) {
			return $cache;
		}
		$settings = PCKZ_Settings::get_all();
		$master   = self::normalize_master_url( (string) ( $settings['licensing_master_url'] ?? '' ) );
		$key      = trim( (string) ( $settings['licensing_key'] ?? '' ) );
		if ( '' === $master || '' === $key ) {
			return array();
		}
		$payload = array(
			'license_key'     => $key,
			'domain'          => self::normalized_domain(),
			'install_uuid'    => self::get_install_uuid(),
			'current_version' => PCKZCE_VERSION,
			'plugin_version'  => PCKZCE_VERSION,
			'plugin_build'    => defined( 'PCKZCE_BUILD' ) ? PCKZCE_BUILD : PCKZCE_VERSION,
			'wp_version'      => get_bloginfo( 'version' ),
			'php_version'     => PHP_VERSION,
			'integrity_hash'  => self::client_integrity_hash(),
			'tamper_signals'  => class_exists( 'PCKZ_Security' ) ? PCKZ_Security::tamper_signals() : array(),
		);
		$body = wp_json_encode( $payload );
		$resp = wp_remote_post(
			$master . '/wp-json/pckzce-license/v1/client/update-meta',
			array(
				'timeout' => 10,
				'headers' => self::build_signed_request_headers( $body ),
				'body'    => $body,
			)
		);
		if ( is_wp_error( $resp ) ) {
			return array();
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $resp ), true );
		if ( ! is_array( $data ) ) {
			$data = array();
		}
		set_transient( 'pckzce_update_meta_cache', $data, 15 * MINUTE_IN_SECONDS );
		return $data;
	}

	/**
	 * Validate incoming client payload against master-side license records.
	 *
	 * @param array $payload Payload.
	 * @return array|WP_Error
	 */
	private function server_validate_client_payload( $payload, WP_REST_Request $request = null, $body_raw = '', $skip_signature = false ) {
		global $wpdb;
		$key         = sanitize_text_field( (string) ( $payload['license_key'] ?? '' ) );
		$domain      = self::normalize_domain_value( (string) ( $payload['domain'] ?? '' ) );
		$install_uuid = sanitize_text_field( (string) ( $payload['install_uuid'] ?? '' ) );

		if ( '' === $key || '' === $domain || '' === $install_uuid ) {
			return new WP_Error( 'invalid_payload', __( 'Missing license, domain, or installation UUID.', 'pckz-canonical-engine' ) );
		}
		$licenses_table = $wpdb->prefix . 'pckz_license_keys';
		$installs_table = $wpdb->prefix . 'pckz_license_installations';
		$license = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$licenses_table} WHERE license_key = %s LIMIT 1", $key ),
			ARRAY_A
		);
		if ( ! $license ) {
			return new WP_Error( 'license_not_found', __( 'License not found.', 'pckz-canonical-engine' ) );
		}
		if ( 'active' !== (string) $license['status'] ) {
			return new WP_Error( 'license_revoked', __( 'License is not active.', 'pckz-canonical-engine' ) );
		}
		if ( ! empty( $license['expires_at'] ) && strtotime( (string) $license['expires_at'] ) < time() ) {
			return new WP_Error( 'license_expired', __( 'License expired.', 'pckz-canonical-engine' ) );
		}
		$release_meta      = get_option( self::OPTION_RELEASE_META, array() );
		$min_client_build  = sanitize_text_field( (string) ( $release_meta['min_client_build'] ?? '' ) );
		$reported_version  = sanitize_text_field( (string) ( $payload['plugin_version'] ?? '' ) );
		$reported_build    = sanitize_text_field( (string) ( $payload['plugin_build'] ?? '' ) );
		$version_for_check = $reported_version ? $reported_version : $reported_build;
		if ( $min_client_build && $version_for_check && version_compare( $version_for_check, $min_client_build, '<' ) ) {
			return new WP_Error( 'minimum_build_required', __( 'Client version below minimum required build.', 'pckz-canonical-engine' ) );
		}
		$domains = self::decode_json_array( $license['domains'] ?? '' );
		if ( ! self::domain_allowed( $domain, $domains ) ) {
			return new WP_Error( 'domain_not_allowed', __( 'Domain is not approved for this license.', 'pckz-canonical-engine' ) );
		}

		$install = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$installs_table} WHERE license_id = %d AND install_uuid = %s AND domain = %s LIMIT 1",
				(int) $license['id'],
				$install_uuid,
				$domain
			),
			ARRAY_A
		);

		$now = current_time( 'mysql' );
		$plugin_version = sanitize_text_field( (string) ( $payload['plugin_version'] ?? '' ) );
		$plugin_build   = sanitize_text_field( (string) ( $payload['plugin_build'] ?? '' ) );
		$wp_version     = sanitize_text_field( (string) ( $payload['wp_version'] ?? '' ) );
		$php_version    = sanitize_text_field( (string) ( $payload['php_version'] ?? '' ) );
		$integrity_hash = sanitize_text_field( (string) ( $payload['integrity_hash'] ?? '' ) );
		$tamper_signals = is_array( $payload['tamper_signals'] ?? null ) ? wp_json_encode( array_values( $payload['tamper_signals'] ) ) : wp_json_encode( array() );
		$require_signed = ! empty( PCKZ_Settings::get_all()['licensing_require_signed_requests'] );
		if ( $install ) {
			if ( ! $skip_signature ) {
				$sig_ok = $this->verify_incoming_signature( $request, (string) ( $install['install_secret'] ?? '' ), $body_raw, $install_uuid, $require_signed );
				if ( is_wp_error( $sig_ok ) ) {
					return $sig_ok;
				}
			}
			if ( 'active' !== (string) $install['status'] ) {
				return new WP_Error( 'installation_blocked', __( 'Installation is blocked.', 'pckz-canonical-engine' ) );
			}
			if ( ! empty( PCKZ_Settings::get_all()['licensing_strict_integrity'] ) ) {
				$old_build = (string) ( $install['plugin_build'] ?? '' );
				$old_hash  = (string) ( $install['integrity_hash'] ?? '' );
				if ( $old_hash && $integrity_hash && $old_build === $plugin_build && ! hash_equals( $old_hash, $integrity_hash ) ) {
					return new WP_Error( 'integrity_mismatch', __( 'Client integrity check failed.', 'pckz-canonical-engine' ) );
				}
			}
			$wpdb->update(
				$installs_table,
				array(
					'plugin_version' => $plugin_version,
					'plugin_build'   => $plugin_build,
					'wp_version'     => $wp_version,
					'php_version'    => $php_version,
					'integrity_hash' => $integrity_hash,
					'tamper_signals' => $tamper_signals,
					'last_check_in'  => $now,
					'heartbeat_count'=> (int) ( $install['heartbeat_count'] ?? 0 ) + 1,
					'last_ip'        => self::request_ip(),
					'last_user_agent'=> self::request_user_agent(),
					'updated_at'     => $now,
					'last_error'     => '',
				),
				array( 'id' => (int) $install['id'] ),
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
		} else {
			$max = max( 1, (int) ( $license['max_installs'] ?? 1 ) );
			$count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$installs_table} WHERE license_id = %d AND status = %s",
					(int) $license['id'],
					'active'
				)
			);
			if ( $count >= $max ) {
				return new WP_Error( 'license_limit', __( 'Maximum installations reached.', 'pckz-canonical-engine' ) );
			}
			$secret = wp_generate_password( 48, false, false );
			$wpdb->insert(
				$installs_table,
				array(
					'license_id'     => (int) $license['id'],
					'install_uuid'   => $install_uuid,
					'domain'         => $domain,
					'status'         => 'active',
					'plugin_version' => $plugin_version,
					'plugin_build'   => $plugin_build,
					'wp_version'     => $wp_version,
					'php_version'    => $php_version,
					'integrity_hash' => $integrity_hash,
					'tamper_signals' => $tamper_signals,
					'last_check_in'  => $now,
					'heartbeat_count'=> 1,
					'last_ip'        => self::request_ip(),
					'last_user_agent'=> self::request_user_agent(),
					'install_secret' => $secret,
					'created_at'     => $now,
					'updated_at'     => $now,
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
			);
			$install = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$installs_table} WHERE license_id = %d AND install_uuid = %s AND domain = %s LIMIT 1",
					(int) $license['id'],
					$install_uuid,
					$domain
				),
				ARRAY_A
			);
		}

		return array(
			'license'       => $license,
			'install'       => $install,
			'domain'        => $domain,
			'install_uuid'  => $install_uuid,
			'permissions'   => self::decode_json_assoc(
				$license['permissions'] ?? '',
				array( 'export' => true, 'updates' => true )
			),
			'install_secret' => (string) ( $install['install_secret'] ?? '' ),
		);
	}

	/**
	 * Apply embedded customer package configuration once per package hash.
	 */
	private function apply_embedded_client_package_config() {
		$config_file  = trailingslashit( PCKZCE_PLUGIN_DIR ) . 'CLIENT_LICENSE_CONFIG.json';
		$binding_file = trailingslashit( PCKZCE_PLUGIN_DIR ) . 'LICENSE_BINDING.json';
		if ( ! file_exists( $config_file ) && ! file_exists( $binding_file ) ) {
			return;
		}

		$config_raw  = file_exists( $config_file ) ? (string) @file_get_contents( $config_file ) : '';
		$binding_raw = file_exists( $binding_file ) ? (string) @file_get_contents( $binding_file ) : '';
		$hash_seed   = $config_raw . "\n" . $binding_raw;
		if ( '' === trim( $hash_seed ) ) {
			return;
		}
		$current_hash = hash( 'sha256', $hash_seed );
		$applied_hash = (string) get_option( self::OPTION_CLIENT_PACKAGE_HASH, '' );
		if ( $applied_hash && hash_equals( $applied_hash, $current_hash ) ) {
			return;
		}

		$config  = json_decode( $config_raw, true );
		$binding = json_decode( $binding_raw, true );
		$config  = is_array( $config ) ? $config : array();
		$binding = is_array( $binding ) ? $binding : array();
		if ( empty( $config ) && empty( $binding ) ) {
			return;
		}

		$settings = PCKZ_Settings::get_all();
		$changed  = false;

		$master_url = self::normalize_master_url( (string) ( $config['master_url'] ?? $binding['master_url'] ?? '' ) );
		if ( $master_url && $master_url !== (string) $settings['licensing_master_url'] ) {
			$settings['licensing_master_url'] = $master_url;
			$changed = true;
		}
		$license_key = sanitize_text_field( (string) ( $config['license_key'] ?? '' ) );
		if ( $license_key && $license_key !== (string) $settings['licensing_key'] ) {
			$settings['licensing_key'] = $license_key;
			$changed = true;
		}

		if ( ! empty( $settings['licensing_master_mode'] ) ) {
			$settings['licensing_master_mode'] = false;
			$changed = true;
		}

		if ( array_key_exists( 'licensing_enforce', $config ) ) {
			$settings['licensing_enforce'] = ! empty( $config['licensing_enforce'] );
			$changed = true;
		}
		if ( array_key_exists( 'licensing_grace_minutes', $config ) ) {
			$settings['licensing_grace_minutes'] = max( 5, min( 1440, absint( $config['licensing_grace_minutes'] ) ) );
			$changed = true;
		}
		if ( array_key_exists( 'licensing_require_signed_requests', $config ) ) {
			$settings['licensing_require_signed_requests'] = ! empty( $config['licensing_require_signed_requests'] );
			$changed = true;
		}
		if ( array_key_exists( 'licensing_export_authorize', $config ) ) {
			$settings['licensing_export_authorize'] = ! empty( $config['licensing_export_authorize'] );
			$changed = true;
		}
		if ( array_key_exists( 'licensing_export_remote_mode', $config ) ) {
			$settings['licensing_export_remote_mode'] = ! empty( $config['licensing_export_remote_mode'] );
			$changed = true;
		}
		if ( array_key_exists( 'licensing_export_remote_strict', $config ) ) {
			$settings['licensing_export_remote_strict'] = ! empty( $config['licensing_export_remote_strict'] );
			$changed = true;
		}

		$bound_domains = array();
		if ( ! empty( $config['domains'] ) && is_array( $config['domains'] ) ) {
			$bound_domains = array_map( array( __CLASS__, 'normalize_domain_value' ), $config['domains'] );
		} elseif ( ! empty( $binding['domains'] ) && is_array( $binding['domains'] ) ) {
			$bound_domains = array_map( array( __CLASS__, 'normalize_domain_value' ), $binding['domains'] );
		}
		$bound_domains = array_values( array_filter( array_unique( $bound_domains ) ) );
		if ( ! empty( $bound_domains ) ) {
			update_option( self::OPTION_CLIENT_BOUND_DOMAINS, $bound_domains, false );
		}

		if ( $changed ) {
			update_option( PCKZ_Settings::OPTION_KEY, $settings, false );
		}
		update_option( self::OPTION_CLIENT_PACKAGE_HASH, $current_hash, false );
	}

	/**
	 * Ensure installation UUID exists.
	 */
	private function ensure_install_uuid() {
		$settings = PCKZ_Settings::get_all();
		if ( ! empty( $settings['licensing_install_uuid'] ) ) {
			return;
		}
		$settings['licensing_install_uuid'] = wp_generate_uuid4();
		update_option( PCKZ_Settings::OPTION_KEY, $settings );
	}

	/**
	 * Get installation UUID.
	 *
	 * @return string
	 */
	public static function get_install_uuid() {
		$settings = PCKZ_Settings::get_all();
		$uuid     = sanitize_text_field( (string) ( $settings['licensing_install_uuid'] ?? '' ) );
		if ( '' === $uuid ) {
			$uuid = wp_generate_uuid4();
			$settings['licensing_install_uuid'] = $uuid;
			update_option( PCKZ_Settings::OPTION_KEY, $settings );
		}
		return $uuid;
	}

	/**
	 * Return current cached client state.
	 *
	 * @return array
	 */
	public static function get_client_state() {
		$state = get_option( self::OPTION_CLIENT_STATE, array() );
		return is_array( $state ) ? $state : array();
	}

	/**
	 * Master-mode helper.
	 *
	 * @return bool
	 */
	public static function is_master_mode() {
		return self::master_mode_enabled() && self::is_master_host_allowed();
	}

	/**
	 * Raw master-mode toggle from settings.
	 *
	 * @return bool
	 */
	private static function master_mode_enabled() {
		$settings = PCKZ_Settings::get_all();
		return ! empty( $settings['licensing_master_mode'] );
	}

	/**
	 * Whether current host is allowed to run master functionality.
	 *
	 * @return bool
	 */
	private static function is_master_host_allowed() {
		$current = self::normalized_domain();
		return self::domain_allowed( $current, self::master_allowed_domains() );
	}

	/**
	 * Master-eligible domains (server-side hard gate).
	 *
	 * @return array
	 */
	private static function master_allowed_domains() {
		$normalized = array();
		foreach ( array( 'paxdesign.at' ) as $domain ) {
			$value = self::normalize_domain_value( (string) $domain );
			if ( '' !== $value ) {
				$normalized[] = $value;
			}
		}
		$normalized = array_values( array_unique( $normalized ) );
		return ! empty( $normalized ) ? $normalized : array( 'paxdesign.at' );
	}

	/**
	 * Normalize master URL.
	 *
	 * @param string $url Raw URL.
	 * @return string
	 */
	private static function normalize_master_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}
		$url = preg_replace( '#/+$#', '', $url );
		return esc_url_raw( $url );
	}

	/**
	 * Normalize current site domain.
	 *
	 * @return string
	 */
	private static function normalized_domain() {
		return self::normalize_domain_value( home_url() );
	}

	/**
	 * Normalize domain value.
	 *
	 * @param string $raw Raw value.
	 * @return string
	 */
	private static function normalize_domain_value( $raw ) {
		$host = wp_parse_url( trim( (string) $raw ), PHP_URL_HOST );
		if ( ! $host ) {
			$host = trim( (string) $raw );
		}
		$host = strtolower( preg_replace( '#^www\.#', '', $host ) );
		return sanitize_text_field( $host );
	}

	/**
	 * Domain allow-list matcher.
	 *
	 * @param string $domain  Domain.
	 * @param array  $allowed Allowed list.
	 * @return bool
	 */
	private static function domain_allowed( $domain, $allowed ) {
		if ( empty( $allowed ) ) {
			return false;
		}
		$domain = self::normalize_domain_value( $domain );
		foreach ( $allowed as $entry ) {
			$entry = self::normalize_domain_value( $entry );
			if ( '' === $entry ) {
				continue;
			}
			if ( $entry === $domain ) {
				return true;
			}
			if ( 0 === strpos( $entry, '*.' ) ) {
				$suffix = substr( $entry, 2 );
				if ( $suffix && preg_match( '/(^|\.)' . preg_quote( $suffix, '/' ) . '$/', $domain ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Parse domains input into a list.
	 *
	 * @param string $raw Raw textarea.
	 * @return array
	 */
	private static function parse_domains( $raw ) {
		$parts = preg_split( '/[\r\n,;]+/', (string) $raw );
		$out   = array();
		foreach ( (array) $parts as $part ) {
			$domain = self::normalize_domain_value( $part );
			if ( $domain ) {
				$out[ $domain ] = $domain;
			}
		}
		return array_values( $out );
	}

	/**
	 * Decode JSON list with array fallback.
	 *
	 * @param string $raw Raw JSON.
	 * @return array
	 */
	private static function decode_json_array( $raw ) {
		$decoded = json_decode( (string) $raw, true );
		return is_array( $decoded ) ? array_values( $decoded ) : array();
	}

	/**
	 * Decode JSON object with fallback.
	 *
	 * @param string $raw      Raw JSON.
	 * @param array  $fallback Fallback.
	 * @return array
	 */
	private static function decode_json_assoc( $raw, $fallback = array() ) {
		$decoded = json_decode( (string) $raw, true );
		return is_array( $decoded ) ? $decoded : $fallback;
	}

	/**
	 * Create signed token string.
	 *
	 * @param array $payload Payload.
	 * @return string
	 */
	private static function build_signed_token( $payload ) {
		$body = rtrim( strtr( base64_encode( wp_json_encode( $payload ) ), '+/', '-_' ), '=' );
		$sig  = hash_hmac( 'sha256', $body, wp_salt( 'auth' ) );
		return $body . '.' . $sig;
	}

	/**
	 * Verify signed token payload.
	 *
	 * @param string $token Token.
	 * @return array|WP_Error
	 */
	private static function verify_signed_token( $token ) {
		$parts = explode( '.', (string) $token );
		if ( count( $parts ) !== 2 ) {
			return new WP_Error( 'invalid_token', __( 'Invalid token format.', 'pckz-canonical-engine' ) );
		}
		list( $body_b64, $sig ) = $parts;
		$expect = hash_hmac( 'sha256', $body_b64, wp_salt( 'auth' ) );
		if ( ! hash_equals( $expect, (string) $sig ) ) {
			return new WP_Error( 'bad_signature', __( 'Token signature mismatch.', 'pckz-canonical-engine' ) );
		}
		$body_json = base64_decode( strtr( $body_b64, '-_', '+/' ) . str_repeat( '=', ( 4 - strlen( $body_b64 ) % 4 ) % 4 ) );
		$data = json_decode( (string) $body_json, true );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'invalid_token_body', __( 'Token body invalid.', 'pckz-canonical-engine' ) );
		}
		if ( ! empty( $data['exp'] ) && time() > (int) $data['exp'] ) {
			return new WP_Error( 'token_expired', __( 'Token expired.', 'pckz-canonical-engine' ) );
		}
		return $data;
	}

	/**
	 * Generate a new license key.
	 *
	 * @return string
	 */
	private static function generate_license_key() {
		return 'PCKZCE-' . strtoupper( wp_generate_password( 24, false, false ) );
	}

	/**
	 * Mask key for UI.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	private static function mask_key( $key ) {
		$key = (string) $key;
		$len = strlen( $key );
		if ( $len <= 8 ) {
			return str_repeat( '*', max( 4, $len ) );
		}
		return substr( $key, 0, 6 ) . '…' . substr( $key, -4 );
	}

	/**
	 * Persist download telemetry for protected package requests.
	 *
	 * @param array  $validated  Validated payload state.
	 * @param array  $payload    Download payload.
	 * @param string $package_url Package URL.
	 */
	private function record_download_event( $validated, $payload, $package_url ) {
		global $wpdb;
		$license_id = (int) ( $validated['license']['id'] ?? 0 );
		if ( $license_id <= 0 ) {
			return;
		}
		$installation_id = (int) ( $validated['install']['id'] ?? 0 );
		$wpdb->insert(
			$wpdb->prefix . 'pckz_license_downloads',
			array(
				'license_id'         => $license_id,
				'installation_id'    => $installation_id,
				'domain'             => sanitize_text_field( (string) ( $validated['domain'] ?? $payload['domain'] ?? '' ) ),
				'install_uuid'       => sanitize_text_field( (string) ( $validated['install_uuid'] ?? $payload['install_uuid'] ?? '' ) ),
				'requested_version'  => sanitize_text_field( (string) ( $payload['current_version'] ?? '' ) ),
				'package_url'        => esc_url_raw( (string) $package_url ),
				'last_ip'            => self::request_ip(),
				'last_user_agent'    => self::request_user_agent(),
				'created_at'         => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Request IP helper.
	 *
	 * @return string
	 */
	private static function request_ip() {
		$keys = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );
		foreach ( $keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$raw = trim( (string) wp_unslash( $_SERVER[ $key ] ) );
				$ip  = explode( ',', $raw );
				return sanitize_text_field( trim( $ip[0] ) );
			}
		}
		return '';
	}

	/**
	 * Request user agent helper.
	 *
	 * @return string
	 */
	private static function request_user_agent() {
		return sanitize_text_field( (string) wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) );
	}

	/**
	 * Build client request headers (signed when possible/enabled).
	 *
	 * @param string $body Raw request body.
	 * @return array
	 */
	private static function build_signed_request_headers( $body ) {
		$headers = array( 'Content-Type' => 'application/json' );
		$settings = PCKZ_Settings::get_all();
		if ( empty( $settings['licensing_require_signed_requests'] ) ) {
			return $headers;
		}
		$secret = (string) get_option( self::OPTION_INSTALL_SECRET, '' );
		if ( ! $secret ) {
			return $headers;
		}
		$ts    = (string) time();
		$nonce = wp_generate_uuid4();
		$sig   = hash_hmac( 'sha256', $ts . '.' . $nonce . '.' . hash( 'sha256', (string) $body ), $secret );
		$headers['X-PCKZ-Timestamp'] = $ts;
		$headers['X-PCKZ-Nonce']     = $nonce;
		$headers['X-PCKZ-Signature'] = $sig;
		return $headers;
	}

	/**
	 * Verify incoming signed request from a client installation.
	 *
	 * @param WP_REST_Request|null $request      Request.
	 * @param string               $secret       Shared install secret.
	 * @param string               $body_raw     Raw body.
	 * @param string               $install_uuid Install UUID.
	 * @param bool                 $required     Require signature.
	 * @return true|WP_Error
	 */
	private function verify_incoming_signature( $request, $secret, $body_raw, $install_uuid, $required ) {
		if ( ! $required ) {
			return true;
		}
		if ( ! $request || ! $secret ) {
			return new WP_Error( 'missing_signature_context', __( 'Signed request required but context is missing.', 'pckz-canonical-engine' ) );
		}
		$ts    = sanitize_text_field( (string) $request->get_header( 'x-pckz-timestamp' ) );
		$nonce = sanitize_text_field( (string) $request->get_header( 'x-pckz-nonce' ) );
		$sig   = sanitize_text_field( (string) $request->get_header( 'x-pckz-signature' ) );
		if ( '' === $ts || '' === $nonce || '' === $sig ) {
			return new WP_Error( 'missing_signature_headers', __( 'Missing signature headers.', 'pckz-canonical-engine' ) );
		}
		$delta = abs( time() - (int) $ts );
		if ( $delta > 300 ) {
			return new WP_Error( 'signature_expired', __( 'Signature timestamp is outside accepted window.', 'pckz-canonical-engine' ) );
		}
		$replay_key = self::OPTION_REPLAY_PREFIX . md5( $install_uuid . '|' . $nonce );
		if ( get_transient( $replay_key ) ) {
			return new WP_Error( 'signature_replay', __( 'Replay signature detected.', 'pckz-canonical-engine' ) );
		}
		$expect = hash_hmac( 'sha256', $ts . '.' . $nonce . '.' . hash( 'sha256', (string) $body_raw ), $secret );
		if ( ! hash_equals( $expect, $sig ) ) {
			return new WP_Error( 'signature_mismatch', __( 'Signature validation failed.', 'pckz-canonical-engine' ) );
		}
		set_transient( $replay_key, '1', 10 * MINUTE_IN_SECONDS );
		return true;
	}

	/**
	 * Build stable hash for export authorization payload.
	 *
	 * @param mixed $payload Payload.
	 * @return string
	 */
	public static function hash_export_payload( $payload ) {
		return hash( 'sha256', wp_json_encode( $payload ) );
	}

	/**
	 * Compute client integrity fingerprint for anti-tamper telemetry.
	 *
	 * @return string
	 */
	public static function client_integrity_hash() {
		if ( class_exists( 'PCKZ_Security' ) ) {
			return PCKZ_Security::integrity_fingerprint();
		}
		return hash( 'sha256', PCKZCE_VERSION . '|' . ( defined( 'PCKZCE_BUILD' ) ? PCKZCE_BUILD : PCKZCE_VERSION ) );
	}

	/**
	 * Execute export generation payload for optional remote mode.
	 *
	 * @param array $export_payload Payload.
	 * @return array|WP_Error
	 */
	private static function run_remote_export_generation( $export_payload ) {
		$product_id = absint( $export_payload['product_id'] ?? 0 );
		$config = is_array( $export_payload['config'] ?? null )
			? $export_payload['config']
			: PCKZ_Post_Type::get_product_config( $product_id );
		$selections = is_array( $export_payload['selections'] ?? null ) ? $export_payload['selections'] : array();
		$args = array(
			'config'                => $config,
			'selections'            => $selections,
			'canvas_json'           => (string) ( $export_payload['canvas_json'] ?? '{}' ),
			'production_vector_svg' => (string) ( $export_payload['production_vector_svg'] ?? '' ),
			'text_plate_paths'      => (string) ( $export_payload['text_plate_paths'] ?? '' ),
			'design_id'             => absint( $export_payload['design_id'] ?? 0 ),
			'std_spec'              => is_array( $export_payload['std_spec'] ?? null ) ? $export_payload['std_spec'] : array(),
		);
		if ( ! empty( $export_payload['canonical_scene_json'] ) ) {
			$args['canonical_scene'] = (string) $export_payload['canonical_scene_json'];
			$package = PCKZ_Export_Engine::run( $args );
		} else {
			$package = PCKZ_Production::build_package( $args );
		}
		return is_wp_error( $package ) ? $package : $package;
	}
}

add_filter(
	'cron_schedules',
	static function ( $schedules ) {
		if ( ! isset( $schedules['five_minutes'] ) ) {
			$schedules['five_minutes'] = array(
				'interval' => 300,
				'display'  => __( 'Every 5 Minutes', 'pckz-canonical-engine' ),
			);
		}
		return $schedules;
	}
);
