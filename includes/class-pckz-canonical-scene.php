<?php
/**
 * Canonical scene JSON — validation and normalization (lightburn-mm-bottom-left).
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PCKZ_Canonical_Scene
 */
class PCKZ_Canonical_Scene {

	const FORMAT            = 'pckzce-canonical-scene';
	const VERSION           = 2;
	const COORD_SYSTEM      = 'lightburn-mm-bottom-left';
	const ERROR_INVALID_GEO = 'canonical_geometry_invalid';

	/**
	 * Parse JSON string or array into canonical scene.
	 *
	 * @param mixed $input JSON string or array.
	 * @return array|WP_Error
	 */
	public static function from_input( $input ) {
		if ( is_string( $input ) ) {
			$decoded = json_decode( $input, true );
			if ( ! is_array( $decoded ) ) {
				return new WP_Error( 'invalid_json', __( 'Canonical scene JSON is invalid.', 'pckz-canonical-engine' ) );
			}
			$input = $decoded;
		}

		if ( ! is_array( $input ) ) {
			return new WP_Error( 'invalid_scene', __( 'Canonical scene must be an object.', 'pckz-canonical-engine' ) );
		}

		return self::normalize( $input );
	}

	/**
	 * Build canonical scene from legacy layout block (preview engine output).
	 *
	 * @param array $layout Layout.
	 * @param array $meta   Extra metadata.
	 * @return array
	 */
	public static function from_layout( $layout, $meta = array() ) {
		$plate_w = (float) ( $layout['canvas_mm']['width'] ?? $meta['plate_width_mm'] ?? 0 );
		$plate_h = (float) ( $layout['canvas_mm']['height'] ?? $meta['plate_height_mm'] ?? 0 );
		$objects = array();
		$errors  = array();
		$counts  = array();

		foreach ( (array) ( $layout['objects'] ?? array() ) as $obj ) {
			$role  = (string) ( $obj['role'] ?? 'object' );
			$count = (int) ( $counts[ $role ] ?? 0 );
			$counts[ $role ] = $count + 1;
			$id    = self::role_to_id( $role, $count );

			$bbox = self::normalize_bbox( $obj['mm'] ?? $obj, $plate_w, $plate_h );
			if ( ! $bbox ) {
				$errors[] = array(
					'code'      => self::ERROR_INVALID_GEO,
					'object_id' => $id,
					'role'      => $role,
					'message'   => __( 'Object bbox is invalid or missing in lightburn-mm-bottom-left.', 'pckz-canonical-engine' ),
				);
				continue;
			}

			$entry = array(
				'id'                 => $id,
				'role'               => $role,
				'x_mm'               => $bbox['x_mm'],
				'y_mm'               => $bbox['y_mm'],
				'bbox'               => $bbox,
				'width_mm'           => $bbox['width_mm'],
				'height_mm'          => $bbox['height_mm'],
				'scale'              => array(
					'x' => (float) ( $obj['scale']['x'] ?? $obj['scaleX'] ?? 1 ),
					'y' => (float) ( $obj['scale']['y'] ?? $obj['scaleY'] ?? 1 ),
				),
				'rotation_deg'       => (float) ( $obj['rotation_deg'] ?? $obj['angle'] ?? 0 ),
				'z_order'            => self::z_order_for_role( $role ),
				'color'              => (string) ( $obj['fill'] ?? $obj['color'] ?? '' ),
				'transforms'         => is_array( $obj['transforms'] ?? null ) ? $obj['transforms'] : array(),
				'svg_ref'            => self::svg_ref_for_object( $obj, $layout['selections'] ?? array() ),
				'text'               => null,
				'font_family'        => null,
				'text_path_geometry' => $obj['text_path_geometry'] ?? $obj['text_plate_paths'] ?? null,
			);

			if ( in_array( $role, array( 'text', 'main-text' ), true ) ) {
				$entry['text']        = trim( (string) ( $obj['text'] ?? '' ) );
				$entry['font_family'] = (string) ( $obj['font_family'] ?? $obj['fontFamily'] ?? '' );
				if ( '' === $entry['text'] ) {
					$errors[] = array(
						'code'      => self::ERROR_INVALID_GEO,
						'object_id' => $id,
						'role'      => $role,
						'message'   => __( 'Text object has empty value.', 'pckz-canonical-engine' ),
					);
				}
			}

			if ( in_array( $role, array( 'lines', 'line-overlay' ), true ) ) {
				$entry['line_type'] = (string) ( $obj['line_type'] ?? ( $layout['selections']['linien'] ?? 'none' ) );
			}

			if ( in_array( $role, array( 'icon-left', 'icon-right' ), true ) ) {
				$entry['symbol'] = (string) ( $obj['symbol'] ?? '' );
			}

			$objects[] = $entry;
		}

		usort(
			$objects,
			function ( $a, $b ) {
				return ( $a['z_order'] ?? 0 ) - ( $b['z_order'] ?? 0 );
			}
		);

		return array(
			'format'             => self::FORMAT,
			'version'            => self::VERSION,
			'coordinate_system'  => self::COORD_SYSTEM,
			'engine'             => $layout['engine'] ?? 'cloudlift-3651',
			'standard'           => $layout['standard'] ?? '',
			'generated_at'       => current_time( 'c' ),
			'plate'              => array(
				'width_mm'  => $plate_w,
				'height_mm' => $plate_h,
			),
			'safe_zone_mm'       => $layout['safe_zone_mm'] ?? null,
			'strip_zone_mm'      => $layout['strip_zone_mm'] ?? null,
			'design_px'          => $layout['design_px'] ?? null,
			'selections'         => $layout['selections'] ?? ( $meta['selections'] ?? array() ),
			'preview_mode'       => $meta['preview_mode'] ?? ( $layout['preview_mode'] ?? 'day' ),
			'product_id'         => (int) ( $meta['product_id'] ?? 0 ),
			'objects'            => $objects,
			'status'             => empty( $errors ) ? 'PASS' : 'FAIL',
			'errors'             => $errors,
		);
	}

