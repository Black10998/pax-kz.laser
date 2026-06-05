<?php
/**
 * Licensing dashboard view.
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

/*
 * Shared formatter closures.
 *
 * These MUST be defined before any partial include (fleet, management, …) is
 * pulled in below. PHP includes inherit the calling scope, so partials can
 * call $badge_class(), $status_label(), and $format_datetime() directly.
 *
 * Historic bug (v2.28.0): $format_datetime used to live inside the
 * licensing-master-management.php partial, which is included AFTER the fleet
 * partial. Under PHP 8+ that turned the fleet rendering into a fatal
 * "Value of type null is not callable" and produced a blank Master Control
 * page. Defining the closure here keeps all partials safe.
 */
$badge_class = static function ( $status ) {
	$status = sanitize_key( (string) $status );
	$map    = array(
		'active'      => 'is-success',
		'authorized'  => 'is-success',
		'ok'          => 'is-success',
		'available'   => 'is-warning',
		'blocked'     => 'is-danger',
		'disabled'    => 'is-danger',
		'revoked'     => 'is-danger',
		'expired'     => 'is-danger',
		'denied'      => 'is-danger',
		'network_error' => 'is-warning',
		'unconfigured'  => 'is-muted',
	);
	return isset( $map[ $status ] ) ? $map[ $status ] : 'is-muted';
};

$status_label = static function ( $status ) {
	$status = sanitize_key( (string) $status );
	$map    = array(
		'active'       => __( 'Active', 'pckz-canonical-engine' ),
		'authorized'   => __( 'Authorized', 'pckz-canonical-engine' ),
		'blocked'      => __( 'Blocked', 'pckz-canonical-engine' ),
		'disabled'     => __( 'Disabled', 'pckz-canonical-engine' ),
		'revoked'      => __( 'Revoked', 'pckz-canonical-engine' ),
		'expired'      => __( 'Expired', 'pckz-canonical-engine' ),
		'denied'       => __( 'Denied', 'pckz-canonical-engine' ),
		'suspended'    => __( 'Suspended', 'pckz-canonical-engine' ),
		'network_error'=> __( 'Network Error', 'pckz-canonical-engine' ),
		'unconfigured' => __( 'Not Configured', 'pckz-canonical-engine' ),
		'ok'           => __( 'Up to Date', 'pckz-canonical-engine' ),
		'available'    => __( 'Update Available', 'pckz-canonical-engine' ),
	);
	return isset( $map[ $status ] ) ? $map[ $status ] : ucwords( str_replace( '_', ' ', $status ) );
};

$format_datetime = static function ( $raw ) {
	$raw = trim( (string) $raw );
	if ( '' === $raw ) {
		return '—';
	}
	$ts = strtotime( $raw );
	if ( ! $ts ) {
		return $raw;
	}
	return gmdate( 'Y-m-d H:i:s', $ts ) . ' UTC';
};
?>

