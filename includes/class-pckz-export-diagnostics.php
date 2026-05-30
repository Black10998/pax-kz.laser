<?php
/**
 * Export payload diagnostics for LBRN2 text vector failures.
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PCKZ_Export_Diagnostics
 */
class PCKZ_Export_Diagnostics {

	/**
	 * Decode text_plate_paths from request (plain or base64).
	 *
	 * @param string $plain Plain POST field.
	 * @param string $b64   Base64 POST field.
	 * @return string
	 */
	public static function decode_text_plate_paths_from_request( $plain, $b64 = '' ) {
		$b64 = is_string( $b64 ) ? trim( $b64 ) : '';
		if ( '' !== $b64 ) {
			$decoded = base64_decode( $b64, true );
			if ( is_string( $decoded ) && '' !== trim( $decoded ) ) {
				return class_exists( 'PCKZ_Production_Scene' )
					? PCKZ_Production_Scene::normalize_text_plate_paths_fragment( $decoded )
					: $decoded;
			}
		}
		$plain = is_string( $plain ) ? trim( $plain ) : '';
		return class_exists( 'PCKZ_Production_Scene' )
			? PCKZ_Production_Scene::normalize_text_plate_paths_fragment( $plain )
			: $plain;
	}

	/**
	 * Canvas mm dimensions from export_validate POST (falls back to plate calibration).
	 *
	 * @return array{width:float,height:float}
	 */
	public static function canvas_mm_from_request() {
		$w = isset( $_POST['canvas_width_mm'] ) ? (float) wp_unslash( $_POST['canvas_width_mm'] ) : 0.0;
		$h = isset( $_POST['canvas_height_mm'] ) ? (float) wp_unslash( $_POST['canvas_height_mm'] ) : 0.0;
		if ( $w > 0 && $h > 0 ) {
			return array(
				'width'  => $w,
				'height' => $h,
			);
		}
		return array(
			'width'  => class_exists( 'PCKZ_Plate_Calibration' ) ? (float) PCKZ_Plate_Calibration::TOTAL_WIDTH_MM : 529.1,
			'height' => class_exists( 'PCKZ_Plate_Calibration' ) ? (float) PCKZ_Plate_Calibration::PLATE_HEIGHT_MM : 116.0,
		);
	}

