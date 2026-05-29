<?php
/**
 * Export parity validation — canonical scene vs generated production layers.
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PCKZ_Export_Parity
 */
class PCKZ_Export_Parity {

	const ROLE_ALIASES = array(
		'main-text'    => 'text',
		'text'         => 'main-text',
		'line-overlay' => 'lines',
		'lines'        => 'line-overlay',
	);

	const TOLERANCE_MM = 0.05;

	public static function validate( $canonical, $scene ) {
		$errors  = array();
		$matches = array();

		$canonical_objects = (array) ( $canonical['objects'] ?? array() );
		$layers            = (array) ( $scene['layers'] ?? array() );

		foreach ( $canonical_objects as $obj ) {
			$id   = (string) ( $obj['id'] ?? '' );
			$role = (string) ( $obj['role'] ?? '' );
			$bbox = $obj['bbox'] ?? array();

			$matched_layers = self::layers_for_object( $layers, $id, $role );
			if ( empty( $matched_layers ) ) {
				$errors[] = array(
					'code'      => 'parity_missing_object',
					'object_id' => $id,
					'role'      => $role,
					'message'   => __( 'Generated export is missing object.', 'pckz-canonical-engine' ),
					'expected'  => $bbox,
				);
				continue;
			}

			$export_box = self::export_bbox_for_layers( $matched_layers );
			if ( in_array( $role, array( 'text', 'main-text' ), true ) ) {
				foreach ( $matched_layers as $text_layer ) {
					if ( ! empty( $text_layer['placement_bbox_mm'] ) && is_array( $text_layer['placement_bbox_mm'] ) ) {
						$export_box = $text_layer['placement_bbox_mm'];
						break;
					}
				}
			}
			if ( ! $export_box ) {
				$errors[] = array(
					'code'      => 'parity_invalid_layer',
					'object_id' => $id,
					'role'      => $role,
					'message'   => __( 'Generated layer has no measurable geometry.', 'pckz-canonical-engine' ),
					'expected'  => $bbox,
				);
				continue;
			}

			$mismatch = self::compare_boxes( $bbox, $export_box );
			if ( ! empty( $mismatch ) ) {
				$errors[] = self::build_mismatch_error( $obj, $matched_layers, $bbox, $export_box, $mismatch );
				continue;
			}

			$matches[] = array(
				'object_id' => $id,
				'role'      => $role,
				'status'    => 'PASS',
			);
		}

		return array(
			'status'  => empty( $errors ) ? 'PASS' : 'FAIL',
			'errors'  => $errors,
			'matches' => $matches,
		);
	}

	private static function layers_for_object( $layers, $id, $role ) {
		$matched = array();
		$aliases = array( $role );
		if ( ! empty( self::ROLE_ALIASES[ $role ] ) ) {
			$aliases[] = self::ROLE_ALIASES[ $role ];
		}

		foreach ( $layers as $layer ) {
			$layer_id   = (string) ( $layer['layer_id'] ?? '' );
			$layer_role = (string) ( $layer['role'] ?? '' );
			$canonical  = (string) ( $layer['canonical_object_id'] ?? '' );

			if ( $id && ( $canonical === $id || $layer_id === $id ) ) {
				$matched[] = $layer;
				continue;
			}

			if ( in_array( $layer_role, $aliases, true ) ) {
				$matched[] = $layer;
				continue;
			}

			if ( in_array( $role, array( 'text', 'main-text' ), true ) ) {
				if ( in_array( $layer_id, array( 'pckz-text', 'pckz-main-text' ), true ) ) {
					$matched[] = $layer;
					continue;
				}
				if ( in_array( $layer_role, array( 'text-engrave', 'pckz-text-engrave', 'text-paths', 'pckz-text-paths' ), true ) ) {
					$matched[] = $layer;
				}
			}
		}

		return $matched;
	}

