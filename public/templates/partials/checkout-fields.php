<?php
/**
 * Checkout fields: email, wishes, reassurance (commerce only — no export logic).
 *
 * @package PCKZCanonicalEngine
 * @var array $pricing
 * @var bool  $paypal_enabled
 */

defined( 'ABSPATH' ) || exit;

$show_price = ! empty( $pricing['show'] ) && ( (float) ( $pricing['base'] ?? 0 ) > 0 || (float) ( $pricing['setup_fee'] ?? 0 ) > 0 );
?>
<div class="pckz-checkout" data-checkout>
	<h2 class="pckz-checkout__heading">Bestellung abschließen</h2>

	<?php if ( $show_price ) : ?>
		<div class="pckz-checkout__price" data-checkout-price>
			<span class="pckz-checkout__price-label">Preis pro Stück</span>
			<span class="pckz-checkout__price-value" data-price-unit><?php echo esc_html( $pricing['formatted_base'] ?? '' ); ?></span>
			<?php if ( ! empty( $pricing['setup_fee'] ) && (float) $pricing['setup_fee'] > 0 ) : ?>
				<span class="pckz-checkout__price-setup">
					+ <?php echo esc_html( PCKZ_Commerce::format_money( $pricing['setup_fee'], $pricing['currency_symbol'], $pricing['currency_code'] ) ); ?>
					Einrichtung
				</span>
			<?php endif; ?>
			<span class="pckz-checkout__price-total" data-price-total hidden></span>
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

	<div class="pckz-checkout__notice" role="note">
		<p><strong>Keine Sorge</strong> – Ihr Entwurf wird nach der Bestellung zusätzlich von unserem Team geprüft und für die bestmögliche Produktionsqualität optimiert. Das finale Ergebnis entspricht der Vorschau und wird für eine optimale Laserproduktion professionell vorbereitet.</p>
	</div>

	<div class="pckz-checkout__status pckz-hidden" data-payment-status role="status" aria-live="polite"></div>
</div>
