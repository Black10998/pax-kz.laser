<?php
/**
 * Checkout payment actions.
 *
 * @package PCKZCanonicalEngine
 * @var bool   $payment_enabled
 * @var bool   $payment_only
 * @var string $payment_provider
 * @var string $payment_provider_label
 * @var string $payment_button_label
 * @var string $payment_hint
 */

defined( 'ABSPATH' ) || exit;

$payment_only = isset( $payment_only ) ? (bool) $payment_only : ( class_exists( 'PCKZ_Commerce' ) && PCKZ_Commerce::checkout_paypal_only() );
$payment_provider = isset( $payment_provider ) ? sanitize_key( $payment_provider ) : ( class_exists( 'PCKZ_Commerce' ) ? PCKZ_Commerce::active_payment_provider() : 'paypal' );
$payment_provider_label = isset( $payment_provider_label ) ? (string) $payment_provider_label : ( class_exists( 'PCKZ_Payments' ) ? PCKZ_Payments::active_provider_label() : 'PayPal' );
$payment_button_label = isset( $payment_button_label ) ? (string) $payment_button_label : ( class_exists( 'PCKZ_Payments' ) ? PCKZ_Payments::active_button_label() : __( 'Jetzt mit PayPal bezahlen', 'pckz-canonical-engine' ) );
$payment_hint = isset( $payment_hint ) ? (string) $payment_hint : ( class_exists( 'PCKZ_Payments' ) ? PCKZ_Payments::active_provider_hint() : __( 'Sie werden sicher zu PayPal weitergeleitet. Nach erfolgreicher Zahlung erhalten Sie eine Bestellbestätigung per E-Mail.', 'pckz-canonical-engine' ) );
$payment_diagnostics = class_exists( 'PCKZ_Commerce' ) ? PCKZ_Commerce::payment_configuration_diagnostics() : array();
$is_admin_viewer     = current_user_can( 'manage_options' );
?>
<section class="pckz-checkout__payment" aria-labelledby="pckz-payment-heading">
	<h3 id="pckz-payment-heading" class="pckz-checkout__payment-heading">Zahlung</h3>

	<?php if ( $payment_only ) : ?>
		<p class="pckz-checkout__payment-lead">
			<strong><?php echo esc_html( sprintf( __( 'Schließen Sie die Zahlung über %s ab, um Ihre Bestellung zu finalisieren.', 'pckz-canonical-engine' ), $payment_provider_label ) ); ?></strong>
			<?php echo esc_html( sprintf( __( 'Ohne bestätigte %s-Zahlung wird keine Bestellung ausgelöst.', 'pckz-canonical-engine' ), $payment_provider_label ) ); ?>
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
			<p class="pckz-checkout__export-hint pckz-hidden" data-export-ready-hint aria-hidden="true">
				<?php esc_html_e( 'Exportdaten werden im Hintergrund vorbereitet.', 'pckz-canonical-engine' ); ?>
			</p>
			<button type="button" class="pckz-btn pckz-btn--paypal pckz-btn--checkout-primary" data-action="paypal-checkout" data-provider="<?php echo esc_attr( $payment_provider ); ?>" disabled aria-disabled="true">
				<span class="pckz-btn__paypal-mark" aria-hidden="true"><?php echo esc_html( $payment_provider_label ); ?></span>
				<span class="pckz-btn__text"><?php esc_html_e( 'Weiter zur Zahlung', 'pckz-canonical-engine' ); ?></span>
				<span class="pckz-btn__spinner pckz-hidden" aria-hidden="true"></span>
			</button>
			<p class="pckz-checkout__paypal-hint"><?php echo esc_html( $payment_hint ); ?></p>
		</div>
	<?php else : ?>
		<div class="pckz-checkout__payment-unavailable">
			<p class="pckz-checkout__payment-lead">
				<strong><?php esc_html_e( 'Online-Zahlung ist derzeit nicht verfügbar.', 'pckz-canonical-engine' ); ?></strong>
				<?php esc_html_e( 'Bitte kontaktieren Sie den Shop-Betreiber.', 'pckz-canonical-engine' ); ?>
			</p>
			<?php if ( $is_admin_viewer && ! empty( $payment_diagnostics['issues'] ) ) : ?>
				<div class="pckz-checkout__payment-admin-hint" role="note">
					<p><strong><?php esc_html_e( 'Administrator-Hinweis:', 'pckz-canonical-engine' ); ?></strong></p>
					<ul>
						<?php foreach ( (array) $payment_diagnostics['issues'] as $issue ) : ?>
							<li><?php echo esc_html( (string) $issue ); ?></li>
						<?php endforeach; ?>
					</ul>
					<p class="description">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=pckz-settings&section=paypal' ) ); ?>">
							<?php esc_html_e( 'PayPal-Einstellungen öffnen', 'pckz-canonical-engine' ); ?>
						</a>
					</p>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</section>
