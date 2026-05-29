<?php
/**
 * Admin: enable/disable icons for customer selector.
 *
 * @package PCKZCanonicalEngine
 * @var array<string,array> $catalog
 * @var string[]            $disabled
 */

defined( 'ABSPATH' ) || exit;

$disabled_lookup = array_fill_keys( $disabled, true );
?>
<div class="wrap pckz-admin-wrap">
	<h1><?php esc_html_e( 'Icon Library', 'pckz-canonical-engine' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Enable or disable icons for the customer-facing symbol selector. SVG files are never deleted—disabled icons are hidden until re-enabled.', 'pckz-canonical-engine' ); ?>
	</p>

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
					<th style="width:100px"><?php esc_html_e( 'Customer', 'pckz-canonical-engine' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $catalog as $slug => $data ) :
					if ( 'none' === $slug ) {
						continue;
					}
					$thumb   = ! empty( $data['preview'] ) ? $data['preview'] : ( $data['url'] ?? '' );
					$label   = $data['label'] ?? $slug;
					$enabled = empty( $disabled_lookup[ $slug ] );
					?>
					<tr>
						<td>
							<?php if ( $thumb ) : ?>
								<img src="<?php echo esc_url( $thumb ); ?>" alt="" width="48" height="48" style="object-fit:contain;background:#eee;border-radius:4px;">
							<?php else : ?>
								<span aria-hidden="true">—</span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $label ); ?></td>
						<td><code><?php echo esc_html( $slug ); ?></code></td>
						<td>
							<label>
								<input
									type="checkbox"
									name="pckz_icon_enabled[]"
									value="<?php echo esc_attr( $slug ); ?>"
									<?php checked( $enabled ); ?>
								>
								<?php esc_html_e( 'Enabled', 'pckz-canonical-engine' ); ?>
							</label>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php submit_button( __( 'Save icon visibility', 'pckz-canonical-engine' ) ); ?>
	</form>
</div>
<script>
(function () {
	const table = document.querySelector('.pckz-icon-library-table');
	if (!table) return;
	const boxes = () => table.querySelectorAll('input[type="checkbox"]');
	document.getElementById('pckz-icon-enable-all')?.addEventListener('click', () => boxes().forEach((c) => { c.checked = true; }));
	document.getElementById('pckz-icon-disable-all')?.addEventListener('click', () => boxes().forEach((c) => { c.checked = false; }));
})();
</script>