	/**
	 * Summarize text fragment + Fabric SVG for error messages.
	 *
	 * @param string $fragment          text_plate_paths HTML.
	 * @param string $production_vector_svg Fabric SVG.
	 * @param string $font_family       Font family label.
	 * @param string $font_url          Export font URL.
	 * @return array
	 */
	public static function summarize_payload( $fragment, $production_vector_svg, $font_family = '', $font_url = '' ) {
		$fragment = class_exists( 'PCKZ_Production_Scene' )
			? PCKZ_Production_Scene::normalize_text_plate_paths_fragment( $fragment )
			: trim( (string) $fragment );
		$svg      = trim( (string) $production_vector_svg );
		$path_ds  = array();
		if ( class_exists( 'PCKZ_Production_Scene' ) ) {
			foreach ( PCKZ_Production_Scene::extract_path_entries_from_text_fragment( $fragment ) as $entry ) {
				if ( ! empty( $entry['d'] ) ) {
					$path_ds[] = (string) $entry['d'];
				}
			}
		} elseif ( '' !== $fragment && preg_match_all( '/\bd=(["\'])(.*?)\1/s', $fragment, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $row ) {
				$path_ds[] = html_entity_decode( (string) ( $row[2] ?? '' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			}
		}
		return array(
			'pckz_version'              => defined( 'PCKZCE_VERSION' ) ? PCKZCE_VERSION : '',
			'pckz_build'                => defined( 'PCKZCE_BUILD' ) ? PCKZCE_BUILD : '',
			'font_family'               => (string) $font_family,
			'font_url'                  => (string) $font_url,
			'has_production_vector_svg' => '' !== $svg,
			'production_vector_svg_len' => strlen( $svg ),
			'has_text_plate_paths'      => '' !== $fragment,
			'text_plate_paths_len'      => strlen( $fragment ),
			'text_path_d_count'         => count( $path_ds ),
			'text_path_d_max_len'       => $path_ds ? max( array_map( 'strlen', $path_ds ) ) : 0,
		);
	}

	/**
	 * Probe in-memory LBRN2 XML generation from a production package (no file persist).
	 *
	 * @param array $package Production package from export pipeline.
	 * @return array
	 */
	public static function probe_lbrn2_generation( $package ) {
		$package = is_array( $package ) ? $package : array();
		$xml     = '';
		$error   = '';
		$built   = false;

		if ( class_exists( 'PCKZ_Production_Lbrn2' ) ) {
			$result = PCKZ_Production_Lbrn2::build_from_package( $package );
			if ( is_wp_error( $result ) ) {
				$error = $result->get_error_message();
			} else {
				$xml   = (string) $result;
				$built = '' !== trim( $xml );
			}
		}

		$url = (string) ( $package['production_lbrn2_url'] ?? '' );
		$text_shapes = ( '' !== $xml ) ? preg_match_all( '/<!-- (pckz-)?text-engrave/', $xml ) : 0;

		return array(
			'lbrn2_generated'            => $built,
			'lbrn2_exists'               => $built && false !== stripos( $xml, '<LightBurnProject' ),
			'lbrn2_length'               => strlen( $xml ),
			'lbrn2_text_shape_count'     => (int) $text_shapes,
			'lbrn2_attached_to_request'  => '' !== $url,
			'lbrn2_url'                  => $url,
			'lbrn2_has_production_scene' => ! empty( $package['production_scene'] ),
			'lbrn2_build_error'          => $error,
			'lbrn2_persist_error'        => (string) ( $package['production_lbrn2_error'] ?? '' ),
		);
	}

	/**
	 * Probe merge/parse of text_plate_paths without full export.
	 *
	 * @param string $fragment Fragment.
	 * @param float  $w        Canvas W.
	 * @param float  $h        Canvas H.
	 * @param array  $layout   Layout.
	 * @return array
	 */
	public static function probe_text_fragment_parse( $fragment, $w, $h, $layout = array() ) {
		$raw_len  = strlen( (string) $fragment );
		$fragment = class_exists( 'PCKZ_Production_Scene' )
			? PCKZ_Production_Scene::normalize_text_plate_paths_fragment( $fragment )
			: trim( (string) $fragment );
		if ( '' === $fragment ) {
			return array(
				'parsed_layer_count' => 0,
				'parsed_vert_count'  => 0,
				'parser'             => 'empty',
				'raw_fragment_len'   => $raw_len,
			);
		}

		$path_entries = class_exists( 'PCKZ_Production_Scene' )
			? PCKZ_Production_Scene::extract_path_entries_from_text_fragment( $fragment )
			: array();
		$raw_path_verts = 0;
		$path_d_max     = 0;
		foreach ( $path_entries as $entry ) {
			$d = (string) ( $entry['d'] ?? '' );
			if ( '' === $d ) {
				continue;
			}
			$path_d_max = max( $path_d_max, strlen( $d ) );
			$raw        = PCKZ_Production_Geometry::parse_svg_path_to_verts( $d );
			$raw_path_verts += count( $raw['verts'] ?? array() );
		}
		$meta    = sprintf(
			'<metadata id="pckz-export-meta"><pckz:export format="text-plate-paths" coordinate-system="%s"/></metadata>',
			esc_attr(
				class_exists( 'PCKZ_Production_Scene' )
					? PCKZ_Production_Scene::text_fragment_coordinate_system( $fragment )
					: 'svg-top-left-mm'
			)
		);
		$wrapped = sprintf(
			'<?xml version="1.0" encoding="UTF-8"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %s %s">%s%s</svg>',
			PCKZ_Production_Geometry::fmt( $w ),
			PCKZ_Production_Geometry::fmt( $h ),
			$meta,
			$fragment
		);
		$dom_ok  = true;
		$dom     = new DOMDocument();
		$prev    = libxml_use_internal_errors( true );
		if ( ! $dom->loadXML( $wrapped ) ) {
			$dom_ok = false;
		}
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );

		$parsed = PCKZ_Production_Scene::parse_master_svg( $wrapped, $w, $h, $layout );
		$parser = 'parse_master_svg';
		$verts  = 0;
		foreach ( (array) ( $parsed['layers'] ?? array() ) as $layer ) {
			if ( 'path' === ( $layer['type'] ?? '' ) && ! empty( $layer['verts'] ) ) {
				$verts += count( $layer['verts'] );
			}
		}
		if ( 0 === $verts ) {
			$parsed = PCKZ_Production_Scene::parse_text_plate_paths_fragment( $fragment, $w, $h, $layout );
			$parser = 'parse_text_plate_paths_fragment';
			foreach ( (array) ( $parsed['layers'] ?? array() ) as $layer ) {
				if ( 'path' === ( $layer['type'] ?? '' ) && ! empty( $layer['verts'] ) ) {
					$verts += count( $layer['verts'] );
				}
			}
		}

		return array(
			'dom_ok'               => $dom_ok,
			'parsed_layer_count'   => count( $parsed['layers'] ?? array() ),
			'parsed_vert_count'    => $verts,
			'parser'               => $parser,
			'lbrn2_parse_ok'       => $verts >= 2,
			'raw_fragment_len'     => $raw_len,
			'normalized_len'       => strlen( $fragment ),
			'path_entry_count'     => count( $path_entries ),
			'path_d_max_len'       => $path_d_max,
			'raw_path_vert_count'  => $raw_path_verts,
			'fragment_head'        => substr( $fragment, 0, 80 ),
		);
	}

	/**
	 * Build user-facing debug suffix.
	 *
	 * @param array $summary Payload summary.
	 * @param array $probe   Parse probe.
	 * @return string
	 */
	public static function format_debug_suffix( $summary, $probe = array() ) {
		$parts = array(
			'[pckz=' . ( $summary['pckz_version'] ?? '?' ) . ']',
			'font=' . ( $summary['font_family'] ?? '' ),
			'font_url=' . ( $summary['font_url'] ?? '' ),
			'production_vector_svg=' . ( ! empty( $summary['has_production_vector_svg'] ) ? 'yes(' . (int) ( $summary['production_vector_svg_len'] ?? 0 ) . ')' : 'no' ),
			'text_plate_paths=' . ( ! empty( $summary['has_text_plate_paths'] ) ? 'yes(' . (int) ( $summary['text_plate_paths_len'] ?? 0 ) . ')' : 'no' ),
		);
		if ( ! empty( $probe ) ) {
			$parts[] = 'lbrn2_parse=' . ( ! empty( $probe['lbrn2_parse_ok'] ) ? 'ok' : 'fail' );
			$parts[] = 'parser=' . ( $probe['parser'] ?? '' );
			$parts[] = 'verts=' . (int) ( $probe['parsed_vert_count'] ?? 0 );
			if ( isset( $probe['path_entry_count'] ) ) {
				$parts[] = 'path_entries=' . (int) $probe['path_entry_count'];
			}
			if ( isset( $probe['raw_path_vert_count'] ) ) {
				$parts[] = 'raw_path_verts=' . (int) $probe['raw_path_vert_count'];
			}
			if ( isset( $probe['path_d_max_len'] ) && (int) $probe['path_d_max_len'] > 0 ) {
				$parts[] = 'path_d_max=' . (int) $probe['path_d_max_len'];
			}
			if ( isset( $probe['dom_ok'] ) && ! $probe['dom_ok'] ) {
				$parts[] = 'xml=invalid';
			}
			if ( isset( $probe['lbrn2_generated'] ) ) {
				$parts[] = 'lbrn2_generated=' . ( ! empty( $probe['lbrn2_generated'] ) ? 'yes' : 'no' );
			}
			if ( isset( $probe['lbrn2_exists'] ) ) {
				$parts[] = 'lbrn2_exists=' . ( ! empty( $probe['lbrn2_exists'] ) ? 'yes' : 'no' );
			}
			if ( isset( $probe['lbrn2_length'] ) ) {
				$parts[] = 'lbrn2_length=' . (int) $probe['lbrn2_length'];
			}
			if ( isset( $probe['lbrn2_text_shape_count'] ) ) {
				$parts[] = 'lbrn2_text_shapes=' . (int) $probe['lbrn2_text_shape_count'];
			}
			if ( isset( $probe['lbrn2_attached_to_request'] ) ) {
				$parts[] = 'lbrn2_attached=' . ( ! empty( $probe['lbrn2_attached_to_request'] ) ? 'yes' : 'no' );
			}
			if ( ! empty( $probe['lbrn2_build_error'] ) ) {
				$parts[] = 'lbrn2_err=' . substr( (string) $probe['lbrn2_build_error'], 0, 80 );
			}
		}
		return implode( ' ', $parts );
	}
}
