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
			<tr>
				<th scope="row"><?php esc_html_e( 'Show price', 'pckz-canonical-engine' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[price_show_enabled]" value="1" <?php checked( ! empty( $settings['price_show_enabled'] ) ); ?>>
						<?php esc_html_e( 'Display price in configurator', 'pckz-canonical-engine' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Base price', 'pckz-canonical-engine' ); ?></th>
				<td><input type="number" step="0.01" min="0" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[price_base]" value="<?php echo esc_attr( $settings['price_base'] ?? 0 ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Setup fee', 'pckz-canonical-engine' ); ?></th>
				<td><input type="number" step="0.01" min="0" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[price_setup_fee]" value="<?php echo esc_attr( $settings['price_setup_fee'] ?? 0 ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Default currency', 'pckz-canonical-engine' ); ?></th>
				<td>
					<?php
					$catalog         = class_exists( 'PCKZ_Commerce' ) ? PCKZ_Commerce::currency_catalog() : array( 'EUR' => array( 'label' => 'EUR' ) );
					$enabled         = $settings['price_currencies_enabled'] ?? array( 'EUR' );
					$default_currency = $settings['price_default_currency'] ?? ( $settings['price_currency_code'] ?? 'EUR' );
					if ( ! is_array( $enabled ) ) {
						$enabled = array( 'EUR' );
					}
					?>
					<select name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[price_default_currency]">
						<?php foreach ( $catalog as $code => $meta ) : ?>
							<?php if ( in_array( $code, $enabled, true ) ) : ?>
								<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $default_currency, $code ); ?>>
									<?php echo esc_html( $meta['label'] ?? $code ); ?>
								</option>
							<?php endif; ?>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Used for PayPal and checkout when the customer does not switch currency.', 'pckz-canonical-engine' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Enabled currencies', 'pckz-canonical-engine' ); ?></th>
				<td>
					<?php foreach ( $catalog as $code => $meta ) : ?>
						<label style="display:block;margin-bottom:6px;">
							<input type="checkbox" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[price_currencies_enabled][]" value="<?php echo esc_attr( $code ); ?>" <?php checked( in_array( $code, $enabled, true ) ); ?>>
							<?php echo esc_html( $meta['label'] ?? $code ); ?>
						</label>
					<?php endforeach; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Currency symbol (default)', 'pckz-canonical-engine' ); ?></th>
				<td>
					<input type="text" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[price_currency_symbol]" value="<?php echo esc_attr( $settings['price_currency_symbol'] ?? '€' ); ?>" maxlength="4">
					<p class="description"><?php esc_html_e( 'Display symbol for the default currency. Other currencies use built-in symbols (€, $, CHF, £).', 'pckz-canonical-engine' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Allow currency selection', 'pckz-canonical-engine' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[price_allow_currency_switch]" value="1" <?php checked( ! empty( $settings['price_allow_currency_switch'] ) ); ?>>
						<?php esc_html_e( 'Let customers choose from enabled currencies at checkout', 'pckz-canonical-engine' ); ?>
					</label>
				</td>
			</tr>
			<?php if ( class_exists( 'PCKZ_Commerce' ) ) : ?>
				<?php
				$by_currency = $settings['price_by_currency'] ?? array();
				$global_base = (float) ( $settings['price_base'] ?? 0 );
				?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Base price per currency', 'pckz-canonical-engine' ); ?></th>
					<td>
						<p class="description"><?php esc_html_e( 'Optional overrides. Leave empty to use the global base price above.', 'pckz-canonical-engine' ); ?></p>
						<?php foreach ( $enabled as $code ) : ?>
							<?php if ( isset( $catalog[ $code ] ) ) : ?>
								<p>
									<label>
										<strong><?php echo esc_html( $code ); ?></strong>
										<input type="number" step="0.01" min="0" name="<?php echo esc_attr( PCKZ_Settings::OPTION_KEY ); ?>[price_by_currency][<?php echo esc_attr( $code ); ?>]" value="<?php echo esc_attr( $by_currency[ $code ] ?? '' ); ?>" placeholder="<?php echo esc_attr( (string) $global_base ); ?>">
									</label>
								</p>
							<?php endif; ?>
						<?php endforeach; ?>
					</td>
				</tr>
			<?php endif; ?>
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
					<p class="description"><?php esc_html_e( 'Customer is redirected here after successful PayPal payment.', 'pckz-canonical-engine' ); ?></p>
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
