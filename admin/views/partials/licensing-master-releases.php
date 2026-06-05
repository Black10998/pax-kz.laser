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

<section class="pckz-mc-section pckz-release-hub">
	<header class="pckz-mc-section__header pckz-release-hub__header">
		<div>
			<h2><?php esc_html_e( 'Software updates', 'pckz-canonical-engine' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Build, upload, and publish protected update packages for all licensed customer sites.', 'pckz-canonical-engine' ); ?>
			</p>
		</div>
		<div class="pckz-release-live" aria-live="polite">
			<span class="pckz-release-live__label"><?php esc_html_e( 'Live for clients', 'pckz-canonical-engine' ); ?></span>
			<strong class="pckz-release-live__version">
				<?php echo esc_html( $published_version ? $published_version : __( 'Not published', 'pckz-canonical-engine' ) ); ?>
			</strong>
			<?php if ( $published_at ) : ?>
				<span class="pckz-release-live__meta">
					<?php
					printf(
						esc_html__( 'Published %s', 'pckz-canonical-engine' ),
						esc_html( $format_datetime( $published_at ) )
					);
					?>
				</span>
			<?php endif; ?>
		</div>
	</header>

	<div class="pckz-mc-panel pckz-release-notes-panel">
		<label for="pckz-shared-release-changelog"><strong><?php esc_html_e( 'Release notes (shared)', 'pckz-canonical-engine' ); ?></strong></label>
		<p class="description"><?php esc_html_e( 'Optional notes shown to administrators on client sites. Used when you generate, upload, or publish below.', 'pckz-canonical-engine' ); ?></p>
		<textarea id="pckz-shared-release-changelog" rows="3" class="large-text pckz-shared-changelog-source" placeholder="<?php esc_attr_e( 'Brief notes for this update…', 'pckz-canonical-engine' ); ?>"><?php echo esc_textarea( $published_notes ); ?></textarea>
	</div>

	<div class="pckz-mc-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Release workflow', 'pckz-canonical-engine' ); ?>">
		<button type="button" class="pckz-mc-tabs__tab is-active" data-pckz-release-tab="generate" role="tab" aria-selected="true"><?php esc_html_e( 'Generate', 'pckz-canonical-engine' ); ?></button>
		<button type="button" class="pckz-mc-tabs__tab" data-pckz-release-tab="upload" role="tab" aria-selected="false"><?php esc_html_e( 'Upload', 'pckz-canonical-engine' ); ?></button>
		<?php if ( ! empty( $pending_releases ) ) : ?>
			<button type="button" class="pckz-mc-tabs__tab" data-pckz-release-tab="publish" role="tab" aria-selected="false"><?php esc_html_e( 'Publish pending', 'pckz-canonical-engine' ); ?></button>
		<?php endif; ?>
	</div>

	<div class="pckz-release-flow">
		<div class="pckz-mc-panel pckz-release-tab-panel is-active" data-pckz-release-panel="generate">
				<h3><?php esc_html_e( 'Generate protected package', 'pckz-canonical-engine' ); ?></h3>
				<p class="description">
					<?php esc_html_e( 'Build a publish-ready protected ZIP from this master installation.', 'pckz-canonical-engine' ); ?>
				</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pckz-release-generate-form pckz-mc-form" data-pckz-sync-changelog>
					<?php wp_nonce_field( 'pckzce_generate_protected_release', 'pckzce_generate_release_nonce' ); ?>
					<input type="hidden" name="action" value="pckzce_generate_protected_release">
					<input type="hidden" name="redirect_section" value="releases">
					<input type="hidden" name="requires" value="6.0">
					<input type="hidden" name="requires_php" value="7.4">
					<input type="hidden" name="tested" value="<?php echo esc_attr( get_bloginfo( 'version' ) ); ?>">
					<div class="pckz-mc-form__grid">
						<p>
							<label for="pckz-generate-version"><strong><?php esc_html_e( 'Release version', 'pckz-canonical-engine' ); ?></strong></label>
							<input id="pckz-generate-version" type="text" class="regular-text" name="release_version" value="<?php echo esc_attr( PCKZCE_VERSION ); ?>" required>
						</p>
						<p>
							<label for="pckz-generate-build"><strong><?php esc_html_e( 'Build ID', 'pckz-canonical-engine' ); ?></strong></label>
							<input id="pckz-generate-build" type="text" class="regular-text" name="release_build" value="<?php echo esc_attr( PCKZCE_BUILD ); ?>" placeholder="<?php esc_attr_e( 'Auto-generated when blank', 'pckz-canonical-engine' ); ?>">
						</p>
					</div>
					<input type="hidden" name="changelog" value="">
					<p>
						<label>
							<input type="checkbox" name="download_after_generate" value="1" checked>
							<?php esc_html_e( 'Download ZIP immediately after generation', 'pckz-canonical-engine' ); ?>
						</label>
					</p>
					<p>
						<label>
							<input type="checkbox" name="publish_after_generate" value="1">
							<?php esc_html_e( 'Publish immediately for all licensed clients', 'pckz-canonical-engine' ); ?>
						</label>
					</p>
					<p class="pckz-release-generate-actions">
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Generate protected package', 'pckz-canonical-engine' ); ?></button>
						<?php
						$bundled_download = PCKZ_Licensing::protected_release_filename_for_version( PCKZCE_VERSION );
						$bundled_url      = wp_nonce_url(
							add_query_arg(
								array(
									'action'           => 'pckzce_download_protected_release',
									'release_filename' => $bundled_download,
								),
								admin_url( 'admin-post.php' )
							),
							'pckzce_download_protected_release'
						);
						?>
						<a class="button button-secondary" href="<?php echo esc_url( $bundled_url ); ?>">
							<?php
							printf(
								/* translators: %s: plugin version */
								esc_html__( 'Download bundled v%s package', 'pckz-canonical-engine' ),
								esc_html( PCKZCE_VERSION )
							);
							?>
						</a>
					</p>
				</form>
		</div>

		<div class="pckz-mc-panel pckz-release-tab-panel" data-pckz-release-panel="upload">
				<h3><?php esc_html_e( 'Upload update package', 'pckz-canonical-engine' ); ?></h3>
				<p class="description">
					<?php esc_html_e( 'Or upload a protected ZIP that was generated elsewhere. The archive must use the pckz-canonical-engine/ layout and matching version fields.', 'pckz-canonical-engine' ); ?>
				</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="pckz-release-upload-form pckz-mc-form" data-pckz-sync-changelog>
					<?php wp_nonce_field( 'pckzce_upload_protected_release', 'pckzce_upload_release_nonce' ); ?>
					<input type="hidden" name="action" value="pckzce_upload_protected_release">
					<input type="hidden" name="redirect_section" value="releases">
					<input type="hidden" name="requires" value="6.0">
					<input type="hidden" name="requires_php" value="7.4">
					<input type="hidden" name="tested" value="<?php echo esc_attr( get_bloginfo( 'version' ) ); ?>">
					<input type="hidden" name="changelog" value="">
					<p>
						<label for="pckz-protected-package"><strong><?php esc_html_e( 'Protected ZIP file', 'pckz-canonical-engine' ); ?></strong></label>
						<input id="pckz-protected-package" type="file" name="protected_package" accept=".zip,application/zip" required>
					</p>
					<p>
						<label>
							<input type="checkbox" name="publish_after_upload" value="1" checked>
							<?php esc_html_e( 'Publish immediately for all licensed clients', 'pckz-canonical-engine' ); ?>
						</label>
					</p>
					<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Upload & publish', 'pckz-canonical-engine' ); ?></button></p>
				</form>
		</div>

		<?php if ( ! empty( $pending_releases ) ) : ?>
			<div class="pckz-mc-panel pckz-release-tab-panel" data-pckz-release-panel="publish">
					<h3><?php esc_html_e( 'Publish pending package', 'pckz-canonical-engine' ); ?></h3>
					<p class="description">
						<?php esc_html_e( 'If you uploaded without publishing, select a package here and publish it as the live client version.', 'pckz-canonical-engine' ); ?>
					</p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pckz-release-publish-form pckz-mc-form" data-pckz-sync-changelog>
						<?php wp_nonce_field( 'pckzce_publish_release', 'pckzce_publish_release_nonce' ); ?>
						<input type="hidden" name="action" value="pckzce_publish_release">
						<input type="hidden" name="redirect_section" value="releases">
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
						<input type="hidden" name="changelog" value="">
						<p><button type="submit" class="button button-primary" data-pckz-confirm="<?php esc_attr_e( 'Publish this package for all licensed client sites?', 'pckz-canonical-engine' ); ?>"><?php esc_html_e( 'Publish selected package', 'pckz-canonical-engine' ); ?></button></p>
					</form>
			</div>
		<?php endif; ?>
	</div>

	<?php if ( ! empty( $protected_releases ) ) : ?>
		<article class="pckz-mc-panel pckz-release-inventory">
			<h3><?php esc_html_e( 'Stored protected packages', 'pckz-canonical-engine' ); ?></h3>
			<div class="pckz-mc-table-wrap">
			<table class="widefat striped pckz-mc-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Version', 'pckz-canonical-engine' ); ?></th>
						<th><?php esc_html_e( 'File size', 'pckz-canonical-engine' ); ?></th>
						<th><?php esc_html_e( 'Status', 'pckz-canonical-engine' ); ?></th>
						<th><?php esc_html_e( 'Download', 'pckz-canonical-engine' ); ?></th>
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
							<td>
								<?php
								$download_url = wp_nonce_url(
									add_query_arg(
										array(
											'action'           => 'pckzce_download_protected_release',
											'release_filename' => (string) ( $release_row['filename'] ?? '' ),
										),
										admin_url( 'admin-post.php' )
									),
									'pckzce_download_protected_release'
								);
								?>
								<a class="button button-small" href="<?php echo esc_url( $download_url ); ?>">
									<?php esc_html_e( 'Download', 'pckz-canonical-engine' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			</div>
		</article>
	<?php endif; ?>

	<details class="pckz-mc-advanced pckz-release-advanced">
		<summary><?php esc_html_e( 'Advanced settings (technical)', 'pckz-canonical-engine' ); ?></summary>
		<p class="description">
			<?php esc_html_e( 'Only use this if support asked you to set a custom download URL or minimum client version.', 'pckz-canonical-engine' ); ?>
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'pckzce_save_release_meta', 'pckzce_release_nonce' ); ?>
			<input type="hidden" name="action" value="pckzce_save_release_meta">
			<input type="hidden" name="redirect_section" value="releases">
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
