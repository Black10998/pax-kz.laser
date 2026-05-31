<?php
/**
 * Admin: Icon Library CMS.
 *
 * @package PCKZCanonicalEngine
 * @var array<string,array> $catalog
 * @var string[]            $disabled
 */

defined( 'ABSPATH' ) || exit;

$disabled_lookup = array_fill_keys( $disabled, true );
$hero_title       = __( 'Icon Library', 'pckz-canonical-engine' );
$hero_description = __( 'Upload SVG icons, rename labels, and control customer visibility. Bundled icons cannot be deleted.', 'pckz-canonical-engine' );
$hero_badge       = __( 'Icons', 'pckz-canonical-engine' );
?>
<div class="wrap pckz-admin-wrap pckz-icon-library-admin">
	<?php include PCKZCE_PLUGIN_DIR . 'admin/views/partials/page-hero.php'; ?>

	<div class="pckz-panel">
		<header class="pckz-panel__header">
			<h2><?php esc_html_e( 'Upload icon (SVG)', 'pckz-canonical-engine' ); ?></h2>
		</header>
		<div class="pckz-panel__body">
		<form method="post" enctype="multipart/form-data">
			<?php wp_nonce_field( 'pckz_icon_library_upload', 'pckz_icon_library_upload_nonce' ); ?>
			<input type="hidden" name="pckz_icon_library_upload" value="1">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'SVG file', 'pckz-canonical-engine' ); ?></th>
					<td><input type="file" name="pckz_icon_file" accept=".svg,image/svg+xml" required></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Display label', 'pckz-canonical-engine' ); ?></th>
					<td><input type="text" class="regular-text" name="icon_upload_label" placeholder="<?php esc_attr_e( 'e.g. Company Logo', 'pckz-canonical-engine' ); ?>"></td>
				</tr>
			</table>
			<?php submit_button( __( 'Upload icon', 'pckz-canonical-engine' ), 'secondary' ); ?>
		</form>
		</div>
	</div>

	<div class="pckz-panel">
		<header class="pckz-panel__header">
			<h2><?php esc_html_e( 'Icon inventory', 'pckz-canonical-engine' ); ?></h2>
		</header>
		<div class="pckz-panel__body">
	<form method="post" action="">
		<?php wp_nonce_field( 'pckz_icon_library_save', 'pckz_icon_library_nonce' ); ?>
		<input type="hidden" name="pckz_icon_library_save" value="1">

		<p>
			<button type="button" class="button" id="pckz-icon-enable-all"><?php esc_html_e( 'Enable all', 'pckz-canonical-engine' ); ?></button>
			<button type="button" class="button" id="pckz-icon-disable-all"><?php esc_html_e( 'Disable all', 'pckz-canonical-engine' ); ?></button>
		</p>

		<table class="widefat striped pckz-icon-library-table">
			<thead>
				<tr>
					<th style="width:72px"><?php esc_html_e( 'Preview', 'pckz-canonical-engine' ); ?></th>
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
					$enabled   = empty( $disabled_lookup[ $slug ] );
					$is_custom = ! empty( $data['custom'] );
					?>
					<tr>
						<td>
							<?php if ( $thumb ) : ?>
								<img src="<?php echo esc_url( $thumb ); ?>" alt="" width="48" height="48" style="object-fit:contain;background:#eee;border-radius:4px;">
							<?php else : ?>
								<span aria-hidden="true">—</span>
							<?php endif; ?>
						</td>
						<td>
							<input type="text" class="regular-text" name="pckz_icon_labels[<?php echo esc_attr( $slug ); ?>]" value="<?php echo esc_attr( $label ); ?>">
						</td>
						<td>
							<code><?php echo esc_html( $slug ); ?></code>
							<?php if ( $is_custom ) : ?>
								<br><span class="pckz-badge"><?php esc_html_e( 'Upload', 'pckz-canonical-engine' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<label>
								<input type="checkbox" name="pckz_icon_enabled[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $enabled ); ?>>
								<?php esc_html_e( 'On', 'pckz-canonical-engine' ); ?>
							</label>
						</td>
						<td>
							<?php if ( $is_custom ) : ?>
								<button type="submit" class="button-link-delete" name="pckz_icon_delete" value="<?php echo esc_attr( $slug ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this icon?', 'pckz-canonical-engine' ) ); ?>');">
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

		<?php submit_button( __( 'Save icon library', 'pckz-canonical-engine' ) ); ?>
	</form>
		</div>
	</div>
</div>
<script>
(function () {
	const table = document.querySelector('.pckz-icon-library-table');
	if (!table) return;
	const boxes = () => table.querySelectorAll('input[type="checkbox"][name="pckz_icon_enabled[]"]');
	document.getElementById('pckz-icon-enable-all')?.addEventListener('click', () => boxes().forEach((c) => { c.checked = true; }));
	document.getElementById('pckz-icon-disable-all')?.addEventListener('click', () => boxes().forEach((c) => { c.checked = false; }));
})();
</script>
