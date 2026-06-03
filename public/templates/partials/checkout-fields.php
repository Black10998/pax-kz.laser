<?php
/**
 * Checkout fields: customer data, order summary (commerce only).
 *
 * @package PCKZCanonicalEngine
 * @var array  $pricing
 * @var bool   $paypal_enabled
 * @var bool   $paypal_only
 * @var string $checkout_product_title
 * @var int    $product_id
 */

defined( 'ABSPATH' ) || exit;

$show_price           = ! empty( $pricing['show'] ) && ( (float) ( $pricing['unit_price'] ?? 0 ) > 0 );
$notice_html          = class_exists( 'PCKZ_Commerce' ) ? PCKZ_Commerce::get_checkout_notice_html() : '';
$allowed_currencies   = class_exists( 'PCKZ_Commerce' ) ? PCKZ_Commerce::get_allowed_currency_codes() : array();
$currency_switch      = class_exists( 'PCKZ_Commerce' ) && PCKZ_Commerce::currency_switch_enabled();
$catalog              = class_exists( 'PCKZ_Commerce' ) ? PCKZ_Commerce::currency_catalog() : array();
$active_currency      = $pricing['currency_code'] ?? 'EUR';
$countries            = class_exists( 'PCKZ_Commerce' ) ? PCKZ_Commerce::checkout_countries() : array( 'DE' => 'Deutschland' );
$product_title        = $checkout_product_title ?? get_the_title( $product_id ?? 0 );
$product_price_label  = $pricing['formatted_base'] ?? ( $pricing['formatted_unit'] ?? '' );
$shipping_price_label = $pricing['formatted_setup'] ?? '';
if ( '' === $shipping_price_label && class_exists( 'PCKZ_Commerce' ) ) {
	$shipping_price_label = PCKZ_Commerce::format_money(
		0,
		(string) ( $pricing['currency_symbol'] ?? '€' ),
		(string) ( $pricing['currency_code'] ?? 'EUR' )
	);
}
$artwork_max_mb = class_exists( 'PCKZ_Customer_Artwork' )
	? (int) ( PCKZ_Customer_Artwork::MAX_BYTES / 1048576 )
	: 5;
