<?php
/**
 * Single saved design — grouped order / design / production view.
 *
 * @package PCKZCanonicalEngine
 *
 * @var array|null $design Design row.
 * @var array      $package Production package.
 */

defined( 'ABSPATH' ) || exit;

$back_url = admin_url( 'admin.php?page=pckz-designs' );

if ( empty( $design ) ) {
	?>
	<div class="wrap pckz-admin-wrap">
		<p><a href="<?php echo esc_url( $back_url ); ?>">&larr; <?php esc_html_e( 'Back to designs', 'pckz-canonical-engine' ); ?></a></p>
		<h1><?php esc_html_e( 'Design not found', 'pckz-canonical-engine' ); ?></h1>
	</div>
	<?php
	return;
}

$meta       = array();
if ( ! empty( $design['meta_json'] ) ) {
	$meta = json_decode( $design['meta_json'], true );
}
if ( ! is_array( $meta ) ) {
	$meta = array();
}
$selections = is_array( $meta['selections'] ?? null ) ? $meta['selections'] : array();
$config     = PCKZ_Post_Type::get_product_config( (int) ( $design['product_id'] ?? 0 ) );
$commerce   = class_exists( 'PCKZ_Commerce' ) ? PCKZ_Commerce::get_order_by_design_id( (int) $design['id'] ) : null;
$details    = array();
if ( ! empty( $meta['customer_details'] ) && is_array( $meta['customer_details'] ) ) {
	$details = $meta['customer_details'];
} elseif ( $commerce && ! empty( $commerce['customer_details'] ) ) {
	$details = PCKZ_Commerce::decode_customer_details( $commerce['customer_details'] );
}

$text_value = (string) ( $selections['custom_text'] ?? $selections['text'] ?? '' );
if ( '' === $text_value ) {
	foreach ( $package['layout']['objects'] ?? array() as $obj ) {
		if ( in_array( $obj['role'] ?? '', array( 'text', 'main-text' ), true ) && ! empty( $obj['text'] ) ) {
			$text_value = (string) $obj['text'];
			break;
		}
	}
}
$font_family = (string) ( $selections['font_family'] ?? '' );
$font_color  = (string) ( $selections['text_color'] ?? $selections['textfarbe'] ?? '' );
if ( '' === $font_family ) {
	foreach ( $package['layout']['objects'] ?? array() as $obj ) {
		if ( in_array( $obj['role'] ?? '', array( 'text', 'main-text' ), true ) ) {
			$font_family = (string) ( $obj['font_family'] ?? $obj['fontFamily'] ?? '' );
			if ( '' === $font_color ) {
				$font_color = (string) ( $obj['fill'] ?? '' );
			}
			break;
		}
	}
}

$icon_left  = PCKZ_Production::format_selection_value( 'symbol_links', $selections['symbol_links'] ?? '—', $config );
$icon_right = PCKZ_Production::format_selection_value( 'symbol_rechts', $selections['symbol_rechts'] ?? '—', $config );
$lines      = PCKZ_Production::format_selection_value( 'linien', $selections['linien'] ?? '—', $config );
$preview_mode = PCKZ_Production::format_selection_value( 'preview_mode', $selections['preview_mode'] ?? 'day', $config );

$customer_name = trim( ( $details['first_name'] ?? '' ) . ' ' . ( $details['last_name'] ?? '' ) );
if ( '' === $customer_name && ! empty( $details['name'] ) ) {
	$customer_name = (string) $details['name'];
}
$customer_email = (string) ( $details['email'] ?? $meta['customer_email'] ?? ( $commerce['customer_email'] ?? '' ) );
$customer_phone = (string) ( $details['phone'] ?? '' );
$address_parts  = array_filter(
	array(
		trim( ( $details['street'] ?? '' ) . ' ' . ( $details['house_number'] ?? '' ) ),
		trim( ( $details['postal_code'] ?? '' ) . ' ' . ( $details['city'] ?? '' ) ),
		$details['country'] ?? '',
	)
);
$customer_address = implode( ', ', $address_parts );

