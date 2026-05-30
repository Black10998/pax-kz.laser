<?php
/**
 * Order tracking form.
 *
 * @package PCKZCanonicalEngine
 *
 * @var string     $order_number Submitted order number.
 * @var array|null $order        Matched order row.
 * @var string     $message      Error message.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="pckz-tracking" lang="de">
	<h2 class="pckz-tracking__title"><?php esc_html_e( 'Bestellung verfolgen', 'pckz-canonical-engine' ); ?></h2>
	<p class="pckz-tracking__lead"><?php esc_html_e( 'Geben Sie Ihre Bestellnummer ein, um den aktuellen Status zu sehen.', 'pckz-canonical-engine' ); ?></p>

	<form class="pckz-tracking__form" method="post" action="">
		<label class="pckz-tracking__label" for="pckz-order-number"><?php esc_html_e( 'Bestellnummer', 'pckz-canonical-engine' ); ?></label>
		<input
			class="pckz-tracking__input"
			type="text"
			id="pckz-order-number"
			name="pckz_order_number"
			value="<?php echo esc_attr( $order_number ); ?>"
			placeholder="PCKZ-000123"
			required
			autocomplete="off"
		>
		<button type="submit" class="pckz-tracking__submit"><?php esc_html_e( 'Status anzeigen', 'pckz-canonical-engine' ); ?></button>
	</form>

	<?php if ( $message ) : ?>
		<p class="pckz-tracking__message pckz-tracking__message--error" role="alert"><?php echo esc_html( $message ); ?></p>
	<?php endif; ?>

	<?php if ( $order ) : ?>
		<?php
		$current_status = $order['status'] ?? '';
		$status_label   = PCKZ_Commerce::customer_status_label( $current_status );
		$status_message = PCKZ_Commerce::customer_status_message( $current_status );
		$badge_class    = PCKZ_Commerce::status_badge_css_class( $current_status );
		$timeline       = PCKZ_Commerce::customer_tracking_timeline( $current_status );
		?>
		<div class="pckz-tracking__result">
			<h3 class="pckz-tracking__result-title"><?php esc_html_e( 'Bestellstatus', 'pckz-canonical-engine' ); ?></h3>
			<dl class="pckz-tracking__dl">
				<div class="pckz-tracking__row">
					<dt><?php esc_html_e( 'Bestellnummer', 'pckz-canonical-engine' ); ?></dt>
					<dd><?php echo esc_html( PCKZ_Commerce::format_order_number( (int) $order['id'] ) ); ?></dd>
				</div>
				<div class="pckz-tracking__row">
					<dt><?php esc_html_e( 'Status', 'pckz-canonical-engine' ); ?></dt>
					<dd><span class="pckz-status-badge <?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( $status_label ); ?></span></dd>
				</div>
				<div class="pckz-tracking__row">
					<dt><?php esc_html_e( 'Datum', 'pckz-canonical-engine' ); ?></dt>
					<dd><?php echo esc_html( $order['created_at'] ?? '' ); ?></dd>
				</div>
			</dl>
			<p class="pckz-tracking__status-message"><?php echo esc_html( $status_message ); ?></p>
			<?php if ( ! empty( $timeline ) ) : ?>
				<ul class="pckz-tracking__timeline" aria-label="<?php esc_attr_e( 'Bestellfortschritt', 'pckz-canonical-engine' ); ?>">
					<?php foreach ( $timeline as $step ) : ?>
						<li class="pckz-tracking__timeline-item is-<?php echo esc_attr( $step['state'] ); ?>">
							<span class="pckz-status-badge <?php echo esc_attr( PCKZ_Commerce::status_badge_css_class( $step['code'] ) ); ?>">
								<?php echo esc_html( $step['label'] ); ?>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