<div class="wrap pckz-admin-wrap pckz-license-dashboard <?php echo $master_mode ? 'pckz-license-dashboard--master' : 'pckz-license-dashboard--client'; ?>">
	<div class="pckz-license-hero">
		<div>
			<h1>
				<?php echo esc_html( $master_mode ? __( 'Master Control', 'pckz-canonical-engine' ) : __( 'License Dashboard', 'pckz-canonical-engine' ) ); ?>
			</h1>
			<p>
				<?php echo esc_html(
					$master_mode
						? __( 'Central hub for licenses, customer fleet health, protected updates, and delivery packages.', 'pckz-canonical-engine' )
						: __( 'This installation is restricted to product-level license status and update health only.', 'pckz-canonical-engine' )
				); ?>
			</p>
		</div>
		<span class="pckz-license-badge <?php echo esc_attr( $master_mode ? 'is-success' : 'is-muted' ); ?>">
			<?php echo esc_html( $master_mode ? __( 'Master Mode', 'pckz-canonical-engine' ) : __( 'Client Mode', 'pckz-canonical-engine' ) ); ?>
		</span>
	</div>

	<?php if ( ! empty( $generated ) ) : ?>
		<div class="notice notice-success">
			<p>
				<strong><?php esc_html_e( 'New license key created:', 'pckz-canonical-engine' ); ?></strong>
				<code><?php echo esc_html( $generated ); ?></code>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $package_notice ) && is_array( $package_notice ) ) : ?>
		<div class="notice notice-success">
			<p>
				<strong><?php esc_html_e( 'Customer package generated.', 'pckz-canonical-engine' ); ?></strong>
				<?php if ( ! empty( $package_notice['url'] ) ) : ?>
					<a href="<?php echo esc_url( $package_notice['url'] ); ?>" target="_blank" rel="noopener noreferrer">
						<?php echo esc_html( $package_notice['filename'] ?? __( 'Download package', 'pckz-canonical-engine' ) ); ?>
					</a>
				<?php endif; ?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $package_error ) ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( $package_error ); ?></p></div>
	<?php endif; ?>

	<?php if ( $master_mode && ! empty( $admin_notice ) && is_array( $admin_notice ) && ! empty( $admin_notice['message'] ) ) : ?>
		<div class="notice <?php echo esc_attr( $admin_notice['type'] ?? 'notice-success' ); ?> is-dismissible">
			<p><?php echo esc_html( (string) $admin_notice['message'] ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( ! $master_mode ) : ?>
		<?php if ( ! empty( $client_notice ) && is_array( $client_notice ) && ! empty( $client_notice['message'] ) ) : ?>
			<div class="notice <?php echo esc_attr( $client_notice['type'] ?? 'notice-success' ); ?> is-dismissible">
				<p><?php echo esc_html( (string) $client_notice['message'] ); ?></p>
			</div>
		<?php endif; ?>

		<?php
		$client_license_key = trim( (string) ( $client_summary['license_key_full'] ?? '' ) );
		$license_key_error  = trim( (string) ( $client_summary['license_key_error'] ?? '' ) );
		$license_key_status = sanitize_key( (string) ( $client_summary['license_status'] ?? 'unknown' ) );
		?>
		<article class="pckz-license-card pckz-license-card--license-key-panel">
			<div class="pckz-license-key-panel__header">
				<div>
					<h2><?php esc_html_e( 'License Key', 'pckz-canonical-engine' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Your assigned license for this website. Status is checked automatically with the master server.', 'pckz-canonical-engine' ); ?></p>
				</div>
				<span class="pckz-license-badge pckz-license-key-status <?php echo esc_attr( $badge_class( $license_key_status ) ); ?>">
					<?php echo esc_html( (string) ( $client_summary['license_status_label'] ?? $status_label( $license_key_status ) ) ); ?>
				</span>
			</div>
			<?php if ( ! empty( $client_summary['license_reason'] ) ) : ?>
				<p class="pckz-license-key-panel__reason"><?php echo esc_html( $client_summary['license_reason'] ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $client_license_key ) : ?>
				<div
					class="pckz-license-key-panel__body"
					data-masked="<?php echo esc_attr( (string) ( $client_summary['license_key_masked'] ?? '' ) ); ?>"
					data-full="<?php echo esc_attr( $client_license_key ); ?>"
				>
					<label class="pckz-license-key-panel__label" for="pckz-client-license-key-value"><?php esc_html_e( 'Assigned key', 'pckz-canonical-engine' ); ?></label>
					<div class="pckz-license-key-field">
						<code class="pckz-license-key-value" id="pckz-client-license-key-value"><?php echo esc_html( (string) ( $client_summary['license_key_masked'] ?? '' ) ); ?></code>
						<div class="pckz-license-key-actions">
							<button type="button" class="button pckz-license-key-toggle" aria-pressed="false" aria-controls="pckz-client-license-key-value">
								<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
								<span class="pckz-license-key-toggle-label"><?php esc_html_e( 'Show', 'pckz-canonical-engine' ); ?></span>
							</button>
							<button type="button" class="button pckz-code-copy pckz-license-key-copy" data-copy="<?php echo esc_attr( $client_license_key ); ?>">
								<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
								<?php esc_html_e( 'Copy', 'pckz-canonical-engine' ); ?>
							</button>
						</div>
					</div>
				</div>
			<?php else : ?>
				<p class="pckz-license-value"><?php esc_html_e( 'License key unavailable', 'pckz-canonical-engine' ); ?></p>
				<p class="description"><?php echo esc_html( $license_key_error ? $license_key_error : __( 'No license key is stored in plugin settings.', 'pckz-canonical-engine' ) ); ?></p>
			<?php endif; ?>
		</article>

		<div class="pckz-license-client-cards">
			<article class="pckz-license-card">
				<h2><?php esc_html_e( 'Connected Server', 'pckz-canonical-engine' ); ?></h2>
				<p class="pckz-license-value"><?php echo esc_html( $client_summary['connected_server'] ?? '' ); ?></p>
			</article>

			<article class="pckz-license-card">
				<h2><?php esc_html_e( 'Domain', 'pckz-canonical-engine' ); ?></h2>
				<p class="pckz-license-value"><?php echo esc_html( $client_summary['domain'] ?? '' ); ?></p>
			</article>

			<article class="pckz-license-card">
				<h2><?php esc_html_e( 'License Type', 'pckz-canonical-engine' ); ?></h2>
				<p class="pckz-license-value"><?php echo esc_html( $client_summary['license_type'] ?? '' ); ?></p>
			</article>

			<article class="pckz-license-card">
				<h2><?php esc_html_e( 'Installed Version', 'pckz-canonical-engine' ); ?></h2>
				<p class="pckz-license-value">
					<?php echo esc_html( $client_summary['installed_version'] ?? '' ); ?>
					<?php if ( ! empty( $client_summary['installed_build'] ) ) : ?>
						<span class="description">Build: <?php echo esc_html( $client_summary['installed_build'] ); ?></span>
					<?php endif; ?>
				</p>
			</article>

			<article class="pckz-license-card">
				<h2><?php esc_html_e( 'Last Check-In', 'pckz-canonical-engine' ); ?></h2>
				<p class="pckz-license-value"><?php echo esc_html( $client_summary['last_check_in_time'] ?? '' ); ?></p>
				<p class="description"><?php echo esc_html( $client_summary['last_check_in_human'] ?? '' ); ?></p>
			</article>

			<article class="pckz-license-card pckz-license-card--update">
				<h2><?php esc_html_e( 'Update Status', 'pckz-canonical-engine' ); ?></h2>
				<p class="pckz-license-value">
					<span class="pckz-license-badge <?php echo esc_attr( $badge_class( $client_summary['update_status'] ?? 'unknown' ) ); ?>">
						<?php echo esc_html( $client_summary['update_label'] ?? __( 'Unknown', 'pckz-canonical-engine' ) ); ?>
					</span>
				</p>
				<dl class="pckz-update-summary">
					<div>
						<dt><?php esc_html_e( 'Installed version', 'pckz-canonical-engine' ); ?></dt>
						<dd><strong><?php echo esc_html( $client_summary['installed_version'] ?? '—' ); ?></strong></dd>
					</div>
					<div>
						<dt><?php esc_html_e( 'Latest available', 'pckz-canonical-engine' ); ?></dt>
						<dd>
							<strong><?php echo esc_html( ! empty( $client_summary['latest_version'] ) ? $client_summary['latest_version'] : __( 'Not checked yet', 'pckz-canonical-engine' ) ); ?></strong>
						</dd>
					</div>
				</dl>
				<?php if ( ! empty( $client_summary['update_detail'] ) ) : ?>
					<p class="description pckz-update-detail"><?php echo esc_html( $client_summary['update_detail'] ); ?></p>
				<?php endif; ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pckz-check-updates-form">
					<?php wp_nonce_field( 'pckzce_check_for_updates', 'pckzce_check_updates_nonce' ); ?>
					<input type="hidden" name="action" value="pckzce_check_for_updates">
					<button type="submit" class="button button-secondary">
						<?php esc_html_e( 'Check for updates', 'pckz-canonical-engine' ); ?>
					</button>
					<span class="description"><?php esc_html_e( 'Refreshes version information from the master server. Use Update Now below to install when an update is available.', 'pckz-canonical-engine' ); ?></span>
				</form>
				<?php if ( ! empty( $client_summary['can_update_now'] ) ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pckz-update-now-form">
						<?php wp_nonce_field( 'pckzce_run_plugin_update', 'pckzce_update_nonce' ); ?>
						<input type="hidden" name="action" value="pckzce_run_plugin_update">
						<button type="submit" class="button button-primary button-hero pckz-update-now-btn" data-pckz-confirm="<?php esc_attr_e( 'Install the latest protected update from the master server now? Your site will download and update the plugin automatically.', 'pckz-canonical-engine' ); ?>">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: version number */
									__( 'Update Now to %s', 'pckz-canonical-engine' ),
									$client_summary['latest_version'] ?? ''
								)
							);
							?>
						</button>
					</form>
					<p class="description pckz-update-now-note">
						<?php esc_html_e( 'You can also update from Plugins → Installed Plugins when an update is available.', 'pckz-canonical-engine' ); ?>
					</p>
				<?php endif; ?>
			</article>
		</div>
	<?php else : ?>
		<div class="pckz-license-shell pckz-mc-shell">
			<?php include PCKZCE_PLUGIN_DIR . 'admin/views/partials/licensing-master-nav.php'; ?>
			<div class="pckz-license-panels pckz-mc-panels">
				<div id="pckz-master-section-overview">
					<?php include PCKZCE_PLUGIN_DIR . 'admin/views/partials/licensing-master-overview.php'; ?>
				</div>
				<div id="pckz-master-section-fleet">
					<?php include PCKZCE_PLUGIN_DIR . 'admin/views/partials/licensing-master-fleet.php'; ?>
				</div>
				<div id="pckz-master-section-releases">
					<?php include PCKZCE_PLUGIN_DIR . 'admin/views/partials/licensing-master-releases.php'; ?>
				</div>
				<div id="pckz-master-section-licenses">
					<?php include PCKZCE_PLUGIN_DIR . 'admin/views/partials/licensing-master-licenses.php'; ?>
				</div>
				<div id="pckz-master-section-records">
					<?php include PCKZCE_PLUGIN_DIR . 'admin/views/partials/licensing-master-records.php'; ?>
				</div>
			</div>
		</div>
	<?php endif; ?>
</div>
