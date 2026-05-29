<?php
/**
 * Manufacturing geometry validator — plate bounds, mm bboxes, coordinate system.
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PCKZ_Geometry_Validator
 */
class PCKZ_Geometry_Validator {

	const TOLERANCE_MM = 0.05;

	const PLATE_SAFE_W_MM = 518.0;

	const PLATE_SAFE_H_MM = 89.6;

	/**
	 * Validate canonical scene geometry before export.
	 *
	 * @param array $scene Canonical scene.
	 * @return array{status:string,errors:array}
	 */
	public static function validate_canonical_scene( $scene ) {
		$errors = array();

		if ( empty( $scene['coordinate_system'] ) || 'lightburn-mm-bottom-left' !== $scene['coordinate_system'] ) {
			$errors[] = array(
				'code'    => 'invalid_coordinate_system',
				'message' => __( 'Canonical scene must use lightburn-mm-bottom-left.', 'pckz-canonical-engine' ),
			);
		}

		$plate_w = (float) ( $scene['plate']['width_mm'] ?? 0 );
		$plate_h = (float) ( $scene['plate']['height_mm'] ?? 0 );
		if ( $plate_w <= 0 || $plate_h <= 0 ) {
			$errors[] = array(
				'code'    => 'invalid_plate',
				'message' => __( 'Plate dimensions are missing.', 'pckz-canonical-engine' ),
			);
		}

		foreach ( (array) ( $scene['objects'] ?? array() ) as $obj ) {
			$bbox = $obj['bbox'] ?? array();
			if ( ! self::is_valid_bbox( $bbox ) ) {
				$errors[] = array(
					'code'      => 'invalid_object_bbox',
					'object_id' => $obj['id'] ?? '',
					'role'      => $obj['role'] ?? '',
					'message'   => __( 'Object bbox invalid in canonical scene.', 'pckz-canonical-engine' ),
				);
				continue;
			}

			if ( $plate_w > 0 && $plate_h > 0 ) {
				$ox = (float) $bbox['x_mm'];
				$oy = (float) $bbox['y_mm'];
				$ow = (float) $bbox['width_mm'];
				$oh = (float) $bbox['height_mm'];
				if ( $ox < -self::TOLERANCE_MM || $oy < -self::TOLERANCE_MM
					|| $ox + $ow > $plate_w + self::TOLERANCE_MM
					|| $oy + $oh > $plate_h + self::TOLERANCE_MM ) {
					$errors[] = array(
						'code'      => 'object_out_of_plate',
						'object_id' => $obj['id'] ?? '',
						'role'      => $obj['role'] ?? '',
						'message'   => __( 'Object extends outside plate bounds.', 'pckz-canonical-engine' ),
						'bbox'      => $bbox,
					);
				}
			}
		}

		return array(
			'status' => empty( $errors ) ? 'PASS' : 'FAIL',
			'errors' => $errors,
		);
	}

	/**
	 * Validate production scene layers have measurable geometry.
	 *
	 * @param array $scene Production scene.
	 * @return array{status:string,errors:array}
	 */
	public static function validate_production_scene( $scene ) {
		$errors = array();

		if ( empty( $scene['layers'] ) || ! is_array( $scene['layers'] ) ) {
			$errors[] = array(
				'code'    => 'empty_production_scene',
				'message' => __( 'Production scene has no layers.', 'pckz-canonical-engine' ),
			);
			return array( 'status' => 'FAIL', 'errors' => $errors );
		}

		foreach ( $scene['layers'] as $layer ) {
			$has_paths = ! empty( $layer['paths'] ) || ! empty( $layer['svg_fragment'] ) || ! empty( $layer['path_data'] );
			$has_bbox  = ! empty( $layer['placement_bbox_mm'] ) || ! empty( $layer['bbox_mm'] );
			if ( ! $has_paths && ! $has_bbox ) {
				$errors[] = array(
					'code'     => 'empty_layer_geometry',
					'layer_id' => $layer['layer_id'] ?? '',
					'role'     => $layer['role'] ?? '',
					'message'  => __( 'Production layer missing geometry.', 'pckz-canonical-engine' ),
				);
			}
		}

		return array(
			'status' => empty( $errors ) ? 'PASS' : 'FAIL',
			'errors' => $errors,
		);
	}

	/**
	 * @param array $bbox Bbox.
	 * @return bool
	 */
	private static function is_valid_bbox( $bbox ) {
		if ( empty( $bbox ) || ! is_array( $bbox ) ) {
			return false;
		}
		$w = (float) ( $bbox['width_mm'] ?? 0 );
		$h = (float) ( $bbox['height_mm'] ?? 0 );
		return isset( $bbox['x_mm'], $bbox['y_mm'] ) && $w > 0 && $h > 0;
	}
}
