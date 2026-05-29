<?php
/**
 * Cloudlift-compatible preview layout (3651×2132 design space).
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PCKZ_Ledos_Preview
 */
class PCKZ_Ledos_Preview {

	/**
	 * Original Cloudlift canvas size (px).
	 */
	const DESIGN_WIDTH  = 3651;
	const DESIGN_HEIGHT = 2132;

	/**
	 * Config payload for JavaScript preview engine.
	 *
	 * @return array
	 */
	public static function config_for_js() {
		return array(
			'designWidth'  => self::DESIGN_WIDTH,
			'designHeight' => self::DESIGN_HEIGHT,
			'layers'       => self::layer_refs(),
			'lineTypes'    => self::line_types(),
			'iconCatalog'  => self::icon_catalog(),
			'colors'       => self::color_swatches(),
		);
	}

	/**
	 * Reference rectangles from Cloudlift previewConfig (Ledos frame).
	 *
	 * @return array
	 */
	public static function layer_refs() {
		return array(
			'text'         => array(
				'refX'      => 1136,
				'refY'      => 1256,
				'refWidth'  => 1392,
				'refHeight' => 93,
				'fontSize'  => 55,
				'stroke'    => 30,
			),
			'iconLeft'     => array(
				'refX'      => 816,
				'refY'      => 1243,
				'refWidth'  => 81,
				'refHeight' => 114,
			),
			'iconRight'    => array(
				'refX'      => 2750,
				'refY'      => 1243,
				'refWidth'  => 81,
				'refHeight' => 114,
			),
			'iconBgLeft'   => array(
				'refX'      => 767,
				'refY'      => 1244,
				'refWidth'  => 178,
				'refHeight' => 113,
				'url'       => 'https://cdn.shopify.com/s/files/1/0746/3672/2449/files/a_Tx6b_Icon_background.svg',
			),
			'iconBgRight'  => array(
				'refX'      => 2700,
				'refY'      => 1244,
				'refWidth'  => 178,
				'refHeight' => 113,
				'url'       => 'https://cdn.shopify.com/s/files/1/0746/3672/2449/files/a_Tx6b_Icon_background.svg',
			),
			'lines'        => array(
				'refX'      => 609,
				'refY'      => 1173,
				'refWidth'  => 2424,
				'refHeight' => 254,
			),
		);
	}

	/**
	 * Bundled line ornament directory (type_21+), relative to plugin root.
	 */
	const LINE_ASSETS_DIR = 'public/assets/lines/';

	/**
	 * First / last bundled line type index (model 21–71 SVGs).
	 */
	const BUNDLED_LINE_TYPE_MIN = 21;
	const BUNDLED_LINE_TYPE_MAX = 71;

	/**
	 * Absolute path to bundled line SVG directory.
	 *
	 * @return string
	 */
	public static function line_assets_dir() {
		return trailingslashit( PCKZCE_PLUGIN_DIR ) . self::LINE_ASSETS_DIR;
	}

	/**
	 * Public URL for bundled line SVG directory.
	 *
	 * @return string
	 */
	public static function line_assets_url() {
		return trailingslashit( PCKZCE_PLUGIN_URL ) . self::LINE_ASSETS_DIR;
	}

	/**
	 * Line overlay SVGs shipped with the plugin (type_21–type_71).
	 *
	 * @return array<string,string>
	 */
	public static function bundled_line_types() {
		$out = array();
		$dir = self::line_assets_dir();
		if ( ! is_dir( $dir ) ) {
			return $out;
		}
		for ( $i = self::BUNDLED_LINE_TYPE_MIN; $i <= self::BUNDLED_LINE_TYPE_MAX; $i++ ) {
			$key  = 'type_' . $i;
			$file = $dir . $key . '.svg';
			if ( is_readable( $file ) ) {
				$out[ $key ] = self::line_assets_url() . $key . '.svg';
			}
		}
		return $out;
	}

