<?php
/**
 * Compact option fields (dropdowns + wrapped color chips).
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

$icon_registry = PCKZ_Icons::registry_for_js();

/**
 * Resolve icon image URL for a choice.
 *
 * @param array  $choice        Choice row.
 * @param string $slug          Icon slug.
 * @param array  $icon_registry Icon registry.
 * @return string
 */
function pckz_icon_choice_img( $choice, $slug, $icon_registry ) {
	if ( ! empty( $choice['img'] ) ) {
		return $choice['img'];
	}
	if ( 'none' !== $slug && ! empty( $icon_registry[ $slug ]['black'] ) ) {
		return $icon_registry[ $slug ]['black'];
	}
	if ( 'none' !== $slug && ! empty( $icon_registry[ $slug ]['white'] ) ) {
		return $icon_registry[ $slug ]['white'];
	}
	return '';
}

foreach ( $customer_options as $option ) :
	$id      = $option['id'] ?? '';
	if ( 'preview_mode' === $id ) {
		continue;
	}
	$type    = $option['type'] ?? 'text';
	if ( 'swatch_icon' === $type ) {
		$type = 'icon_select';
	}
	$label   = $option['label'] ?? '';
	$default = $option['default'] ?? '';
	$req     = ! empty( $option['required'] );
	$choices = (array) ( $option['choices'] ?? array() );
	if ( in_array( $type, array( 'swatch_color', 'color' ), true ) && ! empty( $choices ) ) {
		$filtered = array();
		foreach ( $choices as $c ) {
			$hex = is_string( $c ) ? sanitize_hex_color( $c ) : '';
			if ( $hex ) {
				$filtered[] = $hex;
			}
		}
		$choices = ! empty( $filtered ) ? $filtered : array( '#ffffff', '#000000' );
	} elseif ( in_array( $type, array( 'swatch_color', 'color' ), true ) ) {
		$choices = array( '#ffffff', '#000000' );
	}
	$show_when_attr = ! empty( $option['show_when'] ) ? wp_json_encode( $option['show_when'] ) : '';
	?>
	<div class="pckz-field pckz-field--<?php echo esc_attr( $type ); ?>" data-option-id="<?php echo esc_attr( $id ); ?>" data-option-type="<?php echo esc_attr( $type ); ?>" <?php echo ! empty( $option['maps_to'] ) ? 'data-maps-to="' . esc_attr( $option['maps_to'] ) . '"' : ''; ?> <?php echo $show_when_attr ? 'data-show-when="' . esc_attr( $show_when_attr ) . '"' : ''; ?>>
		<label class="pckz-field__label" for="pckz-opt-<?php echo esc_attr( $id ); ?>">
			<?php echo esc_html( $label ); ?>
			<?php if ( $req ) : ?><span class="pckz-required" aria-hidden="true">*</span><?php endif; ?>
		</label>

		<?php if ( 'text' === $type ) : ?>
			<input
				type="text"
				class="pckz-field__control"
				id="pckz-opt-<?php echo esc_attr( $id ); ?>"
				name="pckz_options[<?php echo esc_attr( $id ); ?>]"
				value="<?php echo esc_attr( $default ); ?>"
				placeholder="<?php echo esc_attr( $option['placeholder'] ?? 'Text eingeben…' ); ?>"
				<?php echo ! empty( $option['maxlength'] ) ? 'maxlength="' . esc_attr( (string) $option['maxlength'] ) . '"' : ''; ?>
				<?php echo $req ? 'required' : ''; ?>
				autocomplete="off"
			>

		<?php elseif ( 'textarea' === $type ) : ?>
			<textarea class="pckz-field__control" id="pckz-opt-<?php echo esc_attr( $id ); ?>" name="pckz_options[<?php echo esc_attr( $id ); ?>]" rows="3"><?php echo esc_textarea( $default ); ?></textarea>

		<?php elseif ( 'select' === $type ) : ?>
			<select class="pckz-field__control" id="pckz-opt-<?php echo esc_attr( $id ); ?>" name="pckz_options[<?php echo esc_attr( $id ); ?>]">
				<?php foreach ( $choices as $choice ) : ?>
					<option value="<?php echo esc_attr( $choice['value'] ?? '' ); ?>" <?php selected( $default, $choice['value'] ?? '' ); ?>>
						<?php echo esc_html( $choice['label'] ?? '' ); ?>
					</option>
				<?php endforeach; ?>
			</select>

		<?php elseif ( 'radio' === $type ) : ?>
			<div class="pckz-field__choices pckz-field__choices--radio" role="radiogroup" aria-label="<?php echo esc_attr( $label ); ?>">
				<?php foreach ( $choices as $choice ) : ?>
					<label class="pckz-choice pckz-choice--radio">
						<input type="radio" name="pckz_options[<?php echo esc_attr( $id ); ?>]" value="<?php echo esc_attr( $choice['value'] ?? '' ); ?>" <?php checked( $default, $choice['value'] ?? '' ); ?>>
						<span><?php echo esc_html( $choice['label'] ?? '' ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>

		<?php elseif ( 'icon_select' === $type ) :
			$preview_img = '';
			$preview_lbl = $default;
			foreach ( $choices as $choice ) {
				if ( ( $choice['value'] ?? '' ) === $default ) {
					$preview_img = pckz_icon_choice_img( $choice, $default, $icon_registry );
					$preview_lbl = $choice['label'] ?? $default;
					break;
				}
			}
			$list_id    = 'pckz-visual-list-' . esc_attr( $id );
			$is_icon_ui = in_array( $id, array( 'symbol_links', 'symbol_rechts' ), true );
			$picker_cls = 'pckz-visual-picker' . ( $is_icon_ui ? ' pckz-visual-picker--icon-grid' : '' );
			?>
			<div class="<?php echo esc_attr( $picker_cls ); ?>" data-visual-picker="<?php echo esc_attr( $id ); ?>">
				<input
					type="hidden"
					class="pckz-icon-hidden"
					id="pckz-opt-<?php echo esc_attr( $id ); ?>"
					name="pckz_options[<?php echo esc_attr( $id ); ?>]"
					value="<?php echo esc_attr( $default ); ?>"
				>
				<button
					type="button"
					class="pckz-visual-picker__trigger"
					data-visual-trigger
					aria-expanded="false"
					aria-haspopup="listbox"
					aria-controls="<?php echo esc_attr( $list_id ); ?>"
				>
					<span class="pckz-visual-picker__thumb o--img" data-visual-preview>
						<img
							class="<?php echo $preview_img ? '' : 'pckz-hidden'; ?>"
							data-visual-preview-img
							src="<?php echo $preview_img ? esc_url( $preview_img ) : ''; ?>"
							alt=""
							width="36"
							height="36"
							loading="lazy"
							crossorigin="anonymous"
						>
						<span class="pckz-visual-picker__empty<?php echo $preview_img ? ' pckz-hidden' : ''; ?>" data-visual-preview-empty aria-hidden="true">—</span>
					</span>
					<span class="pckz-visual-picker__label o--text" data-visual-preview-label><?php echo esc_html( $preview_lbl ); ?></span>
					<span class="pckz-visual-picker__caret" aria-hidden="true"></span>
				</button>
				<ul
					class="pckz-visual-picker__list"
					id="<?php echo esc_attr( $list_id ); ?>"
					data-visual-list
					role="listbox"
					aria-label="<?php echo esc_attr( $label ); ?>"
				>
					<?php foreach ( $choices as $choice ) :
						$val = $choice['value'] ?? '';
						$img = pckz_icon_choice_img( $choice, $val, $icon_registry );
						$lbl = $choice['label'] ?? $val;
						$active = ( $default === $val );
						?>
						<li
							class="pckz-visual-picker__option<?php echo $active ? ' is-active' : ''; ?>"
							role="option"
							tabindex="0"
							data-visual-value="<?php echo esc_attr( $val ); ?>"
							data-visual-img="<?php echo esc_attr( $img ); ?>"
							data-visual-label="<?php echo esc_attr( $lbl ); ?>"
							aria-selected="<?php echo $active ? 'true' : 'false'; ?>"
						>
							<span class="o--img">
								<?php if ( $img ) : ?>
									<img src="<?php echo esc_url( $img ); ?>" alt="" width="32" height="32" loading="lazy" crossorigin="anonymous">
								<?php else : ?>
									<span class="pckz-visual-picker__opt-empty">—</span>
								<?php endif; ?>
							</span>
							<?php if ( ! $is_icon_ui ) : ?>
								<span class="o--text"><?php echo esc_html( $lbl ); ?></span>
							<?php else : ?>
								<span class="o--text pckz-sr-only"><?php echo esc_html( $lbl ); ?></span>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>

		<?php elseif ( in_array( $type, array( 'swatch_color', 'color' ), true ) ) : ?>
			<div class="pckz-color-grid pckz-color-grid--compact" role="listbox" aria-label="<?php echo esc_attr( $label ); ?>">
				<?php foreach ( $choices as $tone ) :
					$is_white = '#ffffff' === strtolower( $tone );
					?>
					<button
						type="button"
						class="pckz-color-chip<?php echo $default === $tone ? ' is-active' : ''; ?><?php echo $is_white ? ' pckz-color-chip--white' : ''; ?>"
						data-value="<?php echo esc_attr( $tone ); ?>"
						style="--chip-color: <?php echo esc_attr( $tone ); ?>"
						title="<?php echo esc_attr( $tone ); ?>"
						aria-label="<?php echo esc_attr( $tone ); ?>"
						aria-pressed="<?php echo $default === $tone ? 'true' : 'false'; ?>"
					></button>
				<?php endforeach; ?>
				<input type="hidden" class="pckz-tone-hidden" id="pckz-opt-<?php echo esc_attr( $id ); ?>" name="pckz_options[<?php echo esc_attr( $id ); ?>]" value="<?php echo esc_attr( $default ?: '#ffffff' ); ?>">
			</div>

		<?php elseif ( 'font' === $type ) : ?>
			<select class="pckz-field__control" id="pckz-opt-<?php echo esc_attr( $id ); ?>" name="pckz_options[<?php echo esc_attr( $id ); ?>]">
				<?php foreach ( $fonts as $font ) : ?>
					<option value="<?php echo esc_attr( $font['family'] ?? '' ); ?>" <?php selected( $default, $font['family'] ?? '' ); ?>>
						<?php echo esc_html( $font['label'] ?? $font['family'] ?? '' ); ?>
					</option>
				<?php endforeach; ?>
			</select>

		<?php elseif ( 'html' === $type && ! empty( $option['html'] ) ) : ?>
			<div class="pckz-field__html"><?php echo wp_kses_post( $option['html'] ); ?></div>

		<?php elseif ( 'file' === $type ) : ?>
			<div class="pckz-field__file">
				<input type="file" class="pckz-hidden pckz-option-file" id="pckz-opt-<?php echo esc_attr( $id ); ?>" accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml">
				<button type="button" class="pckz-btn pckz-btn--secondary pckz-file-trigger" data-target="pckz-opt-<?php echo esc_attr( $id ); ?>">
					Logo / Bild hochladen
				</button>
				<span class="pckz-file-name" data-file-label></span>
			</div>
		<?php endif; ?>
	</div>
<?php endforeach; ?>
