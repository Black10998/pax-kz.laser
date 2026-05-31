<?php
/**
 * Saved designs admin list.
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

$hero_title       = __( 'Saved Designs', 'pckz-canonical-engine' );
$hero_description = __( 'Open any design to download the full LightBurn production package (exact mm coordinates, SVG references, and production JSON).', 'pckz-canonical-engine' );
?>
<div class="wrap pckz-admin-wrap">
	<?php include PCKZCE_PLUGIN_DIR . 'admin/views/partials/page-hero.php'; ?>

	<div class="pckz-panel">
		<div class="pckz-panel__body">
	<?php if ( empty( $designs ) ) : ?>
		<p><?php esc_html_e( 'No designs saved yet. Designs appear when customers use the creator and click Save Design.', 'pckz-canonical-engine' ); ?></p>
	<?php else : ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'ID', 'pckz-canonical-engine' ); ?></th>
					<th><?php esc_html_e( 'Product', 'pckz-canonical-engine' ); ?></th>
					<th><?php esc_html_e( 'Order status', 'pckz-canonical-engine' ); ?></th>
					<th><?php esc_html_e( 'User', 'pckz-canonical-engine' ); ?></th>
					<th><?php esc_html_e( 'Preview', 'pckz-canonical-engine' ); ?></th>
					<th><?php esc_html_e( 'Created', 'pckz-canonical-engine' ); ?></th>
					<th><?php esc_html_e( 'Production', 'pckz-canonical-engine' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $designs as $design ) :
					$detail_url = admin_url( 'admin.php?page=pckz-designs&design_id=' . (int) $design->id );
					$commerce_row = class_exists( 'PCKZ_Commerce' ) ? PCKZ_Commerce::get_order_by_design_id( (int) $design->id ) : null;
					$status_label = $commerce_row
						? PCKZ_Commerce::status_label( $commerce_row['status'] ?? '' )
						: '—';
					?>
					<tr>
						<td><?php echo esc_html( $design->id ); ?></td>
						<td><?php echo esc_html( get_the_title( $design->product_id ) ?: $design->product_id ); ?></td>
						<td><span class="pckz-order-status pckz-status-badge <?php echo esc_attr( PCKZ_Commerce::status_badge_css_class( $commerce_row['status'] ?? '' ) ); ?>"><?php echo esc_html( $status_label ); ?></span></td>
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
	</div>
</div>