$order_number = $commerce ? ( '#' . (int) $commerce['id'] ) : '—';
$order_date   = $commerce['created_at'] ?? ( $design['created_at'] ?? '' );
$pay_status   = $commerce && class_exists( 'PCKZ_Commerce' )
	? PCKZ_Commerce::status_label( $commerce['status'] ?? 'pending' )
	: __( 'Keine Bestellung', 'pckz-canonical-engine' );

$status_updated = isset( $_GET['pckz_status_updated'] ) && '1' === (string) $_GET['pckz_status_updated'];
?>
<div class="wrap pckz-admin-wrap">
	<p><a href="<?php echo esc_url( $back_url ); ?>">&larr; <?php esc_html_e( 'Back to designs', 'pckz-canonical-engine' ); ?></a></p>

	<h1><?php esc_html_e( 'Design', 'pckz-canonical-engine' ); ?> #<?php echo esc_html( (string) $design['id'] ); ?></h1>
	<p class="description">
		<?php
		echo esc_html(
			sprintf(
				/* translators: 1: product title, 2: date */
				__( 'Product: %1$s · Created: %2$s', 'pckz-canonical-engine' ),
				get_the_title( $design['product_id'] ) ?: (string) $design['product_id'],
				$design['created_at'] ?? ''
			)
		);
		?>
	</p>

	<?php if ( $status_updated ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Bestellstatus wurde gespeichert.', 'pckz-canonical-engine' ); ?></p></div>
	<?php endif; ?>

	<div class="pckz-detail-sections">
		<section class="pckz-card pckz-detail-section">
			<h2 class="pckz-detail-section__title"><?php esc_html_e( 'Kundeninformation', 'pckz-canonical-engine' ); ?></h2>
			<dl class="pckz-detail-dl">
				<div class="pckz-detail-dl__row"><dt><?php esc_html_e( 'Name', 'pckz-canonical-engine' ); ?></dt><dd><?php echo esc_html( $customer_name ?: '—' ); ?></dd></div>
				<div class="pckz-detail-dl__row"><dt><?php esc_html_e( 'Email', 'pckz-canonical-engine' ); ?></dt><dd><?php echo $customer_email ? '<a href="mailto:' . esc_attr( $customer_email ) . '">' . esc_html( $customer_email ) . '</a>' : '—'; ?></dd></div>
				<div class="pckz-detail-dl__row"><dt><?php esc_html_e( 'Phone', 'pckz-canonical-engine' ); ?></dt><dd><?php echo esc_html( $customer_phone ?: '—' ); ?></dd></div>
				<div class="pckz-detail-dl__row"><dt><?php esc_html_e( 'Address', 'pckz-canonical-engine' ); ?></dt><dd><?php echo esc_html( $customer_address ?: '—' ); ?></dd></div>
			</dl>
			<?php if ( ! empty( $meta['customer_note'] ) || ! empty( $commerce['customer_note'] ) ) : ?>
				<p class="pckz-detail-note"><strong><?php esc_html_e( 'Customer note', 'pckz-canonical-engine' ); ?>:</strong> <?php echo esc_html( $meta['customer_note'] ?? $commerce['customer_note'] ?? '' ); ?></p>
			<?php endif; ?>
		</section>

		<section class="pckz-card pckz-detail-section">
			<h2 class="pckz-detail-section__title"><?php esc_html_e( 'Bestellinformation', 'pckz-canonical-engine' ); ?></h2>
			<dl class="pckz-detail-dl">
				<div class="pckz-detail-dl__row"><dt><?php esc_html_e( 'Order Number', 'pckz-canonical-engine' ); ?></dt><dd><?php echo esc_html( $order_number ); ?></dd></div>
				<div class="pckz-detail-dl__row"><dt><?php esc_html_e( 'Date', 'pckz-canonical-engine' ); ?></dt><dd><?php echo esc_html( $order_date ?: '—' ); ?></dd></div>
				<div class="pckz-detail-dl__row"><dt><?php esc_html_e( 'Payment Status', 'pckz-canonical-engine' ); ?></dt><dd><span class="pckz-order-status"><?php echo esc_html( $pay_status ); ?></span></dd></div>
			</dl>
			<?php if ( $commerce ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pckz-order-status-form">
					<?php wp_nonce_field( 'pckz_update_order_status', 'pckz_order_status_nonce' ); ?>
					<input type="hidden" name="action" value="pckz_update_order_status">
					<input type="hidden" name="order_id" value="<?php echo esc_attr( (string) $commerce['id'] ); ?>">
					<input type="hidden" name="redirect" value="<?php echo esc_url( admin_url( 'admin.php?page=pckz-designs&design_id=' . (int) $design['id'] ) ); ?>">
					<label for="pckz-workflow-status" class="screen-reader-text"><?php esc_html_e( 'Production status', 'pckz-canonical-engine' ); ?></label>
					<select name="workflow_status" id="pckz-workflow-status">
						<?php foreach ( PCKZ_Commerce::workflow_statuses() as $code => $label ) : ?>
							<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $commerce['status'] ?? 'pending', $code ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<button type="submit" class="button"><?php esc_html_e( 'Update status', 'pckz-canonical-engine' ); ?></button>
				</form>
				<p class="description">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=pckz-orders&order_id=' . (int) $commerce['id'] ) ); ?>"><?php esc_html_e( 'Open in orders list', 'pckz-canonical-engine' ); ?></a>
				</p>
			<?php endif; ?>
		</section>

		<section class="pckz-card pckz-detail-section">
			<h2 class="pckz-detail-section__title"><?php esc_html_e( 'Designinformation', 'pckz-canonical-engine' ); ?></h2>
			<dl class="pckz-detail-dl">
				<div class="pckz-detail-dl__row"><dt><?php esc_html_e( 'Text', 'pckz-canonical-engine' ); ?></dt><dd><?php echo esc_html( $text_value ?: '—' ); ?></dd></div>
				<div class="pckz-detail-dl__row"><dt><?php esc_html_e( 'Font Color', 'pckz-canonical-engine' ); ?></dt><dd><?php echo esc_html( $font_color ?: '—' ); ?></dd></div>
				<div class="pckz-detail-dl__row"><dt><?php esc_html_e( 'Icons', 'pckz-canonical-engine' ); ?></dt><dd><?php echo esc_html( $icon_left . ' / ' . $icon_right ); ?></dd></div>
				<div class="pckz-detail-dl__row"><dt><?php esc_html_e( 'Lines', 'pckz-canonical-engine' ); ?></dt><dd><?php echo esc_html( $lines ); ?></dd></div>
				<div class="pckz-detail-dl__row"><dt><?php esc_html_e( 'Preview Mode', 'pckz-canonical-engine' ); ?></dt><dd><?php echo esc_html( $preview_mode ); ?></dd></div>
			</dl>
			<div class="pckz-detail-font-block">
				<h3 class="pckz-detail-subheading"><?php esc_html_e( 'Selected Font', 'pckz-canonical-engine' ); ?></h3>
				<?php echo PCKZ_Production::render_admin_font_block( $font_family, $text_value ?: $font_family ); ?>
			</div>
			<?php if ( ! empty( $design['preview_url'] ) ) : ?>
				<p class="pckz-detail-preview-wrap">
					<img src="<?php echo esc_url( $design['preview_url'] ); ?>" alt="" class="pckz-production-preview-img">
				</p>
			<?php endif; ?>
		</section>

		<section class="pckz-card pckz-detail-section pckz-detail-section--production">
			<h2 class="pckz-detail-section__title"><?php esc_html_e( 'Produktionsinformation', 'pckz-canonical-engine' ); ?></h2>
			<?php
			echo PCKZ_Production::render_admin_production_panel( $package, array( 'design_id' => (int) $design['id'] ) );
			?>
		</section>
	</div>
</div>
