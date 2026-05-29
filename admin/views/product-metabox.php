<?php
/**
 * Creator product configuration metabox.
 *
 * @package PCKZCanonicalEngine
 * @var array  $config Product config.
 * @var array  $fonts  Font list.
 * @var array  $colors Color palette.
 * @var WP_Post $post   Post object.
 */

defined( 'ABSPATH' ) || exit;

$benefits_text   = implode( "\n", $config['benefits'] ?? array() );
$options_json    = wp_json_encode( $config['customer_options'] ?? array(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
?>
<div class="pckz-metabox">
	<p class="description"><?php esc_html_e( 'Customer-facing shop customizer (Ledos-style). Production files are delivered with each order in WooCommerce admin.', 'pckz-canonical-engine' ); ?></p>

	<div class="pckz-metabox-grid">
		<fieldset>
			<legend><?php esc_html_e( 'Storefront', 'pckz-canonical-engine' ); ?></legend>
			<label><?php esc_html_e( 'Short description (under price)', 'pckz-canonical-engine' ); ?>
				<textarea name="pckz_config[description]" rows="3" class="large-text"><?php echo esc_textarea( $config['description'] ?? '' ); ?></textarea>
			</label>
			<label><?php esc_html_e( 'Benefits (one per line)', 'pckz-canonical-engine' ); ?>
				<textarea name="pckz_config[benefits]" rows="4" class="large-text"><?php echo esc_textarea( $benefits_text ); ?></textarea>
			</label>
			<label>
				<input type="checkbox" name="pckz_config[enabled_tools][]" value="position" <?php checked( in_array( 'position', $config['enabled_tools'] ?? array(), true ) ); ?>>
				<?php esc_html_e( 'Allow drag-to-position on preview (advanced)', 'pckz-canonical-engine' ); ?>
			</label>
		</fieldset>

		<fieldset>
			<legend><?php esc_html_e( 'Customer options (JSON)', 'pckz-canonical-engine' ); ?></legend>
			<p class="description"><?php esc_html_e( 'Dropdowns, colors, text fields shown to customers. Leave empty to use Ledos-style defaults.', 'pckz-canonical-engine' ); ?></p>
			<textarea name="pckz_config[customer_options]" rows="12" class="large-text code"><?php echo esc_textarea( $options_json ); ?></textarea>
		</fieldset>

		<fieldset>
			<legend><?php esc_html_e( 'Canvas & print area (mm)', 'pckz-canonical-engine' ); ?></legend>
			<label><?php esc_html_e( 'Width', 'pckz-canonical-engine' ); ?>
				<input type="number" step="0.1" name="pckz_config[canvas_width_mm]" value="<?php echo esc_attr( $config['canvas_width_mm'] ); ?>">
			</label>
			<label><?php esc_html_e( 'Height', 'pckz-canonical-engine' ); ?>
				<input type="number" step="0.1" name="pckz_config[canvas_height_mm]" value="<?php echo esc_attr( $config['canvas_height_mm'] ); ?>">
			</label>
			<label>X <input type="number" step="0.1" name="pckz_config[safe_zone_x_mm]" value="<?php echo esc_attr( $config['safe_zone_x_mm'] ); ?>"></label>
			<label>Y <input type="number" step="0.1" name="pckz_config[safe_zone_y_mm]" value="<?php echo esc_attr( $config['safe_zone_y_mm'] ); ?>"></label>
			<label>W <input type="number" step="0.1" name="pckz_config[safe_zone_w_mm]" value="<?php echo esc_attr( $config['safe_zone_w_mm'] ); ?>"></label>
			<label>H <input type="number" step="0.1" name="pckz_config[safe_zone_h_mm]" value="<?php echo esc_attr( $config['safe_zone_h_mm'] ); ?>"></label>
			<label><?php esc_html_e( 'Production export DPI', 'pckz-canonical-engine' ); ?>
				<input type="number" name="pckz_config[dpi]" value="<?php echo esc_attr( $config['dpi'] ); ?>">
			</label>
		</fieldset>

		<fieldset>
			<legend><?php esc_html_e( 'Preview images', 'pckz-canonical-engine' ); ?></legend>
			<?php
			$bg_fields = array(
				'background_image' => __( 'Fallback', 'pckz-canonical-engine' ),
				'background_day'   => __( 'Day', 'pckz-canonical-engine' ),
				'background_night' => __( 'Night (LED)', 'pckz-canonical-engine' ),
			);
			foreach ( $bg_fields as $key => $label ) :
				?>
				<div class="pckz-media-field">
					<label><?php echo esc_html( $label ); ?></label>
					<input type="url" class="pckz-media-url" name="pckz_config[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $config[ $key ] ?? '' ); ?>">
					<button type="button" class="button pckz-media-upload"><?php esc_html_e( 'Select', 'pckz-canonical-engine' ); ?></button>
				</div>
			<?php endforeach; ?>
		</fieldset>

		<fieldset>
			<legend><?php esc_html_e( 'WooCommerce', 'pckz-canonical-engine' ); ?></legend>
			<label><?php esc_html_e( 'WooCommerce Product ID', 'pckz-canonical-engine' ); ?>
				<input type="number" name="pckz_config[woo_product_id]" value="<?php echo esc_attr( $config['woo_product_id'] ); ?>">
			</label>
			<label><?php esc_html_e( 'Display price', 'pckz-canonical-engine' ); ?>
				<input type="text" name="pckz_config[price]" value="<?php echo esc_attr( $config['price'] ); ?>">
			</label>
			<label><?php esc_html_e( 'Currency', 'pckz-canonical-engine' ); ?>
				<input type="text" name="pckz_config[currency]" value="<?php echo esc_attr( $config['currency'] ); ?>">
			</label>
			<label><?php esc_html_e( 'Max upload (MB)', 'pckz-canonical-engine' ); ?>
				<input type="number" name="pckz_config[max_upload_mb]" value="<?php echo esc_attr( $config['max_upload_mb'] ); ?>">
			</label>
		</fieldset>
	</div>

	<p>
		<strong><?php esc_html_e( 'Shortcode', 'pckz-canonical-engine' ); ?></strong><br>
		<code>[product_creator id="<?php echo esc_attr( (string) $post->ID ); ?>"]</code>
	</p>
</div>
