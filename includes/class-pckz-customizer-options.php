<?php
/**
 * Customer-facing customizer option definitions (Ledos / license plate frame).
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PCKZ_Customizer_Options
 */
class PCKZ_Customizer_Options {

	/**
	 * Default Ledos-style option set (German customer UI).
	 *
	 * @return array
	 */
	public static function default_ledos_options() {
		if ( class_exists( 'PCKZ_Ledos_Preview' ) ) {
			return PCKZ_Ledos_Preview::default_cloudlift_options();
		}

		$symbols = PCKZ_Icons::symbol_choices();

		return array(
			array(
				'id'          => 'preview_mode',
				'type'        => 'radio',
				'label'       => 'Vorschau',
				'choices'     => array(
					array( 'value' => 'day', 'label' => 'Tag' ),
					array( 'value' => 'night', 'label' => 'Nacht' ),
				),
				'default'     => 'day',
				'preview_key' => 'background',
			),
			array(
				'id'          => 'led_color',
				'type'        => 'swatch_color',
				'label'       => 'LED-Beleuchtung',
				'choices'     => array( '#ffffff', '#eeeeee', '#cccccc', '#999999' ),
				'default'     => '#ffffff',
				'preview_key' => 'led_glow',
			),
			array(
				'id'          => 'custom_text',
				'type'        => 'text',
				'label'       => 'Text',
				'placeholder' => 'Ihr Text für den Rahmen',
				'default'     => '',
				'maxlength'   => 32,
				'maps_to'     => 'canvas_text',
				'required'    => true,
			),
			array(
				'id'      => 'font_family',
				'type'    => 'font',
				'label'   => 'Schriftart',
				'default' => 'Ubuntu',
				'maps_to' => 'canvas_font',
			),
			array(
				'id'      => 'text_color',
				'type'    => 'color',
				'label'   => 'Textfarbe',
				'choices' => array( '#ffffff', '#000000' ),
				'default' => '#ffffff',
				'maps_to' => 'canvas_fill',
			),
			array(
				'id'      => 'symbol_links',
				'type'    => 'swatch_icon',
				'label'   => 'Symbol links',
				'choices' => $symbols,
				'default' => 'none',
				'maps_to' => 'icon_left',
			),
			array(
				'id'      => 'symbol_rechts',
				'type'    => 'swatch_icon',
				'label'   => 'Symbol rechts',
				'choices' => $symbols,
				'default' => 'none',
				'maps_to' => 'icon_right',
			),
			array(
				'id'      => 'symbol_color',
				'type'    => 'color',
				'label'   => 'Symbolfarbe',
				'choices' => array( '#ffffff', '#000000' ),
				'default' => '#ffffff',
				'maps_to' => 'icon_fill',
			),
			array(
				'id'      => 'linien',
				'type'    => 'radio',
				'label'   => 'Linien',
				'choices' => array(
					array( 'value' => 'no', 'label' => 'Keine Linien' ),
					array( 'value' => 'yes', 'label' => 'Linien anzeigen' ),
				),
				'default' => 'no',
				'maps_to' => 'strip_lines',
			),
			array(
				'id'      => 'logo_upload',
				'type'    => 'file',
				'label'   => 'Eigenes Logo / Bild (optional)',
				'maps_to' => 'canvas_image',
			),
		);
	}

	/**
	 * Sanitize options array from admin JSON.
	 *
	 * @param mixed $raw Raw value.
	 * @return array
	 */
	public static function sanitize_options( $raw ) {
		if ( is_string( $raw ) ) {
			$decoded = json_decode( $raw, true );
			$raw     = is_array( $decoded ) ? $decoded : array();
		}

		if ( ! is_array( $raw ) || empty( $raw ) ) {
			return self::default_ledos_options();
		}

		$allowed_types = array( 'text', 'textarea', 'select', 'radio', 'swatch_color', 'swatch_icon', 'icon_select', 'swatch_button', 'color', 'font', 'file', 'html' );
		$clean         = array();

		foreach ( $raw as $option ) {
			if ( empty( $option['id'] ) || empty( $option['type'] ) ) {
				continue;
			}
			if ( ! in_array( $option['type'], $allowed_types, true ) ) {
				continue;
			}

			$row = array(
				'id'          => sanitize_key( $option['id'] ),
				'type'        => $option['type'],
				'label'       => sanitize_text_field( $option['label'] ?? '' ),
				'placeholder' => sanitize_text_field( $option['placeholder'] ?? '' ),
				'default'     => sanitize_text_field( $option['default'] ?? '' ),
				'maps_to'     => sanitize_key( $option['maps_to'] ?? '' ),
				'preview_key' => sanitize_key( $option['preview_key'] ?? '' ),
				'required'    => ! empty( $option['required'] ),
				'maxlength'   => isset( $option['maxlength'] ) ? absint( $option['maxlength'] ) : 0,
				'choices'     => self::sanitize_choices( $option['choices'] ?? array(), $option['type'] ),
			);
			if ( ! empty( $option['show_when'] ) && is_array( $option['show_when'] ) ) {
				$row['show_when'] = array_map( 'sanitize_text_field', $option['show_when'] );
			}
			if ( 'html' === $option['type'] && ! empty( $option['html'] ) ) {
				$row['html'] = wp_kses_post( $option['html'] );
			}
			$clean[] = $row;
		}

		return ! empty( $clean ) ? $clean : self::default_ledos_options();
	}

	/**
	 * Sanitize choice lists.
	 *
	 * @param mixed  $choices Choice data.
	 * @param string $type    Field type.
	 * @return array
	 */
	private static function sanitize_choices( $choices, $type ) {
		if ( ! is_array( $choices ) ) {
			return array();
		}

		if ( in_array( $type, array( 'swatch_color', 'color' ), true ) ) {
			$colors = array();
			foreach ( $choices as $color ) {
				$sanitized = sanitize_hex_color( $color );
				if ( $sanitized ) {
					$colors[] = $sanitized;
				}
			}
			return $colors;
		}

		$out = array();
		foreach ( $choices as $choice ) {
			if ( is_array( $choice ) && isset( $choice['value'] ) ) {
				$row = array(
					'value' => sanitize_text_field( $choice['value'] ),
					'label' => sanitize_text_field( $choice['label'] ?? $choice['value'] ),
				);
				if ( ! empty( $choice['img'] ) ) {
					$row['img'] = esc_url_raw( $choice['img'] );
				}
				$out[] = $row;
			} else {
				$out[] = array(
					'value' => sanitize_text_field( (string) $choice ),
					'label' => sanitize_text_field( (string) $choice ),
				);
			}
		}
		return $out;
	}
}
