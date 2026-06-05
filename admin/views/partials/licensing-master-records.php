<?php
/**
 * Master Control — installation records, download audit, and client errors.
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

$filter_license_id = isset( $_GET['pckz_license_id'] ) ? absint( $_GET['pckz_license_id'] ) : 0;
$filter_search     = isset( $_GET['pckz_install_s'] ) ? sanitize_text_field( wp_unslash( $_GET['pckz_install_s'] ) ) : '';
$filter_status     = isset( $_GET['pckz_install_status'] ) ? sanitize_key( wp_unslash( $_GET['pckz_install_status'] ) ) : '';
$filter_download_s = isset( $_GET['pckz_download_s'] ) ? sanitize_text_field( wp_unslash( $_GET['pckz_download_s'] ) ) : '';
$records_base_url  = admin_url( 'admin.php?page=pckz-license-server' );
?>

<section class="pckz-mc-section" id="pckz-mc-records">
	<header class="pckz-mc-section__header">
		<div>
			<h2><?php esc_html_e( 'Activity & audit logs', 'pckz-canonical-engine' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Installation records, protected update downloads, and recent client-side errors.', 'pckz-canonical-engine' ); ?></p>
		</div>
	</header>

	<article class="pckz-mc-panel pckz-mc-panel--table">
		<h3><?php esc_html_e( 'Installation records', 'pckz-canonical-engine' ); ?></h3>
		<p class="description"><?php esc_html_e( 'Each row is one registered site. Remove stale records to free installation slots.', 'pckz-canonical-engine' ); ?></p>

		<form method="get" class="pckz-mc-filter-bar">
			<input type="hidden" name="page" value="pckz-license-server">
			<input type="search" name="pckz_install_s" value="<?php echo esc_attr( $filter_search ); ?>" placeholder="<?php esc_attr_e( 'Search domain or UUID…', 'pckz-canonical-engine' ); ?>">
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
				<a class="button button-link" href="<?php echo esc_url( $records_base_url . '#pckz-master-section-records' ); ?>"><?php esc_html_e( 'Clear', 'pckz-canonical-engine' ); ?></a>
			<?php endif; ?>
		</form>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="pckz-bulk-install-form" class="pckz-mc-bulk-bar">
			<?php wp_nonce_field( 'pckzce_bulk_installations', 'pckzce_bulk_installs_nonce' ); ?>
			<input type="hidden" name="action" value="pckzce_bulk_installations">
			<input type="hidden" name="redirect_section" value="records">
			<input type="hidden" name="redirect_search" value="<?php echo esc_attr( $filter_search ); ?>">
			<input type="hidden" name="redirect_status" value="<?php echo esc_attr( $filter_status ); ?>">
			<input type="hidden" name="redirect_license_id" value="<?php echo esc_attr( (string) $filter_license_id ); ?>">
			<select id="pckz-bulk-install-action" name="bulk_action">
				<option value=""><?php esc_html_e( 'Bulk actions', 'pckz-canonical-engine' ); ?></option>
				<option value="activate"><?php esc_html_e( 'Activate', 'pckz-canonical-engine' ); ?></option>
				<option value="block"><?php esc_html_e( 'Block', 'pckz-canonical-engine' ); ?></option>
				<option value="delete"><?php esc_html_e( 'Remove records', 'pckz-canonical-engine' ); ?></option>
			</select>
			<button type="submit" class="button" data-pckz-confirm="<?php esc_attr_e( 'Apply the selected bulk action to all checked installations?', 'pckz-canonical-engine' ); ?>"><?php esc_html_e( 'Apply', 'pckz-canonical-engine' ); ?></button>
		</form>

		<div class="pckz-mc-table-wrap">
			<table class="widefat striped pckz-mc-table pckz-install-table">
				<thead>
					<tr>
						<th class="check-column"><input type="checkbox" data-pckz-select-all="installation"></th>
						<th><?php esc_html_e( 'Domain', 'pckz-canonical-engine' ); ?></th>
						<th><?php esc_html_e( 'License', 'pckz-canonical-engine' ); ?></th>
						<th><?php esc_html_e( 'Version', 'pckz-canonical-engine' ); ?></th>
						<th><?php esc_html_e( 'Last check-in', 'pckz-canonical-engine' ); ?></th>
						<th><?php esc_html_e( 'Status', 'pckz-canonical-engine' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'pckz-canonical-engine' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $installs ) ) : ?>
						<tr><td colspan="7"><?php esc_html_e( 'No installations match your filters.', 'pckz-canonical-engine' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $installs as $install ) : ?>
							<?php
							$install_id    = (int) ( $install['id'] ?? 0 );
							$license_id    = (int) ( $install['license_id'] ?? 0 );
							$license_info  = $license_map[ $license_id ] ?? null;
							$license_label = $license_info ? ( $license_info['label'] ? $license_info['label'] : sprintf( '#%d', $license_id ) ) : sprintf( '#%d', $license_id );
							$tamper_signals = json_decode( (string) ( $install['tamper_signals'] ?? '[]' ), true );
							$tamper_signals = is_array( $tamper_signals ) ? array_values( array_filter( array_map( 'sanitize_key', $tamper_signals ) ) ) : array();
							?>
							<tr>
								<th scope="row" class="check-column"><input type="checkbox" name="installation_ids[]" value="<?php echo esc_attr( (string) $install_id ); ?>" form="pckz-bulk-install-form" class="pckz-bulk-install-checkbox"></th>
								<td data-label="<?php esc_attr_e( 'Domain', 'pckz-canonical-engine' ); ?>"><code><?php echo esc_html( $install['domain'] ?? '' ); ?></code></td>
								<td data-label="<?php esc_attr_e( 'License', 'pckz-canonical-engine' ); ?>">
									<strong><?php echo esc_html( $license_label ); ?></strong>
									<span class="pckz-mc-table__sub">#<?php echo esc_html( (string) $license_id ); ?></span>
								</td>
								<td data-label="<?php esc_attr_e( 'Version', 'pckz-canonical-engine' ); ?>">
									<?php echo esc_html( $install['plugin_version'] ? $install['plugin_version'] : '—' ); ?>
									<?php if ( ! empty( $install['plugin_build'] ) ) : ?>
										<span class="pckz-mc-table__sub"><?php echo esc_html( $install['plugin_build'] ); ?></span>
									<?php endif; ?>
								</td>
								<td data-label="<?php esc_attr_e( 'Last check-in', 'pckz-canonical-engine' ); ?>">
									<?php echo esc_html( $format_datetime( $install['last_check_in'] ?? '' ) ); ?>
									<?php if ( ! empty( $install['last_error'] ) ) : ?>
										<span class="pckz-install-error"><?php echo esc_html( $install['last_error'] ); ?></span>
									<?php endif; ?>
									<?php if ( ! empty( $tamper_signals ) ) : ?>
										<span class="pckz-mc-table__sub"><?php echo esc_html( sprintf( __( 'Tamper: %s', 'pckz-canonical-engine' ), implode( ', ', $tamper_signals ) ) ); ?></span>
									<?php endif; ?>
								</td>
								<td data-label="<?php esc_attr_e( 'Status', 'pckz-canonical-engine' ); ?>">
									<span class="pckz-license-badge <?php echo esc_attr( $badge_class( $install['status'] ?? '' ) ); ?>"><?php echo esc_html( $status_label( $install['status'] ?? '' ) ); ?></span>
								</td>
								<td class="pckz-license-actions" data-label="<?php esc_attr_e( 'Actions', 'pckz-canonical-engine' ); ?>">
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pckz-inline-form">
										<?php wp_nonce_field( 'pckzce_update_installation_status', 'pckzce_install_status_nonce' ); ?>
										<input type="hidden" name="action" value="pckzce_update_installation_status">
										<input type="hidden" name="redirect_section" value="records">
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
										<input type="hidden" name="redirect_section" value="records">
										<input type="hidden" name="installation_id" value="<?php echo esc_attr( (string) $install_id ); ?>">
										<input type="hidden" name="redirect_search" value="<?php echo esc_attr( $filter_search ); ?>">
										<input type="hidden" name="redirect_status" value="<?php echo esc_attr( $filter_status ); ?>">
										<input type="hidden" name="redirect_license_id" value="<?php echo esc_attr( (string) $filter_license_id ); ?>">
										<button type="submit" class="button button-small pckz-btn-danger" data-pckz-confirm="<?php esc_attr_e( 'Remove this installation record permanently?', 'pckz-canonical-engine' ); ?>"><?php esc_html_e( 'Remove', 'pckz-canonical-engine' ); ?></button>
									</form>
									<?php if ( ! empty( $tamper_signals ) ) : ?>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pckz-inline-form">
											<?php wp_nonce_field( 'pckzce_acknowledge_tamper_signals', 'pckzce_ack_tamper_nonce' ); ?>
											<input type="hidden" name="action" value="pckzce_acknowledge_tamper_signals">
											<input type="hidden" name="redirect_section" value="records">
											<input type="hidden" name="installation_id" value="<?php echo esc_attr( (string) $install_id ); ?>">
											<button type="submit" class="button button-small" data-pckz-confirm="<?php esc_attr_e( 'Acknowledge and clear tamper signals?', 'pckz-canonical-engine' ); ?>"><?php esc_html_e( 'Acknowledge', 'pckz-canonical-engine' ); ?></button>
										</form>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</article>

	<article class="pckz-mc-panel pckz-mc-panel--table" id="pckz-download-history">
		<h3><?php esc_html_e( 'Protected download history', 'pckz-canonical-engine' ); ?></h3>
		<p class="description"><?php esc_html_e( 'Audit trail of protected update packages downloaded by licensed client sites.', 'pckz-canonical-engine' ); ?></p>

		<div class="pckz-mc-filter-bar pckz-mc-filter-bar--client">
			<label class="screen-reader-text" for="pckz-download-filter"><?php esc_html_e( 'Filter downloads', 'pckz-canonical-engine' ); ?></label>
			<input type="search" id="pckz-download-filter" value="<?php echo esc_attr( $filter_download_s ); ?>" placeholder="<?php esc_attr_e( 'Filter by domain, UUID, or version…', 'pckz-canonical-engine' ); ?>" data-pckz-download-filter autocomplete="off">
			<span class="pckz-mc-filter-count" data-pckz-download-count></span>
		</div>

		<div class="pckz-mc-table-wrap">
			<table class="widefat striped pckz-mc-table" id="pckz-download-history-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'pckz-canonical-engine' ); ?></th>
						<th><?php esc_html_e( 'Domain', 'pckz-canonical-engine' ); ?></th>
						<th><?php esc_html_e( 'Install UUID', 'pckz-canonical-engine' ); ?></th>
						<th><?php esc_html_e( 'Version', 'pckz-canonical-engine' ); ?></th>
						<th><?php esc_html_e( 'Package', 'pckz-canonical-engine' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $downloads ) ) : ?>
						<tr class="pckz-download-row pckz-download-row--empty"><td colspan="5"><?php esc_html_e( 'No protected downloads recorded yet.', 'pckz-canonical-engine' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $downloads as $download ) : ?>
							<?php
							$row_search_blob = strtolower(
								implode(
									' ',
									array(
										(string) ( $download['domain'] ?? '' ),
										(string) ( $download['install_uuid'] ?? '' ),
										(string) ( $download['requested_version'] ?? '' ),
										(string) ( $download['package_url'] ?? '' ),
									)
								)
							);
							?>
							<tr class="pckz-download-row" data-search="<?php echo esc_attr( $row_search_blob ); ?>">
								<td data-label="<?php esc_attr_e( 'Date', 'pckz-canonical-engine' ); ?>"><?php echo esc_html( $format_datetime( $download['created_at'] ?? '' ) ); ?></td>
								<td data-label="<?php esc_attr_e( 'Domain', 'pckz-canonical-engine' ); ?>"><code><?php echo esc_html( $download['domain'] ?? '' ); ?></code></td>
								<td data-label="<?php esc_attr_e( 'Install UUID', 'pckz-canonical-engine' ); ?>"><code class="pckz-code-copy pckz-code-copy--uuid" data-copy="<?php echo esc_attr( $download['install_uuid'] ?? '' ); ?>"><?php echo esc_html( $download['install_uuid'] ?? '' ); ?></code></td>
								<td data-label="<?php esc_attr_e( 'Version', 'pckz-canonical-engine' ); ?>">
									<?php if ( ! empty( $download['requested_version'] ) ) : ?>
										<span class="pckz-license-badge is-muted"><?php echo esc_html( $download['requested_version'] ); ?></span>
									<?php else : ?>
										—
									<?php endif; ?>
								</td>
								<td data-label="<?php esc_attr_e( 'Package', 'pckz-canonical-engine' ); ?>"><code class="pckz-code-truncate" title="<?php echo esc_attr( $download['package_url'] ?? '' ); ?>"><?php echo esc_html( $download['package_url'] ?? '' ); ?></code></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</article>

	<?php if ( ! empty( $recent_errors ) ) : ?>
		<article class="pckz-mc-panel pckz-mc-panel--errors">
			<h3><?php esc_html_e( 'Recent client errors', 'pckz-canonical-engine' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Latest check-in failures reported by client installations.', 'pckz-canonical-engine' ); ?></p>
			<ul class="pckz-mc-error-list">
				<?php foreach ( $recent_errors as $error_row ) : ?>
					<li>
						<div class="pckz-mc-error-list__head">
							<strong><?php echo esc_html( $error_row['domain'] ?? '' ); ?></strong>
							<em><?php echo esc_html( $format_datetime( $error_row['updated_at'] ?? '' ) ); ?></em>
						</div>
						<code class="pckz-code-copy pckz-code-copy--uuid" data-copy="<?php echo esc_attr( $error_row['install_uuid'] ?? '' ); ?>"><?php echo esc_html( $error_row['install_uuid'] ?? '' ); ?></code>
						<p><?php echo esc_html( $error_row['last_error'] ?? '' ); ?></p>
					</li>
				<?php endforeach; ?>
			</ul>
		</article>
	<?php endif; ?>
</section>
