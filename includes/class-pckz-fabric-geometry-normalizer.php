<?php
/**
 * Server-side Fabric geometry records from canonical scene (no manual layout rebuild).
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PCKZ_Fabric_Geometry_Normalizer
 */
class PCKZ_Fabric_Geometry_Normalizer {

	/**
	 * Build normalized manufacturing objects from canonical scene (Fabric-sourced).
	 *
	 * @param array $canonical Canonical scene.
	 * @return array{objects:array,plate:array,coordinate_system:string}
	 */
	public static function from_canonical_scene( $canonical ) {
		$objects = array();
		$plate   = array(
			'width_mm'  => (float) ( $canonical['plate']['width_mm'] ?? ( class_exists( 'PCKZ_Plate_Calibration' ) ? PCKZ_Plate_Calibration::TOTAL_WIDTH_MM : 529.1 ) ),
			'height_mm' => (float) ( $canonical['plate']['height_mm'] ?? ( class_exists( 'PCKZ_Plate_Calibration' ) ? PCKZ_Plate_Calibration::PLATE_HEIGHT_MM : 116 ) ),
		);

		foreach ( (array) ( $canonical['objects'] ?? array() ) as $obj ) {
			$bbox = $obj['bbox'] ?? array();
			if ( ! PCKZ_Production_Geometry::bbox_from_mm( $bbox ) ) {
				continue;
			}

			$normalized = array(
				'id'                 => $obj['id'] ?? '',
				'role'               => $obj['role'] ?? 'object',
				'mm'                 => $bbox,
				'bbox'               => $bbox,
				'scale'              => $obj['scale'] ?? array( 'x' => 1, 'y' => 1 ),
				'rotation_deg'       => (float) ( $obj['rotation_deg'] ?? 0 ),
				'z_order'            => (int) ( $obj['z_order'] ?? 15 ),
				'fabric_geometry'    => $obj['fabric_geometry'] ?? null,
				'fabric'             => $obj['fabric'] ?? null,
				'text'               => $obj['text'] ?? null,
				'font_family'        => $obj['font_family'] ?? null,
				'text_path_geometry' => $obj['text_path_geometry'] ?? null,
				'svg_ref'            => $obj['svg_ref'] ?? null,
				'color'              => $obj['color'] ?? '',
			);

			if ( ! empty( $obj['transforms'] ) ) {
				$normalized['transforms'] = $obj['transforms'];
			}

			$objects[] = $normalized;
		}

		usort(
			$objects,
			function ( $a, $b ) {
				return ( $a['z_order'] ?? 15 ) - ( $b['z_order'] ?? 15 );
			}
		);

		return array(
			'objects'            => $objects,
			'plate'              => $plate,
			'coordinate_system'  => $canonical['coordinate_system'] ?? 'lightburn-mm-bottom-left',
			'export_pipeline'    => $canonical['export_pipeline'] ?? 'fabric-production-pipeline-v1',
		);
	}

	/**
	 * Convert normalized object to layout object for production scene builders.
	 *
	 * @param array $obj Normalized object.
	 * @return array
	 */
	public static function to_layout_object( $obj ) {
		return array_merge(
			array(
				'role' => $obj['role'] ?? 'object',
				'mm'   => $obj['mm'] ?? $obj['bbox'] ?? array(),
			),
			$obj
		);
	}
}
