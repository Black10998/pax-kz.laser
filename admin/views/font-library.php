<?php
/**
 * Admin: Font Library CMS.
 *
 * @package PCKZCanonicalEngine
 * @var array<string,array> $entries
 * @var string[]            $disabled
 * @var array<string,string> $categories
 */

defined( 'ABSPATH' ) || exit;

$disabled_lookup = array_fill_keys( $disabled, true );
$hero_title       = __( 'Font Library', 'pckz-canonical-engine' );
$hero_description = __( 'Manage fonts for the customer configurator. Built-in Google Fonts cannot be deleted—only hidden. Upload WOFF2, WOFF, TTF, or OTF for custom fonts.', 'pckz-canonical-engine' );
$hero_badge       = __( 'Fonts', 'pckz-canonical-engine' );
?>
<div class="wrap pckz-admin-wrap pckz-font-library-admin">
	<?php include PCKZCE_PLUGIN_DIR . 'admin/views/partials/page-hero.php'; ?>

	<div class="pckz-panel">
		<header class="pckz-panel__header">
			<h2><?php esc_html_e( 'Upload font', 'pckz-canonical-engine' ); ?></h2>
		</header>
		<div class="pckz-panel__body">
		<form method="post" enctype="multipart/form-data">
			<?php wp_nonce_field( 'pckz_font_library_upload', 'pckz_font_library_upload_nonce' ); ?>
			<input type="hidden" name="pckz_font_library_upload" value="1">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Font file', 'pckz-canonical-engine' ); ?></th>
					<td><input type="file" name="pckz_font_file" accept=".woff2,.woff,.ttf,.otf,font/woff2,font/woff,font/ttf,font/otf" required></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Display label', 'pckz-canonical-engine' ); ?></th>
					<td><input type="text" class="regular-text" name="font_upload_label" placeholder="<?php esc_attr_e( 'My Brand Font', 'pckz-canonical-engine' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'CSS font-family', 'pckz-canonical-engine' ); ?></th>
					<td>
						<input type="text" class="regular-text" name="font_upload_family" placeholder="<?php esc_attr_e( 'Same as label if empty', 'pckz-canonical-engine' ); ?>">
						<p class="description"><?php esc_html_e( 'Must match the font’s internal family name for preview and export.', 'pckz-canonical-engine' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Upload font', 'pckz-canonical-engine' ), 'secondary' ); ?>
		</form>
		</div>
	</div>

	<div class="pckz-panel">
		<header class="pckz-panel__header">
			<h2><?php esc_html_e( 'Font inventory', 'pckz-canonical-engine' ); ?></h2>
			<p><?php esc_html_e( 'Enable or disable fonts shown to customers in the configurator.', 'pckz-canonical-engine' ); ?></p>
		</header>
		<div class="pckz-panel__body">
	<form method="post" action="">
		<?php wp_nonce_field( 'pckz_font_library_save', 'pckz_font_library_nonce' ); ?>
		<input type="hidden" name="pckz_font_library_save" value="1">

		<p>
			<button type="button" class="button" id="pckz-font-enable-all"><?php esc_html_e( 'Enable all', 'pckz-canonical-engine' ); ?></button>
			<button type="button" class="button" id="pckz-font-disable-all"><?php esc_html_e( 'Disable all', 'pckz-canonical-engine' ); ?></button>
		</p>

		<table class="widefat striped pckz-font-library-table">
			<thead>
				<tr>
					<th style="width:200px"><?php esc_html_e( 'Preview', 'pckz-canonical-engine' ); ?></th>
					<th><?php esc_html_e( 'Label', 'pckz-canonical-engine' ); ?></th>
					<th><?php esc_html_e( 'Family / ID', 'pckz-canonical-engine' ); ?></th>
					<th><?php esc_html_e( 'Category', 'pckz-canonical-engine' ); ?></th>
					<th style="width:90px"><?php esc_html_e( 'Customer', 'pckz-canonical-engine' ); ?></th>
					<th style="width:80px"><?php esc_html_e( 'Actions', 'pckz-canonical-engine' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $entries as $id => $font ) :
					$enabled  = empty( $disabled_lookup[ $id ] );
					$family   = $font['family'] ?? '';
					$sample   = $font['sample'] ?? 'Aa Bb 123';
					$cat      = $categories[ $font['category'] ?? '' ] ?? ( $font['category'] ?? '' );
					$is_custom = empty( $font['builtin'] );
					?>
					<tr>
						<td>
							<span class="pckz-admin-font-preview" style="font-family: <?php echo esc_attr( $family ); ?>, sans-serif; font-size: 26px; font-weight: 700;">
								<?php echo esc_html( $sample ); ?>
							</span>
						</td>
						<td>
							<input type="text" class="regular-text" name="pckz_font_labels[<?php echo esc_attr( $id ); ?>]" value="<?php echo esc_attr( $font['label'] ?? '' ); ?>">
						</td>
						<td>
							<code><?php echo esc_html( $id ); ?></code><br>
							<small><?php echo esc_html( $family ); ?></small>
							<?php if ( $is_custom ) : ?>
								<br><span class="pckz-badge"><?php esc_html_e( 'Upload', 'pckz-canonical-engine' ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $cat ); ?></td>
						<td>
							<label>
								<input type="checkbox" name="pckz_font_enabled[]" value="<?php echo esc_attr( $id ); ?>" <?php checked( $enabled ); ?>>
								<?php esc_html_e( 'On', 'pckz-canonical-engine' ); ?>
							</label>
						</td>
						<td>
							<?php if ( $is_custom ) : ?>
								<button type="submit" class="button-link-delete" name="pckz_font_delete" value="<?php echo esc_attr( $id ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this font file?', 'pckz-canonical-engine' ) ); ?>');">
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

		<?php submit_button( __( 'Save font library', 'pckz-canonical-engine' ) ); ?>
	</form>
		</div>
	</div>
</div>
<script>
(function () {
	const table = document.querySelector('.pckz-font-library-table');
	if (!table) return;
	const boxes = () => table.querySelectorAll('input[type="checkbox"][name="pckz_font_enabled[]"]');
	document.getElementById('pckz-font-enable-all')?.addEventListener('click', () => boxes().forEach((c) => { c.checked = true; }));
	document.getElementById('pckz-font-disable-all')?.addEventListener('click', () => boxes().forEach((c) => { c.checked = false; }));
})();
</script>
