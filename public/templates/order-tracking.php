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
	<div class="pckz-tracking__shell">
		<header class="pckz-tracking__header">
			<p class="pckz-tracking__eyebrow"><?php esc_html_e( 'Tracking', 'pckz-canonical-engine' ); ?></p>
			<h2 class="pckz-tracking__title"><?php esc_html_e( 'Bestellstatus', 'pckz-canonical-engine' ); ?></h2>
			<p class="pckz-tracking__lead"><?php esc_html_e( 'Tracking-ID eingeben und den aktuellen Produktions- und Versandstatus auf einen Blick sehen.', 'pckz-canonical-engine' ); ?></p>
		</header>

		<form class="pckz-tracking__form" method="post" action="">
			<div class="pckz-tracking__field">
				<label class="pckz-tracking__label" for="pckz-order-number"><?php esc_html_e( 'Tracking-ID / Bestellnummer', 'pckz-canonical-engine' ); ?></label>
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
			</div>
			<button type="submit" class="pckz-tracking__submit"><?php esc_html_e( 'Jetzt prüfen', 'pckz-canonical-engine' ); ?></button>
		</form>

		<?php if ( $message ) : ?>
			<p class="pckz-tracking__message pckz-tracking__message--error" role="alert"><?php echo esc_html( $message ); ?></p>
		<?php endif; ?>

		<?php if ( $order ) : ?>
			<?php
			$current_status      = $order['status'] ?? '';
			$status_label        = PCKZ_Commerce::customer_status_label( $current_status );
			$status_message      = PCKZ_Commerce::customer_status_message( $current_status );
			$badge_class         = PCKZ_Commerce::status_badge_css_class( $current_status );
			$timeline            = PCKZ_Commerce::customer_tracking_timeline( $current_status );
			$shipping            = PCKZ_Commerce::customer_shipping_summary( $order );
			$public_id           = PCKZ_Commerce::format_order_number( (int) $order['id'] );
			$timeline_count      = count( $timeline );
			$current_step_index  = 0;
			foreach ( $timeline as $index => $step ) {
				if ( in_array( $step['state'], array( 'current', 'complete' ), true ) ) {
					$current_step_index = (int) $index + 1;
				}
			}
			$progress_percent = $timeline_count > 0 ? (int) floor( ( $current_step_index / $timeline_count ) * 100 ) : 0;
			$product_title = '';
			if ( ! empty( $order['product_id'] ) && function_exists( 'get_the_title' ) ) {
				$product_title = (string) get_the_title( (int) $order['product_id'] );
			}

			$normalized_status = PCKZ_Commerce::normalize_status_code( $current_status );
			$timeline_state_labels = array(
				'complete' => __( 'Erledigt', 'pckz-canonical-engine' ),
				'current'  => __( 'Aktuell', 'pckz-canonical-engine' ),
				'inactive' => __( 'Ausstehend', 'pckz-canonical-engine' ),
			);
			$order_stage_label = __( 'Offen', 'pckz-canonical-engine' );
			if ( in_array( $normalized_status, array( 'paid', 'in_progress', 'production', 'ready_to_ship', 'shipped', 'completed' ), true ) ) {
				$order_stage_label = __( 'Bestätigt', 'pckz-canonical-engine' );
			} elseif ( 'cancelled' === $normalized_status ) {
				$order_stage_label = __( 'Storniert', 'pckz-canonical-engine' );
			}

			$production_stage_label = __( 'Wartend', 'pckz-canonical-engine' );
			if ( in_array( $normalized_status, array( 'in_progress', 'production' ), true ) ) {
				$production_stage_label = __( 'In Produktion', 'pckz-canonical-engine' );
			} elseif ( in_array( $normalized_status, array( 'ready_to_ship', 'shipped', 'completed' ), true ) ) {
				$production_stage_label = __( 'Abgeschlossen', 'pckz-canonical-engine' );
			} elseif ( 'cancelled' === $normalized_status ) {
				$production_stage_label = __( 'Gestoppt', 'pckz-canonical-engine' );
			}

			$shipping_stage_label = __( 'Noch nicht versendet', 'pckz-canonical-engine' );
			if ( 'ready_to_ship' === $normalized_status ) {
				$shipping_stage_label = __( 'Versand wird vorbereitet', 'pckz-canonical-engine' );
			} elseif ( 'shipped' === $normalized_status ) {
				$shipping_stage_label = __( 'Unterwegs', 'pckz-canonical-engine' );
			} elseif ( 'completed' === $normalized_status ) {
				$shipping_stage_label = __( 'Zugestellt', 'pckz-canonical-engine' );
			} elseif ( 'cancelled' === $normalized_status ) {
				$shipping_stage_label = __( 'Storniert', 'pckz-canonical-engine' );
			}
			if ( ! empty( $shipping['shipment_status'] ) ) {
				$shipping_stage_label = (string) $shipping['shipment_status'];
			}
			?>
			<section class="pckz-tracking__result">
				<header class="pckz-tracking__result-header">
					<div>
						<p class="pckz-tracking__meta-label"><?php esc_html_e( 'Tracking-ID', 'pckz-canonical-engine' ); ?></p>
						<p class="pckz-tracking__tracking-id"><?php echo esc_html( $public_id ); ?></p>
					</div>
					<span class="pckz-status-badge <?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( $status_label ); ?></span>
				</header>

				<p class="pckz-tracking__status-message"><?php echo esc_html( $status_message ); ?></p>

				<section class="pckz-tracking__progress-card" aria-label="<?php esc_attr_e( 'Fortschrittsübersicht', 'pckz-canonical-engine' ); ?>">
					<div class="pckz-tracking__progress-head">
						<h3 class="pckz-tracking__section-title"><?php esc_html_e( 'Bestellfortschritt', 'pckz-canonical-engine' ); ?></h3>
						<span class="pckz-tracking__progress-value"><?php echo esc_html( $progress_percent ); ?>%</span>
					</div>
					<div class="pckz-tracking__progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( (string) $progress_percent ); ?>">
						<span class="pckz-tracking__progress-fill" style="width: <?php echo esc_attr( (string) $progress_percent ); ?>%;"></span>
					</div>
				</section>

				<div class="pckz-tracking__status-grid">
					<article class="pckz-tracking__status-card">
						<p class="pckz-tracking__status-label"><?php esc_html_e( 'Bestellung', 'pckz-canonical-engine' ); ?></p>
						<p class="pckz-tracking__status-value"><?php echo esc_html( $order_stage_label ); ?></p>
					</article>
					<article class="pckz-tracking__status-card">
						<p class="pckz-tracking__status-label"><?php esc_html_e( 'Produktion', 'pckz-canonical-engine' ); ?></p>
						<p class="pckz-tracking__status-value"><?php echo esc_html( $production_stage_label ); ?></p>
					</article>
					<article class="pckz-tracking__status-card">
						<p class="pckz-tracking__status-label"><?php esc_html_e( 'Versand', 'pckz-canonical-engine' ); ?></p>
						<p class="pckz-tracking__status-value"><?php echo esc_html( $shipping_stage_label ); ?></p>
					</article>
				</div>

				<div class="pckz-tracking__facts">
					<article class="pckz-tracking__fact">
						<p class="pckz-tracking__fact-label"><?php esc_html_e( 'Artikel', 'pckz-canonical-engine' ); ?></p>
						<p class="pckz-tracking__fact-value"><?php echo esc_html( $product_title ?: '—' ); ?></p>
					</article>
					<article class="pckz-tracking__fact">
						<p class="pckz-tracking__fact-label"><?php esc_html_e( 'Bestelldatum', 'pckz-canonical-engine' ); ?></p>
						<p class="pckz-tracking__fact-value"><?php echo esc_html( $order['created_at'] ?? '' ); ?></p>
					</article>
					<article class="pckz-tracking__fact">
						<p class="pckz-tracking__fact-label"><?php esc_html_e( 'Trackingnummer', 'pckz-canonical-engine' ); ?></p>
						<p class="pckz-tracking__fact-value"><?php echo esc_html( $shipping['tracking_number'] ?: '—' ); ?></p>
					</article>
					<article class="pckz-tracking__fact">
						<p class="pckz-tracking__fact-label"><?php esc_html_e( 'Versanddienstleister', 'pckz-canonical-engine' ); ?></p>
						<p class="pckz-tracking__fact-value"><?php echo esc_html( $shipping['carrier'] ?: '—' ); ?></p>
					</article>
					<article class="pckz-tracking__fact">
						<p class="pckz-tracking__fact-label"><?php esc_html_e( 'Aktueller Standort', 'pckz-canonical-engine' ); ?></p>
						<p class="pckz-tracking__fact-value"><?php echo esc_html( $shipping['current_location'] ?: '—' ); ?></p>
					</article>
					<article class="pckz-tracking__fact">
						<p class="pckz-tracking__fact-label"><?php esc_html_e( 'Versandstatus', 'pckz-canonical-engine' ); ?></p>
						<p class="pckz-tracking__fact-value"><?php echo esc_html( $shipping_stage_label ?: '—' ); ?></p>
					</article>
					<article class="pckz-tracking__fact">
						<p class="pckz-tracking__fact-label"><?php esc_html_e( 'Voraussichtliche Lieferung', 'pckz-canonical-engine' ); ?></p>
						<p class="pckz-tracking__fact-value"><?php echo esc_html( $shipping['estimated_delivery'] ?: '—' ); ?></p>
					</article>
					<article class="pckz-tracking__fact">
						<p class="pckz-tracking__fact-label"><?php esc_html_e( 'Versandt am', 'pckz-canonical-engine' ); ?></p>
						<p class="pckz-tracking__fact-value"><?php echo esc_html( $shipping['shipping_date'] ?: '—' ); ?></p>
					</article>
				</div>

				<?php if ( ! empty( $timeline ) ) : ?>
					<section class="pckz-tracking__timeline-wrap" aria-label="<?php esc_attr_e( 'Vollständige Timeline', 'pckz-canonical-engine' ); ?>">
						<h3 class="pckz-tracking__section-title"><?php esc_html_e( 'Produktion & Bestellung', 'pckz-canonical-engine' ); ?></h3>
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
									<div class="pckz-tracking__timeline-copy">
										<span class="pckz-tracking__timeline-label"><?php echo esc_html( $step['label'] ); ?></span>
										<span class="pckz-tracking__timeline-state"><?php echo esc_html( $timeline_state_labels[ $step['state'] ] ?? $step['state'] ); ?></span>
									</div>
								</li>
							<?php endforeach; ?>
						</ol>
					</section>
				<?php endif; ?>

				<?php if ( ! empty( $shipping['events'] ) && is_array( $shipping['events'] ) ) : ?>
					<section class="pckz-tracking__timeline-wrap" aria-label="<?php esc_attr_e( 'Versandverlauf', 'pckz-canonical-engine' ); ?>">
						<h3 class="pckz-tracking__section-title"><?php esc_html_e( 'Versandverlauf', 'pckz-canonical-engine' ); ?></h3>
						<ol class="pckz-tracking__shipment-events">
							<?php foreach ( $shipping['events'] as $event ) : ?>
								<li class="pckz-tracking__shipment-event">
									<p class="pckz-tracking__shipment-main">
										<?php echo esc_html( (string) ( $event['status'] ?? '' ) ?: __( 'Update', 'pckz-canonical-engine' ) ); ?>
									</p>
									<p class="pckz-tracking__shipment-meta">
										<?php
										echo esc_html(
											trim(
												(string) ( $event['date'] ?? '' )
												. ( ! empty( $event['location'] ) ? ' · ' . (string) $event['location'] : '' )
												. ( ! empty( $event['message'] ) ? ' · ' . (string) $event['message'] : '' )
											)
										);
										?>
									</p>
								</li>
							<?php endforeach; ?>
						</ol>
					</section>
				<?php endif; ?>

				<?php if ( ! empty( $shipping['tracking_url'] ) ) : ?>
					<p class="pckz-tracking__shipping-action">
						<a href="<?php echo esc_url( $shipping['tracking_url'] ); ?>" target="_blank" rel="noopener noreferrer" class="pckz-tracking__submit pckz-tracking__submit--ghost">
							<?php esc_html_e( 'Sendung beim Versanddienstleister verfolgen', 'pckz-canonical-engine' ); ?>
						</a>
					</p>
				<?php endif; ?>
			</section>
		<?php endif; ?>
	</div>
</div>
