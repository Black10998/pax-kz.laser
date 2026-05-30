<?php
/**
 * Commerce orders admin list and detail.
 *
 * @package PCKZCanonicalEngine
 *
 * @var array      $orders      Order rows.
 * @var array|null $order       Single order (detail).
 * @var array|null $order_design Linked design.
 * @var array      $order_package Production package for detail.
 */

defined( 'ABSPATH' ) || exit;

$list_url = admin_url( 'admin.php?page=pckz-orders' );
$status_updated = isset( $_GET['pckz_status_updated'] ) && '1' === (string) $_GET['pckz_status_updated'];
?>
<div class="wrap pckz-admin-wrap">
	<h1><?php esc_html_e( 'Orders', 'pckz-canonical-engine' ); ?></h1>

	<?php if ( $status_updated ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Bestellstatus wurde gespeichert.', 'pckz-canonical-engine' ); ?></p></div>
	<?php endif; ?>

	<?php if ( ! empty( $order ) ) : ?>
		<p><a href="<?php echo esc_url( $list_url ); ?>">&larr; <?php esc_html_e( 'Back to orders', 'pckz-canonical-engine' ); ?></a></p>
		<h2><?php echo esc_html( sprintf( __( 'Order %s', 'pckz-canonical-engine' ), '#' . (int) $order['id'] ) ); ?></h2>

		<section class="pckz-card pckz-detail-section">
			<h3 class="pckz-detail-section__title"><?php esc_html_e( 'Order Information', 'pckz-canonical-engine' ); ?></h3>
			<dl class="pckz-detail-dl">
				<div class="pckz-detail-dl__row"><dt><?php esc_html_e( 'Status', 'pckz-canonical-engine' ); ?></dt><dd><span class="pckz-order-status"><?php echo esc_html( PCKZ_Commerce::status_label( $order['status'] ?? '' ) ); ?></span></dd></div>
				<div class="pckz-detail-dl__row"><dt><?php esc_html_e( 'Date', 'pckz-canonical-engine' ); ?></dt><dd><?php echo esc_html( $order['created_at'] ?? '' ); ?></dd></div>
				<div class="pckz-detail-dl__row"><dt><?php esc_html_e( 'Amount', 'pckz-canonical-engine' ); ?></dt><dd><?php echo esc_html( ( $order['amount'] ?? '' ) . ' ' . ( $order['currency'] ?? 'EUR' ) ); ?></dd></div>
				<div class="pckz-detail-dl__row"><dt><?php esc_html_e( 'Email', 'pckz-canonical-engine' ); ?></dt><dd><?php echo esc_html( $order['customer_email'] ?? '' ); ?></dd></div>
				<?php if ( ! empty( $order['design_id'] ) ) : ?>
					<div class="pckz-detail-dl__row"><dt><?php esc_html_e( 'Design', 'pckz-canonical-engine' ); ?></dt><dd><a href="<?php echo esc_url( admin_url( 'admin.php?page=pckz-designs&design_id=' . (int) $order['design_id'] ) ); ?>">#<?php echo esc_html( (string) $order['design_id'] ); ?></a></dd></div>
				<?php endif; ?>
			</dl>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pckz-order-status-form">
				<?php wp_nonce_field( 'pckz_update_order_status', 'pckz_order_status_nonce' ); ?>
				<input type="hidden" name="action" value="pckz_update_order_status">
				<input type="hidden" name="order_id" value="<?php echo esc_attr( (string) $order['id'] ); ?>">
				<input type="hidden" name="redirect" value="<?php echo esc_url( admin_url( 'admin.php?page=pckz-orders&order_id=' . (int) $order['id'] ) ); ?>">
				<select name="workflow_status" id="pckz-order-workflow-status">
					<?php foreach ( PCKZ_Commerce::workflow_statuses() as $code => $label ) : ?>
						<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $order['status'] ?? 'pending', $code ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="button"><?php esc_html_e( 'Update status', 'pckz-canonical-engine' ); ?></button>
			</form>
		</section>

		<?php if ( ! empty( $order_package ) ) : ?>
			<section class="pckz-card pckz-detail-section">
				<h3 class="pckz-detail-section__title"><?php esc_html_e( 'Production files', 'pckz-canonical-engine' ); ?></h3>
				<?php echo PCKZ_Production::render_admin_production_panel( $order_package, array( 'design_id' => (int) ( $order['design_id'] ?? 0 ) ) ); ?>
			</section>
		<?php endif; ?>

	<?php elseif ( empty( $orders ) ) : ?>
		<p><?php esc_html_e( 'No orders yet.', 'pckz-canonical-engine' ); ?></p>
	<?php else : ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Order', 'pckz-canonical-engine' ); ?></th>
					<th><?php esc_html_e( 'Status', 'pckz-canonical-engine' ); ?></th>
					<th><?php esc_html_e( 'Design', 'pckz-canonical-engine' ); ?></th>
					<th><?php esc_html_e( 'Customer', 'pckz-canonical-engine' ); ?></th>
					<th><?php esc_html_e( 'Amount', 'pckz-canonical-engine' ); ?></th>
					<th><?php esc_html_e( 'Date', 'pckz-canonical-engine' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $orders as $row ) : ?>
					<tr>
						<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=pckz-orders&order_id=' . (int) $row['id'] ) ); ?>">#<?php echo esc_html( (string) $row['id'] ); ?></a></td>
						<td><span class="pckz-order-status"><?php echo esc_html( PCKZ_Commerce::status_label( $row['status'] ?? '' ) ); ?></span></td>
						<td>
							<?php if ( ! empty( $row['design_id'] ) ) : ?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=pckz-designs&design_id=' . (int) $row['design_id'] ) ); ?>">#<?php echo esc_html( (string) $row['design_id'] ); ?></a>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $row['customer_email'] ?? '' ); ?></td>
						<td><?php echo esc_html( ( $row['amount'] ?? '' ) . ' ' . ( $row['currency'] ?? '' ) ); ?></td>
						<td><?php echo esc_html( $row['created_at'] ?? '' ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