	/**
	 * Validate canonical scene; returns PASS/FAIL with per-object mismatch details.
	 *
	 * @param array $scene Canonical scene.
	 * @return array{status:string,errors:array<int,array<string,mixed>>}
	 */
	public static function validate( $scene ) {
		$errors = array();

		if ( ( $scene['format'] ?? '' ) !== self::FORMAT ) {
			$errors[] = array(
				'code'    => 'invalid_format',
				'message' => __( 'Canonical scene format mismatch.', 'pckz-canonical-engine' ),
			);
		}

		if ( (int) ( $scene['version'] ?? 0 ) !== self::VERSION ) {
			$errors[] = array(
				'code'    => 'invalid_version',
				'message' => __( 'Canonical scene version mismatch.', 'pckz-canonical-engine' ),
			);
		}

		if ( ( $scene['coordinate_system'] ?? '' ) !== self::COORD_SYSTEM ) {
			$errors[] = array(
				'code'    => 'invalid_coordinate_system',
				'message' => __( 'Coordinate system must be lightburn-mm-bottom-left.', 'pckz-canonical-engine' ),
			);
		}

		$plate_w = (float) ( $scene['plate']['width_mm'] ?? 0 );
		$plate_h = (float) ( $scene['plate']['height_mm'] ?? 0 );
		if ( $plate_w <= 0 || $plate_h <= 0 ) {
			$errors[] = array(
				'code'    => self::ERROR_INVALID_GEO,
				'message' => __( 'Plate size is invalid.', 'pckz-canonical-engine' ),
			);
		}

		if ( empty( $scene['objects'] ) || ! is_array( $scene['objects'] ) ) {
			$errors[] = array(
				'code'    => self::ERROR_INVALID_GEO,
				'message' => __( 'Canonical scene has no objects.', 'pckz-canonical-engine' ),
			);
		} else {
			foreach ( $scene['objects'] as $obj ) {
				$errors = array_merge( $errors, self::validate_object( $obj, $plate_w, $plate_h ) );
			}
		}

		if ( ! empty( $scene['errors'] ) && is_array( $scene['errors'] ) ) {
			$errors = array_merge( $errors, $scene['errors'] );
		}

		return array(
			'status' => empty( $errors ) ? 'PASS' : 'FAIL',
			'errors' => $errors,
		);
	}

