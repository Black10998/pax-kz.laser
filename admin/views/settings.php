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
?>
<div class="wrap pckz-admin-wrap pckz-settings-wrap">
	<h1><?php esc_html_e( 'Product Creator Settings', 'pckz-canonical-engine' ); ?></h1>

	<div class="pckz-settings-layout">
		<?php PCKZ_Branding::render_settings_panel( true ); ?>

	<div class="pckz-settings-layout__main">
	<form method="post" action="options.php" class="pckz-settings-form">
		<?php settings_fields( 'pckz_settings_group' ); ?>

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
				<th scope="row"><?php esc_html_e( 'Default DPI (print export)', 'pckz-canonical-engine' ); ?></th>
				<td><input type="number" min="72" max="600" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[default_dpi]" value="<?php echo esc_attr( $settings['default_dpi'] ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Production file DPI', 'pckz-canonical-engine' ); ?></th>
				<td>
					<p class="description"><?php esc_html_e( 'Used when generating PNG files attached to orders (for your laser workflow).', 'pckz-canonical-engine' ); ?></p>
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
				<th scope="row"><?php esc_html_e( 'Manufacturing export', 'pckz-canonical-engine' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[enable_dxf_export]" value="1" <?php checked( ! empty( $settings['enable_dxf_export'] ) ); ?>>
						<?php esc_html_e( 'Generate DXF files (optional; .lbrn2 and production SVG are always created)', 'pckz-canonical-engine' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row" colspan="2"><h2 class="title"><?php esc_html_e( 'Pricing (frontend display)', 'pckz-canonical-engine' ); ?></h2></th>
			</tr>
			<?php
			$catalog          = class_exists( 'PCKZ_Commerce' ) ? PCKZ_Commerce::currency_catalog() : array( 'EUR' => array( 'label' => 'EUR' ) );
			$enabled          = $settings['price_currencies_enabled'] ?? array( 'EUR' );
			$default_currency = $settings['price_default_currency'] ?? ( $settings['price_currency_code'] ?? 'EUR' );
			if ( ! is_array( $enabled ) ) {
				$enabled = array( 'EUR' );
			}
			$base_price      = (float) ( $settings['price_base'] ?? 0 );
			$shipping_price  = (float) ( $settings['price_setup_fee'] ?? 0 );
			$preview_total   = $base_price + $shipping_price;
			$by_currency     = $settings['price_by_currency'] ?? array();
			?>
			<tr>
				<td colspan="2">
					<section class="pckz-pricing-panel" data-pricing-panel>
						<p class="description pckz-pricing-panel__intro">
							<?php esc_html_e( 'Configure what customers see as product price, shipping cost, and final total during checkout.', 'pckz-canonical-engine' ); ?>
						</p>

						<div class="pckz-pricing-panel__toggle">
							<label class="pckz-pricing-checkbox">
								<input type="checkbox" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[price_show_enabled]" value="1" <?php checked( ! empty( $settings['price_show_enabled'] ) ); ?>>
								<span><?php esc_html_e( 'Preis im Konfigurator anzeigen', 'pckz-canonical-engine' ); ?></span>
							</label>
						</div>

						<div class="pckz-pricing-grid">
							<div class="pckz-pricing-field">
								<label for="pckz-price-base"><?php esc_html_e( 'Produktpreis (€)', 'pckz-canonical-engine' ); ?></label>
								<input id="pckz-price-base" class="pckz-pricing-input" type="number" step="0.01" min="0" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[price_base]" value="<?php echo esc_attr( $base_price ); ?>" data-pricing-base>
								<p class="description"><?php esc_html_e( 'Base product price shown to customers.', 'pckz-canonical-engine' ); ?></p>
							</div>

							<div class="pckz-pricing-field">
								<label for="pckz-price-setup-fee"><?php esc_html_e( 'Versandkosten (€)', 'pckz-canonical-engine' ); ?></label>
								<input id="pckz-price-setup-fee" class="pckz-pricing-input" type="number" step="0.01" min="0" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[price_setup_fee]" value="<?php echo esc_attr( $shipping_price ); ?>" data-pricing-shipping>
								<p class="description"><?php esc_html_e( 'Shipping cost added during checkout.', 'pckz-canonical-engine' ); ?></p>
							</div>

							<div class="pckz-pricing-field pckz-pricing-field--total">
								<label for="pckz-price-total-preview"><?php esc_html_e( 'Gesamtpreis (Vorschau)', 'pckz-canonical-engine' ); ?></label>
								<input id="pckz-price-total-preview" class="pckz-pricing-input pckz-pricing-input--readonly" type="text" value="<?php echo esc_attr( number_format( $preview_total, 2, '.', '' ) ); ?>" readonly data-pricing-total>
								<p class="description"><?php esc_html_e( 'Automatically calculated preview of base price + shipping.', 'pckz-canonical-engine' ); ?></p>
							</div>
						</div>

						<div class="pckz-pricing-impact">
							<p><strong><?php esc_html_e( 'What each value affects:', 'pckz-canonical-engine' ); ?></strong></p>
							<ul>
								<li><?php esc_html_e( 'Produktpreis: visible base amount in checkout summary.', 'pckz-canonical-engine' ); ?></li>
								<li><?php esc_html_e( 'Versandkosten: additional setup/shipping line in checkout summary.', 'pckz-canonical-engine' ); ?></li>
								<li><?php esc_html_e( 'Gesamtpreis: preview of final per-item amount shown to customers.', 'pckz-canonical-engine' ); ?></li>
							</ul>
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
							<p class="description"><?php esc_html_e( 'Used for PayPal and checkout when the customer does not switch currency.', 'pckz-canonical-engine' ); ?></p>
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
								<p class="description"><?php esc_html_e( 'Display symbol for the default currency. Other currencies use built-in symbols (€, $, CHF, £).', 'pckz-canonical-engine' ); ?></p>
							</div>
							<div class="pckz-pricing-field">
								<label class="pckz-pricing-checkbox pckz-pricing-checkbox--switch">
									<input type="checkbox" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[price_allow_currency_switch]" value="1" <?php checked( ! empty( $settings['price_allow_currency_switch'] ) ); ?>>
									<span><?php esc_html_e( 'Allow currency selection in checkout', 'pckz-canonical-engine' ); ?></span>
								</label>
								<p class="description"><?php esc_html_e( 'Let customers choose from enabled currencies at checkout.', 'pckz-canonical-engine' ); ?></p>
							</div>
						</div>

						<?php if ( class_exists( 'PCKZ_Commerce' ) ) : ?>
							<div class="pckz-pricing-subsection">
								<p><strong><?php esc_html_e( 'Base price per currency', 'pckz-canonical-engine' ); ?></strong></p>
								<p class="description"><?php esc_html_e( 'Optional overrides. Leave empty to use the global base price above.', 'pckz-canonical-engine' ); ?></p>
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

						<div class="pckz-pricing-panel__actions">
							<button type="submit" class="button button-primary button-hero pckz-pricing-save">
								<?php esc_html_e( 'Save pricing settings', 'pckz-canonical-engine' ); ?>
							</button>
							<button type="button" class="button button-secondary pckz-pricing-preview-btn" data-pricing-preview-refresh>
								<?php esc_html_e( 'Refresh total preview', 'pckz-canonical-engine' ); ?>
							</button>
						</div>
					</section>
				</td>
			</tr>
			<tr>
				<th scope="row" colspan="2"><h2 class="title"><?php esc_html_e( 'Licensing & Master Control', 'pckz-canonical-engine' ); ?></h2></th>
			</tr>
			<?php $is_master_install = ! empty( $settings['licensing_master_mode'] ); ?>
			<?php if ( $is_master_install ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Master control mode', 'pckz-canonical-engine' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[licensing_master_mode]" value="1" <?php checked( true ); ?>>
							<?php esc_html_e( 'This installation acts as the central license server.', 'pckz-canonical-engine' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Master server URL', 'pckz-canonical-engine' ); ?></th>
					<td>
						<input type="url" class="large-text" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[licensing_master_url]" value="<?php echo esc_attr( $settings['licensing_master_url'] ?? 'https://paxdesign.at' ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Master API key', 'pckz-canonical-engine' ); ?></th>
					<td>
						<input type="text" class="large-text code" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[licensing_master_api_key]" value="<?php echo esc_attr( $settings['licensing_master_api_key'] ?? '' ); ?>">
						<input type="hidden" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[licensing_key]" value="<?php echo esc_attr( $settings['licensing_key'] ?? '' ); ?>">
						<input type="hidden" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[licensing_install_uuid]" value="<?php echo esc_attr( $settings['licensing_install_uuid'] ?? '' ); ?>">
						<input type="hidden" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[licensing_enforce]" value="<?php echo esc_attr( ! empty( $settings['licensing_enforce'] ) ? '1' : '0' ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Security defaults', 'pckz-canonical-engine' ); ?></th>
					<td>
						<label><input type="checkbox" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[licensing_require_signed_requests]" value="1" <?php checked( ! empty( $settings['licensing_require_signed_requests'] ) ); ?>> <?php esc_html_e( 'Require signed license requests', 'pckz-canonical-engine' ); ?></label><br>
						<label><input type="checkbox" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[licensing_export_authorize]" value="1" <?php checked( ! empty( $settings['licensing_export_authorize'] ) ); ?>> <?php esc_html_e( 'Require export authorization', 'pckz-canonical-engine' ); ?></label><br>
						<label><input type="checkbox" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[licensing_export_remote_mode]" value="1" <?php checked( ! empty( $settings['licensing_export_remote_mode'] ) ); ?>> <?php esc_html_e( 'Enable remote export mode', 'pckz-canonical-engine' ); ?></label><br>
						<label><input type="checkbox" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[licensing_export_remote_strict]" value="1" <?php checked( ! empty( $settings['licensing_export_remote_strict'] ) ); ?>> <?php esc_html_e( 'Strict remote export enforcement', 'pckz-canonical-engine' ); ?></label><br>
						<label><input type="checkbox" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[licensing_strict_integrity]" value="1" <?php checked( ! empty( $settings['licensing_strict_integrity'] ) ); ?>> <?php esc_html_e( 'Strict integrity policy', 'pckz-canonical-engine' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Grace period (minutes)', 'pckz-canonical-engine' ); ?></th>
					<td>
						<input type="number" min="5" max="1440" class="small-text" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[licensing_grace_minutes]" value="<?php echo esc_attr( (string) ( $settings['licensing_grace_minutes'] ?? 120 ) ); ?>">
					</td>
				</tr>
			<?php else : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Client licensing', 'pckz-canonical-engine' ); ?></th>
					<td>
						<p class="description"><?php esc_html_e( 'This installation is running in restricted client mode. Master server controls are intentionally hidden.', 'pckz-canonical-engine' ); ?></p>
						<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=pckz-license-server' ) ); ?>"><?php esc_html_e( 'Open License Dashboard', 'pckz-canonical-engine' ); ?></a></p>
						<?php if ( class_exists( 'PCKZ_Licensing' ) ) : ?>
							<?php $license_state = PCKZ_Licensing::get_client_state(); ?>
							<p><strong><?php esc_html_e( 'Current status:', 'pckz-canonical-engine' ); ?></strong> <?php echo esc_html( $license_state['status'] ?? 'unknown' ); ?><?php echo ! empty( $license_state['reason'] ) ? ' — ' . esc_html( $license_state['reason'] ) : ''; ?></p>
						<?php endif; ?>
						<input type="hidden" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[licensing_master_mode]" value="0">
						<input type="hidden" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[licensing_master_url]" value="<?php echo esc_attr( $settings['licensing_master_url'] ?? '' ); ?>">
						<input type="hidden" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[licensing_key]" value="<?php echo esc_attr( $settings['licensing_key'] ?? '' ); ?>">
						<input type="hidden" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[licensing_install_uuid]" value="<?php echo esc_attr( $settings['licensing_install_uuid'] ?? '' ); ?>">
						<input type="hidden" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[licensing_enforce]" value="<?php echo esc_attr( ! empty( $settings['licensing_enforce'] ) ? '1' : '0' ); ?>">
						<input type="hidden" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[licensing_grace_minutes]" value="<?php echo esc_attr( (string) ( $settings['licensing_grace_minutes'] ?? 120 ) ); ?>">
						<input type="hidden" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[licensing_require_signed_requests]" value="<?php echo esc_attr( ! empty( $settings['licensing_require_signed_requests'] ) ? '1' : '0' ); ?>">
						<input type="hidden" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[licensing_export_authorize]" value="<?php echo esc_attr( ! empty( $settings['licensing_export_authorize'] ) ? '1' : '0' ); ?>">
						<input type="hidden" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[licensing_export_remote_mode]" value="<?php echo esc_attr( ! empty( $settings['licensing_export_remote_mode'] ) ? '1' : '0' ); ?>">
						<input type="hidden" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[licensing_export_remote_strict]" value="<?php echo esc_attr( ! empty( $settings['licensing_export_remote_strict'] ) ? '1' : '0' ); ?>">
						<input type="hidden" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[licensing_strict_integrity]" value="<?php echo esc_attr( ! empty( $settings['licensing_strict_integrity'] ) ? '1' : '0' ); ?>">
						<input type="hidden" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[licensing_master_api_key]" value="<?php echo esc_attr( $settings['licensing_master_api_key'] ?? '' ); ?>">
					</td>
				</tr>
			<?php endif; ?>
			<tr>
				<th scope="row" colspan="2"><h2 class="title"><?php esc_html_e( 'Payment architecture (future-ready)', 'pckz-canonical-engine' ); ?></h2></th>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Primary provider strategy', 'pckz-canonical-engine' ); ?></th>
				<td>
					<select name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[payments_primary_provider]">
						<option value="paypal" <?php selected( $settings['payments_primary_provider'] ?? 'paypal', 'paypal' ); ?>><?php esc_html_e( 'PayPal (current production)', 'pckz-canonical-engine' ); ?></option>
						<option value="stripe" <?php selected( $settings['payments_primary_provider'] ?? 'paypal', 'stripe' ); ?>><?php esc_html_e( 'Stripe (cards, Apple Pay, Google Pay)', 'pckz-canonical-engine' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Architecture setting for future gateway expansion. Current checkout behavior remains unchanged.', 'pckz-canonical-engine' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable Stripe architecture', 'pckz-canonical-engine' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[payments_enable_stripe]" value="1" <?php checked( ! empty( $settings['payments_enable_stripe'] ) ); ?>>
						<?php esc_html_e( 'Enable Stripe checkout + webhook processing for production payment flow.', 'pckz-canonical-engine' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Stripe test mode', 'pckz-canonical-engine' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[payments_stripe_test_mode]" value="1" <?php checked( ! empty( $settings['payments_stripe_test_mode'] ) ); ?>>
						<?php esc_html_e( 'Use Stripe test keys and test checkout mode.', 'pckz-canonical-engine' ); ?>
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
				<td>
					<input type="url" class="large-text" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[payments_stripe_success_url]" value="<?php echo esc_attr( $settings['payments_stripe_success_url'] ?? '' ); ?>">
					<p class="description"><?php esc_html_e( 'Optional. If empty, the configurator page URL is used.', 'pckz-canonical-engine' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Stripe cancel URL', 'pckz-canonical-engine' ); ?></th>
				<td>
					<input type="url" class="large-text" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[payments_stripe_cancel_url]" value="<?php echo esc_attr( $settings['payments_stripe_cancel_url'] ?? '' ); ?>">
					<p class="description"><?php esc_html_e( 'Optional. If empty, the configurator page URL is used.', 'pckz-canonical-engine' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Stripe webhook tolerance (seconds)', 'pckz-canonical-engine' ); ?></th>
				<td><input type="number" min="60" max="1800" class="small-text" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[payments_stripe_webhook_tolerance]" value="<?php echo esc_attr( (string) ( $settings['payments_stripe_webhook_tolerance'] ?? 300 ) ); ?>"></td>
			</tr>
			<tr>
				<th scope="row" colspan="2"><h2 class="title"><?php esc_html_e( 'Checkout customer message', 'pckz-canonical-engine' ); ?></h2></th>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Show message', 'pckz-canonical-engine' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[checkout_notice_enabled]" value="1" <?php checked( ! empty( $settings['checkout_notice_enabled'] ) ); ?>>
						<?php esc_html_e( 'Display reassurance notice in checkout', 'pckz-canonical-engine' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Customer information message', 'pckz-canonical-engine' ); ?></th>
				<td>
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
				</td>
			</tr>
			<tr>
				<th scope="row" colspan="2"><h2 class="title"><?php esc_html_e( 'PayPal', 'pckz-canonical-engine' ); ?></h2></th>
			</tr>
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
						<?php esc_html_e( 'Use PayPal Sandbox (test mode)', 'pckz-canonical-engine' ); ?>
					</label>
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
				<th scope="row"><?php esc_html_e( 'Payment success URL', 'pckz-canonical-engine' ); ?></th>
				<td>
					<input type="url" class="large-text" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[paypal_success_url]" value="<?php echo esc_attr( $settings['paypal_success_url'] ?? '' ); ?>" placeholder="<?php echo esc_attr( home_url( '/' ) ); ?>">
					<p class="description"><?php esc_html_e( 'Optional fallback. After PayPal, customers are normally returned to the configurator page where they placed the order (stored automatically). Set this to your konfigurator URL if auto-detection fails.', 'pckz-canonical-engine' ); ?></p>
				</td>
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
				<th scope="row"><?php esc_html_e( 'Payment cancel URL', 'pckz-canonical-engine' ); ?></th>
				<td>
					<input type="url" class="large-text" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[paypal_cancel_url]" value="<?php echo esc_attr( $settings['paypal_cancel_url'] ?? '' ); ?>">
					<p class="description"><?php esc_html_e( 'Customer is redirected here if PayPal payment is cancelled.', 'pckz-canonical-engine' ); ?></p>
				</td>
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

		<input type="hidden" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[fonts_json]" value="<?php echo esc_attr( $fonts_json ); ?>">

		<?php submit_button(); ?>
	</form>
	</div>
	</div>
</div>
