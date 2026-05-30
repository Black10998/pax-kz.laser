<?php
/**
 * Checkout payment actions (PayPal-first when enabled).
 *
 * @package PCKZCanonicalEngine
 * @var bool $paypal_enabled
 * @var bool $paypal_only
 */

defined( 'ABSPATH' ) || exit;

$paypal_only = isset( $paypal_only ) ? $paypal_only : ( class_exists( 'PCKZ_Commerce' ) && PCKZ_Commerce::checkout_paypal_only() );
?>
<section class="pckz-checkout__payment" aria-labelledby="pckz-payment-heading" data-mobile-checkout-part="payment">
	<h3 id="pckz-payment-heading" class="pckz-checkout__payment-heading">Zahlung</h3>

	<?php if ( $paypal_only ) : ?>
		<p class="pckz-checkout__payment-lead">
			<strong>Schließen Sie die Zahlung über PayPal ab, um Ihre Bestellung zu finalisieren.</strong>
			Ohne bestätigte PayPal-Zahlung wird keine Bestellung ausgelöst.
		</p>
		<div class="pckz-checkout__actions">
			<div class="pckz-quantity" data-quantity>
				<label class="pckz-quantity__label" for="pckz-qty-<?php echo esc_attr( (string) $product_id ); ?>">
					Anzahl
				</label>
				<div class="pckz-quantity__control">
					<button type="button" class="pckz-quantity__btn" data-qty="minus" aria-label="Anzahl verringern">−</button>
					<input
						type="number"
						class="pckz-quantity__input"
						id="pckz-qty-<?php echo esc_attr( (string) $product_id ); ?>"
						min="1"
						value="1"
						data-field="quantity"
					>
					<button type="button" class="pckz-quantity__btn" data-qty="plus" aria-label="Anzahl erhöhen">+</button>
				</div>
			</div>
			<p class="pckz-checkout__export-hint" data-export-ready-hint>
				<?php esc_html_e( 'Zahlung wird freigegeben, sobald die Vorschau und Exportdaten bereit sind.', 'pckz-canonical-engine' ); ?>
			</p>
			<button type="button" class="pckz-btn pckz-btn--paypal pckz-btn--checkout-primary" data-action="paypal-checkout" disabled aria-disabled="true">
				<span class="pckz-btn__paypal-mark" aria-hidden="true">PayPal</span>
				<span class="pckz-btn__text">Jetzt mit PayPal bezahlen</span>
				<span class="pckz-btn__spinner" aria-hidden="true"></span>
			</button>
			<p class="pckz-checkout__paypal-hint">Sie werden sicher zu PayPal weitergeleitet. Nach erfolgreicher Zahlung erhalten Sie eine Bestellbestätigung per E-Mail.</p>
		</div>
	<?php else : ?>
		<p class="pckz-checkout__payment-lead">PayPal ist derzeit nicht aktiv. Bitte kontaktieren Sie den Shop-Betreiber.</p>
	<?php endif; ?>
</section>