	/**
	 * Convert canonical scene to export layout context (single bbox path).
	 *
	 * @param array $scene Canonical scene.
	 * @return array
	 */
	public static function to_layout( $scene ) {
		$objects = array();
		foreach ( (array) ( $scene['objects'] ?? array() ) as $obj ) {
			$bbox = $obj['bbox'] ?? array();
			$layout_obj = array(
				'role'       => $obj['role'] ?? 'object',
				'object_id'  => $obj['id'] ?? '',
				'mm'         => $bbox,
				'angle'      => $obj['rotation_deg'] ?? 0,
				'fill'       => $obj['color'] ?? '',
				'svg_url'    => $obj['svg_ref'] ?? '',
				'svg_source' => '',
				'transforms' => $obj['transforms'] ?? array(),
			);

			if ( in_array( $layout_obj['role'], array( 'text', 'main-text' ), true ) ) {
				$layout_obj['text']         = $obj['text'] ?? '';
				$layout_obj['font_family']  = $obj['font_family'] ?? '';
				$layout_obj['text_plate_paths'] = $obj['text_path_geometry'] ?? null;
			}
			if ( in_array( $layout_obj['role'], array( 'lines', 'line-overlay' ), true ) ) {
				$layout_obj['line_type'] = $obj['line_type'] ?? 'none';
			}
			if ( in_array( $layout_obj['role'], array( 'icon-left', 'icon-right' ), true ) ) {
				$layout_obj['symbol'] = $obj['symbol'] ?? '';
			}

			$objects[] = $layout_obj;
		}

		return array(
			'engine'            => $scene['engine'] ?? 'cloudlift-3651',
			'standard'          => $scene['standard'] ?? '',
			'coordinate_origin' => 'bottom-left',
			'coordinate_system' => self::COORD_SYSTEM,
			'canvas_mm'         => array(
				'width'  => (float) ( $scene['plate']['width_mm'] ?? 0 ),
				'height' => (float) ( $scene['plate']['height_mm'] ?? 0 ),
			),
			'safe_zone_mm'      => $scene['safe_zone_mm'] ?? null,
			'strip_zone_mm'     => $scene['strip_zone_mm'] ?? null,
			'design_px'         => $scene['design_px'] ?? null,
			'selections'        => $scene['selections'] ?? array(),
			'objects'           => $objects,
			'canonical_scene'   => $scene,
		);
	}

	/**
	 * @param array $scene Scene.
	 * @return array
	 */
	private static function normalize( $scene ) {
		if ( empty( $scene['format'] ) && ! empty( $scene['objects'] ) ) {
			return self::from_layout( $scene );
		}
		$validation = self::validate( $scene );
		$scene['status'] = $validation['status'];
		$scene['errors'] = $validation['errors'];
		return $scene;
	}

	/**
	 * @param array $obj     Object.
	 * @param float $plate_w Plate W.
	 * @param float $plate_h Plate H.
	 * @return array<int,array<string,mixed>>
	 */
	private static function validate_object( $obj, $plate_w, $plate_h ) {
		$errors = array();
		$id     = (string) ( $obj['id'] ?? 'unknown' );
		$role   = (string) ( $obj['role'] ?? 'object' );
		$bbox   = self::normalize_bbox( $obj['bbox'] ?? $obj, $plate_w, $plate_h );

		if ( ! $bbox ) {
			$errors[] = array(
				'code'      => self::ERROR_INVALID_GEO,
				'object_id' => $id,
				'role'      => $role,
				'message'   => __( 'Invalid bbox for object.', 'pckz-canonical-engine' ),
			);
			return $errors;
		}

		foreach ( array( 'x_mm', 'y_mm', 'width_mm', 'height_mm' ) as $key ) {
			if ( ! isset( $bbox[ $key ] ) || ! is_numeric( $bbox[ $key ] ) ) {
				$errors[] = array(
					'code'      => self::ERROR_INVALID_GEO,
					'object_id' => $id,
					'role'      => $role,
					'field'     => $key,
					'message'   => sprintf(
						/* translators: %s: field name */
						__( 'Missing numeric field: %s', 'pckz-canonical-engine' ),
						$key
					),
				);
			}
		}

		if ( $bbox['x_mm'] < -0.01 || $bbox['y_mm'] < -0.01 ) {
			$errors[] = array(
				'code'      => self::ERROR_INVALID_GEO,
				'object_id' => $id,
				'role'      => $role,
				'message'   => __( 'Object position is outside plate (negative mm).', 'pckz-canonical-engine' ),
			);
		}

		if ( $bbox['x_mm'] + $bbox['width_mm'] > $plate_w + 0.01 ) {
			$errors[] = array(
				'code'      => self::ERROR_INVALID_GEO,
				'object_id' => $id,
				'role'      => $role,
				'message'   => __( 'Object extends beyond plate width.', 'pckz-canonical-engine' ),
			);
		}

		if ( $bbox['y_mm'] + $bbox['height_mm'] > $plate_h + 0.01 ) {
			$errors[] = array(
				'code'      => self::ERROR_INVALID_GEO,
				'object_id' => $id,
				'role'      => $role,
				'message'   => __( 'Object extends beyond plate height.', 'pckz-canonical-engine' ),
			);
		}

		if ( in_array( $role, array( 'text', 'main-text' ), true ) && '' === trim( (string) ( $obj['text'] ?? '' ) ) ) {
			$errors[] = array(
				'code'      => self::ERROR_INVALID_GEO,
				'object_id' => $id,
				'role'      => $role,
				'message'   => __( 'Text object is empty.', 'pckz-canonical-engine' ),
			);
		}

		return $errors;
	}

