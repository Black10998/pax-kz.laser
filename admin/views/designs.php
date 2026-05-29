<?php
/**
 * Saved designs admin list.
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap pckz-admin-wrap">
	<h1><?php esc_html_e( 'Saved Designs', 'pckz-canonical-engine' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Open any design to download the full LightBurn production package (exact mm coordinates, SVG references, and production JSON).', 'pckz-canonical-engine' ); ?></p>

	<?php if ( empty( $designs ) ) : ?>
		<p><?php esc_html_e( 'No designs saved yet. Designs appear when customers use the creator and click Save Design.', 'pckz-canonical-engine' ); ?></p>
	<?php else : ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'ID', 'pckz-canonical-engine' ); ?></th>
					<th><?php esc_html_e( 'Product', 'pckz-canonical-engine' ); ?></th>
					<th><?php esc_html_e( 'User', 'pckz-canonical-engine' ); ?></th>
					<th><?php esc_html_e( 'Preview', 'pckz-canonical-engine' ); ?></th>
					<th><?php esc_html_e( 'Created', 'pckz-canonical-engine' ); ?></th>
					<th><?php esc_html_e( 'Production', 'pckz-canonical-engine' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $designs as $design ) :
					$detail_url = admin_url( 'admin.php?page=pckz-designs&design_id=' . (int) $design->id );
					?>
					<tr>
						<td><?php echo esc_html( $design->id ); ?></td>
						<td><?php echo esc_html( get_the_title( $design->product_id ) ?: $design->product_id ); ?></td>
						<td><?php echo esc_html( $design->user_id ? get_userdata( $design->user_id )->display_name ?? $design->user_id : __( 'Guest', 'pckz-canonical-engine' ) ); ?></td>
						<td>
							<?php if ( ! empty( $design->preview_url ) ) : ?>
								<a href="<?php echo esc_url( $design->preview_url ); ?>" target="_blank" rel="noopener">
									<img src="<?php echo esc_url( $design->preview_url ); ?>" alt="" style="max-width:120px;border-radius:4px">
								</a>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $design->created_at ); ?></td>
						<td>
							<a class="button button-small" href="<?php echo esc_url( $detail_url ); ?>">
								<?php esc_html_e( 'LightBurn package', 'pckz-canonical-engine' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
