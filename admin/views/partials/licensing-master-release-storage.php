<?php
/**
 * Master Control — release storage inventory and maintenance.
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

$release_storage_inventory = is_array( $release_storage_inventory ?? null ) ? $release_storage_inventory : array();
$release_storage_summary   = is_array( $release_storage_summary ?? null ) ? $release_storage_summary : array();
$storage_search            = isset( $_GET['pckz_storage_search'] ) ? sanitize_text_field( wp_unslash( $_GET['pckz_storage_search'] ) ) : '';
$storage_type              = isset( $_GET['pckz_storage_type'] ) ? sanitize_key( wp_unslash( $_GET['pckz_storage_type'] ) ) : '';
$storage_validation        = isset( $_GET['pckz_storage_validation'] ) ? sanitize_key( wp_unslash( $_GET['pckz_storage_validation'] ) ) : '';
$storage_area              = isset( $_GET['pckz_storage_area'] ) ? sanitize_key( wp_unslash( $_GET['pckz_storage_area'] ) ) : '';
$storage_publish           = isset( $_GET['pckz_storage_publish'] ) ? sanitize_key( wp_unslash( $_GET['pckz_storage_publish'] ) ) : '';
$storage_base_url          = admin_url( 'admin.php?page=pckz-license-server' );

$status_badge = static function ( $status ) {
	$status = sanitize_key( (string) $status );
	$map    = array(
		'valid'       => 'is-success',
		'live'        => 'is-success',
		'stored'      => 'is-muted',
		'invalid'     => 'is-danger',
		'quarantined' => 'is-danger',
		'blocked'     => 'is-danger',
		'missing'     => 'is-warning',
		'unknown'     => 'is-muted',
		'ok'          => 'is-success',
	);
	return $map[ $status ] ?? 'is-muted';
};
?>

<section class="pckz-mc-section pckz-release-storage" id="pckz-release-storage">
	<header class="pckz-mc-section__header">
		<div>
			<h2><?php esc_html_e( 'Release storage', 'pckz-canonical-engine' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Inventory of stored protected packages with validation status, quarantine state, and maintenance tools.', 'pckz-canonical-engine' ); ?>
			</p>
		</div>
		<a class="button button-secondary" href="#pckz-master-section-releases"><?php esc_html_e( 'Software updates', 'pckz-canonical-engine' ); ?></a>
	</header>

	<div class="pckz-mc-kpi-grid pckz-release-storage__summary">
		<div class="pckz-mc-kpi">
			<span class="pckz-mc-kpi__value"><?php echo esc_html( number_format_i18n( (int) ( $release_storage_summary['total'] ?? 0 ) ) ); ?></span>
			<span class="pckz-mc-kpi__label"><?php esc_html_e( 'Stored packages', 'pckz-canonical-engine' ); ?></span>
		</div>
		<div class="pckz-mc-kpi pckz-mc-kpi--success">
			<span class="pckz-mc-kpi__value"><?php echo esc_html( number_format_i18n( (int) ( $release_storage_summary['valid'] ?? 0 ) ) ); ?></span>
			<span class="pckz-mc-kpi__label"><?php esc_html_e( 'Valid', 'pckz-canonical-engine' ); ?></span>
		</div>
		<div class="pckz-mc-kpi pckz-mc-kpi--danger">
			<span class="pckz-mc-kpi__value"><?php echo esc_html( number_format_i18n( (int) ( $release_storage_summary['invalid'] ?? 0 ) ) ); ?></span>
			<span class="pckz-mc-kpi__label"><?php esc_html_e( 'Invalid', 'pckz-canonical-engine' ); ?></span>
		</div>
		<div class="pckz-mc-kpi pckz-mc-kpi--warning">
			<span class="pckz-mc-kpi__value"><?php echo esc_html( number_format_i18n( (int) ( $release_storage_summary['quarantined'] ?? 0 ) ) ); ?></span>
			<span class="pckz-mc-kpi__label"><?php esc_html_e( 'Quarantined', 'pckz-canonical-engine' ); ?></span>
		</div>
	</div>

	<article class="pckz-mc-panel pckz-release-storage__maintenance">
		<h3><?php esc_html_e( 'Storage maintenance', 'pckz-canonical-engine' ); ?></h3>
		<p class="description"><?php esc_html_e( 'Keep release storage clean over time. Invalid packages are moved to quarantine instead of being left beside valid releases.', 'pckz-canonical-engine' ); ?></p>
		<div class="pckz-release-storage__maintenance-grid">
			<?php
			$maintenance_actions = array(
				'clean_invalid'       => __( 'Clean invalid stored packages', 'pckz-canonical-engine' ),
				'remove_legacy'       => __( 'Remove legacy protected releases', 'pckz-canonical-engine' ),
				'remove_master_files' => __( 'Remove packages containing master files', 'pckz-canonical-engine' ),
				'remove_duplicates'   => __( 'Remove duplicate stored releases', 'pckz-canonical-engine' ),
				'rebuild_metadata'    => __( 'Rebuild release metadata', 'pckz-canonical-engine' ),
			);
			foreach ( $maintenance_actions as $action_slug => $action_label ) :
				?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pckz-release-storage__maintenance-form">
					<?php wp_nonce_field( 'pckzce_release_storage_maintenance', 'pckzce_release_storage_nonce' ); ?>
					<input type="hidden" name="action" value="pckzce_release_storage_maintenance">
					<input type="hidden" name="redirect_section" value="storage">
					<input type="hidden" name="maintenance_action" value="<?php echo esc_attr( $action_slug ); ?>">
					<button type="submit" class="button button-secondary"><?php echo esc_html( $action_label ); ?></button>
				</form>
			<?php endforeach; ?>
		</div>
	</article>

	<form method="get" class="pckz-release-storage__filters">
		<input type="hidden" name="page" value="pckz-license-server">
		<input type="hidden" name="pckz_section" value="storage">
		<label>
			<span class="screen-reader-text"><?php esc_html_e( 'Search', 'pckz-canonical-engine' ); ?></span>
			<input type="search" name="pckz_storage_search" value="<?php echo esc_attr( $storage_search ); ?>" placeholder="<?php esc_attr_e( 'Search filename, version, build…', 'pckz-canonical-engine' ); ?>">
		</label>
		<label>
			<select name="pckz_storage_type">
				<option value=""><?php esc_html_e( 'All package types', 'pckz-canonical-engine' ); ?></option>
				<option value="client" <?php selected( $storage_type, 'client' ); ?>><?php esc_html_e( 'Client protected', 'pckz-canonical-engine' ); ?></option>
				<option value="master" <?php selected( $storage_type, 'master' ); ?>><?php esc_html_e( 'Master build', 'pckz-canonical-engine' ); ?></option>
				<option value="legacy" <?php selected( $storage_type, 'legacy' ); ?>><?php esc_html_e( 'Legacy', 'pckz-canonical-engine' ); ?></option>
			</select>
		</label>
		<label>
			<select name="pckz_storage_validation">
				<option value=""><?php esc_html_e( 'All validation statuses', 'pckz-canonical-engine' ); ?></option>
				<option value="valid" <?php selected( $storage_validation, 'valid' ); ?>><?php esc_html_e( 'Valid', 'pckz-canonical-engine' ); ?></option>
				<option value="invalid" <?php selected( $storage_validation, 'invalid' ); ?>><?php esc_html_e( 'Invalid', 'pckz-canonical-engine' ); ?></option>
				<option value="quarantined" <?php selected( $storage_validation, 'quarantined' ); ?>><?php esc_html_e( 'Quarantined', 'pckz-canonical-engine' ); ?></option>
			</select>
		</label>
		<label>
			<select name="pckz_storage_area">
				<option value=""><?php esc_html_e( 'All storage areas', 'pckz-canonical-engine' ); ?></option>
				<option value="active" <?php selected( $storage_area, 'active' ); ?>><?php esc_html_e( 'Active storage', 'pckz-canonical-engine' ); ?></option>
				<option value="quarantine" <?php selected( $storage_area, 'quarantine' ); ?>><?php esc_html_e( 'Quarantine', 'pckz-canonical-engine' ); ?></option>
			</select>
		</label>
		<label>
			<select name="pckz_storage_publish">
				<option value=""><?php esc_html_e( 'All publish statuses', 'pckz-canonical-engine' ); ?></option>
				<option value="live" <?php selected( $storage_publish, 'live' ); ?>><?php esc_html_e( 'Live for clients', 'pckz-canonical-engine' ); ?></option>
				<option value="stored" <?php selected( $storage_publish, 'stored' ); ?>><?php esc_html_e( 'Stored only', 'pckz-canonical-engine' ); ?></option>
				<option value="quarantined" <?php selected( $storage_publish, 'quarantined' ); ?>><?php esc_html_e( 'Quarantined', 'pckz-canonical-engine' ); ?></option>
				<option value="blocked" <?php selected( $storage_publish, 'blocked' ); ?>><?php esc_html_e( 'Blocked', 'pckz-canonical-engine' ); ?></option>
			</select>
		</label>
		<button type="submit" class="button"><?php esc_html_e( 'Filter', 'pckz-canonical-engine' ); ?></button>
		<a class="button button-link" href="<?php echo esc_url( $storage_base_url . '#pckz-master-section-storage' ); ?>"><?php esc_html_e( 'Reset', 'pckz-canonical-engine' ); ?></a>
	</form>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pckz-release-storage__bulk">
		<?php wp_nonce_field( 'pckzce_bulk_release_storage', 'pckzce_bulk_release_storage_nonce' ); ?>
		<input type="hidden" name="action" value="pckzce_bulk_release_storage">
		<input type="hidden" name="redirect_section" value="storage">
		<input type="hidden" name="bulk_action" value="delete_quarantined">

		<div class="pckz-mc-table-wrap">
			<table class="widefat striped pckz-mc-table pckz-release-storage__table">
				<thead>
					<tr>
						<td class="check-column"><input type="checkbox" data-pckz-select-all></td>
						<th><?php esc_html_e( 'Package', 'pckz-canonical-engine' ); ?></th>
						<th><?php esc_html_e( 'Type', 'pckz-canonical-engine' ); ?></th>
						<th><?php esc_html_e( 'Version', 'pckz-canonical-engine' ); ?></th>
						<th><?php esc_html_e( 'Build ID', 'pckz-canonical-engine' ); ?></th>
						<th><?php esc_html_e( 'Validation', 'pckz-canonical-engine' ); ?></th>
						<th><?php esc_html_e( 'Manifest', 'pckz-canonical-engine' ); ?></th>
						<th><?php esc_html_e( 'Checksum', 'pckz-canonical-engine' ); ?></th>
						<th><?php esc_html_e( 'Publish', 'pckz-canonical-engine' ); ?></th>
						<th><?php esc_html_e( 'Storage', 'pckz-canonical-engine' ); ?></th>
						<th><?php esc_html_e( 'Created', 'pckz-canonical-engine' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $release_storage_inventory ) ) : ?>
						<tr>
							<td colspan="11"><?php esc_html_e( 'No stored release packages match the current filters.', 'pckz-canonical-engine' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $release_storage_inventory as $storage_row ) : ?>
							<?php
							$filename   = (string) ( $storage_row['filename'] ?? '' );
							$is_quarantine = 'quarantine' === ( $storage_row['storage_area'] ?? '' );
							$issues     = array_merge(
								(array) ( $storage_row['master_only_files'] ?? array() ),
								(array) ( $storage_row['forbidden_files'] ?? array() )
							);
							?>
							<tr>
								<th scope="row" class="check-column">
									<?php if ( $is_quarantine ) : ?>
										<input type="checkbox" name="release_filenames[]" value="<?php echo esc_attr( $filename ); ?>">
									<?php endif; ?>
								</th>
								<td>
									<strong><?php echo esc_html( $filename ); ?></strong>
									<?php if ( ! empty( $storage_row['quarantine_reason'] ) ) : ?>
										<p class="description pckz-release-storage__reason"><?php echo esc_html( (string) $storage_row['quarantine_reason'] ); ?></p>
									<?php elseif ( ! empty( $storage_row['validation_message'] ) ) : ?>
										<p class="description pckz-release-storage__reason"><?php echo esc_html( (string) $storage_row['validation_message'] ); ?></p>
									<?php endif; ?>
									<?php if ( ! empty( $issues ) ) : ?>
										<ul class="pckz-release-storage__issues">
											<?php foreach ( array_slice( $issues, 0, 5 ) as $issue_path ) : ?>
												<li><code><?php echo esc_html( (string) $issue_path ); ?></code></li>
											<?php endforeach; ?>
										</ul>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( ucfirst( (string) ( $storage_row['package_type'] ?? '' ) ) ); ?></td>
								<td><?php echo esc_html( (string) ( $storage_row['version'] ?? '—' ) ); ?></td>
								<td><code><?php echo esc_html( (string) ( $storage_row['build_id'] ?? '—' ) ); ?></code></td>
								<td>
									<span class="pckz-license-badge <?php echo esc_attr( $status_badge( $storage_row['validation_status'] ?? '' ) ); ?>">
										<?php echo esc_html( ucfirst( (string) ( $storage_row['validation_status'] ?? 'unknown' ) ) ); ?>
									</span>
								</td>
								<td>
									<span class="pckz-license-badge <?php echo esc_attr( $status_badge( $storage_row['manifest_status'] ?? '' ) ); ?>">
										<?php echo esc_html( ucfirst( (string) ( $storage_row['manifest_status'] ?? 'unknown' ) ) ); ?>
									</span>
								</td>
								<td>
									<span class="pckz-license-badge <?php echo esc_attr( $status_badge( $storage_row['checksum_status'] ?? '' ) ); ?>">
										<?php echo esc_html( ucfirst( (string) ( $storage_row['checksum_status'] ?? 'unknown' ) ) ); ?>
									</span>
								</td>
								<td>
									<span class="pckz-license-badge <?php echo esc_attr( $status_badge( $storage_row['publish_status'] ?? '' ) ); ?>">
										<?php echo esc_html( ucfirst( str_replace( '_', ' ', (string) ( $storage_row['publish_status'] ?? 'unknown' ) ) ) ); ?>
									</span>
								</td>
								<td><code><?php echo esc_html( (string) ( $storage_row['storage_location'] ?? '' ) ); ?></code></td>
								<td><?php echo esc_html( $format_datetime( gmdate( 'Y-m-d H:i:s', (int) ( $storage_row['modified'] ?? 0 ) ) ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<p class="pckz-release-storage__bulk-actions">
			<button type="submit" class="button button-link-delete" data-pckz-confirm="<?php esc_attr_e( 'Permanently delete selected quarantined packages?', 'pckz-canonical-engine' ); ?>">
				<?php esc_html_e( 'Delete selected quarantined packages', 'pckz-canonical-engine' ); ?>
			</button>
		</p>
	</form>
</section>