	/**
	 * Line overlay SVGs (Type 1–20 CDN + bundled type_21–71).
	 *
	 * @return array<string,string>
	 */
	public static function line_types() {
		$base = 'https://cdn.shopify.com/s/files/1/0746/3672/2449/files/';
		$map  = array(
			'none'   => '',
			'type_1' => $base . 'a_ndWL_Line1.svg',
			'type_2' => $base . 'a_DdrZ_Line2.svg',
			'type_3' => $base . 'a_U57w_Line3.svg',
			'type_4' => $base . 'a_Qqgq_Type_4.svg',
			'type_5' => $base . 'a_svLa_Type_5.svg',
			'type_6' => $base . 'a_FFQ8_Type_6.svg',
			'type_7' => $base . 'a_dOm6_Type_7.svg',
			'type_8' => $base . 'a_tD3h_Type_8.svg',
			'type_9' => $base . 'a_7Idt_Type_9.svg',
			'type_10' => $base . 'a_js2U_Type_10.svg',
			'type_11' => $base . 'a_ebZG_Type_11.svg',
			'type_12' => $base . 'a_7BWS_Type_12.svg',
			'type_13' => $base . 'a_J599_Type_13.svg',
			'type_14' => $base . 'a_N54Z_Type_14.svg',
			'type_15' => $base . 'a_7sn6_Type_15.svg',
			'type_16' => $base . 'a_r6U7_Type_16.svg',
			'type_17' => $base . 'a_wkLQ_Type_17.svg',
			'type_18' => $base . 'a_1Ywc_Type_18.svg',
			'type_19' => $base . 'a_H3HV_Type_19.svg',
			'type_20' => $base . 'a_bk42_Type_20.svg',
		);
		return array_merge( $map, self::bundled_line_types() );
	}

	/**
	 * Social / symbol icons (subset + bundled fallbacks).
	 *
	 * @return array<string,array>
	 */
	public static function icon_catalog() {
		$cdn = 'https://cdn.shopify.com/s/files/1/0746/3672/2449/files/';
		$items = array(
			'none'        => array( 'url' => '', 'tintable' => false, 'label' => 'Kein Symbol' ),
			'instagram'   => array( 'url' => $cdn . 'a_KxK4_Icon_instagram.svg', 'tintable' => true ),
			'instagram_v2' => array( 'url' => $cdn . 'a_TFTB_Instagram_v2.svg', 'tintable' => true ),
			'instagram_v4' => array( 'url' => $cdn . 'a_WM81_Instagram_20v4.svg', 'tintable' => true ),
			'telegram_v1' => array( 'url' => 'https://storage.googleapis.com/cloudlift-app-cloud-prod-assets/386884-2/a_wR0e_Telegram v1.svg', 'tintable' => true ),
			'telegram_v4' => array( 'url' => 'https://cdn.shopify.com/s/files/1/0768/1048/6109/files/a_ZrX4_Telegram_20v4.svg', 'tintable' => true ),
			'facebook'    => array( 'url' => $cdn . 'a_puqt_Facebook_20v1.svg', 'tintable' => true ),
			'facebook_v2' => array( 'url' => $cdn . 'a_xkPO_Facebook_20v2.svg', 'tintable' => true ),
			'facebook_v4' => array( 'url' => $cdn . 'a_DOml_Facebook_20v4.svg', 'tintable' => true ),
			'youtube_v1'  => array( 'url' => $cdn . 'a_anAT_YouTube_20v1.svg', 'tintable' => true ),
			'youtube_v4'  => array( 'url' => $cdn . 'a_5NBS_YouTube_20v4.svg', 'tintable' => true ),
			'tiktok_v1'   => array( 'url' => $cdn . 'a_pxQh_TikTok_20v1.svg', 'tintable' => true ),
			'tiktok_v3'   => array( 'url' => $cdn . 'a_nugD_TikTok_20v3.svg', 'tintable' => true ),
			'twitter_v1'  => array( 'url' => $cdn . 'a_kmb9_Twitter_20v1.svg', 'tintable' => true ),
			'twitch_v1'   => array( 'url' => $cdn . 'a_3qNo_Twitch_20v1.svg', 'tintable' => true ),
			'verified_v1' => array( 'url' => $cdn . 'a_bx4A_Verified_20v1.svg', 'tintable' => true ),
			'football'    => array( 'url' => $cdn . 'a_madY_Icon-1.svg', 'tintable' => true ),
			'wolf'        => array( 'url' => $cdn . 'a_SNWa_Icon.svg', 'tintable' => true ),
		);

		$symbol_cdn = self::symbol_cdn_map();
		foreach ( PCKZ_Icons::symbol_choices() as $choice ) {
			$slug = $choice['value'] ?? '';
			if ( 'none' === $slug || isset( $items[ $slug ] ) ) {
				continue;
			}
			$cdn_url = $symbol_cdn[ $slug ] ?? '';
			$white   = PCKZ_Icons::icon_url( $slug, 'white' );
			$black   = PCKZ_Icons::icon_url( $slug, 'black' );
			$url = $cdn_url ?: $white ?: $black;
			if ( $url ) {
				$preview = $cdn_url ? $cdn_url : ( $black ?: $white );
				$items[ $slug ] = array(
					'url'      => esc_url_raw( $url ),
					'preview'  => esc_url_raw( $preview ),
					'tintable' => true,
					'label'    => $choice['label'] ?? $slug,
				);
			}
		}

		foreach ( $items as $slug => $data ) {
			if ( 'none' === $slug ) {
				continue;
			}
			if ( empty( $data['preview'] ) && ! empty( $data['url'] ) ) {
				$items[ $slug ]['preview'] = $data['url'];
			}
		}

		return $items;
	}

