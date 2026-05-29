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
<div class="wrap pckz-admin-wrap">
	<h1><?php esc_html_e( 'Product Creator Settings', 'pckz-canonical-engine' ); ?></h1>

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
