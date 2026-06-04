<?php
/**
 * Master Control — simplified release management.
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

$published_version = sanitize_text_field( (string) ( $release_meta['version'] ?? '' ) );
$published_at      = trim( (string) ( $release_meta['published_at'] ?? '' ) );
$published_notes   = (string) ( $release_meta['changelog'] ?? '' );
$pending_releases  = array();
$published_filename = '';

if ( ! empty( $protected_releases ) && is_array( $protected_releases ) ) {
	foreach ( $protected_releases as $release_row ) {
		$row_version = sanitize_text_field( (string) ( $release_row['version'] ?? '' ) );
		$is_live     = $published_version && $row_version === $published_version;
		if ( $is_live ) {
			$published_filename = (string) ( $release_row['filename'] ?? '' );
			continue;
		}
		$pending_releases[] = $release_row;
	}
}
?>

<section class="pckz-license-card pckz-license-card--full pckz-release-hub">
	<header class="pckz-release-hub__header">
		<div>
			<h2><?php esc_html_e( 'Software Updates', 'pckz-canonical-engine' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Publish a new plugin version for all licensed customer sites. After you publish, client dashboards and WordPress update screens pick up the release automatically — usually within a minute.', 'pckz-canonical-engine' ); ?>
			</p>
		</div>
		<div class="pckz-release-live" aria-live="polite">
			<span class="pckz-release-live__label"><?php esc_html_e( 'Live version for clients', 'pckz-canonical-engine' ); ?></span>
			<strong class="pckz-release-live__version">
				<?php echo esc_html( $published_version ? $published_version : __( 'Not published yet', 'pckz-canonical-engine' ) ); ?>
			</strong>
			<?php if ( $published_at ) : ?>
				<span class="pckz-release-live__meta">
					<?php
					printf(
						/* translators: %s: publish datetime */
						esc_html__( 'Published %s', 'pckz-canonical-engine' ),
						esc_html( $format_datetime( $published_at ) )
					);
					?>
				</span>
			<?php endif; ?>
		</div>
	</header>

	<div class="pckz-release-flow">
		<div class="pckz-release-flow__step">
			<span class="pckz-release-flow__number">1</span>
			<div class="pckz-release-flow__body">
				<h3><?php esc_html_e( 'Upload update package', 'pckz-canonical-engine' ); ?></h3>
				<p class="description">
					<?php esc_html_e( 'Choose the protected ZIP file you received from your build (for example pckz-canonical-engine-2.28.12-protected.zip).', 'pckz-canonical-engine' ); ?>
				</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="pckz-release-upload-form">
					<?php wp_nonce_field( 'pckzce_upload_protected_release', 'pckzce_upload_release_nonce' ); ?>
					<input type="hidden" name="action" value="pckzce_upload_protected_release">
					<input type="hidden" name="requires" value="6.0">
					<input type="hidden" name="requires_php" value="7.4">
					<input type="hidden" name="tested" value="<?php echo esc_attr( get_bloginfo( 'version' ) ); ?>">
					<p>
						<label for="pckz-protected-package" class="pckz-release-file-label">
							<strong><?php esc_html_e( 'Update file', 'pckz-canonical-engine' ); ?></strong>
						</label>
						<input id="pckz-protected-package" type="file" name="protected_package" accept=".zip,application/zip" required>
					</p>
					<p>
						<label for="pckz-upload-changelog"><strong><?php esc_html_e( 'What is new in this update? (optional)', 'pckz-canonical-engine' ); ?></strong></label>
						<textarea id="pckz-upload-changelog" name="changelog" rows="3" class="large-text" placeholder="<?php esc_attr_e( 'Brief notes shown to administrators on client sites…', 'pckz-canonical-engine' ); ?>"><?php echo esc_textarea( $published_notes ); ?></textarea>
					</p>
					<p>
						<label>
							<input type="checkbox" name="publish_after_upload" value="1" checked>
							<?php esc_html_e( 'Publish immediately for all licensed clients', 'pckz-canonical-engine' ); ?>
						</label>
					</p>
					<p>
						<button type="submit" class="button button-primary button-hero">
							<?php esc_html_e( 'Upload & Publish Update', 'pckz-canonical-engine' ); ?>
						</button>
					</p>
				</form>
			</div>
		</div>

		<?php if ( ! empty( $pending_releases ) ) : ?>
			<div class="pckz-release-flow__step">
				<span class="pckz-release-flow__number">2</span>
				<div class="pckz-release-flow__body">
					<h3><?php esc_html_e( 'Switch to an uploaded package', 'pckz-canonical-engine' ); ?></h3>
					<p class="description">
						<?php esc_html_e( 'If you uploaded without publishing, select a package here and publish it as the live client version.', 'pckz-canonical-engine' ); ?>
					</p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pckz-release-publish-form">
						<?php wp_nonce_field( 'pckzce_publish_release', 'pckzce_publish_release_nonce' ); ?>
						<input type="hidden" name="action" value="pckzce_publish_release">
						<input type="hidden" name="requires" value="6.0">
						<input type="hidden" name="requires_php" value="7.4">
						<input type="hidden" name="tested" value="<?php echo esc_attr( get_bloginfo( 'version' ) ); ?>">
						<p>
							<label for="pckz-publish-release"><strong><?php esc_html_e( 'Package on this server', 'pckz-canonical-engine' ); ?></strong></label>
							<select id="pckz-publish-release" name="release_filename" required>
								<option value=""><?php esc_html_e( 'Choose a package', 'pckz-canonical-engine' ); ?></option>
								<?php foreach ( $pending_releases as $release_row ) : ?>
									<option value="<?php echo esc_attr( $release_row['filename'] ?? '' ); ?>">
										<?php
										echo esc_html(
											sprintf(
												/* translators: 1: version, 2: file size */
												__( 'Version %1$s (%2$s)', 'pckz-canonical-engine' ),
												$release_row['version'] ?? '',
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
							<textarea id="pckz-publish-changelog" name="changelog" rows="3" class="large-text"><?php echo esc_textarea( $published_notes ); ?></textarea>
						</p>
						<p>
							<button type="submit" class="button button-primary" data-pckz-confirm="<?php esc_attr_e( 'Publish this package so all licensed client sites can install it?', 'pckz-canonical-engine' ); ?>">
								<?php esc_html_e( 'Publish Selected Package', 'pckz-canonical-engine' ); ?>
							</button>
						</p>
					</form>
				</div>
			</div>
		<?php endif; ?>
	</div>

	<?php if ( ! empty( $protected_releases ) ) : ?>
		<div class="pckz-release-inventory pckz-release-inventory--simple">
			<h3><?php esc_html_e( 'Packages on this server', 'pckz-canonical-engine' ); ?></h3>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Version', 'pckz-canonical-engine' ); ?></th>
						<th><?php esc_html_e( 'File size', 'pckz-canonical-engine' ); ?></th>
						<th><?php esc_html_e( 'Status', 'pckz-canonical-engine' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $protected_releases as $release_row ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $release_row['version'] ?? '' ); ?></strong></td>
							<td><?php echo esc_html( size_format( (int) ( $release_row['size'] ?? 0 ), 1 ) ); ?></td>
							<td>
								<?php if ( (string) ( $release_meta['version'] ?? '' ) === (string) ( $release_row['version'] ?? '' ) ) : ?>
									<span class="pckz-license-badge is-success"><?php esc_html_e( 'Live for clients', 'pckz-canonical-engine' ); ?></span>
								<?php else : ?>
									<span class="pckz-license-badge is-muted"><?php esc_html_e( 'Uploaded only', 'pckz-canonical-engine' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>

	<details class="pckz-release-advanced">
		<summary><?php esc_html_e( 'Advanced settings (technical)', 'pckz-canonical-engine' ); ?></summary>
		<p class="description">
			<?php esc_html_e( 'Only use this if support asked you to set a custom download URL or minimum client version.', 'pckz-canonical-engine' ); ?>
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'pckzce_save_release_meta', 'pckzce_release_nonce' ); ?>
			<input type="hidden" name="action" value="pckzce_save_release_meta">
			<p>
				<label for="pckz-release-version"><strong><?php esc_html_e( 'Version number', 'pckz-canonical-engine' ); ?></strong></label>
				<input id="pckz-release-version" type="text" class="regular-text" name="version" value="<?php echo esc_attr( $release_meta['version'] ?? '' ); ?>">
			</p>
			<p>
				<label for="pckz-release-package"><strong><?php esc_html_e( 'Download URL', 'pckz-canonical-engine' ); ?></strong></label>
				<input id="pckz-release-package" type="url" class="large-text" name="package_url" value="<?php echo esc_attr( $release_meta['package_url'] ?? '' ); ?>">
			</p>
			<p>
				<label for="pckz-release-changelog"><strong><?php esc_html_e( 'Release notes', 'pckz-canonical-engine' ); ?></strong></label>
				<textarea id="pckz-release-changelog" name="changelog" rows="3" class="large-text"><?php echo esc_textarea( $release_meta['changelog'] ?? '' ); ?></textarea>
			</p>
			<p>
				<label for="pckz-release-min-build"><strong><?php esc_html_e( 'Minimum client version required', 'pckz-canonical-engine' ); ?></strong></label>
				<input id="pckz-release-min-build" type="text" class="regular-text" name="min_client_build" value="<?php echo esc_attr( $release_meta['min_client_build'] ?? '' ); ?>">
			</p>
			<p>
				<label for="pckz-release-severity"><strong><?php esc_html_e( 'Priority', 'pckz-canonical-engine' ); ?></strong></label>
				<select id="pckz-release-severity" name="update_severity">
					<option value="" <?php selected( $release_meta['update_severity'] ?? '', '' ); ?>><?php esc_html_e( 'Normal update', 'pckz-canonical-engine' ); ?></option>
					<option value="critical" <?php selected( $release_meta['update_severity'] ?? '', 'critical' ); ?>><?php esc_html_e( 'Important security update', 'pckz-canonical-engine' ); ?></option>
				</select>
			</p>
			<p><button type="submit" class="button"><?php esc_html_e( 'Save advanced settings', 'pckz-canonical-engine' ); ?></button></p>
		</form>
	</details>
</section>