	/**
	 * Map simple symbol slugs to Cloudlift CDN SVG assets.
	 *
	 * @return array<string,string>
	 */
	public static function symbol_cdn_map() {
		$cdn = 'https://cdn.shopify.com/s/files/1/0746/3672/2449/files/';
		$plugin = PCKZCE_PLUGIN_URL . 'public/images/icons/';
		return array(
			'instagram' => $cdn . 'a_WM81_Instagram_20v4.svg',
			'telegram'  => 'https://cdn.shopify.com/s/files/1/0768/1048/6109/files/a_ZrX4_Telegram_20v4.svg',
			'facebook'  => $cdn . 'a_DOml_Facebook_20v4.svg',
			'tiktok'    => $cdn . 'a_nugD_TikTok_20v3.svg',
			'snapchat'  => esc_url_raw( $plugin . 'snapchat-white.svg' ),
			'whatsapp'  => esc_url_raw( $plugin . 'whatsapp-white.svg' ),
		);
	}

	/**
	 * Color swatches (text / icons / lines).
	 *
	 * @return array
	 */
	public static function color_swatches() {
		return array(
			array( 'value' => 'White', 'hex' => '#ffffff' ),
			array( 'value' => 'Red', 'hex' => '#FF0000' ),
			array( 'value' => 'Maroon', 'hex' => '#B60000' ),
			array( 'value' => 'Brown', 'hex' => '#BF733C' ),
			array( 'value' => 'Purple', 'hex' => '#CC00FF' ),
			array( 'value' => 'Magenta', 'hex' => '#FF00C7' ),
			array( 'value' => 'Yellow', 'hex' => '#FFEB34' ),
			array( 'value' => 'Orange', 'hex' => '#FFCC00' ),
			array( 'value' => 'Blue', 'hex' => '#0075FF' ),
			array( 'value' => 'Green', 'hex' => '#05DB5B' ),
			array( 'value' => 'Teal', 'hex' => '#6BFFAF' ),
			array( 'value' => 'Cyan', 'hex' => '#6BF6FF' ),
			array( 'value' => 'Violet', 'hex' => '#896BFF' ),
			array( 'value' => 'Grey', 'hex' => '#949494' ),
			array( 'value' => 'Dark Grey', 'hex' => '#424242' ),
			array( 'value' => 'Lime', 'hex' => '#bbff00' ),
		);
	}

