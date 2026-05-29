<?php
/**
 * Engineering plate calibration — real mm dimensions for holder, plate, and zones.
 *
 * Reference (manufacturing drawing):
 * - Holder / engraving width ≈ 523 mm
 * - Total plate width ≈ 529.1 mm
 * - Plate height ≈ 116 mm
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PCKZ_Plate_Calibration
 */
class PCKZ_Plate_Calibration {

	const HOLDER_WIDTH_MM  = 523.0;
	const TOTAL_WIDTH_MM   = 529.1;
	const PLATE_HEIGHT_MM  = 116.0;
	const DESIGN_WIDTH_PX  = 3651;
	const DESIGN_HEIGHT_PX = 2132;
	const COORD_SYSTEM     = 'lightburn-mm-bottom-left';

	const LEGACY_WIDTH_MM  = 525.0;
	const LEGACY_HEIGHT_MM = 145.0;

	/**
	 * @return float
	 */
	public static function holder_offset_x_mm() {
		return round( ( self::TOTAL_WIDTH_MM - self::HOLDER_WIDTH_MM ) / 2, 3 );
	}

	/**
	 * Default plate canvas dimensions for export fallbacks (mm).
	 *
	 * @return array{width:float,height:float}
	 */
	public static function default_canvas_mm_array() {
		return array(
			'width'  => self::TOTAL_WIDTH_MM,
			'height' => self::PLATE_HEIGHT_MM,
		);
	}

	/**
	 * @return array
	 */
	public static function default_product_config() {
		$inset_x       = self::holder_offset_x_mm();
		$holder_margin = 2.5;

		return array(
			'canvas_width_mm'  => self::TOTAL_WIDTH_MM,
			'canvas_height_mm' => self::PLATE_HEIGHT_MM,
			'safe_zone_x_mm'   => round( $inset_x + $holder_margin, 3 ),
			'safe_zone_y_mm'   => round( 16.5 / self::LEGACY_HEIGHT_MM * self::PLATE_HEIGHT_MM, 3 ),
			'safe_zone_w_mm'   => round( self::HOLDER_WIDTH_MM - ( 2 * $holder_margin ), 3 ),
			'safe_zone_h_mm'   => round( 112 / self::LEGACY_HEIGHT_MM * self::PLATE_HEIGHT_MM, 3 ),
			'strip_zone_x_mm'  => round( $inset_x + ( 18 / self::LEGACY_WIDTH_MM ) * self::HOLDER_WIDTH_MM, 3 ),
			'strip_zone_y_mm'  => round( 98 / self::LEGACY_HEIGHT_MM * self::PLATE_HEIGHT_MM, 3 ),
			'strip_zone_w_mm'  => round( 489 / self::LEGACY_WIDTH_MM * self::HOLDER_WIDTH_MM, 3 ),
			'strip_zone_h_mm'  => round( 36 / self::LEGACY_HEIGHT_MM * self::PLATE_HEIGHT_MM, 3 ),
		);
	}

	/**
	 * @param array|null $config Optional product config.
	 * @return array
	 */
	public static function spec( $config = null ) {
		$plate = self::resolve_canvas_mm( $config );
		$zones = self::zones_from_config( $config );

		return array(
			'reference'         => 'engineering-plate-holder-dstu-ledos',
			'coordinate_system' => self::COORD_SYSTEM,
			'coordinate_origin' => is_array( $config ) && in_array( $config['origin'] ?? '', array( 'top-left', 'bottom-left' ), true )
				? $config['origin']
				: 'bottom-left',
			'plate_mm'          => array(
				'width'  => $plate['width'],
				'height' => $plate['height'],
			),
			'holder_mm'         => array(
				'width'  => $plate['holder_width'],
				'height' => $plate['holder_height'],
			),
			'holder_offset_mm'  => array(
				'x' => $plate['holder_offset_x'],
				'y' => 0.0,
			),
			'engraving_safe_mm' => $zones['safe_zone_mm'],
			'strip_zone_mm'     => $zones['strip_zone_mm'],
			'design_space_px'   => array(
				'width'  => self::DESIGN_WIDTH_PX,
				'height' => self::DESIGN_HEIGHT_PX,
			),
			'export_pipeline'   => array(
				'preview_canvas',
				'real_world_mm',
				'canonical_geometry',
				'svg_generation',
				'lbrn2_generation',
				'lightburn_placement',
			),
		);
	}