	private static function export_bbox_for_layers( $layers ) {
		foreach ( $layers as $layer ) {
			if ( ! empty( $layer['placement_bbox_mm'] ) && is_array( $layer['placement_bbox_mm'] ) ) {
				return $layer['placement_bbox_mm'];
			}
		}

		$union = null;
		foreach ( $layers as $layer ) {
			$box = null;
			if ( ! empty( $layer['bbox_mm'] ) && is_array( $layer['bbox_mm'] ) ) {
				$box = $layer['bbox_mm'];
			} elseif ( ! empty( $layer['measured_bbox_mm'] ) && is_array( $layer['measured_bbox_mm'] ) ) {
				$box = $layer['measured_bbox_mm'];
			}
			if ( $box ) {
				$union = $union ? self::union_boxes( $union, $box ) : $box;
			}
		}

		return $union;
	}

	private static function union_boxes( $a, $b ) {
		$ax1 = (float) ( $a['x_mm'] ?? 0 );
		$ay1 = (float) ( $a['y_mm'] ?? 0 );
		$ax2 = $ax1 + (float) ( $a['width_mm'] ?? 0 );
		$ay2 = $ay1 + (float) ( $a['height_mm'] ?? 0 );
		$bx1 = (float) ( $b['x_mm'] ?? 0 );
		$by1 = (float) ( $b['y_mm'] ?? 0 );
		$bx2 = $bx1 + (float) ( $b['width_mm'] ?? 0 );
		$by2 = $by1 + (float) ( $b['height_mm'] ?? 0 );

		$x1 = min( $ax1, $bx1 );
		$y1 = min( $ay1, $by1 );
		$x2 = max( $ax2, $bx2 );
		$y2 = max( $ay2, $by2 );
		$w  = max( 0.001, $x2 - $x1 );
		$h  = max( 0.001, $y2 - $y1 );

		return array(
			'x_mm'        => round( $x1, 3 ),
			'y_mm'        => round( $y1, 3 ),
			'width_mm'    => round( $w, 3 ),
			'height_mm'   => round( $h, 3 ),
			'center_x_mm' => round( $x1 + $w / 2, 3 ),
			'center_y_mm' => round( $y1 + $h / 2, 3 ),
		);
	}

	private static function build_mismatch_error( $obj, $layers, $expected, $actual, $mismatch ) {
		$measured = null;
		foreach ( $layers as $layer ) {
			if ( ! empty( $layer['measured_bbox_mm'] ) ) {
				$measured = $measured
					? self::union_boxes( $measured, $layer['measured_bbox_mm'] )
					: $layer['measured_bbox_mm'];
			}
		}

		$transform = null;
		foreach ( $layers as $layer ) {
			if ( ! empty( $layer['transform'] ) && is_array( $layer['transform'] ) ) {
				$transform = $layer['transform'];
				break;
			}
		}

		$anchor = 'bottom-left';
		foreach ( $layers as $layer ) {
			if ( ! empty( $layer['anchor'] ) ) {
				$anchor = $layer['anchor'];
				break;
			}
		}

		$error = array(
			'code'      => 'parity_geometry_mismatch',
			'object_id' => (string) ( $obj['id'] ?? '' ),
			'role'      => (string) ( $obj['role'] ?? '' ),
			'message'   => __( 'Object placement mismatch between canonical scene and export.', 'pckz-canonical-engine' ),
			'expected'  => $expected,
			'actual'    => $actual,
			'measured'  => $measured,
			'diff'      => $mismatch,
			'delta'     => array(
				'x_mm'      => round( (float) ( $actual['x_mm'] ?? 0 ) - (float) ( $expected['x_mm'] ?? 0 ), 4 ),
				'y_mm'      => round( (float) ( $actual['y_mm'] ?? 0 ) - (float) ( $expected['y_mm'] ?? 0 ), 4 ),
				'width_mm'  => round( (float) ( $actual['width_mm'] ?? 0 ) - (float) ( $expected['width_mm'] ?? 0 ), 4 ),
				'height_mm' => round( (float) ( $actual['height_mm'] ?? 0 ) - (float) ( $expected['height_mm'] ?? 0 ), 4 ),
			),
			'transform' => $transform,
			'matrix'    => self::transform_to_matrix( $transform ),
			'anchor'    => $anchor,
			'origin'    => 'lightburn-mm-bottom-left',
		);

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log(
			'PCKZ parity_geometry_mismatch ' . wp_json_encode(
				array(
					'object_id' => $error['object_id'],
					'role'      => $error['role'],
					'expected'  => $expected,
					'actual'    => $actual,
					'delta'     => $error['delta'],
					'measured'  => $measured,
					'transform' => $transform,
					'matrix'    => $error['matrix'],
					'anchor'    => $anchor,
				)
			)
		);
		}