	/**
	 * Single bbox normalization path (bottom-left mm).
	 *
	 * @param array|null $mm      Mm box.
	 * @param float      $plate_w Plate W.
	 * @param float      $plate_h Plate H.
	 * @return array|null
	 */
	private static function normalize_bbox( $mm, $plate_w, $plate_h ) {
		if ( empty( $mm ) || ! is_array( $mm ) ) {
			return null;
		}

		$x = isset( $mm['x_mm'] ) ? (float) $mm['x_mm'] : null;
		$y = isset( $mm['y_mm'] ) ? (float) $mm['y_mm'] : null;
		$w = isset( $mm['width_mm'] ) ? (float) $mm['width_mm'] : null;
		$h = isset( $mm['height_mm'] ) ? (float) $mm['height_mm'] : null;

		if ( null === $x && isset( $mm['center_x_mm'], $w ) ) {
			$x = (float) $mm['center_x_mm'] - (float) $w / 2;
		}
		if ( null === $y && isset( $mm['center_y_mm'], $h ) ) {
			$y = (float) $mm['center_y_mm'] - (float) $h / 2;
		}

		if ( null === $x || null === $y || null === $w || null === $h || $w <= 0 || $h <= 0 ) {
			return null;
		}

		return array(
			'x_mm'        => round( $x, 3 ),
			'y_mm'        => round( $y, 3 ),
			'width_mm'    => round( $w, 3 ),
			'height_mm'   => round( $h, 3 ),
			'center_x_mm' => round( $x + $w / 2, 3 ),
			'center_y_mm' => round( $y + $h / 2, 3 ),
		);
	}

	/**
	 * @param string $role  Role.
	 * @param int    $index Index.
	 * @return string
	 */
	private static function role_to_id( $role, $index ) {
		$slug = sanitize_title( $role );
		return 'pckz-' . ( $slug ?: 'object' ) . ( $index > 0 ? '-' . $index : '' );
	}

	/**
	 * @param string $role Role.
	 * @return int
	 */
	private static function z_order_for_role( $role ) {
		$order = array(
			'lines'         => 10,
			'line-overlay'  => 10,
			'icon-bg-left'  => 18,
			'icon-bg-right' => 19,
			'icon-left'     => 20,
			'icon-right'    => 21,
			'text'          => 30,
			'main-text'     => 30,
		);
		return $order[ $role ] ?? 15;
	}

	/**
	 * @param array $obj        Object.
	 * @param array $selections Selections.
	 * @return string
	 */
	private static function svg_ref_for_object( $obj, $selections ) {
		if ( ! empty( $obj['svg_url'] ) ) {
			return (string) $obj['svg_url'];
		}
		if ( ! empty( $obj['svg_ref'] ) ) {
			return (string) $obj['svg_ref'];
		}
		if ( class_exists( 'PCKZ_Production_Geometry' ) ) {
			return (string) PCKZ_Production_Geometry::asset_url_for_object( $obj, $selections );
		}
		return '';
	}
}
