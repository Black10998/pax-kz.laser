<?php
/**
 * Phase 1 licensing / master-control foundation.
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

		add_action( 'admin_post_pckzce_create_license', array( $this, 'handle_create_license' ) );
		add_action( 'admin_post_pckzce_update_license_status', array( $this, 'handle_update_license_status' ) );
		add_action( 'admin_post_pckzce_update_installation_status', array( $this, 'handle_update_installation_status' ) );
		add_action( 'admin_post_pckzce_save_release_meta', array( $this, 'handle_save_release_meta' ) );

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
			last_check_in DATETIME NULL,
			last_ip VARCHAR(64) NOT NULL DEFAULT '',
			last_error TEXT NULL,
			install_secret VARCHAR(191) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY license_install_domain (license_id, install_uuid, domain),
			KEY license_id (license_id),
			KEY domain (domain)
		) {$charset};";

		dbDelta( $sql_licenses );
		dbDelta( $sql_installs );
	}

	/**
	 * Core bootstrapping.
	 */
	public function bootstrap() {
		$this->ensure_install_uuid();
		$this->schedule_heartbeat();
	}

	/**
	 * Register master-control admin page.
	 */
	public function register_admin_menu() {
		add_submenu_page(
			'pckz-canonical-engine',
			__( 'License Server', 'pckz-canonical-engine' ),
			__( 'License Server', 'pckz-canonical-engine' ),
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
		$master_mode  = ! empty( $settings['licensing_master_mode'] );
		$generated    = get_transient( 'pckzce_last_created_license' );
		$release_meta = get_option(
			self::OPTION_RELEASE_META,
			array(
				'version'          => '',
				'package_url'      => '',
				'changelog'        => '',
				'requires'         => '6.0',
				'requires_php'     => '7.4',
				'tested'           => '',
				'min_client_build' => '',
			)
		);

		global $wpdb;
		$licenses = array();
		$installs = array();
		if ( $master_mode ) {
			$licenses = $wpdb->get_results( 'SELECT * FROM ' . $wpdb->prefix . 'pckz_license_keys ORDER BY id DESC LIMIT 200', ARRAY_A );
			$installs = $wpdb->get_results( 'SELECT * FROM ' . $wpdb->prefix . 'pckz_license_installations ORDER BY updated_at DESC LIMIT 500', ARRAY_A );
		}
		?>
		<div class="wrap pckz-admin-wrap">
			<h1><?php esc_html_e( 'PAX License Server', 'pckz-canonical-engine' ); ?></h1>
			<?php if ( ! $master_mode ) : ?>
				<div class="notice notice-warning"><p><?php esc_html_e( 'Master mode is disabled. Enable "Master control mode" in Settings to run paxdesign.at license APIs and controls from this installation.', 'pckz-canonical-engine' ); ?></p></div>
			<?php endif; ?>
			<?php if ( $generated ) : ?>
				<div class="notice notice-success is-dismissible"><p><strong><?php esc_html_e( 'New license key:', 'pckz-canonical-engine' ); ?></strong> <code><?php echo esc_html( $generated ); ?></code></p></div>
				<?php delete_transient( 'pckzce_last_created_license' ); ?>
			<?php endif; ?>

			<div class="pckz-card" style="margin-top:16px;">
				<h2><?php esc_html_e( 'Release / Update Metadata', 'pckz-canonical-engine' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'pckzce_save_release_meta', 'pckzce_release_nonce' ); ?>
					<input type="hidden" name="action" value="pckzce_save_release_meta">
					<table class="form-table" role="presentation">
						<tr><th scope="row"><label for="pckzce-release-version"><?php esc_html_e( 'Latest version', 'pckz-canonical-engine' ); ?></label></th><td><input id="pckzce-release-version" type="text" class="regular-text" name="version" value="<?php echo esc_attr( $release_meta['version'] ?? '' ); ?>"></td></tr>
						<tr><th scope="row"><label for="pckzce-release-package"><?php esc_html_e( 'Protected package URL', 'pckz-canonical-engine' ); ?></label></th><td><input id="pckzce-release-package" type="url" class="large-text" name="package_url" value="<?php echo esc_attr( $release_meta['package_url'] ?? '' ); ?>"></td></tr>
						<tr><th scope="row"><label for="pckzce-release-changelog"><?php esc_html_e( 'Changelog', 'pckz-canonical-engine' ); ?></label></th><td><textarea id="pckzce-release-changelog" class="large-text" rows="6" name="changelog"><?php echo esc_textarea( $release_meta['changelog'] ?? '' ); ?></textarea></td></tr>
						<tr><th scope="row"><label for="pckzce-release-tested"><?php esc_html_e( 'Tested up to (WP)', 'pckz-canonical-engine' ); ?></label></th><td><input id="pckzce-release-tested" type="text" class="regular-text" name="tested" value="<?php echo esc_attr( $release_meta['tested'] ?? '' ); ?>"></td></tr>
						<tr><th scope="row"><label for="pckzce-release-requires"><?php esc_html_e( 'Requires WP', 'pckz-canonical-engine' ); ?></label></th><td><input id="pckzce-release-requires" type="text" class="small-text" name="requires" value="<?php echo esc_attr( $release_meta['requires'] ?? '6.0' ); ?>"></td></tr>
						<tr><th scope="row"><label for="pckzce-release-requires-php"><?php esc_html_e( 'Requires PHP', 'pckz-canonical-engine' ); ?></label></th><td><input id="pckzce-release-requires-php" type="text" class="small-text" name="requires_php" value="<?php echo esc_attr( $release_meta['requires_php'] ?? '7.4' ); ?>"></td></tr>
						<tr><th scope="row"><label for="pckzce-release-min-build"><?php esc_html_e( 'Minimum required client build', 'pckz-canonical-engine' ); ?></label></th><td><input id="pckzce-release-min-build" type="text" class="regular-text" name="min_client_build" value="<?php echo esc_attr( $release_meta['min_client_build'] ?? '' ); ?>"><p class="description"><?php esc_html_e( 'Optional. If set, clients below this build can be denied protected features.', 'pckz-canonical-engine' ); ?></p></td></tr>
					</table>
					<?php submit_button( __( 'Save release metadata', 'pckz-canonical-engine' ) ); ?>
				</form>
			</div>

			<div class="pckz-card" style="margin-top:16px;">
				<h2><?php esc_html_e( 'Create License Key', 'pckz-canonical-engine' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'pckzce_create_license', 'pckzce_license_nonce' ); ?>
					<input type="hidden" name="action" value="pckzce_create_license">
					<table class="form-table" role="presentation">
						<tr><th scope="row"><label for="pckzce-license-label"><?php esc_html_e( 'Label', 'pckz-canonical-engine' ); ?></label></th><td><input id="pckzce-license-label" type="text" class="regular-text" name="label" value=""></td></tr>
						<tr><th scope="row"><label for="pckzce-license-domains"><?php esc_html_e( 'Allowed domains', 'pckz-canonical-engine' ); ?></label></th><td><textarea id="pckzce-license-domains" class="large-text" rows="4" name="domains"></textarea><p class="description"><?php esc_html_e( 'One per line. Example: client-domain.com', 'pckz-canonical-engine' ); ?></p></td></tr>
						<tr><th scope="row"><label for="pckzce-license-max"><?php esc_html_e( 'Max installations', 'pckz-canonical-engine' ); ?></label></th><td><input id="pckzce-license-max" type="number" min="1" class="small-text" name="max_installs" value="1"></td></tr>
						<tr><th scope="row"><?php esc_html_e( 'Permissions', 'pckz-canonical-engine' ); ?></th><td>
							<label><input type="checkbox" name="perm_export" value="1" checked> <?php esc_html_e( 'Allow protected export', 'pckz-canonical-engine' ); ?></label><br>
							<label><input type="checkbox" name="perm_updates" value="1" checked> <?php esc_html_e( 'Allow updates/downloads', 'pckz-canonical-engine' ); ?></label>
						</td></tr>
					</table>
					<?php submit_button( __( 'Create license key', 'pckz-canonical-engine' ) ); ?>
				</form>
			</div>

			<?php if ( $master_mode ) : ?>
				<div class="pckz-card" style="margin-top:16px;">
					<h2><?php esc_html_e( 'Licenses', 'pckz-canonical-engine' ); ?></h2>
					<table class="widefat striped">
						<thead><tr><th>ID</th><th><?php esc_html_e( 'Label', 'pckz-canonical-engine' ); ?></th><th><?php esc_html_e( 'Key', 'pckz-canonical-engine' ); ?></th><th><?php esc_html_e( 'Status', 'pckz-canonical-engine' ); ?></th><th><?php esc_html_e( 'Domains', 'pckz-canonical-engine' ); ?></th><th><?php esc_html_e( 'Actions', 'pckz-canonical-engine' ); ?></th></tr></thead>
						<tbody>
						<?php foreach ( $licenses as $license ) : ?>
							<?php $domains = self::decode_json_array( $license['domains'] ); ?>
							<tr>
								<td><?php echo esc_html( (string) $license['id'] ); ?></td>
								<td><?php echo esc_html( $license['label'] ); ?></td>
								<td><code><?php echo esc_html( self::mask_key( $license['license_key'] ) ); ?></code></td>
								<td><?php echo esc_html( $license['status'] ); ?></td>
								<td><?php echo esc_html( implode( ', ', $domains ) ); ?></td>
								<td>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
										<?php wp_nonce_field( 'pckzce_update_license_status', 'pckzce_license_status_nonce' ); ?>
										<input type="hidden" name="action" value="pckzce_update_license_status">
										<input type="hidden" name="license_id" value="<?php echo esc_attr( (string) $license['id'] ); ?>">
										<input type="hidden" name="new_status" value="<?php echo esc_attr( 'active' === $license['status'] ? 'revoked' : 'active' ); ?>">
										<button type="submit" class="button button-secondary"><?php echo esc_html( 'active' === $license['status'] ? __( 'Revoke', 'pckz-canonical-engine' ) : __( 'Activate', 'pckz-canonical-engine' ) ); ?></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<div class="pckz-card" style="margin-top:16px;">
					<h2><?php esc_html_e( 'Active Installations', 'pckz-canonical-engine' ); ?></h2>
					<table class="widefat striped">
						<thead><tr><th>ID</th><th><?php esc_html_e( 'License ID', 'pckz-canonical-engine' ); ?></th><th><?php esc_html_e( 'Domain', 'pckz-canonical-engine' ); ?></th><th><?php esc_html_e( 'Install UUID', 'pckz-canonical-engine' ); ?></th><th><?php esc_html_e( 'Plugin version', 'pckz-canonical-engine' ); ?></th><th><?php esc_html_e( 'Last check-in', 'pckz-canonical-engine' ); ?></th><th><?php esc_html_e( 'Status', 'pckz-canonical-engine' ); ?></th><th><?php esc_html_e( 'Actions', 'pckz-canonical-engine' ); ?></th></tr></thead>
						<tbody>
						<?php foreach ( $installs as $install ) : ?>
							<tr>
								<td><?php echo esc_html( (string) $install['id'] ); ?></td>
								<td><?php echo esc_html( (string) $install['license_id'] ); ?></td>
								<td><?php echo esc_html( $install['domain'] ); ?></td>
								<td><code><?php echo esc_html( $install['install_uuid'] ); ?></code></td>
								<td><?php echo esc_html( $install['plugin_version'] ); ?></td>
								<td><?php echo esc_html( $install['last_check_in'] ); ?></td>
								<td><?php echo esc_html( $install['status'] ); ?></td>
								<td>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
										<?php wp_nonce_field( 'pckzce_update_installation_status', 'pckzce_install_status_nonce' ); ?>
										<input type="hidden" name="action" value="pckzce_update_installation_status">
										<input type="hidden" name="installation_id" value="<?php echo esc_attr( (string) $install['id'] ); ?>">
										<input type="hidden" name="new_status" value="<?php echo esc_attr( 'active' === $install['status'] ? 'blocked' : 'active' ); ?>">
										<button type="submit" class="button button-secondary"><?php echo esc_html( 'active' === $install['status'] ? __( 'Block', 'pckz-canonical-engine' ) : __( 'Unblock', 'pckz-canonical-engine' ) ); ?></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
		<?php
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
			$wpdb->update(
				$wpdb->prefix . 'pckz_license_keys',
				array(
					'status'     => in_array( $new_state, array( 'active', 'revoked', 'disabled' ), true ) ? $new_state : 'revoked',
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
		$meta = array(
			'version'          => sanitize_text_field( wp_unslash( $_POST['version'] ?? '' ) ),
			'package_url'      => esc_url_raw( wp_unslash( $_POST['package_url'] ?? '' ) ),
			'changelog'        => wp_kses_post( wp_unslash( $_POST['changelog'] ?? '' ) ),
			'requires'         => sanitize_text_field( wp_unslash( $_POST['requires'] ?? '6.0' ) ),
			'requires_php'     => sanitize_text_field( wp_unslash( $_POST['requires_php'] ?? '7.4' ) ),
			'tested'           => sanitize_text_field( wp_unslash( $_POST['tested'] ?? '' ) ),
			'min_client_build' => sanitize_text_field( wp_unslash( $_POST['min_client_build'] ?? '' ) ),
		);
		update_option( self::OPTION_RELEASE_META, $meta );
		wp_safe_redirect( admin_url( 'admin.php?page=pckz-license-server' ) );
		exit;
	}

	/**
	 * Register REST API routes for master and update services.
	 */
	public function register_rest_routes() {
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
		$payload = json_decode( (string) $request->get_body(), true );
		if ( ! is_array( $payload ) ) {
			$payload = array();
		}
		$validated = $this->server_validate_client_payload( $payload );
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
		$payload = json_decode( (string) $request->get_body(), true );
		if ( ! is_array( $payload ) ) {
			$payload = array();
		}
		$validated = $this->server_validate_client_payload( $payload );
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
		$query = array(
			'license_key'  => (string) ( $payload['license_key'] ?? '' ),
			'domain'       => (string) ( $payload['domain'] ?? '' ),
			'install_uuid' => (string) ( $payload['install_uuid'] ?? '' ),
			'version'      => $latest,
		);
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
		$payload = array(
			'license_key'  => sanitize_text_field( (string) $request->get_param( 'license_key' ) ),
			'domain'       => sanitize_text_field( (string) $request->get_param( 'domain' ) ),
			'install_uuid' => sanitize_text_field( (string) $request->get_param( 'install_uuid' ) ),
			'current_version' => sanitize_text_field( (string) $request->get_param( 'version' ) ),
		);
		$validated = $this->server_validate_client_payload( $payload );
		if ( is_wp_error( $validated ) || empty( $validated['permissions']['updates'] ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'reason' => 'not_authorized' ), 403 );
		}
		$meta    = get_option( self::OPTION_RELEASE_META, array() );
		$package = esc_url_raw( (string) ( $meta['package_url'] ?? '' ) );
		if ( ! $package ) {
			return new WP_REST_Response( array( 'ok' => false, 'reason' => 'package_not_configured' ), 404 );
		}
		wp_redirect( $package, 302 );
		exit;
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
			'site_name'      => get_bloginfo( 'name' ),
		);
		$body    = wp_json_encode( $payload );
		$headers = array( 'Content-Type' => 'application/json' );
		$secret  = (string) get_option( self::OPTION_INSTALL_SECRET, '' );
		if ( $secret ) {
			$ts    = (string) time();
			$nonce = wp_generate_uuid4();
			$sig   = hash_hmac( 'sha256', $ts . '.' . $nonce . '.' . hash( 'sha256', $body ), $secret );
			$headers['X-PCKZ-Timestamp'] = $ts;
			$headers['X-PCKZ-Nonce']     = $nonce;
			$headers['X-PCKZ-Signature'] = $sig;
		}

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
		);
		$resp = wp_remote_post(
			$master . '/wp-json/pckzce-license/v1/client/update-meta',
			array(
				'timeout' => 10,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $payload ),
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
	private function server_validate_client_payload( $payload ) {
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
		if ( $install ) {
			if ( 'active' !== (string) $install['status'] ) {
				return new WP_Error( 'installation_blocked', __( 'Installation is blocked.', 'pckz-canonical-engine' ) );
			}
			$wpdb->update(
				$installs_table,
				array(
					'plugin_version' => $plugin_version,
					'last_check_in'  => $now,
					'last_ip'        => self::request_ip(),
					'updated_at'     => $now,
					'last_error'     => '',
				),
				array( 'id' => (int) $install['id'] ),
				array( '%s', '%s', '%s', '%s', '%s' ),
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
					'last_check_in'  => $now,
					'last_ip'        => self::request_ip(),
					'install_secret' => $secret,
					'created_at'     => $now,
					'updated_at'     => $now,
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
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
		$settings = PCKZ_Settings::get_all();
		return ! empty( $settings['licensing_master_mode'] );
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
		$body = base64_encode( wp_json_encode( $payload ) );
		$sig  = hash_hmac( 'sha256', $body, wp_salt( 'auth' ) );
		return $body . '.' . $sig;
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
