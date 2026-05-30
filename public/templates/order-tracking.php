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
	<header class="pckz-tracking__header">
		<h2 class="pckz-tracking__title"><?php esc_html_e( 'Bestellung verfolgen', 'pckz-canonical-engine' ); ?></h2>
		<p class="pckz-tracking__lead"><?php esc_html_e( 'Haben Sie bereits bestellt? Geben Sie Ihre Bestellnummer ein, um den aktuellen Status Ihrer Bestellung zu verfolgen.', 'pckz-canonical-engine' ); ?></p>
	</header>

	<form class="pckz-tracking__form" method="post" action="">
		<label class="pckz-tracking__label" for="pckz-order-number"><?php esc_html_e( 'Bestellnummer', 'pckz-canonical-engine' ); ?></label>
		<input
			class="pckz-tracking__input"
			type="text"
			id="pckz-order-number"
			name="pckz_order_number"
			value="<?php echo esc_attr( $order_number ); ?>"
			placeholder="PAX-7F4K-91XM"
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
		$shipping       = PCKZ_Commerce::customer_shipping_summary( $order );
		$public_id      = PCKZ_Commerce::format_order_number( (int) $order['id'] );
		?>
		<section class="pckz-tracking__result">
			<header class="pckz-tracking__result-header">
				<div>
					<p class="pckz-tracking__meta-label"><?php esc_html_e( 'Tracking-ID', 'pckz-canonical-engine' ); ?></p>
					<p class="pckz-tracking__tracking-id"><?php echo esc_html( $public_id ); ?></p>
				</div>
				<span class="pckz-status-badge <?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( $status_label ); ?></span>
			</header>

			<div class="pckz-tracking__facts">
				<article class="pckz-tracking__fact">
					<p class="pckz-tracking__fact-label"><?php esc_html_e( 'Bestelldatum', 'pckz-canonical-engine' ); ?></p>
					<p class="pckz-tracking__fact-value"><?php echo esc_html( $order['created_at'] ?? '' ); ?></p>
				</article>
				<article class="pckz-tracking__fact">
					<p class="pckz-tracking__fact-label"><?php esc_html_e( 'Aktueller Status', 'pckz-canonical-engine' ); ?></p>
					<p class="pckz-tracking__fact-value"><?php echo esc_html( $status_label ); ?></p>
				</article>
				<article class="pckz-tracking__fact">
					<p class="pckz-tracking__fact-label"><?php esc_html_e( 'Öffentliche Bestellnummer', 'pckz-canonical-engine' ); ?></p>
					<p class="pckz-tracking__fact-value"><?php echo esc_html( $public_id ); ?></p>
				</article>
			</div>

			<p class="pckz-tracking__status-message"><?php echo esc_html( $status_message ); ?></p>

			<?php if ( ! empty( $timeline ) ) : ?>
				<section class="pckz-tracking__timeline-wrap" aria-label="<?php esc_attr_e( 'Bestellfortschritt', 'pckz-canonical-engine' ); ?>">
					<h3 class="pckz-tracking__section-title"><?php esc_html_e( 'Fortschritt', 'pckz-canonical-engine' ); ?></h3>
					<ol class="pckz-tracking__timeline">
						<?php foreach ( $timeline as $step ) : ?>
							<li class="pckz-tracking__timeline-item is-<?php echo esc_attr( $step['state'] ); ?>">
								<span class="pckz-tracking__timeline-dot" aria-hidden="true">
									<?php if ( 'complete' === $step['state'] ) : ?>
										&#10003;
									<?php else : ?>
										<span class="pckz-tracking__timeline-dot-inner"></span>
									<?php endif; ?>
								</span>
								<span class="pckz-tracking__timeline-label"><?php echo esc_html( $step['label'] ); ?></span>
							</li>
						<?php endforeach; ?>
					</ol>
				</section>
			<?php endif; ?>

			<?php if ( ! empty( $shipping['has_data'] ) ) : ?>
				<section class="pckz-tracking__shipping">
					<h3 class="pckz-tracking__section-title"><?php esc_html_e( 'Versandinformationen', 'pckz-canonical-engine' ); ?></h3>
					<dl class="pckz-tracking__shipping-dl">
						<?php if ( ! empty( $shipping['carrier'] ) ) : ?>
							<div class="pckz-tracking__row">
								<dt><?php esc_html_e( 'Versanddienstleister', 'pckz-canonical-engine' ); ?></dt>
								<dd><?php echo esc_html( $shipping['carrier'] ); ?></dd>
							</div>
						<?php endif; ?>
						<?php if ( ! empty( $shipping['tracking_number'] ) ) : ?>
							<div class="pckz-tracking__row">
								<dt><?php esc_html_e( 'Sendungsnummer', 'pckz-canonical-engine' ); ?></dt>
								<dd><?php echo esc_html( $shipping['tracking_number'] ); ?></dd>
							</div>
						<?php endif; ?>
						<?php if ( ! empty( $shipping['shipping_date'] ) ) : ?>
							<div class="pckz-tracking__row">
								<dt><?php esc_html_e( 'Versandt am', 'pckz-canonical-engine' ); ?></dt>
								<dd><?php echo esc_html( $shipping['shipping_date'] ); ?></dd>
							</div>
						<?php endif; ?>
					</dl>
					<?php if ( ! empty( $shipping['tracking_url'] ) ) : ?>
						<p class="pckz-tracking__shipping-action">
							<a href="<?php echo esc_url( $shipping['tracking_url'] ); ?>" target="_blank" rel="noopener noreferrer" class="pckz-tracking__submit pckz-tracking__submit--ghost">
								<?php esc_html_e( 'Sendung online verfolgen', 'pckz-canonical-engine' ); ?>
							</a>
						</p>
					<?php endif; ?>
				</section>
			<?php endif; ?>
		</section>
	<?php endif; ?>
</div>