	/**
	 * German customer options aligned with Cloudlift fields.
	 *
	 * @return array
	 */
	public static function default_cloudlift_options() {
		$colors = array();
		foreach ( self::color_swatches() as $sw ) {
			$colors[] = $sw['hex'];
		}

		$icon_choices = array();
		foreach ( self::icon_catalog() as $slug => $data ) {
			$thumb = ! empty( $data['preview'] ) ? $data['preview'] : ( $data['url'] ?? '' );
			$icon_choices[] = array(
				'value' => $slug,
				'label' => $data['label'] ?? PCKZ_Icons::label_for_slug( $slug ),
				'img'   => $thumb ? esc_url_raw( $thumb ) : '',
			);
		}

		$line_choices = array(
			array( 'value' => 'none', 'label' => 'Keine Linien', 'img' => PCKZCE_PLUGIN_URL . 'public/images/icons/lines-black.svg' ),
		);
		$lines = self::line_types();
		foreach ( $lines as $key => $url ) {
			if ( 'none' === $key || '' === $url ) {
				continue;
			}
			if ( ! preg_match( '/^type_(\d+)$/', $key, $m ) ) {
				continue;
			}
			$line_choices[] = array(
				'value' => $key,
				'label' => 'Typ ' . $m[1],
				'img'   => esc_url_raw( $url ),
			);
		}

		return array(
			array(
				'id'          => 'led_enabled',
				'type'        => 'radio',
				'label'       => 'LED-Beleuchtung',
				'choices'     => array(
					array( 'value' => 'no', 'label' => 'Nein' ),
					array( 'value' => 'yes', 'label' => 'Ja (+300 ₴)' ),
				),
				'default'     => 'no',
			),
			array(
				'id'          => 'preview_mode',
				'type'        => 'radio',
				'label'       => 'Vorschau',
				'choices'     => array(
					array( 'value' => 'day', 'label' => 'Tag ☀️' ),
					array( 'value' => 'night', 'label' => 'Nacht 🌙' ),
				),
				'default'     => 'day',
				'preview_key' => 'background',
				'show_when'   => array( 'led_enabled' => 'no' ),
			),
			array(
				'id'          => 'preview_led',
				'type'        => 'radio',
				'label'       => 'Vorschau LED',
				'choices'     => array(
					array( 'value' => 'day', 'label' => 'Tag ☀️' ),
					array( 'value' => 'night', 'label' => 'Nacht 🌙' ),
				),
				'default'     => 'day',
				'preview_key' => 'background',
				'show_when'   => array( 'led_enabled' => 'yes' ),
			),
			array(
				'id'          => 'custom_text',
				'type'        => 'text',
				'label'       => 'Text',
				'placeholder' => 'Ihr Text',
				'default'     => '',
				'maxlength'   => 40,
				'required'    => true,
			),
			array(
				'id'      => 'font_family',
				'type'    => 'font',
				'label'   => 'Schriftart',
				'default' => 'Russo One',
			),
			array(
				'id'      => 'text_color',
				'type'    => 'swatch_color',
				'label'   => 'Textfarbe',
				'choices' => $colors,
				'default' => '#ffffff',
			),
			array(
				'id'      => 'symbol_links',
				'type'    => 'icon_select',
				'label'   => 'Symbol links',
				'choices' => $icon_choices,
				'default' => 'none',
			),
			array(
				'id'      => 'icon_color_left',
				'type'    => 'swatch_color',
				'label'   => 'Symbolfarbe links',
				'choices' => $colors,
				'default' => '#ffffff',
				'show_when' => array( 'symbol_links' => '!none' ),
			),
			array(
				'id'      => 'symbol_rechts',
				'type'    => 'icon_select',
				'label'   => 'Symbol rechts',
				'choices' => $icon_choices,
				'default' => 'none',
			),
			array(
				'id'      => 'icon_color_right',
				'type'    => 'swatch_color',
				'label'   => 'Symbolfarbe rechts',
				'choices' => $colors,
				'default' => '#ffffff',
				'show_when' => array( 'symbol_rechts' => '!none' ),
			),
			array(
				'id'      => 'linien',
				'type'    => 'icon_select',
				'label'   => 'Linien',
				'choices' => $line_choices,
				'default' => 'none',
			),
			array(
				'id'      => 'line_color',
				'type'    => 'swatch_color',
				'label'   => 'Linienfarbe',
				'choices' => $colors,
				'default' => '#FF0000',
				'show_when' => array( 'linien' => '!none' ),
			),
		);
	}

