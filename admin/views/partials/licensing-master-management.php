<?php
/**
 * Master Control — license management, installations, downloads (partial).
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

$filter_license_id = isset( $_GET['pckz_license_id'] ) ? absint( $_GET['pckz_license_id'] ) : 0;
$filter_search     = isset( $_GET['pckz_install_s'] ) ? sanitize_text_field( wp_unslash( $_GET['pckz_install_s'] ) ) : '';
$filter_status     = isset( $_GET['pckz_install_status'] ) ? sanitize_key( wp_unslash( $_GET['pckz_install_status'] ) ) : '';

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

$status_help = array(
	'active'   => __( 'License or installation is valid and can check in, export, and receive updates (when permitted).', 'pckz-canonical-engine' ),
	'blocked'  => __( 'Installation is blocked on the master. The client site cannot authenticate until unblocked or the record is removed.', 'pckz-canonical-engine' ),
	'disabled' => __( 'License is disabled by an administrator. All linked installations are blocked.', 'pckz-canonical-engine' ),
	'revoked'  => __( 'License was revoked permanently for this customer. All linked installations are blocked.', 'pckz-canonical-engine' ),
	'denied'   => __( 'Client check-in was rejected (invalid key, domain mismatch, limit reached, or security failure).', 'pckz-canonical-engine' ),
);
?>

<?php if ( ! empty( $admin_notice ) && is_array( $admin_notice ) && ! empty( $admin_notice['message'] ) ) : ?>
	<div class="notice <?php echo esc_attr( $admin_notice['type'] ?? 'notice-success' ); ?> is-dismissible">
		<p><?php echo esc_html( (string) $admin_notice['message'] ); ?></p>
	</div>
<?php endif; ?>

<section class="pckz-license-card pckz-license-card--full pckz-license-help">
	<h2><?php esc_html_e( 'Status Guide', 'pckz-canonical-engine' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Use this reference when reviewing licenses and installation records.', 'pckz-canonical-engine' ); ?></p>
	<div class="pckz-license-help-grid">
		<?php foreach ( $status_help as $help_key => $help_text ) : ?>
			<div class="pckz-license-help-item">
				<span class="pckz-license-badge <?php echo esc_attr( $badge_class( $help_key ) ); ?>"><?php echo esc_html( $status_label( $help_key ) ); ?></span>
				<p><?php echo esc_html( $help_text ); ?></p>
			</div>
		<?php endforeach; ?>
	</div>
	<p class="pckz-license-tip">
		<strong><?php esc_html_e( 'Reinstall tip:', 'pckz-canonical-engine' ); ?></strong>
		<?php esc_html_e( 'If a customer reinstalls WordPress or the plugin and hits “Maximum installations reached”, use Clear Installations on their license. This removes old UUID records so the same domain can register again without creating a new license key.', 'pckz-canonical-engine' ); ?>
	</p>
</section>

<section class="pckz-license-card pckz-license-card--full">
	<h2><?php esc_html_e( 'License Management', 'pckz-canonical-engine' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Manage customer licenses, installation limits, and recovery actions from one place.', 'pckz-canonical-engine' ); ?></p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="pckz-bulk-license-form" class="pckz-license-bulk-bar">
		<?php wp_nonce_field( 'pckzce_bulk_licenses', 'pckzce_bulk_licenses_nonce' ); ?>
		<input type="hidden" name="action" value="pckzce_bulk_licenses">
		<input type="hidden" name="redirect_search" value="<?php echo esc_attr( $filter_search ); ?>">
		<input type="hidden" name="redirect_status" value="<?php echo esc_attr( $filter_status ); ?>">
		<input type="hidden" name="redirect_license_id" value="<?php echo esc_attr( (string) $filter_license_id ); ?>">
		<label class="screen-reader-text" for="pckz-bulk-license-action"><?php esc_html_e( 'Bulk license action', 'pckz-canonical-engine' ); ?></label>
		<select id="pckz-bulk-license-action" name="bulk_action">
			<option value=""><?php esc_html_e( 'Bulk actions', 'pckz-canonical-engine' ); ?></option>
			<option value="activate"><?php esc_html_e( 'Set Active', 'pckz-canonical-engine' ); ?></option>
			<option value="reset"><?php esc_html_e( 'Reset License (unblock installs)', 'pckz-canonical-engine' ); ?></option>
			<option value="clear_installs"><?php esc_html_e( 'Clear Installations (reset counter)', 'pckz-canonical-engine' ); ?></option>
			<option value="disable"><?php esc_html_e( 'Disable', 'pckz-canonical-engine' ); ?></option>
			<option value="revoke"><?php esc_html_e( 'Revoke', 'pckz-canonical-engine' ); ?></option>
			<option value="delete"><?php esc_html_e( 'Delete permanently', 'pckz-canonical-engine' ); ?></option>
		</select>
		<button type="submit" class="button" data-pckz-confirm="<?php esc_attr_e( 'Apply the selected bulk action to all checked licenses?', 'pckz-canonical-engine' ); ?>"><?php esc_html_e( 'Apply', 'pckz-canonical-engine' ); ?></button>
	</form>

	<div class="pckz-license-table-wrap">
		<table class="widefat striped pckz-license-table">
			<thead>
				<tr>
					<th class="check-column"><input type="checkbox" data-pckz-select-all="license"></th>
					<th><?php esc_html_e( 'ID', 'pckz-canonical-engine' ); ?></th>
					<th><?php esc_html_e( 'Customer', 'pckz-canonical-engine' ); ?></th>
					<th><?php esc_html_e( 'License Key', 'pckz-canonical-engine' ); ?></th>
					<th><?php esc_html_e( 'Status', 'pckz-canonical-engine' ); ?></th>
					<th><?php esc_html_e( 'Active Installations', 'pckz-canonical-engine' ); ?></th>
					<th><?php esc_html_e( 'Allowed Domains', 'pckz-canonical-engine' ); ?></th>
					<th><?php esc_html_e( 'Permissions', 'pckz-canonical-engine' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'pckz-canonical-engine' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $licenses ) ) : ?>
					<tr><td colspan="9"><?php esc_html_e( 'No licenses found.', 'pckz-canonical-engine' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $licenses as $license_row ) : ?>
						<?php
						$license_id      = (int) ( $license_row['id'] ?? 0 );
						$license_domains = json_decode( (string) ( $license_row['domains'] ?? '[]' ), true );
						$license_domains = is_array( $license_domains ) ? $license_domains : array();
						$license_perms   = json_decode( (string) ( $license_row['permissions'] ?? '{}' ), true );
						$license_perms   = is_array( $license_perms ) ? $license_perms : array();
						$install_stats   = $license_install_stats[ $license_id ] ?? array( 'active' => 0, 'blocked' => 0, 'total' => 0, 'max' => 1 );
						$active_count    = (int) ( $install_stats['active'] ?? 0 );
						$max_count       = (int) ( $install_stats['max'] ?? 1 );
						$at_limit        = $active_count >= $max_count;
						?>
						<tr class="<?php echo $at_limit ? 'pckz-row-warning' : ''; ?>">
							<th scope="row" class="check-column">
								<input type="checkbox" name="license_ids[]" value="<?php echo esc_attr( (string) $license_id ); ?>" form="pckz-bulk-license-form" class="pckz-bulk-license-checkbox">
							</th>
							<td><strong>#<?php echo esc_html( (string) $license_id ); ?></strong></td>
							<td>
								<strong><?php echo esc_html( $license_row['label'] ? $license_row['label'] : __( 'No label', 'pckz-canonical-engine' ) ); ?></strong>
								<?php if ( ! empty( $license_row['expires_at'] ) ) : ?>
									<br><span class="description"><?php echo esc_html( sprintf( __( 'Expires: %s', 'pckz-canonical-engine' ), $format_datetime( $license_row['expires_at'] ) ) ); ?></span>
								<?php endif; ?>
							</td>
							<td><code class="pckz-code-copy" data-copy="<?php echo esc_attr( $license_row['license_key'] ?? '' ); ?>"><?php echo esc_html( $license_row['license_key'] ?? '' ); ?></code></td>
							<td><span class="pckz-license-badge <?php echo esc_attr( $badge_class( $license_row['status'] ?? '' ) ); ?>"><?php echo esc_html( $status_label( $license_row['status'] ?? '' ) ); ?></span></td>
							<td>
								<span class="pckz-install-counter <?php echo $at_limit ? 'is-at-limit' : ''; ?>">
									<?php
									echo esc_html(
										sprintf(
											/* translators: 1: active count, 2: max allowed */
											__( 'Active Installations: %1$d / %2$d', 'pckz-canonical-engine' ),
											$active_count,
											$max_count
										)
									);
									?>
								</span>
								<?php if ( (int) ( $install_stats['blocked'] ?? 0 ) > 0 ) : ?>
									<br><span class="description"><?php echo esc_html( sprintf( __( '%d blocked record(s)', 'pckz-canonical-engine' ), (int) $install_stats['blocked'] ) ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $license_domains ? implode( ', ', $license_domains ) : '—' ); ?></td>
							<td>
								<?php if ( ! empty( $license_perms['export'] ) ) : ?><span class="pckz-perm-chip"><?php esc_html_e( 'Export', 'pckz-canonical-engine' ); ?></span><?php endif; ?>
								<?php if ( ! empty( $license_perms['updates'] ) ) : ?><span class="pckz-perm-chip"><?php esc_html_e( 'Updates', 'pckz-canonical-engine' ); ?></span><?php endif; ?>
							</td>
							<td class="pckz-license-actions">
								<details class="pckz-license-details">
									<summary class="button button-small"><?php esc_html_e( 'Manage', 'pckz-canonical-engine' ); ?></summary>
									<div class="pckz-license-details__body">
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pckz-license-edit-form">
											<?php wp_nonce_field( 'pckzce_save_license_detail', 'pckzce_license_detail_nonce' ); ?>
											<input type="hidden" name="action" value="pckzce_save_license_detail">
											<input type="hidden" name="license_id" value="<?php echo esc_attr( (string) $license_id ); ?>">
											<p>
												<label><strong><?php esc_html_e( 'Label', 'pckz-canonical-engine' ); ?></strong></label>
												<input type="text" class="regular-text" name="label" value="<?php echo esc_attr( $license_row['label'] ?? '' ); ?>">
											</p>
											<p>
												<label><strong><?php esc_html_e( 'Allowed domains', 'pckz-canonical-engine' ); ?></strong></label>
												<textarea name="domains" rows="3" class="large-text"><?php echo esc_textarea( implode( "\n", $license_domains ) ); ?></textarea>
											</p>
											<p>
												<label><strong><?php esc_html_e( 'Max installations', 'pckz-canonical-engine' ); ?></strong></label>
												<input type="number" class="small-text" min="1" name="max_installs" value="<?php echo esc_attr( (string) $max_count ); ?>">
											</p>
											<p>
												<label><input type="checkbox" name="perm_export" value="1" <?php checked( ! empty( $license_perms['export'] ) ); ?>> <?php esc_html_e( 'Allow export', 'pckz-canonical-engine' ); ?></label><br>
												<label><input type="checkbox" name="perm_updates" value="1" <?php checked( ! empty( $license_perms['updates'] ) ); ?>> <?php esc_html_e( 'Allow updates', 'pckz-canonical-engine' ); ?></label>
											</p>
											<p>
												<label><strong><?php esc_html_e( 'Expires (optional)', 'pckz-canonical-engine' ); ?></strong></label>
												<input type="datetime-local" name="expires_at" value="<?php echo ! empty( $license_row['expires_at'] ) ? esc_attr( gmdate( 'Y-m-d\TH:i', strtotime( (string) $license_row['expires_at'] ) ) ) : ''; ?>">
											</p>
											<p><button type="submit" class="button button-primary button-small"><?php esc_html_e( 'Save License Details', 'pckz-canonical-engine' ); ?></button></p>
										</form>

										<div class="pckz-license-quick-actions">
											<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pckz-inline-form">
												<?php wp_nonce_field( 'pckzce_update_license_status', 'pckzce_license_status_nonce' ); ?>
												<input type="hidden" name="action" value="pckzce_update_license_status">
												<input type="hidden" name="license_id" value="<?php echo esc_attr( (string) $license_id ); ?>">
												<select name="new_status">
													<option value="active"><?php esc_html_e( 'Set Active', 'pckz-canonical-engine' ); ?></option>
													<option value="disabled"><?php esc_html_e( 'Disable', 'pckz-canonical-engine' ); ?></option>
													<option value="revoked"><?php esc_html_e( 'Revoke', 'pckz-canonical-engine' ); ?></option>
												</select>
												<button type="submit" class="button button-small" data-pckz-confirm="<?php esc_attr_e( 'Change the license status?', 'pckz-canonical-engine' ); ?>"><?php esc_html_e( 'Update Status', 'pckz-canonical-engine' ); ?></button>
											</form>

											<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pckz-inline-form">
												<?php wp_nonce_field( 'pckzce_reset_license', 'pckzce_reset_license_nonce' ); ?>
												<input type="hidden" name="action" value="pckzce_reset_license">
												<input type="hidden" name="license_id" value="<?php echo esc_attr( (string) $license_id ); ?>">
												<input type="hidden" name="redirect_license_id" value="<?php echo esc_attr( (string) $filter_license_id ); ?>">
												<button type="submit" class="button button-small" data-pckz-confirm="<?php esc_attr_e( 'Reset this license to Active and unblock all linked installations?', 'pckz-canonical-engine' ); ?>"><?php esc_html_e( 'Reset License', 'pckz-canonical-engine' ); ?></button>
											</form>

											<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pckz-inline-form">
												<?php wp_nonce_field( 'pckzce_clear_license_installations', 'pckzce_clear_installs_nonce' ); ?>
												<input type="hidden" name="action" value="pckzce_clear_license_installations">
												<input type="hidden" name="license_id" value="<?php echo esc_attr( (string) $license_id ); ?>">
												<input type="hidden" name="redirect_license_id" value="<?php echo esc_attr( (string) $filter_license_id ); ?>">
												<button type="submit" class="button button-small pckz-btn-danger" data-pckz-confirm="<?php esc_attr_e( 'Remove ALL installation UUID records for this license? The customer can register again on next check-in.', 'pckz-canonical-engine' ); ?>"><?php esc_html_e( 'Clear Installations', 'pckz-canonical-engine' ); ?></button>
											</form>

											<a class="button button-small" href="<?php echo esc_url( add_query_arg( array( 'page' => 'pckz-license-server', 'pckz_license_id' => $license_id ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'View Installations', 'pckz-canonical-engine' ); ?></a>

											<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pckz-inline-form">
												<?php wp_nonce_field( 'pckzce_delete_license', 'pckzce_delete_license_nonce' ); ?>
												<input type="hidden" name="action" value="pckzce_delete_license">
												<input type="hidden" name="license_id" value="<?php echo esc_attr( (string) $license_id ); ?>">
												<input type="hidden" name="redirect_license_id" value="<?php echo esc_attr( (string) $filter_license_id ); ?>">
												<button type="submit" class="button button-small pckz-btn-danger" data-pckz-confirm="<?php esc_attr_e( 'Permanently delete this license and ALL installation records? This cannot be undone.', 'pckz-canonical-engine' ); ?>"><?php esc_html_e( 'Delete License', 'pckz-canonical-engine' ); ?></button>
											</form>
										</div>
									</div>
								</details>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
	<p class="description"><?php esc_html_e( 'Tip: check licenses above, choose a bulk action, then click Apply. Destructive actions always ask for confirmation.', 'pckz-canonical-engine' ); ?></p>