	/**
	 * @param array|null $config Product config.
	 * @param float|null $width  Optional width override.
	 * @param float|null $height Optional height override.
	 * @return array
	 */
	public static function resolve_canvas_mm( $config = null, $width = null, $height = null ) {
		$defaults = self::default_product_config();
		if ( is_array( $config ) ) {
			$defaults = wp_parse_args(
				array(
					'canvas_width_mm'  => $config['canvas_width_mm'] ?? null,
					'canvas_height_mm' => $config['canvas_height_mm'] ?? null,
				),
				$defaults
			);
		}

		$plate_w = null !== $width ? (float) $width : (float) $defaults['canvas_width_mm'];
		$plate_h = null !== $height ? (float) $height : (float) $defaults['canvas_height_mm'];

		if ( $plate_w <= 0 ) {
			$plate_w = self::TOTAL_WIDTH_MM;
		}
		if ( $plate_h <= 0 ) {
			$plate_h = self::PLATE_HEIGHT_MM;
		}

		$holder_w = self::HOLDER_WIDTH_MM;
		$holder_h = self::PLATE_HEIGHT_MM;

		return array(
			'width'           => $plate_w,
			'height'          => $plate_h,
			'holder_width'    => $holder_w,
			'holder_height'   => $holder_h,
			'holder_offset_x' => max( 0, round( ( $plate_w - $holder_w ) / 2, 3 ) ),
		);
	}

	/**
	 * @param float      $frac_x   X fraction over design width.
	 * @param float      $frac_y   Y fraction from design top.
	 * @param float      $frac_w   Width fraction.
	 * @param float      $frac_h   Height fraction.
	 * @param string     $origin   Origin.
	 * @param array|null $config   Product config.
	 * @return array
	 */
	public static function design_fraction_to_plate_mm( $frac_x, $frac_y, $frac_w, $frac_h, $origin = 'bottom-left', $config = null ) {
		$cal      = self::resolve_canvas_mm( $config );
		$x_mm     = $cal['holder_offset_x'] + (float) $frac_x * $cal['holder_width'];
		$w_mm     = (float) $frac_w * $cal['holder_width'];
		$h_mm     = (float) $frac_h * $cal['holder_height'];
		$y_top_mm = (float) $frac_y * $cal['holder_height'];
		$y_mm     = ( 'bottom-left' === $origin ) ? ( $cal['height'] - $y_top_mm - $h_mm ) : $y_top_mm;
		$center_y = ( 'bottom-left' === $origin ) ? ( $y_mm + $h_mm / 2 ) : ( $y_top_mm + $h_mm / 2 );

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
	 * @param array|null $config Product config.
	 * @return array
	 */
	public static function zones_from_config( $config = null ) {
		$defaults = self::default_product_config();
		if ( is_array( $config ) ) {
			$defaults = wp_parse_args( $config, $defaults );
		}

		return array(
			'safe_zone_mm'  => array(
				'x' => (float) $defaults['safe_zone_x_mm'],
				'y' => (float) $defaults['safe_zone_y_mm'],
				'w' => (float) $defaults['safe_zone_w_mm'],
				'h' => (float) $defaults['safe_zone_h_mm'],
			),
			'strip_zone_mm' => array(
				'x' => (float) $defaults['strip_zone_x_mm'],
				'y' => (float) $defaults['strip_zone_y_mm'],
				'w' => (float) $defaults['strip_zone_w_mm'],
				'h' => (float) $defaults['strip_zone_h_mm'],
			),
		);
	}
}