?>
<div class="pckz-checkout" data-checkout>
	<header class="pckz-checkout__header">
		<h2 class="pckz-checkout__heading">Kasse</h2>
		<p class="pckz-checkout__intro">Personalisiertes Produkt: <strong><?php echo esc_html( $product_title ); ?></strong></p>
	</header>

	<?php if ( $show_price ) : ?>
		<section class="pckz-checkout__summary" aria-labelledby="pckz-order-summary-heading" data-order-summary>
			<h3 id="pckz-order-summary-heading" class="pckz-checkout__summary-heading">Bestellübersicht</h3>
			<dl class="pckz-checkout__summary-list">
				<div class="pckz-checkout__summary-row">
					<dt>Produktpreis</dt>
					<dd data-summary-product-price><?php echo esc_html( $product_price_label ); ?></dd>
				</div>
				<div class="pckz-checkout__summary-row">
					<dt>Versandkosten</dt>
					<dd data-summary-shipping><?php echo esc_html( $shipping_price_label ); ?></dd>
				</div>
				<div class="pckz-checkout__summary-row pckz-checkout__summary-row--total">
					<dt>Gesamt</dt>
					<dd data-order-summary-total><?php echo esc_html( $pricing['formatted_unit'] ?? '' ); ?></dd>
				</div>
			</dl>
			<p class="pckz-checkout__summary-note">Versandkosten werden aus den Shop-Einstellungen übernommen.</p>
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
		</section>
	<?php endif; ?>

	<section class="pckz-checkout__customer" aria-labelledby="pckz-customer-heading">
		<h3 id="pckz-customer-heading" class="pckz-checkout__section-heading">Kundendaten &amp; Versandadresse</h3>

		<div class="pckz-checkout__field-grid">
			<div class="pckz-field">
				<label class="pckz-field__label" for="pckz-customer-first-name">Vorname <span class="pckz-required">*</span></label>
				<input type="text" class="pckz-field__control" id="pckz-customer-first-name" name="pckz_customer_first_name" autocomplete="given-name" required data-field="customer_first_name">
			</div>
			<div class="pckz-field">
				<label class="pckz-field__label" for="pckz-customer-last-name">Nachname <span class="pckz-required">*</span></label>
				<input type="text" class="pckz-field__control" id="pckz-customer-last-name" name="pckz_customer_last_name" autocomplete="family-name" required data-field="customer_last_name">
			</div>
			<div class="pckz-field pckz-field--full">
				<label class="pckz-field__label" for="pckz-customer-email">E-Mail <span class="pckz-required">*</span></label>
				<input type="email" class="pckz-field__control" id="pckz-customer-email" name="pckz_customer_email" autocomplete="email" required placeholder="ihre@email.de" data-field="customer_email">
				<p class="pckz-field__hint">Für Bestellbestätigung und Rückfragen.</p>
			</div>
			<div class="pckz-field pckz-field--full">
				<label class="pckz-field__label" for="pckz-customer-phone">Telefon <span class="pckz-required">*</span></label>
				<input type="tel" class="pckz-field__control" id="pckz-customer-phone" name="pckz_customer_phone" autocomplete="tel" required data-field="customer_phone">
			</div>
			<div class="pckz-field">
				<label class="pckz-field__label" for="pckz-customer-street">Straße <span class="pckz-required">*</span></label>
				<input type="text" class="pckz-field__control" id="pckz-customer-street" name="pckz_customer_street" autocomplete="street-address" required data-field="customer_street">
			</div>
			<div class="pckz-field">
				<label class="pckz-field__label" for="pckz-customer-house-number">Hausnummer <span class="pckz-required">*</span></label>
				<input type="text" class="pckz-field__control" id="pckz-customer-house-number" name="pckz_customer_house_number" required data-field="customer_house_number">
			</div>
			<div class="pckz-field">
				<label class="pckz-field__label" for="pckz-customer-postal-code">PLZ <span class="pckz-required">*</span></label>
				<input type="text" class="pckz-field__control" id="pckz-customer-postal-code" name="pckz_customer_postal_code" autocomplete="postal-code" required data-field="customer_postal_code">
			</div>
			<div class="pckz-field">
				<label class="pckz-field__label" for="pckz-customer-city">Ort <span class="pckz-required">*</span></label>
				<input type="text" class="pckz-field__control" id="pckz-customer-city" name="pckz_customer_city" autocomplete="address-level2" required data-field="customer_city">
			</div>
			<div class="pckz-field pckz-field--full">
				<label class="pckz-field__label" for="pckz-customer-country">Land <span class="pckz-required">*</span></label>
				<select class="pckz-field__control" id="pckz-customer-country" name="pckz_customer_country" autocomplete="country" required data-field="customer_country">
					<?php foreach ( $countries as $code => $label ) : ?>
						<option value="<?php echo esc_attr( $code ); ?>" <?php selected( 'DE', $code ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>
	</section>

	<section class="pckz-checkout__wishes" aria-labelledby="pckz-wishes-heading" data-customer-wishes-section>
		<h3 id="pckz-wishes-heading" class="pckz-checkout__section-heading">Lassen Sie uns Ihre Wünsche wissen</h3>
		<p class="pckz-checkout__wishes-lead">
			<?php esc_html_e( 'Optional: Besondere Wünsche, Hinweise zur Produktion oder Ihre vorhandene Grafik (Logo, Linienzeichnung, Symbol, Firmenlogo, Vektordatei).', 'pckz-canonical-engine' ); ?>
		</p>
		<div class="pckz-field pckz-field--full">
			<label class="pckz-field__label" for="pckz-customer-wishes"><?php esc_html_e( 'Ihre Nachricht', 'pckz-canonical-engine' ); ?></label>
			<textarea class="pckz-field__control" id="pckz-customer-wishes" name="pckz_customer_wishes" rows="3" placeholder="<?php esc_attr_e( 'Besondere Wünsche, Hinweise zur Produktion oder Fragen (optional)', 'pckz-canonical-engine' ); ?>" data-field="customer_wishes"></textarea>
		</div>
		<div class="pckz-customer-artwork" data-customer-artwork>
			<label class="pckz-customer-artwork__label" for="pckz-customer-artwork-file">
				<?php esc_html_e( 'Eigene Grafik anhängen (optional)', 'pckz-canonical-engine' ); ?>
			</label>
			<div class="pckz-customer-artwork__row">
				<input
					type="file"
					class="pckz-customer-artwork__input"
					id="pckz-customer-artwork-file"
					name="pckz_customer_artwork_file"
					accept=".svg,.png,.jpg,.jpeg,.webp,image/svg+xml,image/png,image/jpeg,image/webp"
					data-customer-artwork-input
				>
				<span class="pckz-customer-artwork__name" data-customer-artwork-name aria-live="polite">
					<?php esc_html_e( 'Keine Datei ausgewählt', 'pckz-canonical-engine' ); ?>
				</span>
				<button type="button" class="pckz-customer-artwork__clear pckz-hidden" data-customer-artwork-clear>
					<?php esc_html_e( 'Entfernen', 'pckz-canonical-engine' ); ?>
				</button>
			</div>
			<p class="pckz-customer-artwork__hint">
				<?php
				printf(
					/* translators: %d: max file size in MB */
					esc_html__( 'SVG, PNG, JPG, JPEG oder WEBP — max. %d MB.', 'pckz-canonical-engine' ),
					$artwork_max_mb
				);
				?>
			</p>
			<p class="pckz-customer-artwork__status pckz-hidden" data-customer-artwork-status role="status" aria-live="polite"></p>
		</div>
	</section>

	<?php if ( $notice_html ) : ?>
		<div class="pckz-checkout__notice" role="note">
			<?php echo $notice_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
	<?php endif; ?>

	<div class="pckz-checkout__status pckz-hidden" data-payment-status role="status" aria-live="polite"></div>
</div>
