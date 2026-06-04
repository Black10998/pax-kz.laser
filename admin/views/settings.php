<?php
/**
 * Admin settings view.
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

$color_palette_str = implode( ', ', $settings['color_palette'] ?? array() );
$fonts_json        = wp_json_encode( $settings['fonts'] ?? array(), JSON_PRETTY_PRINT );
$creator_products  = PCKZ_Post_Type::get_published_products();
$default_product   = absint( $settings['default_creator_product_id'] ?? 0 );
$active_section    = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : 'general';
$payment_diag      = class_exists( 'PCKZ_Commerce' ) ? PCKZ_Commerce::payment_configuration_diagnostics() : array();
$is_master_install = class_exists( 'PCKZ_Licensing' ) && PCKZ_Licensing::is_master_mode();

$catalog          = class_exists( 'PCKZ_Commerce' ) ? PCKZ_Commerce::currency_catalog() : array( 'EUR' => array( 'label' => 'EUR' ) );
$enabled          = $settings['price_currencies_enabled'] ?? array( 'EUR' );
$default_currency = $settings['price_default_currency'] ?? ( $settings['price_currency_code'] ?? 'EUR' );
if ( ! is_array( $enabled ) ) {
	$enabled = array( 'EUR' );
}
$base_price     = (float) ( $settings['price_base'] ?? 0 );
$shipping_price = (float) ( $settings['price_setup_fee'] ?? 0 );
$preview_total  = $base_price + $shipping_price;
$by_currency    = $settings['price_by_currency'] ?? array();
?>
<div class="wrap pckz-admin-wrap pckz-settings-wrap">
	<?php
	$hero_title       = __( 'Product Creator Settings', 'pckz-canonical-engine' );
	$hero_description = __( 'Configure pricing, checkout, frontend appearance, licensing, and export options from one organized workspace.', 'pckz-canonical-engine' );
	include PCKZCE_PLUGIN_DIR . 'admin/views/partials/page-hero.php';
	?>
	<div class="pckz-settings-shell">
		<?php include PCKZCE_PLUGIN_DIR . 'admin/views/partials/settings-nav.php'; ?>

		<div class="pckz-settings-layout__main">
			<form method="post" action="options.php" class="pckz-settings-form">
				<?php settings_fields( 'pckz_settings_group' ); ?>

				<section id="pckz-section-general" class="pckz-panel">
					<header class="pckz-panel__header">
						<h2><?php esc_html_e( 'General Settings', 'pckz-canonical-engine' ); ?></h2>
						<p><?php esc_html_e( 'Default product, production defaults, and WooCommerce integration.', 'pckz-canonical-engine' ); ?></p>
					</header>
					<div class="pckz-panel__body">
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><?php esc_html_e( 'Default creator product', 'pckz-canonical-engine' ); ?></th>
								<td>
									<select name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[default_creator_product_id]">
										<option value="0"><?php esc_html_e( 'Auto (first published product)', 'pckz-canonical-engine' ); ?></option>
										<?php foreach ( $creator_products as $product ) : ?>
											<option value="<?php echo esc_attr( (string) $product->ID ); ?>" <?php selected( $default_product, $product->ID ); ?>>
												<?php echo esc_html( $product->post_title . ' (#' . $product->ID . ')' ); ?>
											</option>
										<?php endforeach; ?>
									</select>
									<p class="description"><?php esc_html_e( 'Used when you embed [product_creator] without an id on a page.', 'pckz-canonical-engine' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Default DPI (print export)', 'pckz-canonical-engine' ); ?></th>
								<td><input type="number" min="72" max="600" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[default_dpi]" value="<?php echo esc_attr( $settings['default_dpi'] ); ?>"></td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'WooCommerce', 'pckz-canonical-engine' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[enable_woocommerce]" value="1" <?php checked( ! empty( $settings['enable_woocommerce'] ) ); ?>>
										<?php esc_html_e( 'Enable WooCommerce cart integration', 'pckz-canonical-engine' ); ?>
									</label>
									<br>
									<label>
										<input type="checkbox" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[require_design]" value="1" <?php checked( ! empty( $settings['require_design'] ) ); ?>>
										<?php esc_html_e( 'Require saved design before add to cart', 'pckz-canonical-engine' ); ?>
									</label>
								</td>
							</tr>
						</table>
					</div>
				</section>

				<section id="pckz-section-frontend" class="pckz-panel">
					<header class="pckz-panel__header">
						<h2><?php esc_html_e( 'Frontend Display', 'pckz-canonical-engine' ); ?></h2>
						<p><?php esc_html_e( 'Colors, theme, fonts, and customer-facing checkout messaging.', 'pckz-canonical-engine' ); ?></p>
					</header>
					<div class="pckz-panel__body">
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><?php esc_html_e( 'Primary Color', 'pckz-canonical-engine' ); ?></th>
								<td><input type="color" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[primary_color]" value="<?php echo esc_attr( $settings['primary_color'] ); ?>"></td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Accent Color', 'pckz-canonical-engine' ); ?></th>
								<td><input type="color" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[accent_color]" value="<?php echo esc_attr( $settings['accent_color'] ); ?>"></td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'UI Theme', 'pckz-canonical-engine' ); ?></th>
								<td>
									<select name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[ui_theme]">
										<option value="dark" <?php selected( $settings['ui_theme'], 'dark' ); ?>><?php esc_html_e( 'Dark (premium)', 'pckz-canonical-engine' ); ?></option>
										<option value="light" <?php selected( $settings['ui_theme'], 'light' ); ?>><?php esc_html_e( 'Light', 'pckz-canonical-engine' ); ?></option>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Color Palette', 'pckz-canonical-engine' ); ?></th>
								<td>
									<input type="text" class="large-text" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[color_palette]" value="<?php echo esc_attr( $color_palette_str ); ?>" placeholder="#FFFFFF, #000000">
									<p class="description"><?php esc_html_e( 'Comma-separated hex colors for the creator UI.', 'pckz-canonical-engine' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Google Fonts URL', 'pckz-canonical-engine' ); ?></th>
								<td><input type="url" class="large-text" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[google_fonts_url]" value="<?php echo esc_attr( $settings['google_fonts_url'] ); ?>"></td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Fabric.js CDN', 'pckz-canonical-engine' ); ?></th>
								<td><input type="url" class="large-text" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[fabric_cdn]" value="<?php echo esc_attr( $settings['fabric_cdn'] ); ?>"></td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Checkout reassurance message', 'pckz-canonical-engine' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[checkout_notice_enabled]" value="1" <?php checked( ! empty( $settings['checkout_notice_enabled'] ) ); ?>>
										<?php esc_html_e( 'Display reassurance notice in checkout', 'pckz-canonical-engine' ); ?>
									</label>
									<div style="margin-top:12px;">
										<?php
										wp_editor(
											$settings['checkout_notice_message'] ?? PCKZ_Settings::default_checkout_notice_message(),
											'pckz_checkout_notice_message',
											array(
												'textarea_name' => PCKZ_Settings::OPTION_KEY . '[checkout_notice_message]',
												'textarea_rows' => 6,
												'media_buttons' => false,
												'teeny'         => true,
												'quicktags'     => true,
											)
										);
										?>
									</div>
								</td>
							</tr>
						</table>
					</div>
				</section>

				<section id="pckz-section-pricing" class="pckz-panel">
					<header class="pckz-panel__header">
						<h2><?php esc_html_e( 'Pricing', 'pckz-canonical-engine' ); ?></h2>
						<p><?php esc_html_e( 'Product price, shipping, currencies, and what customers see during checkout.', 'pckz-canonical-engine' ); ?></p>
					</header>
					<div class="pckz-panel__body">
						<section class="pckz-pricing-panel" data-pricing-panel>
							<div class="pckz-pricing-panel__toggle">
								<label class="pckz-pricing-checkbox">
									<input type="checkbox" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[price_show_enabled]" value="1" <?php checked( ! empty( $settings['price_show_enabled'] ) ); ?>>
									<span><?php esc_html_e( 'Preis im Konfigurator anzeigen', 'pckz-canonical-engine' ); ?></span>
								</label>
							</div>
							<div class="pckz-pricing-grid">
								<div class="pckz-pricing-field">
									<label for="pckz-price-base"><strong><?php esc_html_e( 'Produktpreis (€)', 'pckz-canonical-engine' ); ?></strong></label>
									<input id="pckz-price-base" class="pckz-pricing-input" type="number" step="0.01" min="0" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[price_base]" value="<?php echo esc_attr( $base_price ); ?>" data-pricing-base>
									<p class="description"><?php esc_html_e( 'Base product price shown to customers and sent to PayPal.', 'pckz-canonical-engine' ); ?></p>
								</div>
								<div class="pckz-pricing-field">
									<label for="pckz-price-setup-fee"><strong><?php esc_html_e( 'Versandkosten (€)', 'pckz-canonical-engine' ); ?></strong></label>
									<input id="pckz-price-setup-fee" class="pckz-pricing-input" type="number" step="0.01" min="0" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[price_setup_fee]" value="<?php echo esc_attr( $shipping_price ); ?>" data-pricing-shipping>
								</div>
								<div class="pckz-pricing-field pckz-pricing-field--total">
									<label for="pckz-price-total-preview"><strong><?php esc_html_e( 'Gesamtpreis (Vorschau)', 'pckz-canonical-engine' ); ?></strong></label>
									<input id="pckz-price-total-preview" class="pckz-pricing-input pckz-pricing-input--readonly" type="text" value="<?php echo esc_attr( number_format( $preview_total, 2, '.', '' ) ); ?>" readonly data-pricing-total>
								</div>
							</div>
							<div class="pckz-pricing-subsection">
								<label for="pckz-price-default-currency"><strong><?php esc_html_e( 'Default currency', 'pckz-canonical-engine' ); ?></strong></label>
								<select id="pckz-price-default-currency" class="pckz-pricing-select" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[price_default_currency]">
									<?php foreach ( $catalog as $code => $meta ) : ?>
										<?php if ( in_array( $code, $enabled, true ) ) : ?>
											<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $default_currency, $code ); ?>>
												<?php echo esc_html( $meta['label'] ?? $code ); ?>
											</option>
										<?php endif; ?>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="pckz-pricing-subsection">
								<p><strong><?php esc_html_e( 'Enabled currencies', 'pckz-canonical-engine' ); ?></strong></p>
								<div class="pckz-pricing-currency-list">
									<?php foreach ( $catalog as $code => $meta ) : ?>
										<label class="pckz-pricing-checkbox">
											<input type="checkbox" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[price_currencies_enabled][]" value="<?php echo esc_attr( $code ); ?>" <?php checked( in_array( $code, $enabled, true ) ); ?>>
											<span><?php echo esc_html( $meta['label'] ?? $code ); ?></span>
										</label>
									<?php endforeach; ?>
								</div>
							</div>
							<div class="pckz-pricing-subsection pckz-pricing-subsection--inline">
								<div class="pckz-pricing-field">
									<label for="pckz-price-currency-symbol"><strong><?php esc_html_e( 'Currency symbol (default)', 'pckz-canonical-engine' ); ?></strong></label>
									<input id="pckz-price-currency-symbol" class="pckz-pricing-input pckz-pricing-input--symbol" type="text" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[price_currency_symbol]" value="<?php echo esc_attr( $settings['price_currency_symbol'] ?? '€' ); ?>" maxlength="4">
								</div>
								<div class="pckz-pricing-field">
									<label class="pckz-pricing-checkbox pckz-pricing-checkbox--switch">
										<input type="checkbox" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[price_allow_currency_switch]" value="1" <?php checked( ! empty( $settings['price_allow_currency_switch'] ) ); ?>>
										<span><?php esc_html_e( 'Allow currency selection in checkout', 'pckz-canonical-engine' ); ?></span>
									</label>
								</div>
							</div>
							<?php if ( class_exists( 'PCKZ_Commerce' ) ) : ?>
								<div class="pckz-pricing-subsection">
									<p><strong><?php esc_html_e( 'Base price per currency', 'pckz-canonical-engine' ); ?></strong></p>
									<div class="pckz-pricing-currency-grid">
										<?php foreach ( $enabled as $code ) : ?>
											<?php if ( isset( $catalog[ $code ] ) ) : ?>
												<label class="pckz-pricing-field pckz-pricing-field--currency">
													<span><?php echo esc_html( $code ); ?></span>
													<input class="pckz-pricing-input" type="number" step="0.01" min="0" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[price_by_currency][<?php echo esc_attr( $code ); ?>]" value="<?php echo esc_attr( $by_currency[ $code ] ?? '' ); ?>" placeholder="<?php echo esc_attr( (string) $base_price ); ?>">
												</label>
											<?php endif; ?>
										<?php endforeach; ?>
									</div>
								</div>
							<?php endif; ?>
							<p class="description">
								<button type="button" class="button button-secondary" data-pricing-preview-refresh><?php esc_html_e( 'Refresh total preview', 'pckz-canonical-engine' ); ?></button>
							</p>
						</section>
					</div>
				</section>

				<section id="pckz-section-paypal" class="pckz-panel">
					<header class="pckz-panel__header">
						<h2><?php esc_html_e( 'PayPal Checkout', 'pckz-canonical-engine' ); ?></h2>
						<p><?php esc_html_e( 'Enable PayPal payment and configure sandbox or live REST credentials.', 'pckz-canonical-engine' ); ?></p>
					</header>
					<div class="pckz-panel__body">
						<?php
						$diag_class = ! empty( $payment_diag['ready'] ) ? 'is-success' : ( ! empty( $payment_diag['issues'] ) ? 'is-warning' : 'is-muted' );
						?>
						<div class="pckz-admin-callout <?php echo esc_attr( $diag_class ); ?>">
							<p><strong><?php esc_html_e( 'Checkout status', 'pckz-canonical-engine' ); ?></strong></p>
							<?php if ( ! empty( $payment_diag['ready'] ) ) : ?>
								<p><?php esc_html_e( 'PayPal checkout is configured and ready. The frontend button appears after export validation succeeds.', 'pckz-canonical-engine' ); ?></p>
							<?php elseif ( ! empty( $payment_diag['issues'] ) ) : ?>
								<ul>
									<?php foreach ( (array) $payment_diag['issues'] as $issue ) : ?>
										<li><?php echo esc_html( (string) $issue ); ?></li>
									<?php endforeach; ?>
								</ul>
							<?php else : ?>
								<p><?php esc_html_e( 'Complete the fields below to enable checkout.', 'pckz-canonical-engine' ); ?></p>
							<?php endif; ?>
							<?php if ( ! empty( $payment_diag['notes'] ) ) : ?>
								<ul>
									<?php foreach ( (array) $payment_diag['notes'] as $note ) : ?>
										<li><em><?php echo esc_html( (string) $note ); ?></em></li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
						</div>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><?php esc_html_e( 'Enable PayPal', 'pckz-canonical-engine' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[paypal_enabled]" value="1" <?php checked( ! empty( $settings['paypal_enabled'] ) ); ?>>
										<?php esc_html_e( 'Require PayPal payment before order completion', 'pckz-canonical-engine' ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'PayPal test mode', 'pckz-canonical-engine' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[paypal_test_mode]" value="1" <?php checked( ! empty( $settings['paypal_test_mode'] ) ); ?>>
										<?php esc_html_e( 'Use PayPal Sandbox (recommended for local and staging sites)', 'pckz-canonical-engine' ); ?>
									</label>
									<p class="description"><?php esc_html_e( 'When test mode is ON, Sandbox Client ID and Secret are used — not Live credentials.', 'pckz-canonical-engine' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Sandbox Client ID', 'pckz-canonical-engine' ); ?></th>
								<td><input type="text" class="large-text" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[paypal_sandbox_client_id]" value="<?php echo esc_attr( $settings['paypal_sandbox_client_id'] ?? '' ); ?>"></td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Sandbox Secret', 'pckz-canonical-engine' ); ?></th>
								<td><input type="password" class="large-text" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[paypal_sandbox_secret]" value="<?php echo esc_attr( $settings['paypal_sandbox_secret'] ?? '' ); ?>" autocomplete="new-password"></td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Live Client ID', 'pckz-canonical-engine' ); ?></th>
								<td><input type="text" class="large-text" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[paypal_live_client_id]" value="<?php echo esc_attr( $settings['paypal_live_client_id'] ?? '' ); ?>"></td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Live Secret', 'pckz-canonical-engine' ); ?></th>
								<td><input type="password" class="large-text" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[paypal_live_secret]" value="<?php echo esc_attr( $settings['paypal_live_secret'] ?? '' ); ?>" autocomplete="new-password"></td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Configurator page', 'pckz-canonical-engine' ); ?></th>
								<td>
									<?php
									wp_dropdown_pages(
										array(
											'name'              => PCKZ_Settings::OPTION_KEY . '[creator_page_id]',
											'selected'          => absint( $settings['creator_page_id'] ?? 0 ),
											'show_option_none'  => __( '— Auto-detect —', 'pckz-canonical-engine' ),
											'option_none_value' => '0',
										)
									);
									?>
									<p class="description"><?php esc_html_e( 'Page containing the [product_creator] shortcode (used for PayPal return and payment confirmation).', 'pckz-canonical-engine' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Payment success URL', 'pckz-canonical-engine' ); ?></th>
								<td>
									<input type="url" class="large-text" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[paypal_success_url]" value="<?php echo esc_attr( $settings['paypal_success_url'] ?? '' ); ?>" placeholder="<?php echo esc_attr( home_url( '/' ) ); ?>">
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Payment cancel URL', 'pckz-canonical-engine' ); ?></th>
								<td>
									<input type="url" class="large-text" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[paypal_cancel_url]" value="<?php echo esc_attr( $settings['paypal_cancel_url'] ?? '' ); ?>">
								</td>
							</tr>
						</table>
					</div>
				</section>

				<section id="pckz-section-payments" class="pckz-panel">
					<header class="pckz-panel__header">
						<h2><?php esc_html_e( 'Stripe / Payment Architecture', 'pckz-canonical-engine' ); ?></h2>
						<p><?php esc_html_e( 'Future-ready Stripe configuration. PayPal remains the default production provider unless Stripe is fully configured.', 'pckz-canonical-engine' ); ?></p>
					</header>
					<div class="pckz-panel__body">
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><?php esc_html_e( 'Primary provider strategy', 'pckz-canonical-engine' ); ?></th>
								<td>
									<select name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[payments_primary_provider]">
										<option value="paypal" <?php selected( $settings['payments_primary_provider'] ?? 'paypal', 'paypal' ); ?>><?php esc_html_e( 'PayPal (current production)', 'pckz-canonical-engine' ); ?></option>
										<option value="stripe" <?php selected( $settings['payments_primary_provider'] ?? 'paypal', 'stripe' ); ?>><?php esc_html_e( 'Stripe (cards, Apple Pay, Google Pay)', 'pckz-canonical-engine' ); ?></option>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Enable Stripe architecture', 'pckz-canonical-engine' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[payments_enable_stripe]" value="1" <?php checked( ! empty( $settings['payments_enable_stripe'] ) ); ?>>
										<?php esc_html_e( 'Enable Stripe checkout + webhook processing', 'pckz-canonical-engine' ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Stripe test mode', 'pckz-canonical-engine' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[payments_stripe_test_mode]" value="1" <?php checked( ! empty( $settings['payments_stripe_test_mode'] ) ); ?>>
										<?php esc_html_e( 'Use Stripe test keys', 'pckz-canonical-engine' ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Stripe publishable key', 'pckz-canonical-engine' ); ?></th>
								<td><input type="text" class="large-text" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[payments_stripe_publishable_key]" value="<?php echo esc_attr( $settings['payments_stripe_publishable_key'] ?? '' ); ?>"></td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Stripe secret key', 'pckz-canonical-engine' ); ?></th>
								<td><input type="password" class="large-text" autocomplete="new-password" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[payments_stripe_secret_key]" value="<?php echo esc_attr( $settings['payments_stripe_secret_key'] ?? '' ); ?>"></td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Stripe webhook secret', 'pckz-canonical-engine' ); ?></th>
								<td><input type="password" class="large-text" autocomplete="new-password" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[payments_stripe_webhook_secret]" value="<?php echo esc_attr( $settings['payments_stripe_webhook_secret'] ?? '' ); ?>"></td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Stripe success URL', 'pckz-canonical-engine' ); ?></th>
								<td><input type="url" class="large-text" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[payments_stripe_success_url]" value="<?php echo esc_attr( $settings['payments_stripe_success_url'] ?? '' ); ?>"></td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Stripe cancel URL', 'pckz-canonical-engine' ); ?></th>
								<td><input type="url" class="large-text" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[payments_stripe_cancel_url]" value="<?php echo esc_attr( $settings['payments_stripe_cancel_url'] ?? '' ); ?>"></td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Stripe webhook tolerance (seconds)', 'pckz-canonical-engine' ); ?></th>
								<td><input type="number" min="60" max="1800" class="small-text" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[payments_stripe_webhook_tolerance]" value="<?php echo esc_attr( (string) ( $settings['payments_stripe_webhook_tolerance'] ?? 300 ) ); ?>"></td>
							</tr>
						</table>
					</div>
				</section>

				<section id="pckz-section-licensing" class="pckz-panel">
					<header class="pckz-panel__header">
						<h2><?php esc_html_e( 'Licensing', 'pckz-canonical-engine' ); ?></h2>
						<p><?php esc_html_e( 'Client licensing status or master server security defaults.', 'pckz-canonical-engine' ); ?></p>
					</header>
					<div class="pckz-panel__body">
						<?php if ( $is_master_install ) : ?>
							<table class="form-table" role="presentation">
								<tr>
									<th scope="row"><?php esc_html_e( 'Master server identity', 'pckz-canonical-engine' ); ?></th>
									<td>
										<p><strong><?php esc_html_e( 'paxdesign.at', 'pckz-canonical-engine' ); ?></strong></p>
										<p class="description"><?php esc_html_e( 'Master Control is automatically active on this host.', 'pckz-canonical-engine' ); ?></p>
										<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=pckz-license-server' ) ); ?>"><?php esc_html_e( 'Open Master Control', 'pckz-canonical-engine' ); ?></a></p>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php esc_html_e( 'Master server URL', 'pckz-canonical-engine' ); ?></th>
									<td><input type="url" class="large-text" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[licensing_master_url]" value="<?php echo esc_attr( $settings['licensing_master_url'] ?? 'https://paxdesign.at' ); ?>"></td>
								</tr>
								<tr>
									<th scope="row"><?php esc_html_e( 'Master API key', 'pckz-canonical-engine' ); ?></th>
									<td><input type="text" class="large-text code" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[licensing_master_api_key]" value="<?php echo esc_attr( $settings['licensing_master_api_key'] ?? '' ); ?>"></td>
								</tr>
								<tr>
									<th scope="row"><?php esc_html_e( 'Security defaults', 'pckz-canonical-engine' ); ?></th>
									<td>
										<label><input type="checkbox" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[licensing_require_signed_requests]" value="1" <?php checked( ! empty( $settings['licensing_require_signed_requests'] ) ); ?>> <?php esc_html_e( 'Require signed license requests', 'pckz-canonical-engine' ); ?></label><br>
										<label><input type="checkbox" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[licensing_export_authorize]" value="1" <?php checked( ! empty( $settings['licensing_export_authorize'] ) ); ?>> <?php esc_html_e( 'Require export authorization', 'pckz-canonical-engine' ); ?></label><br>
										<label><input type="checkbox" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[licensing_export_remote_mode]" value="1" <?php checked( ! empty( $settings['licensing_export_remote_mode'] ) ); ?>> <?php esc_html_e( 'Enable remote export mode', 'pckz-canonical-engine' ); ?></label><br>
										<label><input type="checkbox" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[licensing_export_remote_strict]" value="1" <?php checked( ! empty( $settings['licensing_export_remote_strict'] ) ); ?>> <?php esc_html_e( 'Strict remote export enforcement', 'pckz-canonical-engine' ); ?></label><br>
										<label><input type="checkbox" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[licensing_strict_integrity]" value="1" <?php checked( ! empty( $settings['licensing_strict_integrity'] ) ); ?>> <?php esc_html_e( 'Strict integrity policy', 'pckz-canonical-engine' ); ?></label><br>
										<label><input type="checkbox" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[security_prefer_protected_assets]" value="1" <?php checked( ! empty( $settings['security_prefer_protected_assets'] ) || ! empty( $settings['security_prefer_minified_js'] ) ); ?>> <?php esc_html_e( 'Prefer protected/minified public creator assets (.protected.js / .min.js / .min.css)', 'pckz-canonical-engine' ); ?></label>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php esc_html_e( 'Grace period (minutes)', 'pckz-canonical-engine' ); ?></th>
									<td><input type="number" min="5" max="1440" class="small-text" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[licensing_grace_minutes]" value="<?php echo esc_attr( (string) ( $settings['licensing_grace_minutes'] ?? 120 ) ); ?>"></td>
								</tr>
							</table>
						<?php else : ?>
							<div class="pckz-admin-callout">
								<p><?php esc_html_e( 'This installation runs in client mode. License keys and update status are managed on the License Dashboard.', 'pckz-canonical-engine' ); ?></p>
								<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=pckz-license-server' ) ); ?>"><?php esc_html_e( 'Open License Dashboard', 'pckz-canonical-engine' ); ?></a></p>
								<?php if ( class_exists( 'PCKZ_Licensing' ) ) : ?>
									<?php $license_state = PCKZ_Licensing::get_client_state(); ?>
									<p><strong><?php esc_html_e( 'Current status:', 'pckz-canonical-engine' ); ?></strong> <?php echo esc_html( $license_state['status'] ?? 'unknown' ); ?><?php echo ! empty( $license_state['reason'] ) ? ' — ' . esc_html( $license_state['reason'] ) : ''; ?></p>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					</div>
				</section>

				<section id="pckz-section-export" class="pckz-panel">
					<header class="pckz-panel__header">
						<h2><?php esc_html_e( 'Export', 'pckz-canonical-engine' ); ?></h2>
						<p><?php esc_html_e( 'Manufacturing export options for production files attached to orders.', 'pckz-canonical-engine' ); ?></p>
					</header>
					<div class="pckz-panel__body">
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><?php esc_html_e( 'DXF export', 'pckz-canonical-engine' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[enable_dxf_export]" value="1" <?php checked( ! empty( $settings['enable_dxf_export'] ) ); ?>>
										<?php esc_html_e( 'Generate DXF files (optional; .lbrn2 and production SVG are always created)', 'pckz-canonical-engine' ); ?>
									</label>
									<p class="description"><?php esc_html_e( 'Production file DPI follows the default DPI setting in General.', 'pckz-canonical-engine' ); ?></p>
								</td>
							</tr>
						</table>
					</div>
				</section>

				<input type="hidden" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[fonts_json]" value="<?php echo esc_attr( $fonts_json ); ?>">

				<div class="pckz-settings-save-bar">
					<?php submit_button( __( 'Save Settings', 'pckz-canonical-engine' ), 'primary', 'submit', false ); ?>
				</div>
			</form>
		</div>
	</div>
</div>
