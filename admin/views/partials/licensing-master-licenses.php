<?php
/**
 * Master Control — license creation, management, and client package delivery.
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

$filter_license_id = isset( $_GET['pckz_license_id'] ) ? absint( $_GET['pckz_license_id'] ) : 0;
$filter_search     = isset( $_GET['pckz_install_s'] ) ? sanitize_text_field( wp_unslash( $_GET['pckz_install_s'] ) ) : '';

$status_help = array(
	'active'   => __( 'Valid — can check in, export, and receive updates when permitted.', 'pckz-canonical-engine' ),
	'blocked'  => __( 'Installation blocked on master until unblocked or removed.', 'pckz-canonical-engine' ),
	'disabled' => __( 'License disabled — all linked installations are blocked.', 'pckz-canonical-engine' ),
	'revoked'  => __( 'Permanently revoked — all linked installations are blocked.', 'pckz-canonical-engine' ),
	'denied'   => __( 'Check-in rejected (invalid key, domain mismatch, or security failure).', 'pckz-canonical-engine' ),
);
?>

<section class="pckz-mc-section" id="pckz-mc-licenses">
	<header class="pckz-mc-section__header">
		<div>
			<h2><?php esc_html_e( 'Licenses & client delivery', 'pckz-canonical-engine' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Issue license keys, manage customer access, and build ready-to-install client ZIP packages.', 'pckz-canonical-engine' ); ?></p>
		</div>
	</header>

	<details class="pckz-mc-advanced pckz-mc-status-guide">
		<summary><?php esc_html_e( 'Status reference', 'pckz-canonical-engine' ); ?></summary>
		<div class="pckz-mc-status-guide__grid">
			<?php foreach ( $status_help as $help_key => $help_text ) : ?>
				<div class="pckz-mc-status-guide__item">
					<span class="pckz-license-badge <?php echo esc_attr( $badge_class( $help_key ) ); ?>"><?php echo esc_html( $status_label( $help_key ) ); ?></span>
					<p><?php echo esc_html( $help_text ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
		<p class="pckz-mc-tip">
			<strong><?php esc_html_e( 'Reinstall tip:', 'pckz-canonical-engine' ); ?></strong>
			<?php esc_html_e( 'If a customer hits “Maximum installations reached” after reinstalling, use Clear Installations on their license to free the slot without issuing a new key.', 'pckz-canonical-engine' ); ?>
		</p>
	</details>

	<div class="pckz-mc-split">
		<article class="pckz-mc-panel">
			<h3><?php esc_html_e( 'Create license', 'pckz-canonical-engine' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Issue a new license key for a customer site.', 'pckz-canonical-engine' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pckz-mc-form">
				<?php wp_nonce_field( 'pckzce_create_license', 'pckzce_license_nonce' ); ?>
				<input type="hidden" name="action" value="pckzce_create_license">
				<input type="hidden" name="redirect_section" value="licenses">
				<div class="pckz-mc-form__grid">
					<p>
						<label for="pckz-license-label"><strong><?php esc_html_e( 'Customer label', 'pckz-canonical-engine' ); ?></strong></label>
						<input id="pckz-license-label" type="text" class="regular-text" name="label" autocomplete="off">
					</p>
					<p>
						<label for="pckz-license-max"><strong><?php esc_html_e( 'Max installations', 'pckz-canonical-engine' ); ?></strong></label>
						<input id="pckz-license-max" type="number" class="small-text" min="1" name="max_installs" value="1">
					</p>
				</div>
				<p>
					<label for="pckz-license-domains"><strong><?php esc_html_e( 'Allowed domains', 'pckz-canonical-engine' ); ?></strong></label>
					<textarea id="pckz-license-domains" name="domains" rows="3" class="large-text" placeholder="client.example.com"></textarea>
				</p>
				<fieldset class="pckz-mc-fieldset">
					<legend><?php esc_html_e( 'Permissions', 'pckz-canonical-engine' ); ?></legend>
					<label><input type="checkbox" name="perm_export" value="1" checked> <?php esc_html_e( 'Export authorization', 'pckz-canonical-engine' ); ?></label>
					<label><input type="checkbox" name="perm_updates" value="1" checked> <?php esc_html_e( 'Protected updates', 'pckz-canonical-engine' ); ?></label>
					<label><input type="checkbox" name="perm_asset_sync" value="1" checked> <?php esc_html_e( 'Asset synchronization', 'pckz-canonical-engine' ); ?></label>
				</fieldset>
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Create license', 'pckz-canonical-engine' ); ?></button></p>
			</form>
		</article>

		<article class="pckz-mc-panel">
			<h3><?php esc_html_e( 'Generate client package', 'pckz-canonical-engine' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Build a license-bound ZIP for a specific customer.', 'pckz-canonical-engine' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pckz-mc-form" id="pckz-customer-package-form">
				<?php wp_nonce_field( 'pckzce_generate_customer_package', 'pckzce_package_nonce' ); ?>
				<input type="hidden" name="action" value="pckzce_generate_customer_package">
				<input type="hidden" name="redirect_section" value="licenses">
				<p>
					<label for="pckz-package-license"><strong><?php esc_html_e( 'License', 'pckz-canonical-engine' ); ?></strong></label>
					<select id="pckz-package-license" name="license_id" required data-pckz-license-select>
						<option value=""><?php esc_html_e( 'Select license', 'pckz-canonical-engine' ); ?></option>
						<?php foreach ( $licenses as $license_row ) : ?>
							<?php
							$row_domains = json_decode( (string) ( $license_row['domains'] ?? '[]' ), true );
							$row_domains = is_array( $row_domains ) ? implode( "\n", $row_domains ) : '';
							?>
							<option
								value="<?php echo esc_attr( (string) $license_row['id'] ); ?>"
								data-domains="<?php echo esc_attr( $row_domains ); ?>"
								<?php selected( $filter_license_id, (int) ( $license_row['id'] ?? 0 ) ); ?>
							>
								<?php echo esc_html( sprintf( '#%d %s (%s)', (int) $license_row['id'], $license_row['label'] ? $license_row['label'] : __( 'No label', 'pckz-canonical-engine' ), $status_label( $license_row['status'] ) ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</p>
				<p>
					<label for="pckz-package-domains"><strong><?php esc_html_e( 'Allowed domains', 'pckz-canonical-engine' ); ?></strong></label>
					<textarea id="pckz-package-domains" name="domains" rows="3" class="large-text" placeholder="client.example.com"><?php echo esc_textarea( $filter_search ); ?></textarea>
				</p>
				<fieldset class="pckz-mc-fieldset">
					<legend><?php esc_html_e( 'Package permissions', 'pckz-canonical-engine' ); ?></legend>
					<input type="hidden" name="perm_export" value="0">
					<label><input type="checkbox" name="perm_export" value="1" checked> <?php esc_html_e( 'Export permission', 'pckz-canonical-engine' ); ?></label>
					<input type="hidden" name="perm_updates" value="0">
					<label><input type="checkbox" name="perm_updates" value="1" checked> <?php esc_html_e( 'Update permission', 'pckz-canonical-engine' ); ?></label>
				</fieldset>
				<details class="pckz-mc-advanced">
					<summary><?php esc_html_e( 'Advanced export options', 'pckz-canonical-engine' ); ?></summary>
					<input type="hidden" name="export_authorize" value="0">
					<label><input type="checkbox" name="export_authorize" value="1"> <?php esc_html_e( 'Require export authorization from master', 'pckz-canonical-engine' ); ?></label>
					<input type="hidden" name="export_remote_mode" value="0">
					<label><input type="checkbox" name="export_remote_mode" value="1"> <?php esc_html_e( 'Enable remote export mode', 'pckz-canonical-engine' ); ?></label>
					<input type="hidden" name="export_remote_strict" value="0">
					<label><input type="checkbox" name="export_remote_strict" value="1"> <?php esc_html_e( 'Strict remote export (no fallback)', 'pckz-canonical-engine' ); ?></label>
					<p>
						<label for="pckz-package-grace"><strong><?php esc_html_e( 'Grace period (minutes)', 'pckz-canonical-engine' ); ?></strong></label>
						<input id="pckz-package-grace" type="number" class="small-text" min="5" max="1440" name="grace_minutes" value="120">
					</p>
				</details>
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Generate client package', 'pckz-canonical-engine' ); ?></button></p>
			</form>
		</article>
	</div>

	<article class="pckz-mc-panel pckz-mc-panel--table">
		<div class="pckz-mc-panel__toolbar">
			<div>
				<h3><?php esc_html_e( 'License registry', 'pckz-canonical-engine' ); ?></h3>
				<p class="description"><?php esc_html_e( 'All customer licenses with installation limits and quick management actions.', 'pckz-canonical-engine' ); ?></p>
			</div>
		</div>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="pckz-bulk-license-form" class="pckz-mc-bulk-bar">
			<?php wp_nonce_field( 'pckzce_bulk_licenses', 'pckzce_bulk_licenses_nonce' ); ?>
			<input type="hidden" name="action" value="pckzce_bulk_licenses">
			<input type="hidden" name="redirect_section" value="licenses">
			<input type="hidden" name="redirect_search" value="<?php echo esc_attr( $filter_search ); ?>">
			<input type="hidden" name="redirect_status" value="<?php echo esc_attr( isset( $_GET['pckz_install_status'] ) ? sanitize_key( wp_unslash( $_GET['pckz_install_status'] ) ) : '' ); ?>">
			<input type="hidden" name="redirect_license_id" value="<?php echo esc_attr( (string) $filter_license_id ); ?>">
			<label class="screen-reader-text" for="pckz-bulk-license-action"><?php esc_html_e( 'Bulk license action', 'pckz-canonical-engine' ); ?></label>
			<select id="pckz-bulk-license-action" name="bulk_action">
				<option value=""><?php esc_html_e( 'Bulk actions', 'pckz-canonical-engine' ); ?></option>
				<option value="activate"><?php esc_html_e( 'Set active', 'pckz-canonical-engine' ); ?></option>
				<option value="reset"><?php esc_html_e( 'Reset license', 'pckz-canonical-engine' ); ?></option>
				<option value="clear_installs"><?php esc_html_e( 'Clear installations', 'pckz-canonical-engine' ); ?></option>
				<option value="disable"><?php esc_html_e( 'Disable', 'pckz-canonical-engine' ); ?></option>
				<option value="revoke"><?php esc_html_e( 'Revoke', 'pckz-canonical-engine' ); ?></option>
				<option value="delete"><?php esc_html_e( 'Delete permanently', 'pckz-canonical-engine' ); ?></option>
			</select>
			<button type="submit" class="button" data-pckz-confirm="<?php esc_attr_e( 'Apply the selected bulk action to all checked licenses?', 'pckz-canonical-engine' ); ?>"><?php esc_html_e( 'Apply', 'pckz-canonical-engine' ); ?></button>
		</form>

		<div class="pckz-mc-table-wrap">
			<table class="widefat striped pckz-mc-table pckz-license-table">
				<thead>
					<tr>
						<th class="check-column"><input type="checkbox" data-pckz-select-all="license"></th>
						<th><?php esc_html_e( 'Customer', 'pckz-canonical-engine' ); ?></th>
						<th><?php esc_html_e( 'Status', 'pckz-canonical-engine' ); ?></th>
						<th><?php esc_html_e( 'Installations', 'pckz-canonical-engine' ); ?></th>
						<th><?php esc_html_e( 'Domains', 'pckz-canonical-engine' ); ?></th>
						<th><?php esc_html_e( 'Permissions', 'pckz-canonical-engine' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'pckz-canonical-engine' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $licenses ) ) : ?>
						<tr><td colspan="7"><?php esc_html_e( 'No licenses yet. Create your first license above.', 'pckz-canonical-engine' ); ?></td></tr>
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
								<th scope="row" class="check-column" data-label="">
									<input type="checkbox" name="license_ids[]" value="<?php echo esc_attr( (string) $license_id ); ?>" form="pckz-bulk-license-form" class="pckz-bulk-license-checkbox">
								</th>
								<td data-label="<?php esc_attr_e( 'Customer', 'pckz-canonical-engine' ); ?>">
									<strong><?php echo esc_html( $license_row['label'] ? $license_row['label'] : __( 'No label', 'pckz-canonical-engine' ) ); ?></strong>
									<span class="pckz-mc-table__sub">#<?php echo esc_html( (string) $license_id ); ?></span>
									<?php if ( ! empty( $license_row['expires_at'] ) ) : ?>
										<span class="pckz-mc-table__sub"><?php echo esc_html( sprintf( __( 'Expires %s', 'pckz-canonical-engine' ), $format_datetime( $license_row['expires_at'] ) ) ); ?></span>
									<?php endif; ?>
									<code class="pckz-code-copy pckz-mc-table__key" data-copy="<?php echo esc_attr( $license_row['license_key'] ?? '' ); ?>"><?php echo esc_html( $license_row['license_key'] ?? '' ); ?></code>
								</td>
								<td data-label="<?php esc_attr_e( 'Status', 'pckz-canonical-engine' ); ?>">
									<span class="pckz-license-badge <?php echo esc_attr( $badge_class( $license_row['status'] ?? '' ) ); ?>"><?php echo esc_html( $status_label( $license_row['status'] ?? '' ) ); ?></span>
								</td>
								<td data-label="<?php esc_attr_e( 'Installations', 'pckz-canonical-engine' ); ?>">
									<span class="pckz-install-counter <?php echo $at_limit ? 'is-at-limit' : ''; ?>"><?php echo esc_html( sprintf( '%d / %d', $active_count, $max_count ) ); ?></span>
									<?php if ( (int) ( $install_stats['blocked'] ?? 0 ) > 0 ) : ?>
										<span class="pckz-mc-table__sub"><?php echo esc_html( sprintf( __( '%d blocked', 'pckz-canonical-engine' ), (int) $install_stats['blocked'] ) ); ?></span>
									<?php endif; ?>
								</td>
								<td data-label="<?php esc_attr_e( 'Domains', 'pckz-canonical-engine' ); ?>"><?php echo esc_html( $license_domains ? implode( ', ', $license_domains ) : '—' ); ?></td>
								<td data-label="<?php esc_attr_e( 'Permissions', 'pckz-canonical-engine' ); ?>">
									<?php if ( ! empty( $license_perms['export'] ) ) : ?><span class="pckz-perm-chip"><?php esc_html_e( 'Export', 'pckz-canonical-engine' ); ?></span><?php endif; ?>
									<?php if ( ! empty( $license_perms['updates'] ) ) : ?><span class="pckz-perm-chip"><?php esc_html_e( 'Updates', 'pckz-canonical-engine' ); ?></span><?php endif; ?>
									<?php if ( ! isset( $license_perms['asset_sync'] ) || ! empty( $license_perms['asset_sync'] ) ) : ?><span class="pckz-perm-chip"><?php esc_html_e( 'Assets', 'pckz-canonical-engine' ); ?></span><?php endif; ?>
								</td>
								<td class="pckz-license-actions" data-label="<?php esc_attr_e( 'Actions', 'pckz-canonical-engine' ); ?>">
									<details class="pckz-mc-row-details">
										<summary class="button button-small"><?php esc_html_e( 'Manage', 'pckz-canonical-engine' ); ?></summary>
										<div class="pckz-mc-row-details__body">
											<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pckz-license-edit-form">
												<?php wp_nonce_field( 'pckzce_save_license_detail', 'pckzce_license_detail_nonce' ); ?>
												<input type="hidden" name="action" value="pckzce_save_license_detail">
												<input type="hidden" name="redirect_section" value="licenses">
												<input type="hidden" name="license_id" value="<?php echo esc_attr( (string) $license_id ); ?>">
												<p><label><strong><?php esc_html_e( 'Label', 'pckz-canonical-engine' ); ?></strong></label><input type="text" class="regular-text" name="label" value="<?php echo esc_attr( $license_row['label'] ?? '' ); ?>"></p>
												<p><label><strong><?php esc_html_e( 'Allowed domains', 'pckz-canonical-engine' ); ?></strong></label><textarea name="domains" rows="3" class="large-text"><?php echo esc_textarea( implode( "\n", $license_domains ) ); ?></textarea></p>
												<p><label><strong><?php esc_html_e( 'Max installations', 'pckz-canonical-engine' ); ?></strong></label><input type="number" class="small-text" min="1" name="max_installs" value="<?php echo esc_attr( (string) $max_count ); ?>"></p>
												<p>
													<label><input type="checkbox" name="perm_export" value="1" <?php checked( ! empty( $license_perms['export'] ) ); ?>> <?php esc_html_e( 'Allow export', 'pckz-canonical-engine' ); ?></label><br>
													<label><input type="checkbox" name="perm_updates" value="1" <?php checked( ! empty( $license_perms['updates'] ) ); ?>> <?php esc_html_e( 'Allow updates', 'pckz-canonical-engine' ); ?></label><br>
													<label><input type="checkbox" name="perm_asset_sync" value="1" <?php checked( ! isset( $license_perms['asset_sync'] ) || ! empty( $license_perms['asset_sync'] ) ); ?>> <?php esc_html_e( 'Allow asset sync', 'pckz-canonical-engine' ); ?></label>
												</p>
												<p><label><strong><?php esc_html_e( 'Expires (optional)', 'pckz-canonical-engine' ); ?></strong></label><input type="datetime-local" name="expires_at" value="<?php echo ! empty( $license_row['expires_at'] ) ? esc_attr( gmdate( 'Y-m-d\TH:i', strtotime( (string) $license_row['expires_at'] ) ) ) : ''; ?>"></p>
												<p><button type="submit" class="button button-primary button-small"><?php esc_html_e( 'Save changes', 'pckz-canonical-engine' ); ?></button></p>
											</form>
											<div class="pckz-license-quick-actions">
												<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pckz-inline-form">
													<?php wp_nonce_field( 'pckzce_update_license_status', 'pckzce_license_status_nonce' ); ?>
													<input type="hidden" name="action" value="pckzce_update_license_status">
													<input type="hidden" name="redirect_section" value="licenses">
													<input type="hidden" name="license_id" value="<?php echo esc_attr( (string) $license_id ); ?>">
													<select name="new_status">
														<option value="active"><?php esc_html_e( 'Active', 'pckz-canonical-engine' ); ?></option>
														<option value="disabled"><?php esc_html_e( 'Disabled', 'pckz-canonical-engine' ); ?></option>
														<option value="revoked"><?php esc_html_e( 'Revoked', 'pckz-canonical-engine' ); ?></option>
													</select>
													<button type="submit" class="button button-small" data-pckz-confirm="<?php esc_attr_e( 'Change the license status?', 'pckz-canonical-engine' ); ?>"><?php esc_html_e( 'Update status', 'pckz-canonical-engine' ); ?></button>
												</form>
												<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pckz-inline-form">
													<?php wp_nonce_field( 'pckzce_reset_license', 'pckzce_reset_license_nonce' ); ?>
													<input type="hidden" name="action" value="pckzce_reset_license">
													<input type="hidden" name="redirect_section" value="licenses">
													<input type="hidden" name="license_id" value="<?php echo esc_attr( (string) $license_id ); ?>">
													<input type="hidden" name="redirect_license_id" value="<?php echo esc_attr( (string) $filter_license_id ); ?>">
													<button type="submit" class="button button-small" data-pckz-confirm="<?php esc_attr_e( 'Reset this license to Active and unblock all linked installations?', 'pckz-canonical-engine' ); ?>"><?php esc_html_e( 'Reset license', 'pckz-canonical-engine' ); ?></button>
												</form>
												<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pckz-inline-form">
													<?php wp_nonce_field( 'pckzce_clear_license_installations', 'pckzce_clear_installs_nonce' ); ?>
													<input type="hidden" name="action" value="pckzce_clear_license_installations">
													<input type="hidden" name="redirect_section" value="licenses">
													<input type="hidden" name="license_id" value="<?php echo esc_attr( (string) $license_id ); ?>">
													<input type="hidden" name="redirect_license_id" value="<?php echo esc_attr( (string) $filter_license_id ); ?>">
													<button type="submit" class="button button-small pckz-btn-danger" data-pckz-confirm="<?php esc_attr_e( 'Remove ALL installation UUID records for this license?', 'pckz-canonical-engine' ); ?>"><?php esc_html_e( 'Clear installations', 'pckz-canonical-engine' ); ?></button>
												</form>
												<a class="button button-small" href="<?php echo esc_url( add_query_arg( array( 'page' => 'pckz-license-server', 'pckz_license_id' => $license_id ), admin_url( 'admin.php' ) ) . '#pckz-master-section-records' ); ?>"><?php esc_html_e( 'View installations', 'pckz-canonical-engine' ); ?></a>
												<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pckz-inline-form">
													<?php wp_nonce_field( 'pckzce_delete_license', 'pckzce_delete_license_nonce' ); ?>
													<input type="hidden" name="action" value="pckzce_delete_license">
													<input type="hidden" name="redirect_section" value="licenses">
													<input type="hidden" name="license_id" value="<?php echo esc_attr( (string) $license_id ); ?>">
													<input type="hidden" name="redirect_license_id" value="<?php echo esc_attr( (string) $filter_license_id ); ?>">
													<button type="submit" class="button button-small pckz-btn-danger" data-pckz-confirm="<?php esc_attr_e( 'Permanently delete this license and ALL installation records?', 'pckz-canonical-engine' ); ?>"><?php esc_html_e( 'Delete license', 'pckz-canonical-engine' ); ?></button>
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
	</article>

	<?php if ( ! empty( $customer_packages ) ) : ?>
		<article class="pckz-mc-panel pckz-mc-panel--table">
			<h3><?php esc_html_e( 'Generated client packages', 'pckz-canonical-engine' ); ?></h3>
			<p class="description"><?php esc_html_e( 'ZIP files created for customers. Remove old packages to free disk space.', 'pckz-canonical-engine' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="pckz-bulk-package-form" class="pckz-mc-bulk-bar">
				<?php wp_nonce_field( 'pckzce_bulk_customer_packages', 'pckzce_bulk_packages_nonce' ); ?>
				<input type="hidden" name="action" value="pckzce_bulk_customer_packages">
				<input type="hidden" name="redirect_section" value="licenses">
				<select id="pckz-bulk-package-action" name="bulk_action">
					<option value=""><?php esc_html_e( 'Bulk actions', 'pckz-canonical-engine' ); ?></option>
					<option value="delete"><?php esc_html_e( 'Delete selected', 'pckz-canonical-engine' ); ?></option>
					<option value="delete_all_old"><?php esc_html_e( 'Delete all old (keep newest)', 'pckz-canonical-engine' ); ?></option>
				</select>
				<button type="submit" class="button" data-pckz-confirm="<?php esc_attr_e( 'Apply the selected package cleanup action?', 'pckz-canonical-engine' ); ?>"><?php esc_html_e( 'Apply', 'pckz-canonical-engine' ); ?></button>
			</form>
			<div class="pckz-mc-table-wrap">
				<table class="widefat striped pckz-mc-table">
					<thead>
						<tr>
							<th class="check-column"><input type="checkbox" data-pckz-select-all="package"></th>
							<th><?php esc_html_e( 'Filename', 'pckz-canonical-engine' ); ?></th>
							<th><?php esc_html_e( 'Size', 'pckz-canonical-engine' ); ?></th>
							<th><?php esc_html_e( 'Created', 'pckz-canonical-engine' ); ?></th>
							<th><?php esc_html_e( 'Download', 'pckz-canonical-engine' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'pckz-canonical-engine' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $customer_packages as $pkg ) : ?>
							<tr>
								<th scope="row" class="check-column"><input type="checkbox" name="package_filenames[]" value="<?php echo esc_attr( $pkg['filename'] ?? '' ); ?>" form="pckz-bulk-package-form" class="pckz-bulk-package-checkbox"></th>
								<td data-label="<?php esc_attr_e( 'Filename', 'pckz-canonical-engine' ); ?>"><code><?php echo esc_html( $pkg['filename'] ?? '' ); ?></code></td>
								<td data-label="<?php esc_attr_e( 'Size', 'pckz-canonical-engine' ); ?>"><?php echo esc_html( size_format( (int) ( $pkg['size'] ?? 0 ), 2 ) ); ?></td>
								<td data-label="<?php esc_attr_e( 'Created', 'pckz-canonical-engine' ); ?>"><?php echo esc_html( ! empty( $pkg['modified'] ) ? gmdate( 'Y-m-d H:i:s', (int) $pkg['modified'] ) . ' UTC' : '—' ); ?></td>
								<td data-label="<?php esc_attr_e( 'Download', 'pckz-canonical-engine' ); ?>">
									<?php if ( ! empty( $pkg['url'] ) ) : ?>
										<a class="button button-small" href="<?php echo esc_url( $pkg['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Download', 'pckz-canonical-engine' ); ?></a>
									<?php else : ?>
										—
									<?php endif; ?>
								</td>
								<td data-label="<?php esc_attr_e( 'Actions', 'pckz-canonical-engine' ); ?>">
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pckz-inline-form">
										<?php wp_nonce_field( 'pckzce_delete_customer_package', 'pckzce_delete_package_nonce' ); ?>
										<input type="hidden" name="action" value="pckzce_delete_customer_package">
										<input type="hidden" name="redirect_section" value="licenses">
										<input type="hidden" name="package_filename" value="<?php echo esc_attr( $pkg['filename'] ?? '' ); ?>">
										<button type="submit" class="button button-small pckz-btn-danger" data-pckz-confirm="<?php esc_attr_e( 'Delete this customer package permanently?', 'pckz-canonical-engine' ); ?>"><?php esc_html_e( 'Delete', 'pckz-canonical-engine' ); ?></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</article>
	<?php endif; ?>
</section>
