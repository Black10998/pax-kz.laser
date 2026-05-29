<?php
/**
 * Production pipeline orchestrator — Fabric canonical scene → validated production → SVG/LBRN2.
 *
 * Architecture:
 *   Fabric preview state (browser)
 *     → canonical scene (mm, Fabric geometry)
 *     → production scene (stable vectors)
 *     → parity + geometry validation
 *     → SVG + LBRN2
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PCKZ_Production_Pipeline
 */
class PCKZ_Production_Pipeline {

	/**
	 * Run full server-side production pipeline.
	 *
	 * @param array $args Export arguments (same as PCKZ_Export_Engine::run).
	 * @return array|WP_Error
	 */
	public static function run( $args ) {
		$scene = PCKZ_Canonical_Scene::from_input( $args['canonical_scene'] ?? array() );
		if ( is_wp_error( $scene ) ) {
			return $scene;
		}

		$validation = PCKZ_Canonical_Scene::validate( $scene );
		if ( 'FAIL' === $validation['status'] ) {
			return new WP_Error(
				'canonical_validation_failed',
				__( 'Canonical scene validation failed.', 'pckz-canonical-engine' ),
				array( 'status' => 'FAIL', 'errors' => $validation['errors'] )
			);
		}

		$geom_validation = PCKZ_Geometry_Validator::validate_canonical_scene( $scene );
		if ( 'FAIL' === $geom_validation['status'] ) {
			return new WP_Error(
				'geometry_validation_failed',
				__( 'Manufacturing geometry validation failed.', 'pckz-canonical-engine' ),
				array( 'status' => 'FAIL', 'errors' => $geom_validation['errors'] )
			);
		}

		$normalized = PCKZ_Fabric_Geometry_Normalizer::from_canonical_scene( $scene );
		$layout     = PCKZ_Canonical_Scene::to_layout( $scene );
		$layout['objects'] = array_map(
			array( 'PCKZ_Fabric_Geometry_Normalizer', 'to_layout_object' ),
			$normalized['objects']
		);
		$layout['export_pipeline'] = $normalized['export_pipeline'];
		$layout['coordinate_system'] = $normalized['coordinate_system'];

		$selections = $scene['selections'] ?? array();
		$config     = $args['config'] ?? array();
		$std_spec   = $args['std_spec'] ?? array();

		if ( empty( $std_spec ) && class_exists( 'PCKZ_Std_Spec' ) ) {
			$std_spec = PCKZ_Std_Spec::for_product( $config );
		}

		self::hydrate_svg_sources( $layout );

		$production_vector_svg = (string) ( $args['production_vector_svg'] ?? '' );
		$text_plate_paths      = (string) ( $args['text_plate_paths'] ?? '' );
		if ( $production_vector_svg ) {
			$layout['production_vector_svg'] = $production_vector_svg;
		}
		if ( $text_plate_paths ) {
			$layout['text_plate_paths'] = $text_plate_paths;
		}

		$package = array(
			'design_id'             => (int) ( $args['design_id'] ?? 0 ),
			'selections'            => $selections,
			'layout'                => $layout,
			'canonical_scene'       => $scene,
			'canvas_json'           => $args['canvas_json'] ?? '',
			'preview_url'           => $args['preview_url'] ?? '',
			'export_url'            => $args['export_url'] ?? '',
			'std_spec'              => $std_spec,
			'validation'            => $validation,
			'geometry_validation'   => $geom_validation,
			'production_vector_svg' => $production_vector_svg,
			'text_plate_paths'      => $text_plate_paths,
		);

		$production_scene = PCKZ_Production_Scene::from_canonical_layout( $package );
		if ( is_wp_error( $production_scene ) ) {
			return $production_scene;
		}

		$scene_validation = PCKZ_Geometry_Validator::validate_production_scene( $production_scene );
		if ( 'FAIL' === $scene_validation['status'] ) {
			return new WP_Error(
				'production_scene_validation_failed',
				__( 'Production scene validation failed.', 'pckz-canonical-engine' ),
				array( 'status' => 'FAIL', 'errors' => $scene_validation['errors'] )
			);
		}

		$parity = PCKZ_Export_Parity::validate( $scene, $production_scene );
		if ( 'FAIL' === $parity['status'] ) {
			$detail = PCKZ_Export_Parity::format_errors_for_response( $parity );
			return new WP_Error(
				'parity_validation_failed',
				$detail
					? sprintf(
						/* translators: %s: per-object parity detail */
						__( 'Export validation failed: %s', 'pckz-canonical-engine' ),
						$detail
					)
					: __( 'Export parity validation failed.', 'pckz-canonical-engine' ),
				array(
					'status'     => 'FAIL',
					'errors'     => $parity['errors'],
					'parity'     => $parity,
					'http_status'=> 422,
				)
			);
		}

		$lightburn = array();
		if ( class_exists( 'PCKZ_Lightburn_Export' ) ) {
			$lightburn = PCKZ_Lightburn_Export::build_package(
				array(
					'layout'      => $layout,
					'selections'  => $selections,
					'config'      => $config,
					'canvas_json' => $package['canvas_json'],
					'std_spec'    => $std_spec,
					'preview_url' => $package['preview_url'],
					'export_url'  => $package['export_url'],
					'design_id'   => $package['design_id'],
				)
			);
		}

		return PCKZ_Production::build_package_from_canonical(
			array(
				'canonical_scene'       => $scene,
				'layout'                => $layout,
				'selections'            => $selections,
				'config'                => $config,
				'canvas_json'           => $package['canvas_json'],
				'preview_url'           => $package['preview_url'],
				'export_url'            => $package['export_url'],
				'std_spec'              => $std_spec,
				'design_id'             => $package['design_id'],
				'lightburn_package'     => $lightburn,
				'production_scene'      => $production_scene,
				'production_vector_svg' => $production_vector_svg,
				'text_plate_paths'      => $text_plate_paths,
				'validation'            => $validation,
				'geometry_validation'   => $geom_validation,
				'scene_validation'      => $scene_validation,
				'parity'                => $parity,
				'export_pipeline'       => $normalized['export_pipeline'],
			)
		);
	}

	/**
	 * @param array $layout Layout (by reference).
	 */
	private static function hydrate_svg_sources( &$layout ) {
		if ( empty( $layout['objects'] ) || ! is_array( $layout['objects'] ) ) {
			return;
		}
		$selections = $layout['selections'] ?? array();
		foreach ( $layout['objects'] as &$obj ) {
			if ( ! empty( $obj['svg_source'] ) ) {
				continue;
			}
			if ( ! class_exists( 'PCKZ_Production_Geometry' ) ) {
				continue;
			}
			$body = PCKZ_Production_Geometry::resolve_svg_body_for_object( $obj, $selections );
			if ( '' !== trim( $body ) ) {
				$obj['svg_source'] = $body;
			}
		}
		unset( $obj );
	}
}
