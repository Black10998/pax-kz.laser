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
				<?php echo esc_html( $master_mode ? __( 'Master Control Center', 'pckz-canonical-engine' ) : __( 'License Dashboard', 'pckz-canonical-engine' ) ); ?>
			</h1>
			<p>
				<?php
				echo esc_html(
					$master_mode
						? __( 'Manage licenses, publish updates, and deliver customer packages from one place — designed for day-to-day administration without technical setup.', 'pckz-canonical-engine' )
						: __( 'This installation is restricted to product-level license status and update health only.', 'pckz-canonical-engine' )
				);
				?>
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

	<?php if ( ! $master_mode ) : ?>
		<?php if ( ! empty( $client_notice ) && is_array( $client_notice ) && ! empty( $client_notice['message'] ) ) : ?>
			<div class="notice <?php echo esc_attr( $client_notice['type'] ?? 'notice-success' ); ?> is-dismissible">
				<p><?php echo esc_html( (string) $client_notice['message'] ); ?></p>
			</div>
		<?php endif; ?>
		<div class="pckz-license-client-cards">
			<article class="pckz-license-card">
				<h2><?php esc_html_e( 'License Status', 'pckz-canonical-engine' ); ?></h2>
				<p class="pckz-license-value">
					<span class="pckz-license-badge <?php echo esc_attr( $badge_class( $client_summary['license_status'] ?? 'unknown' ) ); ?>">
						<?php echo esc_html( $status_label( $client_summary['license_status'] ?? 'unknown' ) ); ?>
					</span>
				</p>
				<?php if ( ! empty( $client_summary['license_reason'] ) ) : ?>
					<p class="description"><?php echo esc_html( $client_summary['license_reason'] ); ?></p>
				<?php endif; ?>
			</article>

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
		<?php include PCKZCE_PLUGIN_DIR . 'admin/views/partials/licensing-master-fleet.php'; ?>

		<div class="pckz-license-stats">
			<article class="pckz-license-stat">
				<h3><?php esc_html_e( 'Licenses', 'pckz-canonical-engine' ); ?></h3>
				<p><?php echo esc_html( number_format_i18n( (int) $stats['licenses_total'] ) ); ?></p>
				<small><?php echo esc_html( sprintf( __( '%d active', 'pckz-canonical-engine' ), (int) $stats['licenses_active'] ) ); ?></small>
			</article>
			<article class="pckz-license-stat">
				<h3><?php esc_html_e( 'Installations', 'pckz-canonical-engine' ); ?></h3>
				<p><?php echo esc_html( number_format_i18n( (int) $stats['installations_total'] ) ); ?></p>
				<small><?php echo esc_html( sprintf( __( '%d active / %d blocked', 'pckz-canonical-engine' ), (int) $stats['installations_active'], (int) $stats['installations_blocked'] ) ); ?></small>
			</article>
			<article class="pckz-license-stat">
				<h3><?php esc_html_e( 'Protected Downloads', 'pckz-canonical-engine' ); ?></h3>
				<p><?php echo esc_html( number_format_i18n( (int) $stats['downloads_total'] ) ); ?></p>
				<small><?php echo esc_html( sprintf( __( '%d in last 24h', 'pckz-canonical-engine' ), (int) $stats['downloads_24h'] ) ); ?></small>
			</article>
		</div>

		<div class="pckz-license-grid">
			<section class="pckz-license-card">
				<h2><?php esc_html_e( 'Create License', 'pckz-canonical-engine' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Issue a new license key for a customer site.', 'pckz-canonical-engine' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'pckzce_create_license', 'pckzce_license_nonce' ); ?>
					<input type="hidden" name="action" value="pckzce_create_license">
					<p>
						<label for="pckz-license-label"><strong><?php esc_html_e( 'Customer label', 'pckz-canonical-engine' ); ?></strong></label>
						<input id="pckz-license-label" type="text" class="regular-text" name="label" autocomplete="off">
					</p>
					<p>
						<label for="pckz-license-domains"><strong><?php esc_html_e( 'Allowed domains', 'pckz-canonical-engine' ); ?></strong></label>
						<textarea id="pckz-license-domains" name="domains" rows="4" class="large-text" placeholder="client.example.com"></textarea>
					</p>
					<p>
						<label for="pckz-license-max"><strong><?php esc_html_e( 'Max installations', 'pckz-canonical-engine' ); ?></strong></label>
						<input id="pckz-license-max" type="number" class="small-text" min="1" name="max_installs" value="1">
					</p>
					<p>
						<label><input type="checkbox" name="perm_export" value="1" checked> <?php esc_html_e( 'Allow export authorization', 'pckz-canonical-engine' ); ?></label><br>
						<label><input type="checkbox" name="perm_updates" value="1" checked> <?php esc_html_e( 'Allow protected updates', 'pckz-canonical-engine' ); ?></label><br>
						<label><input type="checkbox" name="perm_asset_sync" value="1" checked> <?php esc_html_e( 'Allow asset synchronization', 'pckz-canonical-engine' ); ?></label>
					</p>
					<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Create License', 'pckz-canonical-engine' ); ?></button></p>
				</form>
			</section>

			<section class="pckz-license-card pckz-license-card--release">
				<h2><?php esc_html_e( 'Release Management', 'pckz-canonical-engine' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Upload a protected update package, publish it, and set the version clients receive — no GitHub or manual file editing required.', 'pckz-canonical-engine' ); ?></p>

				<div class="pckz-release-current">
					<span class="pckz-release-current__label"><?php esc_html_e( 'Current published release', 'pckz-canonical-engine' ); ?></span>
					<strong class="pckz-release-current__version"><?php echo esc_html( ! empty( $release_meta['version'] ) ? $release_meta['version'] : __( 'None yet', 'pckz-canonical-engine' ) ); ?></strong>
					<?php if ( ! empty( $release_meta['package_url'] ) ) : ?>
						<code class="pckz-code-truncate"><?php echo esc_html( $release_meta['package_url'] ); ?></code>
					<?php endif; ?>
				</div>

				<div class="pckz-release-steps">
					<div class="pckz-release-step">
						<h3><?php esc_html_e( 'Step 1 — Upload protected package', 'pckz-canonical-engine' ); ?></h3>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
							<?php wp_nonce_field( 'pckzce_upload_protected_release', 'pckzce_upload_release_nonce' ); ?>
							<input type="hidden" name="action" value="pckzce_upload_protected_release">
							<p>
								<label for="pckz-protected-package"><strong><?php esc_html_e( 'Protected ZIP file', 'pckz-canonical-engine' ); ?></strong></label>
								<input id="pckz-protected-package" type="file" name="protected_package" accept=".zip,application/zip" required>
							</p>
							<p class="description"><?php esc_html_e( 'Use the build output named pckz-canonical-engine-VERSION-protected.zip.', 'pckz-canonical-engine' ); ?></p>
							<p>
								<label><input type="checkbox" name="publish_after_upload" value="1"> <?php esc_html_e( 'Publish immediately after upload (set as latest version)', 'pckz-canonical-engine' ); ?></label>
							</p>
							<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Upload Package', 'pckz-canonical-engine' ); ?></button></p>
						</form>
					</div>

					<div class="pckz-release-step">
						<h3><?php esc_html_e( 'Step 2 — Publish release & set latest version', 'pckz-canonical-engine' ); ?></h3>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php wp_nonce_field( 'pckzce_publish_release', 'pckzce_publish_release_nonce' ); ?>
							<input type="hidden" name="action" value="pckzce_publish_release">
							<p>
								<label for="pckz-publish-release"><strong><?php esc_html_e( 'Package to publish', 'pckz-canonical-engine' ); ?></strong></label>
								<select id="pckz-publish-release" name="release_filename" required>
									<option value=""><?php esc_html_e( 'Select uploaded package', 'pckz-canonical-engine' ); ?></option>
									<?php foreach ( $protected_releases as $release_row ) : ?>
										<option value="<?php echo esc_attr( $release_row['filename'] ?? '' ); ?>" <?php selected( (string) ( $release_meta['version'] ?? '' ), (string) ( $release_row['version'] ?? '' ) ); ?>>
											<?php
											echo esc_html(
												sprintf(
													/* translators: 1: version, 2: filename, 3: file size */
													__( 'Version %1$s — %2$s (%3$s)', 'pckz-canonical-engine' ),
													$release_row['version'] ?? '',
													$release_row['filename'] ?? '',
													size_format( (int) ( $release_row['size'] ?? 0 ), 1 )
												)
											);
											?>
										</option>
									<?php endforeach; ?>
								</select>
							</p>
							<p>
								<label for="pckz-publish-changelog"><strong><?php esc_html_e( 'Release notes (optional)', 'pckz-canonical-engine' ); ?></strong></label>
								<textarea id="pckz-publish-changelog" name="changelog" rows="3" class="large-text"><?php echo esc_textarea( $release_meta['changelog'] ?? '' ); ?></textarea>
							</p>
							<div class="pckz-release-meta-row">
								<p>
									<label for="pckz-publish-requires"><strong><?php esc_html_e( 'Requires WordPress', 'pckz-canonical-engine' ); ?></strong></label>
									<input id="pckz-publish-requires" type="text" class="small-text" name="requires" value="<?php echo esc_attr( $release_meta['requires'] ?? '6.0' ); ?>">
								</p>
								<p>
									<label for="pckz-publish-php"><strong><?php esc_html_e( 'Requires PHP', 'pckz-canonical-engine' ); ?></strong></label>
									<input id="pckz-publish-php" type="text" class="small-text" name="requires_php" value="<?php echo esc_attr( $release_meta['requires_php'] ?? '7.4' ); ?>">
								</p>
								<p>
									<label for="pckz-publish-tested"><strong><?php esc_html_e( 'Tested up to', 'pckz-canonical-engine' ); ?></strong></label>
									<input id="pckz-publish-tested" type="text" class="small-text" name="tested" value="<?php echo esc_attr( $release_meta['tested'] ?? '' ); ?>">
								</p>
							</div>
							<p>
								<label for="pckz-publish-min-build"><strong><?php esc_html_e( 'Minimum client build (optional)', 'pckz-canonical-engine' ); ?></strong></label>
								<input id="pckz-publish-min-build" type="text" class="regular-text" name="min_client_build" value="<?php echo esc_attr( $release_meta['min_client_build'] ?? '' ); ?>">
							</p>
							<p>
								<label><input type="checkbox" name="allow_remote_export" value="1" <?php checked( ! empty( $release_meta['allow_remote_export'] ) ); ?>> <?php esc_html_e( 'Allow remote export generation on master', 'pckz-canonical-engine' ); ?></label>
							</p>
							<p><button type="submit" class="button button-primary" data-pckz-confirm="<?php esc_attr_e( 'Publish this package as the latest release for all licensed client sites?', 'pckz-canonical-engine' ); ?>"><?php esc_html_e( 'Publish Release', 'pckz-canonical-engine' ); ?></button></p>
						</form>
					</div>
				</div>

				<?php if ( ! empty( $protected_releases ) ) : ?>
					<div class="pckz-release-inventory">
						<h3><?php esc_html_e( 'Uploaded packages on this server', 'pckz-canonical-engine' ); ?></h3>
						<ul>
							<?php foreach ( $protected_releases as $release_row ) : ?>
								<li>
									<strong><?php echo esc_html( $release_row['version'] ?? '' ); ?></strong>
									<code><?php echo esc_html( $release_row['filename'] ?? '' ); ?></code>
									<span class="description"><?php echo esc_html( size_format( (int) ( $release_row['size'] ?? 0 ), 1 ) ); ?></span>
									<?php if ( (string) ( $release_meta['version'] ?? '' ) === (string) ( $release_row['version'] ?? '' ) ) : ?>
										<span class="pckz-license-badge is-success"><?php esc_html_e( 'Published', 'pckz-canonical-engine' ); ?></span>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<details class="pckz-release-advanced">
					<summary><?php esc_html_e( 'Advanced: edit release metadata manually', 'pckz-canonical-engine' ); ?></summary>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'pckzce_save_release_meta', 'pckzce_release_nonce' ); ?>
						<input type="hidden" name="action" value="pckzce_save_release_meta">
						<p>
							<label for="pckz-release-version"><strong><?php esc_html_e( 'Latest version', 'pckz-canonical-engine' ); ?></strong></label>
							<input id="pckz-release-version" type="text" class="regular-text" name="version" value="<?php echo esc_attr( $release_meta['version'] ?? '' ); ?>">
						</p>
						<p>
							<label for="pckz-release-package"><strong><?php esc_html_e( 'Protected package URL', 'pckz-canonical-engine' ); ?></strong></label>
							<input id="pckz-release-package" type="url" class="large-text" name="package_url" value="<?php echo esc_attr( $release_meta['package_url'] ?? '' ); ?>">
						</p>
						<p>
							<label for="pckz-release-changelog"><strong><?php esc_html_e( 'Changelog', 'pckz-canonical-engine' ); ?></strong></label>
							<textarea id="pckz-release-changelog" name="changelog" rows="3" class="large-text"><?php echo esc_textarea( $release_meta['changelog'] ?? '' ); ?></textarea>
						</p>
						<p>
							<label for="pckz-release-severity"><strong><?php esc_html_e( 'Update severity', 'pckz-canonical-engine' ); ?></strong></label>
							<select id="pckz-release-severity" name="update_severity">
								<option value="" <?php selected( $release_meta['update_severity'] ?? '', '' ); ?>><?php esc_html_e( 'Normal', 'pckz-canonical-engine' ); ?></option>
								<option value="critical" <?php selected( $release_meta['update_severity'] ?? '', 'critical' ); ?>><?php esc_html_e( 'Critical / security', 'pckz-canonical-engine' ); ?></option>
							</select>
						</p>
						<?php if ( class_exists( 'PCKZ_Asset_Sync' ) ) : ?>
							<?php $asset_manifest = PCKZ_Asset_Sync::build_manifest( false ); ?>
							<p class="description">
								<?php
								printf(
									/* translators: 1: revision hash, 2: asset count */
									esc_html__( 'Asset sync catalog: revision %1$s (%2$d distributable files).', 'pckz-canonical-engine' ),
									esc_html( $asset_manifest['revision'] ?? '—' ),
									count( $asset_manifest['assets'] ?? array() )
								);
								?>
							</p>
						<?php endif; ?>
						<p><button type="submit" class="button"><?php esc_html_e( 'Save Metadata', 'pckz-canonical-engine' ); ?></button></p>
					</form>
				</details>
			</section>

			<section class="pckz-license-card">
				<h2><?php esc_html_e( 'Customer Packages', 'pckz-canonical-engine' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Build a ready-to-install ZIP for a specific customer license.', 'pckz-canonical-engine' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'pckzce_generate_customer_package', 'pckzce_package_nonce' ); ?>
					<input type="hidden" name="action" value="pckzce_generate_customer_package">
					<p>
						<label for="pckz-package-license"><strong><?php esc_html_e( 'License', 'pckz-canonical-engine' ); ?></strong></label>
						<select id="pckz-package-license" name="license_id" required>
							<option value=""><?php esc_html_e( 'Select license', 'pckz-canonical-engine' ); ?></option>
							<?php foreach ( $licenses as $license_row ) : ?>
								<option value="<?php echo esc_attr( (string) $license_row['id'] ); ?>">
									<?php echo esc_html( sprintf( '#%d %s (%s)', (int) $license_row['id'], $license_row['label'] ? $license_row['label'] : __( 'No label', 'pckz-canonical-engine' ), $status_label( $license_row['status'] ) ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</p>
					<p>
						<label for="pckz-package-domains"><strong><?php esc_html_e( 'Allowed domains', 'pckz-canonical-engine' ); ?></strong></label>
						<textarea id="pckz-package-domains" name="domains" rows="3" class="large-text" placeholder="client.example.com"><?php echo isset( $_GET['pckz_install_s'] ) ? esc_textarea( wp_unslash( $_GET['pckz_install_s'] ) ) : ''; ?></textarea>
					</p>
					<p>
						<input type="hidden" name="perm_export" value="0">
						<label><input type="checkbox" name="perm_export" value="1" checked> <?php esc_html_e( 'Enable export permission', 'pckz-canonical-engine' ); ?></label><br>
						<input type="hidden" name="perm_updates" value="0">
						<label><input type="checkbox" name="perm_updates" value="1" checked> <?php esc_html_e( 'Enable update permission', 'pckz-canonical-engine' ); ?></label><br>
						<input type="hidden" name="export_authorize" value="0">
						<label><input type="checkbox" name="export_authorize" value="1"> <?php esc_html_e( 'Require export authorization from master', 'pckz-canonical-engine' ); ?></label><br>
						<input type="hidden" name="export_remote_mode" value="0">
						<label><input type="checkbox" name="export_remote_mode" value="1"> <?php esc_html_e( 'Enable remote export mode', 'pckz-canonical-engine' ); ?></label><br>
						<input type="hidden" name="export_remote_strict" value="0">
						<label><input type="checkbox" name="export_remote_strict" value="1"> <?php esc_html_e( 'Strict remote export (no fallback)', 'pckz-canonical-engine' ); ?></label>
					</p>
					<p>
						<label for="pckz-package-grace"><strong><?php esc_html_e( 'Grace period (minutes)', 'pckz-canonical-engine' ); ?></strong></label>
						<input id="pckz-package-grace" type="number" class="small-text" min="5" max="1440" name="grace_minutes" value="120">
					</p>
					<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Generate Client Package', 'pckz-canonical-engine' ); ?></button></p>
				</form>
			</section>
		</div>

		<?php include PCKZCE_PLUGIN_DIR . 'admin/views/partials/licensing-master-management.php'; ?>
	<?php endif; ?>
</div>
