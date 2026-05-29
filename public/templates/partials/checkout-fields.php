<?php
/**
 * Checkout fields: email, wishes, reassurance (commerce only — no export logic).
 *
 * @package PCKZCanonicalEngine
 * @var array $pricing
 * @var bool  $paypal_enabled
 */

defined( 'ABSPATH' ) || exit;

$show_price = ! empty( $pricing['show'] ) && ( (float) ( $pricing['unit_price'] ?? 0 ) > 0 );
$notice_html = class_exists( 'PCKZ_Commerce' ) ? PCKZ_Commerce::get_checkout_notice_html() : '';
$allowed_currencies = class_exists( 'PCKZ_Commerce' ) ? PCKZ_Commerce::get_allowed_currency_codes() : array();
$currency_switch    = class_exists( 'PCKZ_Commerce' ) && PCKZ_Commerce::currency_switch_enabled();
$catalog            = class_exists( 'PCKZ_Commerce' ) ? PCKZ_Commerce::currency_catalog() : array();
$active_currency    = $pricing['currency_code'] ?? 'EUR';
?>
<div class="pckz-checkout" data-checkout>
	<header class="pckz-checkout__header">
		<h2 class="pckz-checkout__heading">Bestellung abschließen</h2>
		<p class="pckz-checkout__intro">Bitte prüfen Sie Ihre Angaben. Die Bestellung wird erst nach erfolgreicher Zahlung abgeschlossen.</p>
	</header>

	<div class="pckz-checkout__body">
		<?php if ( $show_price ) : ?>
			<div class="pckz-checkout__price" data-checkout-price>
				<?php if ( $currency_switch && count( $allowed_currencies ) > 1 ) : ?>
					<div class="pckz-field pckz-field--currency">
						<label class="pckz-field__label" for="pckz-checkout-currency">Währung</label>
						<select class="pckz-field__control" id="pckz-checkout-currency" name="pckz_currency" data-field="currency">
							<?php foreach ( $allowed_currencies as $code ) : ?>
								<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $active_currency, $code ); ?>>
									<?php echo esc_html( $catalog[ $code ]['label'] ?? $code ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
				<?php else : ?>
					<input type="hidden" name="pckz_currency" value="<?php echo esc_attr( $active_currency ); ?>" data-field="currency">
				<?php endif; ?>
				<div class="pckz-checkout__price-row">
					<span class="pckz-checkout__price-label">Preis pro Stück</span>
					<span class="pckz-checkout__price-value" data-price-unit><?php echo esc_html( $pricing['formatted_unit'] ?? '' ); ?></span>
				</div>
				<?php if ( ! empty( $pricing['setup_fee'] ) && (float) $pricing['setup_fee'] > 0 ) : ?>
					<p class="pckz-checkout__price-detail">
						<?php
						printf(
							/* translators: 1: base price, 2: setup fee */
							esc_html__( 'Enthält %1$s zzgl. %2$s Einrichtung pro Stück.', 'pckz-canonical-engine' ),
							esc_html( $pricing['formatted_base'] ?? '' ),
							esc_html( $pricing['formatted_setup'] ?? '' )
						);
						?>
					</p>
				<?php endif; ?>
				<p class="pckz-checkout__price-total" data-price-total hidden></p>
			</div>
		<?php endif; ?>

		<div class="pckz-field pckz-field--email">
			<label class="pckz-field__label" for="pckz-customer-email">
				E-Mail-Adresse für Bestellbestätigung und Rückfragen
				<span class="pckz-required" aria-hidden="true">*</span>
			</label>
			<input
				type="email"
				class="pckz-field__control"
				id="pckz-customer-email"
				name="pckz_customer_email"
				autocomplete="email"
				required
				placeholder="ihre@email.de"
				data-field="customer_email"
			>
			<p class="pckz-field__hint">Wir verwenden Ihre E-Mail ausschließlich für die Bestellbestätigung und bei Rückfragen zu Ihrem Entwurf.</p>
		</div>

		<div class="pckz-field pckz-field--textarea">
			<label class="pckz-field__label" for="pckz-customer-wishes">
				Wünsche oder zusätzliche Informationen
			</label>
			<textarea
				class="pckz-field__control"
				id="pckz-customer-wishes"
				name="pckz_customer_wishes"
				rows="3"
				placeholder="Besondere Wünsche, Hinweise zur Produktion oder Fragen (optional)"
				data-field="customer_wishes"
			></textarea>
		</div>

		<?php if ( $notice_html ) : ?>
			<div class="pckz-checkout__notice" role="note">
				<?php echo $notice_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses_post in Commerce. ?>
			</div>
		<?php endif; ?>

		<div class="pckz-checkout__status pckz-hidden" data-payment-status role="status" aria-live="polite"></div>
	</div>
</div>
