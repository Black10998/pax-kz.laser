<?php
/**
 * Server-side export engine — delegates to production pipeline.
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PCKZ_Export_Engine
 */
class PCKZ_Export_Engine {

	/**
	 * Run full export pipeline from canonical scene JSON.
	 *
	 * @param array $args {
	 *     @type array|string $canonical_scene Canonical scene.
	 *     @type array        $config          Product config.
	 *     @type string       $canvas_json     Optional canvas snapshot.
	 *     @type string       $preview_url     Preview URL.
	 *     @type string       $export_url      Export URL.
	 *     @type int          $design_id       Design ID.
	 *     @type array        $std_spec        STD spec.
	 *     @type string       $production_vector_svg Browser Fabric SVG.
	 *     @type string       $text_plate_paths Text vector paths.
	 * }
	 * @return array|WP_Error
	 */
	public static function run( $args ) {
		if ( class_exists( 'PCKZ_Production_Pipeline' ) ) {
			return PCKZ_Production_Pipeline::run( $args );
		}

		return new WP_Error(
			'pipeline_unavailable',
			__( 'Production pipeline is not available.', 'pckz-canonical-engine' ),
			array( 'status' => 500 )
		);
	}
}
