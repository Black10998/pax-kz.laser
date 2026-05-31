<?php
/**
 * Commerce orders admin list and detail.
 *
 * @package PCKZCanonicalEngine
 *
 * @var array      $orders        Order rows.
 * @var array|null $order         Single order (detail).
 * @var array|null $order_design  Linked design.
 * @var array      $order_meta    Design meta.
 * @var array      $order_package Production package for detail.
 * @var string     $search        Search query.
 */

defined( 'ABSPATH' ) || exit;

$list_url = admin_url( 'admin.php?page=pckz-orders' );
$status_updated = isset( $_GET['pckz_status_updated'] ) && '1' === (string) $_GET['pckz_status_updated'];
$notes_updated    = isset( $_GET['pckz_notes_updated'] ) && '1' === (string) $_GET['pckz_notes_updated'];
$hero_title       = __( 'Orders', 'pckz-canonical-engine' );
$hero_description = __( 'Review customer orders, production files, and fulfillment status.', 'pckz-canonical-engine' );
?>
<div class="wrap pckz-admin-wrap">
	<?php include PCKZCE_PLUGIN_DIR . 'admin/views/partials/page-hero.php'; ?>

	<?php if ( $status_updated ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Bestellstatus wurde gespeichert.', 'pckz-canonical-engine' ); ?></p></div>
	<?php endif; ?>
	<?php if ( $notes_updated ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Interne Notizen wurden gespeichert.', 'pckz-canonical-engine' ); ?></p></div>
	<?php endif; ?>

	<?php if ( ! empty( $order ) ) : ?>
		<?php
		$details = array();
		if ( ! empty( $order['customer_details'] ) ) {
			$details = PCKZ_Commerce::decode_customer_details( $order['customer_details'] );
		}
		$selections = is_array( $order_meta['selections'] ?? null ) ? $order_meta['selections'] : array();
		$config     = PCKZ_Post_Type::get_product_config( (int) ( $order['product_id'] ?? 0 ) );
		$text_value = (string) ( $selections['custom_text'] ?? $selections['text'] ?? '' );
		$font_family = (string) ( $selections['font_family'] ?? '' );
		$font_color  = (string) ( $selections['text_color'] ?? $selections['textfarbe'] ?? '' );
		$icon_left   = PCKZ_Production::format_selection_value( 'symbol_links', $selections['symbol_links'] ?? '—', $config );
		$icon_right  = PCKZ_Production::format_selection_value( 'symbol_rechts', $selections['symbol_rechts'] ?? '—', $config );
		$lines       = PCKZ_Production::format_selection_value( 'linien', $selections['linien'] ?? '—', $config );
		$preview_mode = PCKZ_Production::format_selection_value( 'preview_mode', $selections['preview_mode'] ?? 'day', $config );
		$customer_name = trim( ( $details['first_name'] ?? '' ) . ' ' . ( $details['last_name'] ?? '' ) );
		?>
		<p><a href="<?php echo esc_url( $list_url ); ?>">&larr; <?php esc_html_e( 'Back to orders', 'pckz-canonical-engine' ); ?></a></p>
		<h2><?php echo esc_html( PCKZ_Commerce::format_order_number( (int) $order['id'] ) ); ?></h2>

		<div class="pckz-detail-sections">
			<section class="pckz-card pckz-detail-section">
				<h3 class="pckz-detail-section__title"><?php esc_html_e( 'Bestellinformation', 'pckz-canonical-engine' ); ?></h3>
				<dl class="pckz-detail-dl">
					<div class="pckz-detail-dl__row"><dt><?php esc_html_e( 'Status', 'pckz-canonical-engine' ); ?></dt><dd><span class="pckz-order-status pckz-status-badge <?php echo esc_attr( PCKZ_Commerce::status_badge_css_class( $order['status'] ?? '' ) ); ?>"><?php echo esc_html( PCKZ_Commerce::status_label( $order['status'] ?? '' ) ); ?></span></dd></div>
					<div class="pckz-detail-dl__row"><dt><?php esc_html_e( 'Date', 'pckz-canonical-engine' ); ?></dt><dd><?php echo esc_html( $order['created_at'] ?? '' ); ?></dd></div>
					<div class="pckz-detail-dl__row"><dt><?php esc_html_e( 'Amount', 'pckz-canonical-engine' ); ?></dt><dd><?php echo esc_html( ( $order['amount'] ?? '' ) . ' ' . ( $order['currency'] ?? 'EUR' ) ); ?></dd></div>
					<div class="pckz-detail-dl__row"><dt><?php esc_html_e( 'Email', 'pckz-canonical-engine' ); ?></dt><dd><?php echo esc_html( $order['customer_email'] ?? '' ); ?></dd></div>
					<?php if ( $customer_name ) : ?>
						<div class="pckz-detail-dl__row"><dt><?php esc_html_e( 'Name', 'pckz-canonical-engine' ); ?></dt><dd><?php echo esc_html( $customer_name ); ?></dd></div>
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
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pckz-order-notes-form">
					<?php wp_nonce_field( 'pckz_update_order_notes', 'pckz_order_notes_nonce' ); ?>
					<input type="hidden" name="action" value="pckz_update_order_notes">
					<input type="hidden" name="order_id" value="<?php echo esc_attr( (string) $order['id'] ); ?>">
					<input type="hidden" name="redirect" value="<?php echo esc_url( admin_url( 'admin.php?page=pckz-orders&order_id=' . (int) $order['id'] ) ); ?>">
					<p><label for="pckz-admin-notes"><strong><?php esc_html_e( 'Internal notes', 'pckz-canonical-engine' ); ?></strong></label></p>
					<textarea name="admin_notes" id="pckz-admin-notes" class="large-text" rows="4"><?php echo esc_textarea( $order['admin_notes'] ?? '' ); ?></textarea>
					<p><button type="submit" class="button"><?php esc_html_e( 'Save notes', 'pckz-canonical-engine' ); ?></button></p>
				</form>
			</section>

			<?php if ( ! empty( $order['design_id'] ) ) : ?>
				<section class="pckz-card pckz-detail-section">
					<h3 class="pckz-detail-section__title"><?php esc_html_e( 'Designinformation', 'pckz-canonical-engine' ); ?></h3>
					<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=pckz-designs&design_id=' . (int) $order['design_id'] ) ); ?>"><?php esc_html_e( 'Open full design / production package', 'pckz-canonical-engine' ); ?></a></p>
					<dl class="pckz-detail-dl">
						<div class="pckz-detail-dl__row"><dt><?php esc_html_e( 'Text', 'pckz-canonical-engine' ); ?></dt><dd><?php echo esc_html( $text_value ?: '—' ); ?></dd></div>
						<div class="pckz-detail-dl__row"><dt><?php esc_html_e( 'Font Color', 'pckz-canonical-engine' ); ?></dt><dd><?php echo esc_html( $font_color ?: '—' ); ?></dd></div>
						<div class="pckz-detail-dl__row"><dt><?php esc_html_e( 'Icons', 'pckz-canonical-engine' ); ?></dt><dd><?php echo esc_html( $icon_left . ' / ' . $icon_right ); ?></dd></div>
						<div class="pckz-detail-dl__row"><dt><?php esc_html_e( 'Lines', 'pckz-canonical-engine' ); ?></dt><dd><?php echo esc_html( $lines ); ?></dd></div>
						<div class="pckz-detail-dl__row"><dt><?php esc_html_e( 'Preview Mode', 'pckz-canonical-engine' ); ?></dt><dd><?php echo esc_html( $preview_mode ); ?></dd></div>
					</dl>
					<?php echo PCKZ_Production::render_admin_font_block( $font_family, $text_value ?: $font_family ); ?>
				</section>
			<?php endif; ?>

			<?php if ( ! empty( $order_package ) ) : ?>
				<section class="pckz-card pckz-detail-section">
					<h3 class="pckz-detail-section__title"><?php esc_html_e( 'Produktionsinformation', 'pckz-canonical-engine' ); ?></h3>
					<?php echo PCKZ_Production::render_admin_production_panel( $order_package, array( 'design_id' => (int) ( $order['design_id'] ?? 0 ) ) ); ?>
				</section>
			<?php endif; ?>
		</div>

	<?php else : ?>
		<form method="get" class="pckz-order-search" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
			<input type="hidden" name="page" value="pckz-orders">
			<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Order ID, email, or name…', 'pckz-canonical-engine' ); ?>" class="regular-text">
			<button type="submit" class="button"><?php esc_html_e( 'Search', 'pckz-canonical-engine' ); ?></button>
			<?php if ( $search ) : ?>
				<a class="button" href="<?php echo esc_url( $list_url ); ?>"><?php esc_html_e( 'Reset', 'pckz-canonical-engine' ); ?></a>
			<?php endif; ?>
		</form>
		<p class="description"><?php esc_html_e( 'Customer tracking shortcode:', 'pckz-canonical-engine' ); ?> <code>[pckz_order_tracking]</code></p>

		<?php if ( empty( $orders ) ) : ?>
			<p><?php esc_html_e( 'No orders found.', 'pckz-canonical-engine' ); ?></p>
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
							<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=pckz-orders&order_id=' . (int) $row['id'] ) ); ?>"><?php echo esc_html( PCKZ_Commerce::format_order_number( (int) $row['id'] ) ); ?></a></td>
							<td><span class="pckz-order-status pckz-status-badge <?php echo esc_attr( PCKZ_Commerce::status_badge_css_class( $row['status'] ?? '' ) ); ?>"><?php echo esc_html( PCKZ_Commerce::status_label( $row['status'] ?? '' ) ); ?></span></td>
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
	<?php endif; ?>
</div>
