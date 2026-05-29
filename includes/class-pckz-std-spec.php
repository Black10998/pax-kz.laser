<?php
/**
 * STD / manufacturing specifications for license plate frames (DSTU 4278 preset).
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PCKZ_Std_Spec
 */
class PCKZ_Std_Spec {

	/**
	 * Build manufacturing spec block for frontend + order export.
	 *
	 * @param array $config Product config.
	 * @return array
	 */
	public static function for_product( $config ) {
		$config = wp_parse_args(
			is_array( $config ) ? $config : array(),
			PCKZ_Post_Type::default_config()
		);

		$plate = class_exists( 'PCKZ_Plate_Calibration' )
			? PCKZ_Plate_Calibration::resolve_canvas_mm( $config )
			: array(
				'width'           => (float) ( $config['canvas_width_mm'] ?? 529.1 ),
				'height'          => (float) ( $config['canvas_height_mm'] ?? 116 ),
				'holder_width'    => 523,
				'holder_height'   => 116,
				'holder_offset_x' => 3.05,
			);
		$zones = class_exists( 'PCKZ_Plate_Calibration' )
			? PCKZ_Plate_Calibration::zones_from_config( $config )
			: array();

		return array(
			'standard'          => 'DSTU 4278 / Ledos license plate frame',
			'coordinate_origin' => in_array( $config['origin'] ?? '', array( 'top-left', 'bottom-left' ), true )
				? $config['origin']
				: 'bottom-left',
			'dpi'               => (int) ( $config['dpi'] ?? 300 ),
			'canvas_mm'         => array(
				'width'  => (float) $plate['width'],
				'height' => (float) $plate['height'],
			),
			'plate_calibration' => class_exists( 'PCKZ_Plate_Calibration' )
				? PCKZ_Plate_Calibration::spec( $config )
				: null,
			'safe_zone_mm'      => $zones['safe_zone_mm'] ?? array(
				'x' => (float) ( $config['safe_zone_x_mm'] ?? 5.55 ),
				'y' => (float) ( $config['safe_zone_y_mm'] ?? 13.2 ),
				'w' => (float) ( $config['safe_zone_w_mm'] ?? 518 ),
				'h' => (float) ( $config['safe_zone_h_mm'] ?? 89.6 ),
			),
			'strip_zone_mm'     => $zones['strip_zone_mm'] ?? array(
				'x' => (float) ( $config['strip_zone_x_mm'] ?? 20.94 ),
				'y' => (float) ( $config['strip_zone_y_mm'] ?? 78.48 ),
				'w' => (float) ( $config['strip_zone_w_mm'] ?? 487.37 ),
				'h' => (float) ( $config['strip_zone_h_mm'] ?? 28.83 ),
			),
			'design_space_px'   => array(
				'width'  => PCKZ_Ledos_Preview::DESIGN_WIDTH,
				'height' => PCKZ_Ledos_Preview::DESIGN_HEIGHT,
			),
			'text_layer_ref_px' => PCKZ_Ledos_Preview::layer_refs()['text'] ?? array(),
			'lightburn'         => array(
				'units'  => 'mm',
				'origin' => $config['origin'] ?? 'bottom-left',
				'notes'  => 'Design px maps to holder geometry; plate mm includes holder offset for LightBurn.',
			),
		);
	}
}