	/**
	 * Resolve hex from swatch value name.
	 *
	 * @param string $value Swatch value or hex.
	 * @return string
	 */
	/**
	 * Convert Cloudlift layer ref to mm box (bottom-left origin).
	 *
	 * @param array  $ref      Layer ref (refX, refY, refWidth, refHeight).
	 * @param float  $canvas_w Canvas width mm.
	 * @param float  $canvas_h Canvas height mm.
	 * @param string $origin   Coordinate origin.
	 * @return array
	 */
	public static function ref_to_mm_box( $ref, $canvas_w, $canvas_h, $origin = 'bottom-left' ) {
		$dw = (float) self::DESIGN_WIDTH;
		$dh = (float) self::DESIGN_HEIGHT;
		$rx = (float) ( $ref['refX'] ?? 0 );
		$ry = (float) ( $ref['refY'] ?? 0 );
		$rw = (float) ( $ref['refWidth'] ?? 0 );
		$rh = (float) ( $ref['refHeight'] ?? 0 );

		if ( class_exists( 'PCKZ_Plate_Calibration' ) ) {
			return PCKZ_Plate_Calibration::design_fraction_to_plate_mm(
				$rx / $dw,
				$ry / $dh,
				$rw / $dw,
				$rh / $dh,
				$origin,
				array(
					'canvas_width_mm'  => $canvas_w,
					'canvas_height_mm' => $canvas_h,
				)
			);
		}

		$x_mm      = ( $rx / $dw ) * $canvas_w;
		$w_mm      = ( $rw / $dw ) * $canvas_w;
		$h_mm      = ( $rh / $dh ) * $canvas_h;
		$y_top_mm  = ( $ry / $dh ) * $canvas_h;
		$y_mm      = ( 'bottom-left' === $origin ) ? ( $canvas_h - $y_top_mm - $h_mm ) : $y_top_mm;
		$center_y  = ( 'bottom-left' === $origin ) ? ( $y_mm + $h_mm / 2 ) : ( $y_top_mm + $h_mm / 2 );

		return array(
			'x_mm'        => round( $x_mm, 3 ),
			'y_mm'        => round( $y_mm, 3 ),
			'width_mm'    => round( $w_mm, 3 ),
			'height_mm'   => round( $h_mm, 3 ),
			'center_x_mm' => round( $x_mm + $w_mm / 2, 3 ),
			'center_y_mm' => round( $center_y, 3 ),
		);
	}