		return $error;
	}

	/**
	 * SVG matrix [a b c d e f] from export transform metadata.
	 *
	 * @param array|null $transform Transform metadata.
	 * @return array|null
	 */
	private static function transform_to_matrix( $transform ) {
		if ( empty( $transform ) || ! is_array( $transform ) ) {
			return null;
		}
		return array(
			'a' => round( (float) ( $transform['scale_x'] ?? 1 ), 6 ),
			'b' => 0.0,
			'c' => 0.0,
			'd' => round( (float) ( $transform['scale_y'] ?? 1 ), 6 ),
			'e' => round( (float) ( $transform['offset_x'] ?? 0 ), 3 ),
			'f' => round( (float) ( $transform['offset_y_svg'] ?? $transform['offset_y'] ?? 0 ), 3 ),
		);
	}

	/**
	 * Human-readable parity errors for HTTP 422 responses.
	 *
	 * @param array $parity Parity result.
	 * @return string
	 */
	public static function format_errors_for_response( $parity ) {
		$parts = array();
		foreach ( (array) ( $parity['errors'] ?? array() ) as $err ) {
			$label = trim( (string) ( $err['role'] ?? '' ) );
			$id    = (string) ( $err['object_id'] ?? '' );
			if ( $id ) {
				$label = $label ? $label . ' (' . $id . ')' : $id;
			}
			$exp = $err['expected'] ?? array();
			$act = $err['actual'] ?? array();
			$delta = $err['delta'] ?? array();
			$parts[] = sprintf(
				'%1$s: %2$s — expected x=%3$s y=%4$s w=%5$s h=%6$s mm, export x=%7$s y=%8$s w=%9$s h=%10$s mm (Δx=%11$s Δy=%12$s Δw=%13$s Δh=%14$s)',
				$label ?: 'object',
				(string) ( $err['message'] ?? __( 'Placement mismatch.', 'pckz-canonical-engine' ) ),
				$exp['x_mm'] ?? '?',
				$exp['y_mm'] ?? '?',
				$exp['width_mm'] ?? '?',
				$exp['height_mm'] ?? '?',
				$act['x_mm'] ?? '?',
				$act['y_mm'] ?? '?',
				$act['width_mm'] ?? '?',
				$act['height_mm'] ?? '?',
				$delta['x_mm'] ?? '?',
				$delta['y_mm'] ?? '?',
				$delta['width_mm'] ?? '?',
				$delta['height_mm'] ?? '?'
			);
		}
		return implode( ' ', $parts );
	}


	private static function compare_boxes( $expected, $actual ) {
		$diff = array();
		foreach ( array( 'x_mm', 'y_mm', 'width_mm', 'height_mm' ) as $key ) {
			$delta = abs( (float) ( $expected[ $key ] ?? 0 ) - (float) ( $actual[ $key ] ?? 0 ) );
			if ( $delta > self::TOLERANCE_MM ) {
				$diff[ $key ] = round( $delta, 4 );
			}
		}
		return $diff;
	}
}
