<?php
/**
 * Ledos-style license plate frame configurator (German UI).
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

$config            = PCKZ_Post_Type::get_product_config( $product_id );
$pricing           = class_exists( 'PCKZ_Commerce' ) ? PCKZ_Commerce::get_frontend_pricing( $product_id ) : array();
$show_header_price = ! empty( $pricing['show'] ) && (float) ( $pricing['unit_price'] ?? 0 ) > 0;
$header_price      = $show_header_price ? ( $pricing['formatted_unit'] ?? '' ) : '';
$payment_enabled   = class_exists( 'PCKZ_Commerce' ) && PCKZ_Commerce::payment_checkout_enabled();
$payment_only      = class_exists( 'PCKZ_Commerce' ) && PCKZ_Commerce::checkout_paypal_only();
$payment_provider  = class_exists( 'PCKZ_Commerce' ) ? PCKZ_Commerce::active_payment_provider() : 'paypal';
$payment_provider_label = class_exists( 'PCKZ_Payments' ) ? PCKZ_Payments::active_provider_label() : 'PayPal';
$payment_button_label   = class_exists( 'PCKZ_Payments' ) ? PCKZ_Payments::active_button_label() : __( 'Jetzt mit PayPal bezahlen', 'pckz-canonical-engine' );
$payment_hint           = class_exists( 'PCKZ_Payments' ) ? PCKZ_Payments::active_provider_hint() : __( 'Sie werden sicher zu PayPal weitergeleitet. Nach erfolgreicher Zahlung erhalten Sie eine Bestellbestätigung per E-Mail.', 'pckz-canonical-engine' );
$customer_options  = $config['customer_options'] ?? array();
$icon_choices      = class_exists( 'PCKZ_Icon_Library' ) ? PCKZ_Icon_Library::get_customer_icon_choices() : array();
$line_choices      = class_exists( 'PCKZ_Line_Library' ) ? PCKZ_Line_Library::get_customer_line_choices() : array();
$benefits          = $config['benefits'] ?? array();
$fonts             = PCKZ_Settings::get_fonts();
$colors            = PCKZ_Settings::get_gray_palette();
$description       = $config['description'] ?? '';
$img_day           = $config['background_day'] ?: $config['background_image'];
$img_night         = $config['background_night'] ?: $img_day;
$use_cloudlift     = ! empty( $config['use_cloudlift_layout'] );
$payment_success   = isset( $_GET['pckz_paid'] ) && '1' === (string) $_GET['pckz_paid'];
?>
<div
	class="pckz-product pckz-product--booting<?php echo $use_cloudlift ? ' pckz-product--cloudlift' : ''; ?><?php echo $payment_only ? ' pckz-product--paypal-only' : ''; ?>"
	id="pckz-creator-<?php echo esc_attr( (string) $product_id ); ?>"
	data-product-id="<?php echo esc_attr( (string) $product_id ); ?>"
	lang="de"
>
	<div class="pckz-product__inner page-width">
		<div class="pckz-creator-boot" data-creator-boot aria-live="polite" aria-busy="true">
			<span class="pckz-spinner" aria-hidden="true"></span>
			<span><?php esc_html_e( 'Vorschau wird geladen…', 'pckz-canonical-engine' ); ?></span>
		</div>
		<form class="pckz-product__form" id="pckz-form-<?php echo esc_attr( (string) $product_id ); ?>" novalidate>
			<input type="hidden" name="pckz_options[preview_mode]" value="day" data-preview-mode-input>

			<div class="pckz-product__grid">
				<div class="pckz-product__configure-stack">
				<div class="pckz-product__media-column">
					<p class="pckz-product__preview-heading">Live-Vorschau Ihres Rahmens</p>
					<div class="pckz-gallery" data-gallery>
							<div class="pckz-gallery__stage-wrap">
								<div class="pckz-gallery__stage" data-stage>
									<?php if ( $img_day ) : ?>
										<img
											class="pckz-gallery__fallback"
											data-preview-fallback
											src="<?php echo esc_url( $img_day ); ?>"
											alt="<?php echo esc_attr( get_the_title( $product_id ) ); ?>"
											crossorigin="anonymous"
										>
									<?php endif; ?>
									<div class="pckz-gallery__loader" data-loader>
										<span class="pckz-spinner"></span>
										<span>Vorschau wird geladen…</span>
									</div>
									<canvas
										id="pckz-canvas-<?php echo esc_attr( (string) $product_id ); ?>"
										class="pckz-gallery__canvas"
										aria-label="Produktvorschau mit Ihrer Personalisierung"
									></canvas>
									<button
										type="button"
										class="pckz-preview-zoom-btn"
										data-preview-zoom-btn
										tabindex="-1"
										aria-hidden="true"
									>
										<span aria-hidden="true">&#128270;</span>
									</button>
									<div class="pckz-preview-magnifier-glass" data-preview-magnifier-glass aria-hidden="true">
										<span class="pckz-preview-magnifier-glass__view" data-preview-magnifier-view></span>
									</div>
								</div>
							</div>
							<?php if ( $img_day || $img_night ) : ?>
								<div class="pckz-gallery__thumbs" aria-label="<?php esc_attr_e( 'Vorschau Tag / Nacht', 'pckz-canonical-engine' ); ?>">
									<?php if ( $img_day ) : ?>
										<button type="button" class="pckz-thumb is-active" data-preview-thumb="day" aria-pressed="true">
											<img src="<?php echo esc_url( $img_day ); ?>" alt="Tag" loading="lazy" width="72" height="42" crossorigin="anonymous">
											<span class="pckz-thumb__label">Tag</span>
										</button>
									<?php endif; ?>
									<?php if ( $img_night && $img_night !== $img_day ) : ?>
										<button type="button" class="pckz-thumb" data-preview-thumb="night" aria-pressed="false">
											<img src="<?php echo esc_url( $img_night ); ?>" alt="Nacht" loading="lazy" width="72" height="42" crossorigin="anonymous">
											<span class="pckz-thumb__label">Nacht</span>
										</button>
									<?php endif; ?>
								</div>
							<?php endif; ?>
						</div>
						<p class="pckz-gallery__caption">
							<?php
							echo $use_cloudlift
								? esc_html__( 'Live-Vorschau: Text, Symbole und Linien werden wie im Original-Configurator auf dem Rahmen platziert.', 'pckz-canonical-engine' )
								: esc_html__( 'Text und Symbole erscheinen live im unteren Streifen des Rahmens.', 'pckz-canonical-engine' );
							?>
						</p>
				</div>

				<div class="pckz-product__config-column">
					<div class="pckz-product__info">
						<h1 class="pckz-product__title"><?php echo esc_html( get_the_title( $product_id ) ); ?></h1>

						<?php if ( $show_header_price && $header_price ) : ?>
							<div class="pckz-product__price" data-product-price>
								<span class="pckz-product__price-amount"><?php echo esc_html( $header_price ); ?></span>
								<span class="pckz-product__price-note">pro Stück</span>
							</div>
						<?php endif; ?>

						<?php if ( $description ) : ?>
							<div class="pckz-product__lead"><?php echo wp_kses_post( wpautop( $description ) ); ?></div>
						<?php endif; ?>

						<div class="pckz-product__divider"></div>

						<div class="pckz-options" data-options>
							<?php include PCKZCE_PLUGIN_DIR . 'public/templates/partials/options-form.php'; ?>
						</div>

						<?php if ( ! empty( $benefits ) ) : ?>
							<ul class="pckz-product__benefits">
								<?php foreach ( $benefits as $benefit ) : ?>
									<li>
										<svg class="pckz-benefit-icon" width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><path fill="currentColor" d="M6.5 11.5 3 8l1-1 2.5 2.5L12 4l1 1z"/></svg>
										<span><?php echo esc_html( $benefit ); ?></span>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>
				</div>
				</div>

				<?php if ( class_exists( 'PCKZ_Commerce' ) ) : ?>
					<?php if ( $payment_success ) : ?>
						<div class="pckz-checkout__notice pckz-checkout__notice--success" role="status" data-payment-success>
							<p><?php esc_html_e( 'Vielen Dank. Ihre Zahlung wurde erfolgreich abgeschlossen. Ihre Bestellung wurde erfolgreich übermittelt.', 'pckz-canonical-engine' ); ?></p>
							<?php if ( ! empty( $_GET['pckz_order'] ) && class_exists( 'PCKZ_Commerce' ) ) : ?>
								<p class="pckz-checkout__order-id">
									<?php
									echo esc_html(
										sprintf(
											/* translators: %s: public order number */
											__( 'Ihre Tracking-ID: %s', 'pckz-canonical-engine' ),
											PCKZ_Commerce::format_order_number( absint( $_GET['pckz_order'] ) )
										)
									);
									?>
								</p>
							<?php endif; ?>
						</div>
					<?php endif; ?>
					<div class="pckz-checkout-panel">
						<?php
						$checkout_product_title = get_the_title( $product_id );
						include PCKZCE_PLUGIN_DIR . 'public/templates/partials/checkout-fields.php';
						include PCKZCE_PLUGIN_DIR . 'public/templates/partials/checkout-actions.php';
						?>
					</div>
				<?php endif; ?>
			</div>
		</form>
	</div>

	<div class="pckz-validation-panel pckz-hidden" data-validation-panel role="alert" aria-live="assertive">
		<strong class="pckz-validation-panel__title" data-validation-title></strong>
		<ul class="pckz-validation-panel__list" data-validation-list></ul>
	</div>

	<div class="pckz-toast" role="status" aria-live="polite" hidden></div>
</div>