	/**
	 * Build guaranteed production objects from selections + layer refs (server fallback).
	 *
	 * @param array $selections Customer selections.
	 * @param float $canvas_w   Canvas W mm.
	 * @param float $canvas_h   Canvas H mm.
	 * @param string $origin    Origin.
	 * @return array
	 */
	public static function build_production_objects( $selections, $canvas_w = null, $canvas_h = null, $origin = 'bottom-left' ) {
		if ( null === $canvas_w || null === $canvas_h ) {
			$plate = class_exists( 'PCKZ_Plate_Calibration' ) ? PCKZ_Plate_Calibration::default_canvas_mm_array() : array( 'width' => 529.1, 'height' => 116 );
			$canvas_w = null === $canvas_w ? (float) $plate['width'] : (float) $canvas_w;
			$canvas_h = null === $canvas_h ? (float) $plate['height'] : (float) $canvas_h;
		}
		$refs    = self::layer_refs();
		$lines   = self::line_types();
		$icons   = self::icon_catalog();
		$objects = array();

		$text = trim( (string) ( $selections['custom_text'] ?? '' ) );
		if ( '' !== $text && ! empty( $refs['text'] ) ) {
			$text_ref = $refs['text'];
			$objects[] = array(
				'role'         => 'text',
				'text'         => $text,
				'font_family'  => $selections['font_family'] ?? 'Russo One',
				'font_size_px' => (float) ( $text_ref['fontSize'] ?? 55 ),
				'fill'         => $selections['text_color'] ?? 'White',
				'stroke'       => ( in_array( $selections['text_color'] ?? '', array( 'White', '#ffffff', '#FFFFFF' ), true ) ) ? '#000000' : '',
				'stroke_width' => (float) ( $text_ref['stroke'] ?? 0 ),
				'mm'           => self::ref_to_mm_box( $text_ref, $canvas_w, $canvas_h, $origin ),
			);
		}

		$linien = $selections['linien'] ?? 'none';
		if ( $linien && 'none' !== $linien && 'no' !== $linien && ! empty( $lines[ $linien ] ) && ! empty( $refs['lines'] ) ) {
			$objects[] = array(
				'role'      => 'lines',
				'line_type' => $linien,
				'fill'      => $selections['line_color'] ?? 'Red',
				'svg_url'   => $lines[ $linien ],
				'mm'        => self::ref_to_mm_box( $refs['lines'], $canvas_w, $canvas_h, $origin ),
			);
		}

		$left_slug = $selections['symbol_links'] ?? 'none';
		if ( $left_slug && 'none' !== $left_slug && ! empty( $refs['iconLeft'] ) ) {
			if ( ! empty( $refs['iconBgLeft']['url'] ) ) {
				$objects[] = array(
					'role'    => 'icon-bg-left',
					'symbol'  => $left_slug,
					'fill'    => $selections['icon_color_left'] ?? 'White',
					'svg_url' => $refs['iconBgLeft']['url'],
					'mm'      => self::ref_to_mm_box( $refs['iconBgLeft'], $canvas_w, $canvas_h, $origin ),
				);
			}
			$url = $icons[ $left_slug ]['url'] ?? '';
			$objects[] = array(
				'role'    => 'icon-left',
				'symbol'  => $left_slug,
				'fill'    => $selections['icon_color_left'] ?? 'White',
				'svg_url' => $url,
				'mm'      => self::ref_to_mm_box( $refs['iconLeft'], $canvas_w, $canvas_h, $origin ),
			);
		}

		$right_slug = $selections['symbol_rechts'] ?? 'none';
		if ( $right_slug && 'none' !== $right_slug && ! empty( $refs['iconRight'] ) ) {
			if ( ! empty( $refs['iconBgRight']['url'] ) ) {
				$objects[] = array(
					'role'    => 'icon-bg-right',
					'symbol'  => $right_slug,
					'fill'    => $selections['icon_color_right'] ?? 'White',
					'svg_url' => $refs['iconBgRight']['url'],
					'mm'      => self::ref_to_mm_box( $refs['iconBgRight'], $canvas_w, $canvas_h, $origin ),
				);
			}
			$url = $icons[ $right_slug ]['url'] ?? '';
			$objects[] = array(
				'role'    => 'icon-right',
				'symbol'  => $right_slug,
				'fill'    => $selections['icon_color_right'] ?? 'White',
				'svg_url' => $url,
				'mm'      => self::ref_to_mm_box( $refs['iconRight'], $canvas_w, $canvas_h, $origin ),
			);
		}

		return $objects;
	}

	/**
	 * Merge client layout objects with synthesized fallback (fill missing roles).
	 *
	 * @param array $client     Objects from canvas.
	 * @param array $selections Selections.
	 * @param float $canvas_w   W mm.
	 * @param float $canvas_h   H mm.
	 * @param string $origin    Origin.
	 * @return array
	 */
	public static function ensure_production_objects( $client, $selections, $canvas_w, $canvas_h, $origin = 'bottom-left' ) {
		$client = is_array( $client ) ? $client : array();
		$synth  = self::build_production_objects( $selections, $canvas_w, $canvas_h, $origin );

		if ( empty( $client ) ) {
			return $synth;
		}

		$roles_present = array();
		foreach ( $client as $obj ) {
			$roles_present[ $obj['role'] ?? '' ] = true;
		}

		$merged = $client;
		foreach ( $synth as $obj ) {
			$role = $obj['role'] ?? '';
			if ( empty( $roles_present[ $role ] ) ) {
				$merged[] = $obj;
			}
		}

		return $merged;
	}

	public static function hex_for_color_value( $value ) {
		if ( preg_match( '/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', (string) $value ) ) {
			return sanitize_hex_color( $value );
		}
		foreach ( self::color_swatches() as $sw ) {
			if ( ( $sw['value'] ?? '' ) === $value ) {
				return $sw['hex'];
			}
		}
		return '#ffffff';
	}
}
