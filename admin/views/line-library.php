<?php
/**
 * Admin: Line Library (Linien) CMS.
 *
 * @package PCKZCanonicalEngine
 * @var array<string,array> $catalog
 * @var string[]            $disabled
 * @var array               $payload
 */

defined( 'ABSPATH' ) || exit;

$payload_json = wp_json_encode( $payload ?? PCKZ_Line_Library::build_admin_save_payload() );
$hero_title       = __( 'Line Library', 'pckz-canonical-engine' );
$hero_description = __( 'Upload SVG line designs, rename labels, and control customer visibility. Built-in line types cannot be deleted.', 'pckz-canonical-engine' );
$hero_badge       = __( 'Linien', 'pckz-canonical-engine' );
?>
<div class="wrap pckz-admin-wrap pckz-line-library-admin">
	<?php include PCKZCE_PLUGIN_DIR . 'admin/views/partials/page-hero.php'; ?>

	<div class="pckz-panel">
		<header class="pckz-panel__header">
			<h2><?php esc_html_e( 'Upload line design (SVG)', 'pckz-canonical-engine' ); ?></h2>
		</header>
		<div class="pckz-panel__body">
		<form method="post" enctype="multipart/form-data">
			<?php wp_nonce_field( 'pckz_line_library_upload', 'pckz_line_library_upload_nonce' ); ?>
			<input type="hidden" name="pckz_line_library_upload" value="1">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'SVG file', 'pckz-canonical-engine' ); ?></th>
					<td><input type="file" name="pckz_line_file" accept=".svg,image/svg+xml" required></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Display label', 'pckz-canonical-engine' ); ?></th>
					<td><input type="text" class="regular-text" name="line_upload_label" placeholder="<?php esc_attr_e( 'e.g. Typ 72', 'pckz-canonical-engine' ); ?>"></td>
				</tr>
			</table>
			<?php submit_button( __( 'Upload line', 'pckz-canonical-engine' ), 'secondary' ); ?>
		</form>
		</div>
	</div>

	<div class="pckz-panel">
		<header class="pckz-panel__header">
			<h2><?php esc_html_e( 'Line inventory', 'pckz-canonical-engine' ); ?></h2>
		</header>
		<div class="pckz-panel__body">
	<form method="post" action="" id="pckz-line-library-save-form">
		<?php wp_nonce_field( 'pckz_line_library_save', 'pckz_line_library_nonce' ); ?>
		<input type="hidden" name="pckz_line_library_save" value="1">
		<textarea
			name="pckz_line_library_payload"
			id="pckz-line-library-payload"
			class="screen-reader-text"
			aria-hidden="true"
			rows="1"
			cols="1"
		><?php echo esc_textarea( $payload_json ); ?></textarea>

		<p>
			<button type="button" class="button" id="pckz-line-enable-all"><?php esc_html_e( 'Enable all', 'pckz-canonical-engine' ); ?></button>
			<button type="button" class="button" id="pckz-line-disable-all"><?php esc_html_e( 'Disable all', 'pckz-canonical-engine' ); ?></button>
		</p>

		<table class="widefat striped pckz-line-library-table">
			<thead>
				<tr>
					<th style="width:120px"><?php esc_html_e( 'Preview', 'pckz-canonical-engine' ); ?></th>
					<th><?php esc_html_e( 'Label', 'pckz-canonical-engine' ); ?></th>
					<th><?php esc_html_e( 'Slug', 'pckz-canonical-engine' ); ?></th>
					<th style="width:90px"><?php esc_html_e( 'Customer', 'pckz-canonical-engine' ); ?></th>
					<th style="width:80px"><?php esc_html_e( 'Actions', 'pckz-canonical-engine' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $catalog as $slug => $data ) :
					if ( 'none' === $slug ) {
						continue;
					}
					$thumb     = ! empty( $data['preview'] ) ? $data['preview'] : ( $data['url'] ?? '' );
					$label     = $data['label'] ?? $slug;
					$enabled   = PCKZ_Line_Library::is_visible( $slug );
					$is_custom = ! empty( $data['custom'] );
					?>
					<tr data-line-slug="<?php echo esc_attr( $slug ); ?>">
						<td>
							<?php if ( $thumb ) : ?>
								<img src="<?php echo esc_url( $thumb ); ?>" alt="" width="96" height="32" style="object-fit:contain;background:#eee;border-radius:4px;max-width:100%;">
							<?php else : ?>
								<span aria-hidden="true">—</span>
							<?php endif; ?>
						</td>
						<td>
							<input type="text" class="regular-text pckz-line-label" value="<?php echo esc_attr( $label ); ?>">
						</td>
						<td>
							<code><?php echo esc_html( $slug ); ?></code>
							<?php if ( $is_custom ) : ?>
								<br><span class="pckz-badge"><?php esc_html_e( 'Upload', 'pckz-canonical-engine' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<label>
								<input type="checkbox" class="pckz-line-enabled" <?php checked( $enabled ); ?>>
								<?php esc_html_e( 'On', 'pckz-canonical-engine' ); ?>
							</label>
						</td>
						<td>
							<?php if ( $is_custom ) : ?>
								<button type="submit" class="button-link-delete" name="pckz_line_delete" value="<?php echo esc_attr( $slug ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this line design?', 'pckz-canonical-engine' ) ); ?>');">
									<?php esc_html_e( 'Delete', 'pckz-canonical-engine' ); ?>
								</button>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php submit_button( __( 'Save line library', 'pckz-canonical-engine' ) ); ?>
	</form>
		</div>
	</div>
</div>