</section>

<section class="pckz-license-card pckz-license-card--full">
	<h2><?php esc_html_e( 'Installation History', 'pckz-canonical-engine' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Each row is one registered site UUID. Remove stale records to free installation slots or unblock reinstalls.', 'pckz-canonical-engine' ); ?></p>

	<form method="get" class="pckz-license-filter">
		<input type="hidden" name="page" value="pckz-license-server">
		<input type="search" name="pckz_install_s" value="<?php echo esc_attr( $filter_search ); ?>" placeholder="<?php esc_attr_e( 'Search domain or UUID', 'pckz-canonical-engine' ); ?>">
		<select name="pckz_install_status">
			<option value=""><?php esc_html_e( 'All statuses', 'pckz-canonical-engine' ); ?></option>
			<option value="active" <?php selected( $filter_status, 'active' ); ?>><?php esc_html_e( 'Active', 'pckz-canonical-engine' ); ?></option>
			<option value="blocked" <?php selected( $filter_status, 'blocked' ); ?>><?php esc_html_e( 'Blocked', 'pckz-canonical-engine' ); ?></option>
		</select>
		<select name="pckz_license_id">
			<option value=""><?php esc_html_e( 'All licenses', 'pckz-canonical-engine' ); ?></option>
			<?php foreach ( $licenses as $license_row ) : ?>
				<option value="<?php echo esc_attr( (string) (int) $license_row['id'] ); ?>" <?php selected( $filter_license_id, (int) $license_row['id'] ); ?>>
					<?php echo esc_html( sprintf( '#%d %s', (int) $license_row['id'], $license_row['label'] ? $license_row['label'] : __( 'No label', 'pckz-canonical-engine' ) ) ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<button type="submit" class="button"><?php esc_html_e( 'Filter', 'pckz-canonical-engine' ); ?></button>
		<?php if ( $filter_search || $filter_status || $filter_license_id ) : ?>
			<a class="button button-link" href="<?php echo esc_url( admin_url( 'admin.php?page=pckz-license-server' ) ); ?>"><?php esc_html_e( 'Clear filters', 'pckz-canonical-engine' ); ?></a>
		<?php endif; ?>
	</form>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="pckz-bulk-install-form" class="pckz-license-bulk-bar">
		<?php wp_nonce_field( 'pckzce_bulk_installations', 'pckzce_bulk_installs_nonce' ); ?>
		<input type="hidden" name="action" value="pckzce_bulk_installations">
		<input type="hidden" name="redirect_search" value="<?php echo esc_attr( $filter_search ); ?>">
		<input type="hidden" name="redirect_status" value="<?php echo esc_attr( $filter_status ); ?>">
		<input type="hidden" name="redirect_license_id" value="<?php echo esc_attr( (string) $filter_license_id ); ?>">
		<label class="screen-reader-text" for="pckz-bulk-install-action"><?php esc_html_e( 'Bulk installation action', 'pckz-canonical-engine' ); ?></label>
		<select id="pckz-bulk-install-action" name="bulk_action">
			<option value=""><?php esc_html_e( 'Bulk actions', 'pckz-canonical-engine' ); ?></option>
			<option value="activate"><?php esc_html_e( 'Activate', 'pckz-canonical-engine' ); ?></option>
			<option value="block"><?php esc_html_e( 'Block', 'pckz-canonical-engine' ); ?></option>
			<option value="delete"><?php esc_html_e( 'Remove records', 'pckz-canonical-engine' ); ?></option>
		</select>
		<button type="submit" class="button" data-pckz-confirm="<?php esc_attr_e( 'Apply the selected bulk action to all checked installations?', 'pckz-canonical-engine' ); ?>"><?php esc_html_e( 'Apply', 'pckz-canonical-engine' ); ?></button>
	</form>

	<div class="pckz-license-table-wrap">
		<table class="widefat striped pckz-license-table pckz-install-table">
			<thead>
				<tr>
					<th class="check-column"><input type="checkbox" data-pckz-select-all="installation"></th>
					<th><?php esc_html_e( 'License', 'pckz-canonical-engine' ); ?></th>
					<th><?php esc_html_e( 'Domain', 'pckz-canonical-engine' ); ?></th>
					<th><?php esc_html_e( 'Install UUID', 'pckz-canonical-engine' ); ?></th>
					<th><?php esc_html_e( 'Version / Build', 'pckz-canonical-engine' ); ?></th>
					<th><?php esc_html_e( 'Last Check-In', 'pckz-canonical-engine' ); ?></th>
					<th><?php esc_html_e( 'Status', 'pckz-canonical-engine' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'pckz-canonical-engine' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $installs ) ) : ?>
					<tr><td colspan="8"><?php esc_html_e( 'No installations found.', 'pckz-canonical-engine' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $installs as $install ) : ?>
						<?php
						$install_id   = (int) ( $install['id'] ?? 0 );
						$license_id   = (int) ( $install['license_id'] ?? 0 );
						$license_info = $license_map[ $license_id ] ?? null;
						$license_label = $license_info ? ( $license_info['label'] ? $license_info['label'] : sprintf( '#%d', $license_id ) ) : sprintf( '#%d', $license_id );
						?>
						<tr>
							<th scope="row" class="check-column">
								<input type="checkbox" name="installation_ids[]" value="<?php echo esc_attr( (string) $install_id ); ?>" form="pckz-bulk-install-form" class="pckz-bulk-install-checkbox">
							</th>
							<td>
								<strong><?php echo esc_html( $license_label ); ?></strong>
								<br><span class="description">#<?php echo esc_html( (string) $license_id ); ?></span>
							</td>
							<td><code><?php echo esc_html( $install['domain'] ?? '' ); ?></code></td>
							<td><code class="pckz-code-copy pckz-code-copy--uuid" data-copy="<?php echo esc_attr( $install['install_uuid'] ?? '' ); ?>" title="<?php echo esc_attr( $install['install_uuid'] ?? '' ); ?>"><?php echo esc_html( $install['install_uuid'] ?? '' ); ?></code></td>
							<td>
								<div class="pckz-install-version">
									<span><?php echo esc_html( $install['plugin_version'] ? $install['plugin_version'] : '—' ); ?></span>
									<?php if ( ! empty( $install['plugin_build'] ) ) : ?>
										<span class="description"><?php echo esc_html( $install['plugin_build'] ); ?></span>
									<?php endif; ?>
								</div>
							</td>
							<td>
								<strong><?php echo esc_html( $format_datetime( $install['last_check_in'] ?? '' ) ); ?></strong>
								<?php if ( ! empty( $install['heartbeat_count'] ) ) : ?>
									<br><span class="description"><?php echo esc_html( sprintf( __( '%d check-ins', 'pckz-canonical-engine' ), (int) $install['heartbeat_count'] ) ); ?></span>
								<?php endif; ?>
								<?php if ( ! empty( $install['last_error'] ) ) : ?>
									<br><span class="pckz-install-error"><?php echo esc_html( $install['last_error'] ); ?></span>
								<?php endif; ?>
							</td>
							<td><span class="pckz-license-badge <?php echo esc_attr( $badge_class( $install['status'] ?? '' ) ); ?>"><?php echo esc_html( $status_label( $install['status'] ?? '' ) ); ?></span></td>
							<td class="pckz-license-actions">
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pckz-inline-form">
									<?php wp_nonce_field( 'pckzce_update_installation_status', 'pckzce_install_status_nonce' ); ?>
									<input type="hidden" name="action" value="pckzce_update_installation_status">
									<input type="hidden" name="installation_id" value="<?php echo esc_attr( (string) $install_id ); ?>">
									<select name="new_status">
										<option value="active" <?php selected( sanitize_key( (string) ( $install['status'] ?? '' ) ), 'active' ); ?>><?php esc_html_e( 'Active', 'pckz-canonical-engine' ); ?></option>
										<option value="blocked" <?php selected( sanitize_key( (string) ( $install['status'] ?? '' ) ), 'blocked' ); ?>><?php esc_html_e( 'Blocked', 'pckz-canonical-engine' ); ?></option>
									</select>
									<button type="submit" class="button button-small"><?php esc_html_e( 'Update', 'pckz-canonical-engine' ); ?></button>
								</form>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pckz-inline-form">
									<?php wp_nonce_field( 'pckzce_delete_installation', 'pckzce_delete_install_nonce' ); ?>
									<input type="hidden" name="action" value="pckzce_delete_installation">
									<input type="hidden" name="installation_id" value="<?php echo esc_attr( (string) $install_id ); ?>">
									<input type="hidden" name="redirect_search" value="<?php echo esc_attr( $filter_search ); ?>">
									<input type="hidden" name="redirect_status" value="<?php echo esc_attr( $filter_status ); ?>">
									<input type="hidden" name="redirect_license_id" value="<?php echo esc_attr( (string) $filter_license_id ); ?>">
									<button type="submit" class="button button-small pckz-btn-danger" data-pckz-confirm="<?php esc_attr_e( 'Remove this installation UUID record permanently?', 'pckz-canonical-engine' ); ?>"><?php esc_html_e( 'Remove', 'pckz-canonical-engine' ); ?></button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</section>

<section class="pckz-license-card pckz-license-card--full">
	<h2><?php esc_html_e( 'Protected Download History', 'pckz-canonical-engine' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Audit trail of protected update package downloads delivered to licensed client sites.', 'pckz-canonical-engine' ); ?></p>
	<div class="pckz-license-table-wrap">
		<table class="widefat striped pckz-license-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Date', 'pckz-canonical-engine' ); ?></th>
					<th><?php esc_html_e( 'Domain', 'pckz-canonical-engine' ); ?></th>
					<th><?php esc_html_e( 'Install UUID', 'pckz-canonical-engine' ); ?></th>
					<th><?php esc_html_e( 'Requested Version', 'pckz-canonical-engine' ); ?></th>
					<th><?php esc_html_e( 'Package URL', 'pckz-canonical-engine' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $downloads ) ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'No downloads recorded yet.', 'pckz-canonical-engine' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $downloads as $download ) : ?>
						<tr>
							<td><?php echo esc_html( $format_datetime( $download['created_at'] ?? '' ) ); ?></td>
							<td><code><?php echo esc_html( $download['domain'] ?? '' ); ?></code></td>
							<td><code class="pckz-code-copy pckz-code-copy--uuid" data-copy="<?php echo esc_attr( $download['install_uuid'] ?? '' ); ?>"><?php echo esc_html( $download['install_uuid'] ?? '' ); ?></code></td>
							<td><?php echo esc_html( $download['requested_version'] ?? '—' ); ?></td>
							<td><code class="pckz-code-truncate"><?php echo esc_html( $download['package_url'] ?? '' ); ?></code></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</section>

<section class="pckz-license-card pckz-license-card--full">
	<h2><?php esc_html_e( 'Generated Customer Packages', 'pckz-canonical-engine' ); ?></h2>
	<p class="description"><?php esc_html_e( 'ZIP files created for customers. Remove old packages to free disk space.', 'pckz-canonical-engine' ); ?></p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="pckz-bulk-package-form" class="pckz-license-bulk-bar">
		<?php wp_nonce_field( 'pckzce_bulk_customer_packages', 'pckzce_bulk_packages_nonce' ); ?>
		<input type="hidden" name="action" value="pckzce_bulk_customer_packages">
		<label class="screen-reader-text" for="pckz-bulk-package-action"><?php esc_html_e( 'Bulk package action', 'pckz-canonical-engine' ); ?></label>
		<select id="pckz-bulk-package-action" name="bulk_action">
			<option value=""><?php esc_html_e( 'Bulk actions', 'pckz-canonical-engine' ); ?></option>
			<option value="delete"><?php esc_html_e( 'Delete selected', 'pckz-canonical-engine' ); ?></option>
			<option value="delete_all_old"><?php esc_html_e( 'Delete all old packages (keep newest)', 'pckz-canonical-engine' ); ?></option>
		</select>
		<button type="submit" class="button" data-pckz-confirm="<?php esc_attr_e( 'Apply the selected package cleanup action?', 'pckz-canonical-engine' ); ?>"><?php esc_html_e( 'Apply', 'pckz-canonical-engine' ); ?></button>
	</form>

	<div class="pckz-license-table-wrap">
		<table class="widefat striped pckz-license-table">
			<thead>
				<tr>
					<th class="check-column"><input type="checkbox" data-pckz-select-all="package"></th>
					<th><?php esc_html_e( 'Filename', 'pckz-canonical-engine' ); ?></th>
					<th><?php esc_html_e( 'Size', 'pckz-canonical-engine' ); ?></th>
					<th><?php esc_html_e( 'Updated', 'pckz-canonical-engine' ); ?></th>
					<th><?php esc_html_e( 'Download', 'pckz-canonical-engine' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'pckz-canonical-engine' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $customer_packages ) ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'No customer packages generated yet.', 'pckz-canonical-engine' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $customer_packages as $pkg ) : ?>
						<tr>
							<th scope="row" class="check-column">
								<input type="checkbox" name="package_filenames[]" value="<?php echo esc_attr( $pkg['filename'] ?? '' ); ?>" form="pckz-bulk-package-form" class="pckz-bulk-package-checkbox">
							</th>
							<td><code><?php echo esc_html( $pkg['filename'] ?? '' ); ?></code></td>
							<td><?php echo esc_html( size_format( (int) ( $pkg['size'] ?? 0 ), 2 ) ); ?></td>
							<td><?php echo esc_html( ! empty( $pkg['modified'] ) ? gmdate( 'Y-m-d H:i:s', (int) $pkg['modified'] ) . ' UTC' : '—' ); ?></td>
							<td>
								<?php if ( ! empty( $pkg['url'] ) ) : ?>
									<a class="button button-small" href="<?php echo esc_url( $pkg['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Download ZIP', 'pckz-canonical-engine' ); ?></a>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
							<td>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pckz-inline-form">
									<?php wp_nonce_field( 'pckzce_delete_customer_package', 'pckzce_delete_package_nonce' ); ?>
									<input type="hidden" name="action" value="pckzce_delete_customer_package">
									<input type="hidden" name="package_filename" value="<?php echo esc_attr( $pkg['filename'] ?? '' ); ?>">
									<button type="submit" class="button button-small pckz-btn-danger" data-pckz-confirm="<?php esc_attr_e( 'Delete this customer package file permanently?', 'pckz-canonical-engine' ); ?>"><?php esc_html_e( 'Delete', 'pckz-canonical-engine' ); ?></button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</section>

<?php if ( ! empty( $recent_errors ) ) : ?>
	<section class="pckz-license-card pckz-license-card--full pckz-license-card--errors">
		<h2><?php esc_html_e( 'Recent Client Errors', 'pckz-canonical-engine' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Latest check-in failures reported by client installations.', 'pckz-canonical-engine' ); ?></p>
		<ul class="pckz-license-errors">
			<?php foreach ( $recent_errors as $error_row ) : ?>
				<li>
					<strong><?php echo esc_html( $error_row['domain'] ?? '' ); ?></strong>
					<code class="pckz-code-copy pckz-code-copy--uuid" data-copy="<?php echo esc_attr( $error_row['install_uuid'] ?? '' ); ?>"><?php echo esc_html( $error_row['install_uuid'] ?? '' ); ?></code>
					<span><?php echo esc_html( $error_row['last_error'] ?? '' ); ?></span>
					<em><?php echo esc_html( $format_datetime( $error_row['updated_at'] ?? '' ) ); ?></em>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>
<?php endif; ?>
