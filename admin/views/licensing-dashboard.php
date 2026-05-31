<?php
/**
 * Licensing dashboard view.
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

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
						? __( 'Central license server operations for activations, updates, customer packages, telemetry, and protected export controls.', 'pckz-canonical-engine' )
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

			<article class="pckz-license-card">
				<h2><?php esc_html_e( 'Update Status', 'pckz-canonical-engine' ); ?></h2>
				<p class="pckz-license-value">
					<span class="pckz-license-badge <?php echo esc_attr( $badge_class( $client_summary['update_status'] ?? 'unknown' ) ); ?>">
						<?php echo esc_html( $client_summary['update_label'] ?? __( 'Unknown', 'pckz-canonical-engine' ) ); ?>
					</span>
				</p>
			</article>
		</div>
	<?php else : ?>
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
						<label><input type="checkbox" name="perm_updates" value="1" checked> <?php esc_html_e( 'Allow protected updates', 'pckz-canonical-engine' ); ?></label>
					</p>
					<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Create License', 'pckz-canonical-engine' ); ?></button></p>
				</form>
			</section>

			<section class="pckz-license-card">
				<h2><?php esc_html_e( 'Release & Update Metadata', 'pckz-canonical-engine' ); ?></h2>
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
						<label for="pckz-release-requires"><strong><?php esc_html_e( 'Requires WP', 'pckz-canonical-engine' ); ?></strong></label>
						<input id="pckz-release-requires" type="text" class="small-text" name="requires" value="<?php echo esc_attr( $release_meta['requires'] ?? '6.0' ); ?>">
						<label for="pckz-release-php"><strong><?php esc_html_e( 'Requires PHP', 'pckz-canonical-engine' ); ?></strong></label>
						<input id="pckz-release-php" type="text" class="small-text" name="requires_php" value="<?php echo esc_attr( $release_meta['requires_php'] ?? '7.4' ); ?>">
					</p>
					<p>
						<label for="pckz-release-tested"><strong><?php esc_html_e( 'Tested up to', 'pckz-canonical-engine' ); ?></strong></label>
						<input id="pckz-release-tested" type="text" class="small-text" name="tested" value="<?php echo esc_attr( $release_meta['tested'] ?? '' ); ?>">
					</p>
					<p>
						<label for="pckz-release-min-build"><strong><?php esc_html_e( 'Minimum client build', 'pckz-canonical-engine' ); ?></strong></label>
						<input id="pckz-release-min-build" type="text" class="regular-text" name="min_client_build" value="<?php echo esc_attr( $release_meta['min_client_build'] ?? '' ); ?>">
					</p>
					<p>
						<label for="pckz-release-changelog"><strong><?php esc_html_e( 'Changelog', 'pckz-canonical-engine' ); ?></strong></label>
						<textarea id="pckz-release-changelog" name="changelog" rows="4" class="large-text"><?php echo esc_textarea( $release_meta['changelog'] ?? '' ); ?></textarea>
					</p>
					<p>
						<label><input type="checkbox" name="allow_remote_export" value="1" <?php checked( ! empty( $release_meta['allow_remote_export'] ) ); ?>> <?php esc_html_e( 'Allow remote export generation on master', 'pckz-canonical-engine' ); ?></label>
					</p>
					<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save Release Metadata', 'pckz-canonical-engine' ); ?></button></p>
				</form>
			</section>

			<section class="pckz-license-card">
				<h2><?php esc_html_e( 'Customer Packages', 'pckz-canonical-engine' ); ?></h2>
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
