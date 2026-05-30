<?php
/**
 * Unified production vector scene — single WYSIWYG source for all exporters.
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PCKZ_Production_Scene
 */
class PCKZ_Production_Scene {

	const COORD_SVG_TOP_LEFT         = 'svg-top-left';
	const COORD_LIGHTBURN_BOTTOM_LEFT = 'lightburn-mm-bottom-left';

	/**
	 * Build production scene from canonical layout (server-side only).
	 *
	 * @param array $package Package with canonical_scene + layout.
	 * @return array|WP_Error
	 */
	public static function from_canonical_layout( $package ) {
		$ctx = PCKZ_Production_Geometry::normalize_package( $package, true );

		if ( $ctx['canvas_w'] <= 0 || $ctx['canvas_h'] <= 0 ) {
			return new WP_Error( 'invalid_canvas', __( 'Invalid canvas for production export.', 'pckz-canonical-engine' ) );
		}

		$layout      = $ctx['layout'];
		$browser_svg = self::extract_browser_production_svg( $package, $layout );
		$w           = (float) $ctx['canvas_w'];
		$h           = (float) $ctx['canvas_h'];

		if ( '' === trim( $browser_svg ) ) {
			return new WP_Error(
				'missing_fabric_export',
				__( 'Fabric production SVG missing. Re-save after preview fully loads.', 'pckz-canonical-engine' )
			);
		}
		$scene = self::parse_master_svg( $browser_svg, $w, $h, $layout );
		$svg   = $browser_svg;

		if ( ! $scene ) {
			return new WP_Error(
				'empty_export',
				__( 'No engraving paths found in canonical scene export.', 'pckz-canonical-engine' )
			);
		}

		// Merge OpenType text paths before layer count checks (Fabric SVG may omit <text> paths).
		self::merge_text_plate_paths_from_layout( $scene, $ctx, $package );
		self::consolidate_scene_text_layers( $scene );

		if ( empty( $scene['layers'] ) ) {
			return new WP_Error(
				'empty_export',
				__( 'No engraving paths found in canonical scene export.', 'pckz-canonical-engine' )
			);
		}

		$text_err = self::require_vector_text_layers( $scene, $ctx, $package );
		if ( is_wp_error( $text_err ) ) {
			return $text_err;
		}
		self::attach_canonical_placement_bboxes( $scene, $ctx );
		self::attach_layer_bbox_mm( $scene, $ctx );

		$scene['master_svg'] = $svg;
		$scene['selections'] = $ctx['selections'];
		$scene['design_id']  = $ctx['design_id'];
		$scene['export_source'] = ( '' !== trim( $browser_svg ) ) ? 'browser-wysiwyg' : 'server-synthesized';
		return $scene;
	}

	/**
	 * Build scene from package (prefers browser production_vector_svg).
	 *
	 * @param array $package Production package.
	 * @return array|WP_Error
	 */
	/**
	 * Resolve WYSIWYG master SVG from package (browser Fabric export only).
	 *
	 * @param array $package Package.
	 * @return string
	 */
	public static function get_master_svg( $package ) {
		$layout = $package['layout'] ?? $package['lightburn_ready'] ?? array();
		return self::resolve_master_svg( $package, $layout );
	}

	/**
	 * Parse master SVG for LightBurn (.lbrn2) — no layout reconstruction.
	 *
	 * @param array $package Production package.
	 * @return array|WP_Error Scene.
	 */
	public static function from_package( $package ) {
		if ( ! empty( $package['canonical_scene'] ) || ! empty( $package['layout']['canonical_scene'] ) ) {
			return self::from_canonical_layout( $package );
		}

		$ctx = PCKZ_Production_Geometry::normalize_package( $package );

		if ( $ctx['canvas_w'] <= 0 || $ctx['canvas_h'] <= 0 ) {
			return new WP_Error( 'invalid_canvas', __( 'Invalid canvas for production export.', 'pckz-canonical-engine' ) );
		}

		$layout = $ctx['layout'];
		$svg    = self::resolve_master_svg( $package, $layout );

		if ( '' === trim( $svg ) ) {
			return new WP_Error(
				'empty_export',
				__( 'No production geometry. Re-save the design after the preview has fully loaded.', 'pckz-canonical-engine' )
			);
		}

		$scene = self::parse_master_svg( $svg, $ctx['canvas_w'], $ctx['canvas_h'], $layout );
		if ( ( empty( $scene['layers'] ) ) && self::layout_has_exportable_objects( $layout ) ) {
			$fallback = self::build_scene_from_layout( $ctx, $svg );
			if ( $fallback && ! empty( $fallback['layers'] ) ) {
				$scene = $fallback;
			}
		}
		self::merge_text_plate_paths_from_layout( $scene, $ctx );
		self::ensure_text_layers( $scene, $ctx );
		self::consolidate_scene_text_layers( $scene );

		if ( empty( $scene['layers'] ) ) {
			return new WP_Error(
				'empty_export',
				__( 'No engraving paths found in production artwork. Re-save the design after preview loads.', 'pckz-canonical-engine' )
			);
		}

		$scene['master_svg'] = $svg;
		$scene['selections'] = $ctx['selections'];
		$scene['design_id']  = $ctx['design_id'];
		return $scene;
	}

	/**
	 * Authoritative scene: layout mm placement + browser-knocked line paths.
	 *
	 * @param array  $ctx Normalized package context.
	 * @param string $master_svg Browser production SVG.
	 * @return array|null
	 */
	public static function build_scene_from_layout( $ctx, $master_svg = '' ) {
		$w      = (float) $ctx['canvas_w'];
		$h      = (float) $ctx['canvas_h'];
		$origin = $ctx['origin'];
		$layout = $ctx['layout'];
		$layers = array();

		$line_layers = self::parse_knocked_lines_from_master_svg( $master_svg, $w, $h, $layout );
		if ( ! empty( $line_layers ) ) {
			foreach ( PCKZ_Production_Geometry::sort_objects( $ctx['objects'] ) as $obj ) {
				$obj  = PCKZ_Production_Geometry::normalize_layout_object( $obj );
				$role = $obj['role'] ?? '';
				if ( ! in_array( $role, array( 'lines', 'line-overlay', 'strip-lines', 'strip-line' ), true ) ) {
					continue;
				}
				$box = self::placement_box_mm( $obj, $ctx );
				if ( ! $box ) {
					break;
				}
				$meta = self::layer_export_meta( $obj, $box );
				foreach ( $line_layers as $idx => $layer ) {
					$line_layers[ $idx ] = array_merge( $meta, $layer );
				}
				break;
			}
			$layers = array_merge( $layers, $line_layers );
		}

		foreach ( PCKZ_Production_Geometry::sort_objects( $ctx['objects'] ) as $obj ) {
			$obj  = PCKZ_Production_Geometry::normalize_layout_object( $obj );
			$role = $obj['role'] ?? '';

			if ( in_array( $role, array( 'lines', 'line-overlay', 'strip-lines', 'strip-line' ), true ) ) {
				if ( ! empty( $line_layers ) ) {
					continue;
				}
				$layers = array_merge( $layers, self::layout_vector_layers( $obj, $ctx ) );
				continue;
			}

			if ( in_array( $role, array( 'text', 'main-text' ), true ) ) {
				$text_layer = self::layout_text_layer( $obj, $ctx );
				if ( $text_layer ) {
					$layers[] = $text_layer;
				}
				continue;
			}

			if ( in_array( $role, array( 'icon-left', 'icon-right', 'icon-bg-left', 'icon-bg-right' ), true ) ) {
				$layers = array_merge( $layers, self::layout_vector_layers( $obj, $ctx ) );
			}
		}

		if ( empty( $layers ) ) {
			return null;
		}

		return array(
			'version'   => 1,
			'canvas_w'  => $w,
			'canvas_h'  => $h,
			'origin'    => 'bottom-left',
			'safe_zone' => $layout['safe_zone_mm'] ?? null,
			'strip_zone'=> $layout['strip_zone_mm'] ?? null,
			'layers'    => $layers,
		);
	}

	/**
	 * Parse knocked decorative lines from browser export (pckz-lines group).
	 *
	 * @param string $master_svg Full production SVG.
	 * @param float  $canvas_w   Plate W mm.
	 * @param float  $canvas_h   Plate H mm.
	 * @param array  $layout     Layout metadata.
	 * @return array<int,array>
	 */
	public static function parse_knocked_lines_from_master_svg( $master_svg, $canvas_w, $canvas_h, $layout = array() ) {
		$fragment = self::extract_svg_group_inner( $master_svg, 'pckz-lines' );
		if ( '' === $fragment ) {
			return array();
		}
		$wrapped = sprintf(
			'<?xml version="1.0" encoding="UTF-8"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %s %s"><g id="pckz-lines">%s</g></svg>',
			PCKZ_Production_Geometry::fmt( $canvas_w ),
			PCKZ_Production_Geometry::fmt( $canvas_h ),
			$fragment
		);
		$parsed = self::parse_master_svg( $wrapped, $canvas_w, $canvas_h, $layout );
		$out    = array();
		foreach ( $parsed['layers'] ?? array() as $layer ) {
			if ( empty( $layer['role'] ) || 'engrave' === $layer['role'] ) {
				$layer['role'] = 'lines';
			}
			$out[] = $layer;
		}
		return $out;
	}

	/**
	 * Guarantee text layers exist (LightBurn Text + parsed paths).
	 *
	 * @param array $scene Scene (by reference).
	 * @param array $ctx   Context.
	 */
	public static function ensure_text_layers( &$scene, $ctx ) {
		if ( empty( $scene['layers'] ) ) {
			$scene['layers'] = array();
		}
		$has_text = false;
		foreach ( $scene['layers'] as $layer ) {
			$role = $layer['role'] ?? '';
			$lid  = $layer['layer_id'] ?? '';
			if ( 'text' === ( $layer['type'] ?? '' ) ) {
				$has_text = true;
				break;
			}
			if ( 'path' === ( $layer['type'] ?? '' )
				&& in_array( $role, array( 'pckz-text-paths', 'pckz-text-engrave', 'text-paths', 'text-engrave', 'main-text' ), true ) ) {
				$has_text = true;
				break;
			}
			if ( in_array( $lid, array( 'pckz-text', 'pckz-text-paths', 'pckz-text-engrave' ), true ) ) {
				$has_text = true;
				break;
			}
		}
		if ( $has_text ) {
			return;
		}
		foreach ( $ctx['objects'] as $obj ) {
			$obj = PCKZ_Production_Geometry::normalize_layout_object( $obj );
			if ( ! in_array( $obj['role'] ?? '', array( 'text', 'main-text' ), true ) ) {
				continue;
			}
			$text_layer = self::layout_text_layer( $obj, $ctx );
			if ( $text_layer ) {
				$scene['layers'][] = $text_layer;
			}
		}
	}

	/**
	 * Merge browser text vector paths (layout.text_plate_paths) for reliable LBRN2 engraving.
	 *
	 * @param array $scene Scene (by reference).
	 * @param array $ctx   Context.
	 */
	/**
	 * Whether the scene already contains vector text engrave paths (not SVG <text>).
	 *
	 * @param array $scene Scene.
	 * @return bool
	 */
	private static function scene_has_vector_text_engrave( $scene ) {
		foreach ( (array) ( $scene['layers'] ?? array() ) as $layer ) {
			if ( ! self::layer_is_authoritative_text_engrave_path( $layer ) ) {
				continue;
			}
			return true;
		}
		return false;
	}

	/**
	 * Path layer counts as production vector text (must match require_vector_text_layers).
	 *
	 * @param array $layer Scene layer.
	 * @return bool
	 */
	private static function layer_is_authoritative_text_engrave_path( $layer ) {
		if ( 'path' !== ( $layer['type'] ?? '' ) || empty( $layer['verts'] ) ) {
			return false;
		}
		$role = (string) ( $layer['role'] ?? '' );
		$lid  = (string) ( $layer['layer_id'] ?? '' );
		if ( in_array( $role, array( 'text-engrave', 'pckz-text-engrave' ), true ) ) {
			return true;
		}
		return in_array( $lid, array( 'pckz-text-engrave' ), true )
			|| 0 === strpos( $lid, 'pckz-text-engrave-' );
	}

	/**
	 * Parse text_plate_paths SVG fragment when full document load yields no paths.
	 *
	 * @param string $fragment Inner SVG markup from browser.
	 * @param float  $w        Canvas width mm.
	 * @param float  $h        Canvas height mm.
	 * @param array  $layout   Layout metadata.
	 * @return array Scene slice with layers.
	 */
	/**
	 * Normalize browser text_plate_paths before regex/DOM parse (entities, slashes, UTF-8).
	 *
	 * @param string $fragment Raw fragment.
	 * @return string
	 */
	public static function normalize_text_plate_paths_fragment( $fragment ) {
		$fragment = trim( (string) $fragment );
		if ( '' === $fragment || 'b64' === $fragment ) {
			return '';
		}
		if ( function_exists( 'wp_unslash' ) ) {
			$fragment = wp_unslash( $fragment );
		}
		$fragment = stripslashes( $fragment );
		$fragment = html_entity_decode( $fragment, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		if ( function_exists( 'mb_convert_encoding' ) ) {
			$fragment = mb_convert_encoding( $fragment, 'UTF-8', 'UTF-8' );
		}
		$fragment = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $fragment );
		return trim( $fragment );
	}

	/**
	 * Extract path d + ancestor transforms from a text_plate_paths fragment.
	 *
	 * @param string $fragment Normalized fragment.
	 * @return array<int,array{d:string,matrix:array,layer_id:string,fill:string}>
	 */
	public static function extract_path_entries_from_text_fragment( $fragment ) {
		$fragment = self::normalize_text_plate_paths_fragment( $fragment );
		if ( '' === $fragment ) {
			return array();
		}

		$entries = array();
		$wrapped = '<?xml version="1.0" encoding="UTF-8"?><svg xmlns="http://www.w3.org/2000/svg">' . $fragment . '</svg>';
		$dom     = new DOMDocument();
		$prev    = libxml_use_internal_errors( true );
		if ( $dom->loadXML( $wrapped ) ) {
			foreach ( $dom->getElementsByTagName( 'path' ) as $path_el ) {
				if ( ! $path_el instanceof DOMElement ) {
					continue;
				}
				$d = trim( (string) $path_el->getAttribute( 'd' ) );
				if ( '' === $d ) {
					continue;
				}
				$matrix = array( 1, 0, 0, 1, 0, 0 );
				$node   = $path_el->parentNode;
				while ( $node instanceof DOMElement ) {
					if ( $node->hasAttribute( 'transform' ) ) {
						$matrix = self::multiply_matrix(
							self::parse_transform_attr( $node->getAttribute( 'transform' ) ),
							$matrix
						);
					}
					if ( 'svg' === strtolower( $node->nodeName ) ) {
						break;
					}
					$node = $node->parentNode;
				}
				$layer_id = 'pckz-text-engrave';
				$gid      = $path_el->getAttribute( 'id' );
				if ( $gid ) {
					$layer_id = $gid;
				} elseif ( $path_el->parentNode instanceof DOMElement && $path_el->parentNode->hasAttribute( 'id' ) ) {
					$layer_id = (string) $path_el->parentNode->getAttribute( 'id' );
				}
				$fill = '#ffffff';
				if ( $path_el->hasAttribute( 'fill' ) ) {
					$fill = PCKZ_Production_Geometry::resolve_hex( $path_el->getAttribute( 'fill' ) );
				} elseif ( $path_el->parentNode instanceof DOMElement && $path_el->parentNode->hasAttribute( 'fill' ) ) {
					$fill = PCKZ_Production_Geometry::resolve_hex( $path_el->parentNode->getAttribute( 'fill' ) );
				}
				$entries[] = array(
					'd'        => $d,
					'matrix'   => $matrix,
					'layer_id' => $layer_id,
					'fill'     => $fill,
				);
			}
		}
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );

		if ( ! empty( $entries ) ) {
			return $entries;
		}

		// Regex fallback (no /u — invalid UTF-8 breaks PCRE unicode mode).
		if ( ! preg_match_all( '/<path\b[^>]*\bd=(["\'])(.*?)\1/is', $fragment, $paths, PREG_SET_ORDER ) ) {
			return array();
		}
		$gid = 'pckz-text-engrave';
		if ( preg_match( '/<g\b[^>]*\bid=(["\'])([^"\']+)\1/is', $fragment, $gm ) ) {
			$gid = (string) $gm[2];
		}
		foreach ( $paths as $path_match ) {
			$d = html_entity_decode( (string) ( $path_match[2] ?? '' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			if ( '' === trim( $d ) ) {
				continue;
			}
			$entries[] = array(
				'd'        => $d,
				'matrix'   => array( 1, 0, 0, 1, 0, 0 ),
				'layer_id' => $gid,
				'fill'     => '#ffffff',
			);
		}
		return $entries;
	}

	public static function parse_text_plate_paths_fragment( $fragment, $w, $h, $layout = array() ) {
		$layers = array();
		$coord  = self::COORD_SVG_TOP_LEFT;
		$entries = self::extract_path_entries_from_text_fragment( $fragment );
		foreach ( $entries as $entry ) {
			$d = (string) ( $entry['d'] ?? '' );
			if ( '' === trim( $d ) ) {
				continue;
			}
			$state = array(
				'role'     => 'text-engrave',
				'layer_id' => (string) ( $entry['layer_id'] ?? 'pckz-text-engrave' ),
				'fill'     => (string) ( $entry['fill'] ?? '#ffffff' ),
			);
			$matrix = (array) ( $entry['matrix'] ?? array( 1, 0, 0, 1, 0, 0 ) );
			self::add_path_layer( $d, $matrix, $h, $state, $layers, $coord );
		}
		unset( $layout );
		return array(
			'version'   => 1,
			'canvas_w'  => $w,
			'canvas_h'  => $h,
			'origin'    => 'bottom-left',
			'layers'    => $layers,
		);
	}


	public static function merge_text_plate_paths_from_layout( &$scene, $ctx, $package = array() ) {
		$fragment = trim( (string) ( $ctx['layout']['text_plate_paths'] ?? '' ) );
		if ( '' === $fragment && ! empty( $package['text_plate_paths'] ) ) {
			$fragment = trim( (string) $package['text_plate_paths'] );
		}
		$fragment = self::normalize_text_plate_paths_fragment( $fragment );
		if ( '' === $fragment ) {
			return;
		}
		if ( empty( $scene['layers'] ) ) {
			$scene['layers'] = array();
		}
		$w = (float) $ctx['canvas_w'];
		$h = (float) $ctx['canvas_h'];
		$meta    = '<metadata id="pckz-export-meta"><pckz:export xmlns:pckz="https://pckz-canonical-engine.local/export" format="text-plate-paths" coordinate-system="svg-top-left-mm"/></metadata>';
		$wrapped = sprintf(
			'<?xml version="1.0" encoding="UTF-8"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %s %s">%s%s</svg>',
			PCKZ_Production_Geometry::fmt( $w ),
			PCKZ_Production_Geometry::fmt( $h ),
			$meta,
			$fragment
		);
		$parsed = self::parse_master_svg( $wrapped, $w, $h, $ctx['layout'] );
		$parsed_vert_layers = 0;
		foreach ( (array) ( $parsed['layers'] ?? array() ) as $layer ) {
			if ( 'path' === ( $layer['type'] ?? '' ) && ! empty( $layer['verts'] ) ) {
				++$parsed_vert_layers;
			}
		}
		if ( $parsed_vert_layers < 1 ) {
			$parsed = self::parse_text_plate_paths_fragment( $fragment, $w, $h, $ctx['layout'] );
		}
		$text_meta = null;
		$text_metas = array();
		foreach ( PCKZ_Production_Geometry::sort_objects( $ctx['objects'] ) as $obj ) {
			$obj = PCKZ_Production_Geometry::normalize_layout_object( $obj );
			if ( ! in_array( $obj['role'] ?? '', array( 'text', 'main-text' ), true ) ) {
				continue;
			}
			if ( '' === trim( (string) ( $obj['text'] ?? '' ) ) ) {
				continue;
			}
			$box = self::placement_box_mm( $obj, $ctx );
			if ( ! $box ) {
				continue;
			}
			$meta = self::layer_export_meta( $obj, $box );
			$id   = (string) ( $meta['canonical_object_id'] ?? '' );
			if ( $id ) {
				$text_metas[ $id ] = $meta;
			}
			if ( ! $text_meta ) {
				$text_meta = $meta;
			}
		}
		$scene['layers'] = array_values( array_filter(
			$scene['layers'],
			function ( $layer ) {
				if ( 'path' !== ( $layer['type'] ?? '' ) ) {
					return true;
				}
				$role = (string) ( $layer['role'] ?? '' );
				$lid  = (string) ( $layer['layer_id'] ?? '' );
				if ( in_array( $role, array( 'text-engrave', 'pckz-text-engrave' ), true ) ) {
					return false;
				}
				if ( in_array( $lid, array( 'pckz-text-engrave' ), true ) || 0 === strpos( $lid, 'pckz-text-engrave-' ) ) {
					return false;
				}
				// Drop Fabric/preview text path stubs so OpenType plate paths replace them.
				if ( in_array( $role, array( 'pckz-text', 'text', 'main-text' ), true ) ) {
					return false;
				}
				return ! in_array( $lid, array( 'pckz-text', 'pckz-main-text' ), true );
			}
		) );
		$merged = 0;
		foreach ( $parsed['layers'] ?? array() as $layer ) {
			if ( 'path' !== ( $layer['type'] ?? '' ) || empty( $layer['verts'] ) ) {
				continue;
			}
			$layer['role'] = 'text-engrave';
			$lid = (string) ( $layer['layer_id'] ?? '' );
			if ( '' === $lid || in_array( $lid, array( 'pckz-text', 'pckz-main-text' ), true ) ) {
				$layer['layer_id'] = 'pckz-text-engrave';
			}
			$meta = $text_meta;
			$canonical = (string) ( $layer['canonical_object_id'] ?? '' );
			if ( $canonical && ! empty( $text_metas[ $canonical ] ) ) {
				$meta = $text_metas[ $canonical ];
			} elseif ( ! empty( $text_metas ) ) {
				$meta = reset( $text_metas );
			}
			if ( $meta ) {
				$layer = array_merge( $meta, $layer );
			}
			$scene['layers'][] = $layer;
			++$merged;
		}
		if ( $merged < 1 ) {
			$fallback = self::parse_text_plate_paths_fragment( $fragment, $w, $h, $ctx['layout'] );
			foreach ( $fallback['layers'] ?? array() as $layer ) {
				if ( 'path' !== ( $layer['type'] ?? '' ) || empty( $layer['verts'] ) ) {
					continue;
				}
				$layer['role']     = 'text-engrave';
				$layer['layer_id'] = 'pckz-text-engrave';
				if ( $text_meta ) {
					$layer = array_merge( $text_meta, $layer );
				}
				$scene['layers'][] = $layer;
			}
		}
	}

	/**
	 * Prefer vector text paths over LightBurn Text shape (avoids duplicate/missing fonts).
	 *
	 * @param array $scene Scene (by reference).
	 */
	public static function consolidate_scene_text_layers( &$scene ) {
		if ( empty( $scene['layers'] ) ) {
			return;
		}
		$filtered = array();
		foreach ( $scene['layers'] as $layer ) {
			if ( 'text' === ( $layer['type'] ?? '' ) ) {
				continue;
			}
			if ( 'path' === ( $layer['type'] ?? '' )
				&& in_array( $layer['role'] ?? '', array( 'pckz-text-paths', 'text-paths' ), true ) ) {
				continue;
			}
			$filtered[] = $layer;
		}
		$scene['layers'] = $filtered;
	}

	/**
	 * Production export requires vector text paths (never LightBurn font text).
	 *
	 * @param array $scene   Scene (by reference).
	 * @param array $ctx     Context.
	 * @param array $package Package.
	 * @return true|WP_Error
	 */
	private static function require_vector_text_layers( $scene, $ctx, $package ) {
		$needs_text = false;
		foreach ( $ctx['objects'] as $obj ) {
			$obj = PCKZ_Production_Geometry::normalize_layout_object( $obj );
			if ( in_array( $obj['role'] ?? '', array( 'text', 'main-text' ), true ) && '' !== trim( (string) ( $obj['text'] ?? '' ) ) ) {
				$needs_text = true;
				break;
			}
		}
		if ( ! $needs_text ) {
			return true;
		}
		$has_vector = false;
		foreach ( $scene['layers'] ?? array() as $layer ) {
			if ( self::layer_is_authoritative_text_engrave_path( $layer ) ) {
				$has_vector = true;
				break;
			}
		}
		if ( ! $has_vector ) {
			$fragment = trim( (string) ( $ctx['layout']['text_plate_paths'] ?? $package['text_plate_paths'] ?? '' ) );
			$font_family = '';
			foreach ( $ctx['objects'] as $obj ) {
				$obj = PCKZ_Production_Geometry::normalize_layout_object( $obj );
				if ( in_array( $obj['role'] ?? '', array( 'text', 'main-text' ), true ) ) {
					$font_family = (string) ( $obj['font_family'] ?? $obj['fontFamily'] ?? '' );
					break;
				}
			}
			$font_url = '';
			if ( class_exists( 'PCKZ_Font_Library' ) && '' !== $font_family ) {
				$files = PCKZ_Font_Library::font_files_for_js();
				$key   = strtolower( trim( $font_family ) );
				$font_url = (string) ( $files[ $key ] ?? '' );
			}
			$summary = class_exists( 'PCKZ_Export_Diagnostics' )
				? PCKZ_Export_Diagnostics::summarize_payload(
					$fragment,
					(string) ( $package['production_vector_svg'] ?? $ctx['layout']['production_vector_svg'] ?? '' ),
					$font_family,
					$font_url
				)
				: array();
			$probe = class_exists( 'PCKZ_Export_Diagnostics' )
				? PCKZ_Export_Diagnostics::probe_text_fragment_parse( $fragment, (float) $ctx['canvas_w'], (float) $ctx['canvas_h'], $ctx['layout'] )
				: array();
			$debug = class_exists( 'PCKZ_Export_Diagnostics' )
				? PCKZ_Export_Diagnostics::format_debug_suffix( $summary, $probe )
				: '';
			if ( '' === $fragment ) {
				return new WP_Error(
					'vector_text_missing',
					__( 'Vector text paths are missing from export payload.', 'pckz-canonical-engine' ) . ( $debug ? ' ' . $debug : '' ),
					array(
						'http_status'  => 422,
						'export_debug' => array_merge( $summary, $probe ),
						'parse_probe'  => $probe,
					)
				);
			}
			return new WP_Error(
				'vector_text_invalid',
				__( 'Vector text paths failed to parse for LBRN2 export.', 'pckz-canonical-engine' ) . ( $debug ? ' ' . $debug : '' ),
				array(
					'http_status'  => 422,
					'export_debug' => array_merge( $summary, $probe ),
					'parse_probe'  => $probe,
				)
			);
		}
		return true;
	}



	/**
	 * Canonical object id + placement bbox metadata for export layers.
	 *
	 * @param array $obj Layout object.
	 * @param array $box Placement mm box.
	 * @return array
	 */
	private static function layer_export_meta( $obj, $box ) {
		$object_id = (string) ( $obj['object_id'] ?? $obj['id'] ?? '' );
		$bbox      = array(
			'x_mm'        => round( (float) $box['x'], 3 ),
			'y_mm'        => round( (float) $box['y'], 3 ),
			'width_mm'    => round( (float) $box['width'], 3 ),
			'height_mm'   => round( (float) $box['height'], 3 ),
			'center_x_mm' => round( (float) $box['center_x'], 3 ),
			'center_y_mm' => round( (float) $box['center_y'], 3 ),
		);
		return array(
			'canonical_object_id' => $object_id,
			'placement_bbox_mm'   => $bbox,
			'origin'              => 'bottom-left',
		);
	}

	/**
	 * Map canonical object placement boxes onto parsed export layers (parity metadata).
	 *
	 * @param array $scene Scene (by reference).
	 * @param array $ctx   Normalized export context.
	 */
	private static function attach_canonical_placement_bboxes( &$scene, $ctx ) {
		if ( empty( $scene['layers'] ) || empty( $ctx['objects'] ) ) {
			return;
		}

		$placements = array();
		foreach ( $ctx['objects'] as $obj ) {
			$obj = PCKZ_Production_Geometry::normalize_layout_object( $obj );
			$box = self::placement_box_mm( $obj, $ctx );
			if ( ! $box ) {
				continue;
			}
			$meta = self::layer_export_meta( $obj, $box );
			$role = (string) ( $obj['role'] ?? '' );
			$id   = (string) ( $meta['canonical_object_id'] ?? '' );
			if ( $id ) {
				$placements[ $id ] = $meta;
			}
			if ( $role ) {
				$placements[ 'role:' . $role ] = $meta;
			}
		}

		foreach ( $scene['layers'] as &$layer ) {
			$layer_id = (string) ( $layer['layer_id'] ?? '' );
			$role     = (string) ( $layer['role'] ?? '' );
			$meta     = null;
			if ( $layer_id && ! empty( $placements[ $layer_id ] ) ) {
				$meta = $placements[ $layer_id ];
			} elseif ( $layer_id && 0 === strpos( $layer_id, 'pckz-line-' ) && ! empty( $placements['pckz-lines'] ) ) {
				$meta = $placements['pckz-lines'];
			} elseif ( $role && ! empty( $placements[ 'role:' . $role ] ) ) {
				$meta = $placements[ 'role:' . $role ];
			} elseif ( $layer_id ) {
				$slug = preg_replace( '/-\d+$/', '', $layer_id );
				if ( $slug && ! empty( $placements[ $slug ] ) ) {
					$meta = $placements[ $slug ];
				}
			}
			if ( $meta ) {
				$layer = array_merge( $meta, $layer );
			}
		}
		unset( $layer );
	}

	/**
	 * Attach lightburn-mm-bottom-left bbox to each scene layer (parity + exporters).
	 *
	 * @param array $scene Scene (by reference).
	 * @param array $ctx   Normalized export context.
	 */
	private static function attach_layer_bbox_mm( &$scene, $ctx ) {
		if ( empty( $scene['layers'] ) || ! is_array( $scene['layers'] ) ) {
			return;
		}

		foreach ( $scene['layers'] as &$layer ) {
			$measured = self::measure_layer_bbox_mm( $layer );
			if ( $measured ) {
				$layer['measured_bbox_mm'] = $measured;
			}
			if ( ! empty( $layer['placement_bbox_mm'] ) && is_array( $layer['placement_bbox_mm'] ) ) {
				$layer['bbox_mm'] = $layer['placement_bbox_mm'];
			} elseif ( $measured ) {
				$layer['bbox_mm'] = $measured;
			}
		}
		unset( $layer );
	}

	/**
	 * Measure a scene layer bbox in mm (bottom-left origin).
	 *
	 * @param array $layer Layer.
	 * @return array|null
	 */
	private static function measure_layer_bbox_mm( $layer ) {
		$type = $layer['type'] ?? '';

		if ( 'path' === $type && ! empty( $layer['verts'] ) && is_array( $layer['verts'] ) ) {
			$xs = array();
			$ys = array();
			foreach ( $layer['verts'] as $vert ) {
				$xs[] = (float) ( $vert['x'] ?? 0 );
				$ys[] = (float) ( $vert['y'] ?? 0 );
			}
			if ( empty( $xs ) ) {
				return null;
			}
			$min_x = min( $xs );
			$max_x = max( $xs );
			$min_y = min( $ys );
			$max_y = max( $ys );
			$w     = max( 0.001, $max_x - $min_x );
			$h     = max( 0.001, $max_y - $min_y );
			return array(
				'x_mm'        => round( $min_x, 3 ),
				'y_mm'        => round( $min_y, 3 ),
				'width_mm'    => round( $w, 3 ),
				'height_mm'   => round( $h, 3 ),
				'center_x_mm' => round( $min_x + $w / 2, 3 ),
				'center_y_mm' => round( $min_y + $h / 2, 3 ),
			);
		}

		if ( 'ellipse' === $type ) {
			$cx = (float) ( $layer['cx'] ?? 0 );
			$cy = (float) ( $layer['cy'] ?? 0 );
			$rx = max( 0.001, (float) ( $layer['rx'] ?? 0 ) );
			$ry = max( 0.001, (float) ( $layer['ry'] ?? 0 ) );
			return array(
				'x_mm'        => round( $cx - $rx, 3 ),
				'y_mm'        => round( $cy - $ry, 3 ),
				'width_mm'    => round( $rx * 2, 3 ),
				'height_mm'   => round( $ry * 2, 3 ),
				'center_x_mm' => round( $cx, 3 ),
				'center_y_mm' => round( $cy, 3 ),
			);
		}

		if ( 'text' === $type ) {
			$cx   = (float) ( $layer['x'] ?? 0 );
			$cy   = (float) ( $layer['y'] ?? 0 );
			$font = max( 3.0, (float) ( $layer['font_size'] ?? 8 ) );
			$text = (string) ( $layer['text'] ?? '' );
			$w    = max( $font, strlen( $text ) * $font * 0.55 );
			$h    = $font * 1.2;
			return array(
				'x_mm'        => round( $cx - $w / 2, 3 ),
				'y_mm'        => round( $cy - $h / 2, 3 ),
				'width_mm'    => round( $w, 3 ),
				'height_mm'   => round( $h, 3 ),
				'center_x_mm' => round( $cx, 3 ),
				'center_y_mm' => round( $cy, 3 ),
			);
		}

		return null;
	}

	/**
	 * Map layout role → Cloudlift layer_refs key.
	 *
	 * @param string $role Role.
	 * @return string
	 */
	private static function role_to_ref_key( $role ) {
		$map = array(
			'text'          => 'text',
			'main-text'     => 'text',
			'lines'         => 'lines',
			'line-overlay'  => 'lines',
			'icon-left'     => 'iconLeft',
			'icon-right'    => 'iconRight',
			'icon-bg-left'  => 'iconBgLeft',
			'icon-bg-right' => 'iconBgRight',
		);
		return $map[ $role ] ?? '';
	}

	/**
	 * Placement mm box from layer refs (preview placeInRef targets).
	 *
	 * @param array $obj Layout object.
	 * @param array $ctx Context.
	 * @return array|null
	 */
	private static function placement_box_mm( $obj, $ctx ) {
		$layout_scene  = is_array( $ctx['layout'] ?? null ) ? ( $ctx['layout']['canonical_scene'] ?? null ) : null;
		$package_scene = is_array( $ctx['package'] ?? null ) ? ( $ctx['package']['canonical_scene'] ?? null ) : null;
		$strict        = ! empty( $layout_scene ) || ! empty( $package_scene );
		if ( $strict ) {
			$bbox = PCKZ_Production_Geometry::bbox_from_mm( $obj['mm'] ?? $obj );
			if ( $bbox ) {
				return array(
					'x'        => $bbox['x_mm'],
					'y'        => $bbox['y_mm'],
					'width'    => $bbox['width_mm'],
					'height'   => $bbox['height_mm'],
					'center_x' => $bbox['center_x_mm'],
					'center_y' => $bbox['center_y_mm'],
				);
			}
			return PCKZ_Production_Geometry::object_box_mm( $obj, $ctx['canvas_w'], $ctx['canvas_h'], $ctx['origin'] );
		}

		$role = $obj['role'] ?? '';
		$refs = $ctx['layout']['layer_refs'] ?? array();
		$key  = self::role_to_ref_key( $role );
		if ( $key && ! empty( $refs[ $key ] ) && class_exists( 'PCKZ_Ledos_Preview' ) ) {
			$mm = PCKZ_Ledos_Preview::ref_to_mm_box(
				$refs[ $key ],
				$ctx['canvas_w'],
				$ctx['canvas_h'],
				$ctx['origin']
			);
			return array(
				'x'        => $mm['x_mm'],
				'y'        => $mm['y_mm'],
				'width'    => $mm['width_mm'],
				'height'   => $mm['height_mm'],
				'center_x' => $mm['center_x_mm'],
				'center_y' => $mm['center_y_mm'],
			);
		}
		return PCKZ_Production_Geometry::object_box_mm( $obj, $ctx['canvas_w'], $ctx['canvas_h'], $ctx['origin'] );
	}

	/**
	 * Extract inner markup of a group id from an SVG document.
	 *
	 * @param string $svg SVG document.
	 * @param string $id  Group id.
	 * @return string
	 */
	public static function extract_svg_group_inner( $svg, $id ) {
		if ( '' === trim( $svg ) || '' === $id ) {
			return '';
		}
		$dom  = new DOMDocument();
		$prev = libxml_use_internal_errors( true );
		if ( ! $dom->loadXML( $svg ) ) {
			libxml_clear_errors();
			libxml_use_internal_errors( $prev );
			return '';
		}
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );

		$el = $dom->getElementById( $id );
		if ( ! $el ) {
			$xpath = new DOMXPath( $dom );
			$nodes = $xpath->query( '//*[@id="' . $id . '"]' );
			$el    = ( $nodes && $nodes->length ) ? $nodes->item( 0 ) : null;
		}
		if ( ! $el ) {
			return '';
		}
		$inner = '';
		foreach ( $el->childNodes as $child ) {
			$inner .= $dom->saveXML( $child );
		}
		return trim( $inner );
	}

	/**
	 * Path/ellipse layers for one layout object (uniform mm fit).
	 *
	 * @param array $obj Layout object.
	 * @param array $ctx Context.
	 * @return array<int,array>
	 */
	private static function layout_vector_layers( $obj, $ctx ) {
		$layers = array();
		$box    = self::placement_box_mm( $obj, $ctx );
		if ( ! $box ) {
			return $layers;
		}

		$body = PCKZ_Production_Geometry::resolve_svg_body_for_object( $obj, $ctx['selections'] );
		if ( '' === $body ) {
			return $layers;
		}

		$role = $obj['role'] ?? 'engrave';
		$fill = PCKZ_Production_Geometry::resolve_hex( $obj['fill'] ?? '#000000' );

		if ( PCKZ_Production_Geometry::prefer_embedded_svg( $obj ) ) {
			$vb    = PCKZ_Production_Geometry::svg_viewbox_size( $body );
			$fit   = PCKZ_Production_Geometry::fit_transform_for_box( $vb['w'], $vb['h'], $box );
			$inner = self::extract_svg_inner_markup( $body );
			if ( '' === $inner ) {
				return $layers;
			}
			$sx    = $fit['content_w'] / max( 0.001, $vb['w'] );
			$sy    = $fit['content_h'] / max( 0.001, $vb['h'] );
			$y_svg = PCKZ_Production_Geometry::mm_y_to_svg_top( $fit['offset_y'], $fit['content_h'], $ctx['canvas_h'], $ctx['origin'] );
			$wrapped = sprintf(
				'<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %s %s"><g transform="translate(%s %s) scale(%s %s)">%s</g></svg>',
				PCKZ_Production_Geometry::fmt( $ctx['canvas_w'] ),
				PCKZ_Production_Geometry::fmt( $ctx['canvas_h'] ),
				PCKZ_Production_Geometry::fmt( $fit['offset_x'] ),
				PCKZ_Production_Geometry::fmt( $y_svg ),
				PCKZ_Production_Geometry::fmt( $sx ),
				PCKZ_Production_Geometry::fmt( $sy ),
				$inner
			);
			$parsed = self::parse_master_svg( $wrapped, $ctx['canvas_w'], $ctx['canvas_h'], $ctx['layout'] );
			$meta   = self::layer_export_meta( $obj, $box );
			$fit_meta = array(
				'transform' => array(
					'scale_x'   => round( $sx, 6 ),
					'scale_y'   => round( $sy, 6 ),
					'offset_x'  => round( $fit['offset_x'], 3 ),
					'offset_y'  => round( $fit['offset_y'], 3 ),
					'viewbox_w' => round( $vb['w'], 3 ),
					'viewbox_h' => round( $vb['h'], 3 ),
				),
			);
			foreach ( $parsed['layers'] ?? array() as $layer ) {
				$layer['role'] = $role;
				$layer['fill'] = $fill;
				$object_id     = $meta['canonical_object_id'] ?: $role;
				$layer['layer_id'] = $layer['layer_id'] ?? $object_id;
				$layers[]      = array_merge( $meta, $fit_meta, $layer );
			}
			return $layers;
		}

		$meta = self::layer_export_meta( $obj, $box );
		foreach ( PCKZ_Production_Geometry::paths_for_object_mm( $obj, $box, $ctx['selections'] ) as $loop ) {
			if ( count( $loop['verts'] ) < 2 ) {
				continue;
			}
			$object_id = $meta['canonical_object_id'] ?: $role;
			$layers[]  = array_merge(
				$meta,
				array(
					'type'         => 'path',
					'role'         => $role,
					'layer_id'     => $object_id,
					'fill'         => $fill,
					'stroke'       => '',
					'stroke_width' => 0,
					'closed'       => ! empty( $loop['closed'] ),
					'verts'        => $loop['verts'],
				)
			);
		}

		return $layers;
	}

	/**
	 * Text layer from saved layout (always emitted for LBRN2).
	 *
	 * @param array $obj Layout object.
	 * @param array $ctx Context.
	 * @return array|null
	 */
	private static function layout_text_layer( $obj, $ctx ) {
		$text = trim( (string) ( $obj['text'] ?? '' ) );
		if ( '' === $text ) {
			return null;
		}
		$box = self::placement_box_mm( $obj, $ctx );
		if ( ! $box ) {
			return null;
		}

		$font_mm = self::layout_font_size_mm( $obj, $ctx, $box );
		$fill    = PCKZ_Production_Geometry::resolve_hex( $obj['fill'] ?? '#000000' );

		$meta = self::layer_export_meta( $obj, $box );
		$object_id = $meta['canonical_object_id'] ?: 'pckz-text';

		return array_merge(
			$meta,
			array(
				'type'         => 'text',
				'role'         => $obj['role'] ?? 'text',
				'layer_id'     => $object_id,
				'text'         => $text,
				'font_family'  => $obj['font_family'] ?? $obj['fontFamily'] ?? 'Russo One',
				'font_size'    => $font_mm,
				'x'            => $box['center_x'],
				'y'            => $box['center_y'],
				'anchor'       => 'center',
				'fill'         => $fill,
				'stroke'       => ! empty( $obj['stroke'] ) ? PCKZ_Production_Geometry::resolve_hex( $obj['stroke'] ) : '',
				'stroke_width' => (float) ( $obj['stroke_width'] ?? 0 ),
			)
		);
	}

	/**
	 * Fabric font px → mm using saved background fit (matches preview export).
	 *
	 * @param array $obj  Text object.
	 * @param array $ctx  Context.
	 * @param array $box  Mm box.
	 * @return float
	 */
	private static function layout_font_size_mm( $obj, $ctx, $box ) {
		$font_px  = (float) ( $obj['font_size_px'] ?? 55 );
		$layout   = $ctx['layout'] ?? array();
		$bg       = $layout['background_fit_canvas'] ?? array();
		$bg_w     = (float) ( $bg['width'] ?? 0 );
		$canvas_w = (float) $ctx['canvas_w'];
		$canvas_h = (float) $ctx['canvas_h'];
		$design_h = (float) ( $ctx['design_px']['height'] ?? 2132 );

		if ( $bg_w > 1 && $font_px > 0 ) {
			$font_mm = $font_px * ( $canvas_w / $bg_w );
		} else {
			$font_mm = $design_h > 0 ? ( $font_px / $design_h ) * $canvas_h : 8;
		}

		return max( 3, min( $box['height'] * 0.95, $font_mm ) );
	}

	/**
	 * @param array $package Package.
	 * @param array $layout  Layout.
	 * @return string
	 */
	private static function resolve_master_svg( $package, $layout ) {
		$browser_svg = self::extract_browser_production_svg( $package, $layout );
		if ( '' !== trim( $browser_svg ) ) {
			return $browser_svg;
		}

		if ( ! empty( $package['canonical_scene'] ) || ! empty( $layout['canonical_scene'] ) ) {
			$ctx = PCKZ_Production_Geometry::normalize_package( $package, true );
			return self::synthesize_master_svg_from_layout( $ctx );
		}

		if ( self::layout_has_exportable_objects( $layout ) && class_exists( 'PCKZ_Production_Geometry' ) ) {
			$ctx = PCKZ_Production_Geometry::normalize_package( $package );
			$svg = self::synthesize_master_svg_from_layout( $ctx );
			if ( '' !== trim( $svg ) ) {
				return $svg;
			}
		}

		return self::extract_browser_production_svg( $package, $layout );
	}

	/**
	 * Browser-posted production_vector_svg snapshot (any format).
	 *
	 * @param array $package Package.
	 * @param array $layout  Layout.
	 * @return string
	 */
	private static function extract_browser_production_svg( $package, $layout ) {
		if ( ! empty( $package['production_vector_svg'] ) ) {
			return (string) $package['production_vector_svg'];
		}
		if ( ! empty( $layout['production_vector_svg'] ) ) {
			return (string) $layout['production_vector_svg'];
		}
		$canvas_json = $package['canvas_json'] ?? '';
		if ( $canvas_json ) {
			$data = json_decode( $canvas_json, true );
			if ( ! empty( $data['pckzMeta']['production_vector_svg'] ) ) {
				return (string) $data['pckzMeta']['production_vector_svg'];
			}
			if ( ! empty( $data['pckzMeta']['layout']['production_vector_svg'] ) ) {
				return (string) $data['pckzMeta']['layout']['production_vector_svg'];
			}
		}
		return '';
	}

	/**
	 * Whether layout contains mm boxes suitable for authoritative server export.
	 *
	 * @param array $layout Layout.
	 * @return bool
	 */
	public static function layout_has_exportable_objects( $layout ) {
		if ( empty( $layout['objects'] ) || ! is_array( $layout['objects'] ) ) {
			return false;
		}
		foreach ( $layout['objects'] as $obj ) {
			$mm = $obj['mm'] ?? $obj;
			if (
				isset( $mm['width_mm'], $mm['height_mm'] ) &&
				(float) $mm['width_mm'] > 0 &&
				(float) $mm['height_mm'] > 0 &&
				( isset( $mm['x_mm'], $mm['y_mm'] ) || isset( $mm['center_x_mm'], $mm['center_y_mm'] ) )
			) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Build mm SVG from layout objects (server fallback when browser snapshot missing).
	 *
	 * @param array $ctx Normalized export context.
	 * @return string
	 */
	public static function synthesize_master_svg_from_layout( $ctx ) {
		$w      = $ctx['canvas_w'];
		$h      = $ctx['canvas_h'];
		$origin = $ctx['origin'];
		$parts  = array();
		$parts[] = '<?xml version="1.0" encoding="UTF-8"?>';
		$parts[] = sprintf(
			'<svg xmlns="http://www.w3.org/2000/svg" width="%s" height="%s" viewBox="0 0 %s %s">',
			PCKZ_Production_Geometry::fmt( $w ),
			PCKZ_Production_Geometry::fmt( $h ),
			PCKZ_Production_Geometry::fmt( $w ),
			PCKZ_Production_Geometry::fmt( $h )
		);
		$parts[] = '<g id="pckz-engrave">';

		foreach ( PCKZ_Production_Geometry::sort_objects( $ctx['objects'] ) as $idx => $obj ) {
			$obj  = PCKZ_Production_Geometry::normalize_layout_object( $obj );
			$role = $obj['role'] ?? '';
			$box = self::placement_box_mm( $obj, $ctx );
			if ( ! $box ) {
				continue;
			}

			if ( in_array( $role, array( 'text', 'main-text' ), true ) ) {
				$frag = self::synthesize_text_svg_fragment( $obj, $box, $w, $h, $origin, $ctx );
				if ( $frag ) {
					$parts[] = $frag;
				}
				continue;
			}

			$body = PCKZ_Production_Geometry::resolve_svg_body_for_object( $obj, $ctx['selections'] );
			if ( '' === $body ) {
				continue;
			}

			$inner = self::extract_svg_inner_markup( $body );
			if ( '' === $inner ) {
				continue;
			}

			$vb   = PCKZ_Production_Geometry::svg_viewbox_size( $body );
			$fit  = PCKZ_Production_Geometry::fit_transform_for_box( $vb['w'], $vb['h'], $box );
			$sx   = $fit['content_w'] / max( 0.001, $vb['w'] );
			$sy   = $fit['content_h'] / max( 0.001, $vb['h'] );
			$tx   = $fit['offset_x'];
			$ty   = PCKZ_Production_Geometry::mm_y_to_svg_top( $fit['offset_y'], $fit['content_h'], $h, $origin );
			$fill = PCKZ_Production_Geometry::resolve_hex( $obj['fill'] ?? '' );

			$parts[] = sprintf(
				'<g id="pckz-%s-%d" fill="%s" stroke="none" transform="translate(%s %s) scale(%s %s)">%s</g>',
				esc_attr( $role ),
				(int) $idx,
				esc_attr( $fill ),
				PCKZ_Production_Geometry::fmt( $tx ),
				PCKZ_Production_Geometry::fmt( $ty ),
				PCKZ_Production_Geometry::fmt( $sx ),
				PCKZ_Production_Geometry::fmt( $sy ),
				$inner
			);
		}

		$parts[] = '</g></svg>';
		return implode( "\n", array_filter( $parts ) );
	}

	/**
	 * @param array  $obj    Text object.
	 * @param array  $box    Mm box.
	 * @param float  $w      Canvas W.
	 * @param float  $h      Canvas H.
	 * @param string $origin Origin.
	 * @param array  $ctx    Context.
	 * @return string
	 */
	private static function synthesize_text_svg_fragment( $obj, $box, $w, $h, $origin, $ctx ) {
		$text = trim( (string) ( $obj['text'] ?? '' ) );
		if ( '' === $text ) {
			return '';
		}
		$design_h = (float) ( $ctx['design_px']['height'] ?? 2132 );
		$font_px  = (float) ( $obj['font_size_px'] ?? 55 );
		$font_mm  = $design_h > 0 ? ( $font_px / $design_h ) * $h : 8;
		$font_mm  = max( 3, min( $box['height'] * 0.95, $font_mm ) );
		$cy_svg   = $h - (float) $box['center_y'];
		$fill     = PCKZ_Production_Geometry::resolve_hex( $obj['fill'] ?? '#000000' );
		$font     = esc_attr( $obj['font_family'] ?? $obj['fontFamily'] ?? 'Arial' );
		$stroke   = '';
		if ( ! empty( $obj['stroke'] ) && ! empty( $obj['stroke_width'] ) ) {
			$sw = (float) $obj['stroke_width'] * ( $h / max( 1, $design_h ) );
			$stroke = sprintf(
				' stroke="%s" stroke-width="%s" paint-order="stroke fill"',
				esc_attr( PCKZ_Production_Geometry::resolve_hex( $obj['stroke'] ) ),
				PCKZ_Production_Geometry::fmt( max( 0.1, $sw ) )
			);
		}
		return sprintf(
			'<text id="pckz-text" x="%s" y="%s" font-family="%s" font-size="%s" fill="%s" text-anchor="middle" dominant-baseline="middle"%s>%s</text>',
			PCKZ_Production_Geometry::fmt( $box['center_x'] ),
			PCKZ_Production_Geometry::fmt( $cy_svg ),
			$font,
			PCKZ_Production_Geometry::fmt( $font_mm ),
			esc_attr( $fill ),
			$stroke,
			esc_xml( $text )
		);
	}

	/**
	 * @param string $body SVG file.
	 * @return string
	 */
	private static function extract_svg_inner_markup( $body ) {
		if ( ! preg_match( '/<svg\b[^>]*>(.*)<\/svg>/is', $body, $m ) ) {
			return '';
		}
		$inner = $m[1];
		$inner = preg_replace( '/<script\b[^>]*>.*?<\/script>/is', '', $inner );
		$inner = preg_replace( '/<foreignObject\b[^>]*>.*?<\/foreignObject>/is', '', $inner );
		return trim( $inner );
	}

	/**
	 * Parse master mm SVG into scene layers (internal Y-up mm).
	 *
	 * @param string $svg      Master SVG.
	 * @param float  $canvas_w Width mm.
	 * @param float  $canvas_h Height mm.
	 * @param array  $layout   Layout for zones.
	 * @return array
	 */
	public static function parse_master_svg( $svg, $canvas_w, $canvas_h, $layout = array() ) {
		$layers = array();
		$state  = array(
			'fill'          => '#000000',
			'stroke'        => '',
			'stroke_width'  => 0,
			'role'          => 'engrave',
			'z'             => 20,
		);

		$dom = new DOMDocument();
		$prev = libxml_use_internal_errors( true );
		if ( ! $dom->loadXML( $svg ) ) {
			libxml_clear_errors();
			libxml_use_internal_errors( $prev );
			return self::empty_scene( $canvas_w, $canvas_h, $layout );
		}
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );

		$svg_el = $dom->getElementsByTagName( 'svg' )->item( 0 );
		if ( ! $svg_el ) {
			return self::empty_scene( $canvas_w, $canvas_h, $layout );
		}

		$coord_system = self::svg_coordinate_system( $svg, $svg_el );
		$matrix       = ( self::COORD_LIGHTBURN_BOTTOM_LEFT === $coord_system )
			? array( 1, 0, 0, 1, 0, 0 )
			: self::svg_viewbox_to_mm_matrix( $svg_el, $canvas_w, $canvas_h );
		if ( $svg_el->hasChildNodes() ) {
			foreach ( $svg_el->childNodes as $child ) {
				self::walk_svg_node( $child, $matrix, $canvas_h, $state, $layers, $coord_system );
			}
		}


		return array(
			'version'          => 1,
			'canvas_w'         => $canvas_w,
			'canvas_h'         => $canvas_h,
			'origin'           => 'bottom-left',
			'coordinate_system'=> $coord_system,
			'safe_zone' => $layout['safe_zone_mm'] ?? null,
			'strip_zone'=> $layout['strip_zone_mm'] ?? null,
			'layers'    => $layers,
		);
	}

	/**
	 * @param float $canvas_w W.
	 * @param float $canvas_h H.
	 * @param array $layout   Layout.
	 * @return array
	 */
	private static function empty_scene( $canvas_w, $canvas_h, $layout ) {
		return array(
			'version'   => 1,
			'canvas_w'  => $canvas_w,
			'canvas_h'  => $canvas_h,
			'origin'    => 'bottom-left',
			'safe_zone' => $layout['safe_zone_mm'] ?? null,
			'strip_zone'=> $layout['strip_zone_mm'] ?? null,
			'layers'    => array(),
		);
	}

	/**
	 * @param DOMNode $node      Node.
	 * @param array   $matrix    Current matrix.
	 * @param float   $canvas_h  Canvas height.
	 * @param array   $state     Inherited paint state.
	 * @param array   $layers    Output layers.
	 */
	private static function walk_svg_node( $node, $matrix, $canvas_h, $state, &$layers, $coord_system = self::COORD_SVG_TOP_LEFT ) {
		if ( XML_ELEMENT_NODE !== $node->nodeType ) {
			return;
		}

		$tag = strtolower( $node->nodeName );
		$new_state = $state;

		if ( $node instanceof DOMElement ) {
			$id = $node->getAttribute( 'id' );
			if ( in_array( $id, array( 'pckz-guides', 'pckz-meta', 'pckz-export-meta' ), true ) ) {
				return;
			}
			if ( in_array( $id, array( 'pckz-text-paths' ), true ) ) {
				return;
			}
			if ( $id && 0 === strpos( $id, 'pckz-' ) ) {
				$new_state['layer_id'] = $id;
				if ( preg_match( '/^pckz-line-\d+$/', $id ) ) {
					$new_state['role'] = $id;
				} elseif ( in_array( $id, array( 'pckz-text', 'pckz-text-engrave' ), true ) ) {
					$new_state['role'] = $id;
				} else {
					$new_state['role'] = preg_replace( '/-\d+$/', '', substr( $id, 5 ) );
				}
			}
			if ( $node->hasAttribute( 'fill' ) ) {
				$fill = $node->getAttribute( 'fill' );
				if ( $fill && 'none' !== strtolower( $fill ) ) {
					$new_state['fill'] = PCKZ_Production_Geometry::resolve_hex( $fill );
				}
			}
			if ( $node->hasAttribute( 'stroke' ) ) {
				$stroke = $node->getAttribute( 'stroke' );
				if ( $stroke && 'none' !== strtolower( $stroke ) ) {
					$new_state['stroke'] = PCKZ_Production_Geometry::resolve_hex( $stroke );
				}
			}
			if ( $node->hasAttribute( 'stroke-width' ) ) {
				$new_state['stroke_width'] = (float) $node->getAttribute( 'stroke-width' );
			}
		}

		$local_matrix = $matrix;
		if ( $node instanceof DOMElement && $node->hasAttribute( 'transform' ) ) {
			$local_matrix = self::multiply_matrix( $matrix, self::parse_transform_attr( $node->getAttribute( 'transform' ) ) );
		}

		if ( in_array( $tag, array( 'metadata', 'title', 'desc', 'defs' ), true ) ) {
			return;
		}

		if ( 'text' === $tag && $node instanceof DOMElement ) {
			return;
		}

		if ( 'ellipse' === $tag && $node instanceof DOMElement ) {
			self::add_ellipse_layer( $node, $local_matrix, $canvas_h, $new_state, $layers, $coord_system );
			return;
		}

		if ( in_array( $tag, array( 'path', 'rect', 'circle', 'polygon', 'polyline', 'line' ), true ) && $node instanceof DOMElement ) {
			$d = self::element_to_path_d( $node, $tag );
			if ( $d ) {
				self::add_path_layer( $d, $local_matrix, $canvas_h, $new_state, $layers, $coord_system );
			}
			return;
		}

		if ( $node->hasChildNodes() ) {
			foreach ( $node->childNodes as $child ) {
				self::walk_svg_node( $child, $local_matrix, $canvas_h, $new_state, $layers, $coord_system );
			}
		}
	}

	/**
	 * @param DOMElement $el   Element.
	 * @param string     $tag  Tag name.
	 * @return string
	 */
	private static function element_to_path_d( $el, $tag ) {
		if ( 'path' === $tag ) {
			return $el->getAttribute( 'd' );
		}
		if ( 'rect' === $tag ) {
			$x  = (float) $el->getAttribute( 'x' );
			$y  = (float) $el->getAttribute( 'y' );
			$w  = (float) $el->getAttribute( 'width' );
			$h  = (float) $el->getAttribute( 'height' );
			return sprintf( 'M %s %s L %s %s L %s %s L %s %s Z', $x, $y, $x + $w, $y, $x + $w, $y + $h, $x, $y + $h );
		}
		if ( 'circle' === $tag ) {
			$cx = (float) $el->getAttribute( 'cx' );
			$cy = (float) $el->getAttribute( 'cy' );
			$r  = (float) $el->getAttribute( 'r' );
			return PCKZ_Production_Geometry::ellipse_to_path_d( $cx, $cy, max( 0.01, $r ), max( 0.01, $r ) );
		}
		if ( 'ellipse' === $tag ) {
			$cx = (float) $el->getAttribute( 'cx' );
			$cy = (float) $el->getAttribute( 'cy' );
			$rx = (float) $el->getAttribute( 'rx' );
			$ry = (float) $el->getAttribute( 'ry' );
			return PCKZ_Production_Geometry::ellipse_to_path_d( $cx, $cy, max( 0.01, $rx ), max( 0.01, $ry ) );
		}
		if ( 'line' === $tag ) {
			$x1 = (float) $el->getAttribute( 'x1' );
			$y1 = (float) $el->getAttribute( 'y1' );
			$x2 = (float) $el->getAttribute( 'x2' );
			$y2 = (float) $el->getAttribute( 'y2' );
			return sprintf( 'M %s %s L %s %s', $x1, $y1, $x2, $y2 );
		}
		if ( 'polygon' === $tag || 'polyline' === $tag ) {
			$pts = preg_split( '/[\s,]+/', trim( $el->getAttribute( 'points' ) ) );
			$pairs = array();
			for ( $i = 0; $i + 1 < count( $pts ); $i += 2 ) {
				$pairs[] = (float) $pts[ $i ] . ' ' . (float) $pts[ $i + 1 ];
			}
			if ( count( $pairs ) < 2 ) {
				return '';
			}
			$d = 'M ' . implode( ' L ', $pairs );
			if ( 'polygon' === $tag ) {
				$d .= ' Z';
			}
			return $d;
		}
		return '';
	}

	/**
	 * @param string $d         Path d (SVG top-left coords).
	 * @param array  $matrix    Matrix.
	 * @param float  $canvas_h  Canvas H.
	 * @param array  $state     Paint state.
	 * @param array  $layers    Out.
	 */
	private static function add_path_layer( $d, $matrix, $canvas_h, $state, &$layers, $coord_system = self::COORD_SVG_TOP_LEFT ) {
		// Preserve Fabric path structure (M/L/C subpaths) — do not split into disconnected layers.
		$raw = PCKZ_Production_Geometry::parse_svg_path_to_verts( $d, 0, 0, 1, 1, 0 );
		if ( count( $raw['verts'] ) < 2 ) {
			return;
		}
		$mm_verts = array();
		foreach ( $raw['verts'] as $v ) {
			$p = PCKZ_Production_Geometry::apply_transform_point( $matrix, $v['x'], $v['y'] );
			$mm_verts[] = array(
				'x' => $p['x'],
				'y' => self::svg_y_to_scene_mm( $p['y'], $canvas_h, $coord_system ),
			);
		}
		$layers[] = array(
			'type'         => 'path',
			'role'         => $state['role'] ?? 'engrave',
			'layer_id'     => $state['layer_id'] ?? ( $state['role'] ?? 'engrave' ),
			'fill'         => $state['fill'] ?? '#000000',
			'stroke'       => $state['stroke'] ?? '',
			'stroke_width' => $state['stroke_width'] ?? 0,
			'closed'       => ! empty( $raw['closed'] ),
			'verts'        => $mm_verts,
			'prims'            => $raw['prims'] ?? array(),
			'subpath_starts' => $raw['subpath_starts'] ?? array(),
		);
	}

	/**
	 * @param DOMElement $el       Text element.
	 * @param array      $matrix   Matrix.
	 * @param float      $canvas_h Canvas H.
	 * @param array      $state    State.
	 * @param array      $layers   Out.
	 */
	/**
	 * @param DOMElement $el       Ellipse element.
	 * @param array      $matrix   Matrix.
	 * @param float      $canvas_h Canvas H.
	 * @param array      $state    State.
	 * @param array      $layers   Out.
	 */
	private static function add_ellipse_layer( $el, $matrix, $canvas_h, $state, &$layers, $coord_system = self::COORD_SVG_TOP_LEFT ) {
		$cx = (float) $el->getAttribute( 'cx' );
		$cy = (float) $el->getAttribute( 'cy' );
		$rx = max( 0.01, (float) $el->getAttribute( 'rx' ) );
		$ry = max( 0.01, (float) $el->getAttribute( 'ry' ) );
		$p  = PCKZ_Production_Geometry::apply_transform_point( $matrix, $cx, $cy );
		$sx = sqrt( $matrix[0] * $matrix[0] + $matrix[1] * $matrix[1] );
		$sy = sqrt( $matrix[2] * $matrix[2] + $matrix[3] * $matrix[3] );
		$layers[] = array(
			'type'     => 'ellipse',
			'role'     => $state['role'] ?? 'engrave',
			'layer_id' => $state['layer_id'] ?? ( $state['role'] ?? 'engrave' ),
			'fill'     => $state['fill'] ?? '#000000',
			'cx'       => $p['x'],
			'cy'       => self::svg_y_to_scene_mm( $p['y'], $canvas_h, $coord_system ),
			'rx'       => $rx * $sx,
			'ry'       => $ry * $sy,
		);
	}

	/**
	 * @param DOMElement $el       Text element.
	 * @param array      $matrix   Matrix.
	 * @param float      $canvas_h Canvas H.
	 * @param array      $state    State.
	 * @param array      $layers   Out.
	 */
	private static function add_text_layer( $el, $matrix, $canvas_h, $state, &$layers ) {
		$text = trim( $el->textContent );
		if ( '' === $text ) {
			return;
		}
		$x = (float) $el->getAttribute( 'x' );
		$y = (float) $el->getAttribute( 'y' );
		$p = PCKZ_Production_Geometry::apply_transform_point( $matrix, $x, $y );
		$scale = sqrt( $matrix[0] * $matrix[0] + $matrix[1] * $matrix[1] );
		if ( $scale <= 0 ) {
			$scale = 1;
		}
		$layers[] = array(
			'type'         => 'text',
			'role'         => $state['role'] ?? 'text',
			'layer_id'     => $state['layer_id'] ?? 'pckz-text',
			'fill'         => $state['fill'] ?? '#000000',
			'stroke'       => $state['stroke'] ?? '',
			'stroke_width' => $state['stroke_width'] ?? 0,
			'text'         => $text,
			'font_family'  => $el->getAttribute( 'font-family' ) ?: 'Russo One',
			'font_size'    => max( 3, ( (float) $el->getAttribute( 'font-size' ) ?: 8 ) * $scale ),
			'x'            => $p['x'],
			'y'            => $canvas_h - $p['y'],
		);
	}

	/**
	 * Resolve export coordinate system from browser production SVG metadata.
	 *
	 * @param string     $svg    Full SVG document.
	 * @param DOMElement $svg_el Root SVG element.
	 * @return string
	 */
	public static function svg_coordinate_system( $svg, $svg_el = null ) {
		if ( is_string( $svg ) && preg_match( '/coordinate-system\s*=\s*["\']([^"\']+)["\']/i', $svg, $m ) ) {
			$sys = strtolower( trim( $m[1] ) );
			if ( self::COORD_LIGHTBURN_BOTTOM_LEFT === $sys || 'lightburn-mm-bottom-left' === $sys ) {
				return self::COORD_LIGHTBURN_BOTTOM_LEFT;
			}
			if ( 'svg-top-left-mm' === $sys || self::COORD_SVG_TOP_LEFT === $sys ) {
				return self::COORD_SVG_TOP_LEFT;
			}
		}
		if ( is_string( $svg ) && preg_match( '/format\s*=\s*["\'](fabric-toSVG|fabric-staticCanvas-toSVG)["\']/i', $svg ) ) {
			return self::COORD_LIGHTBURN_BOTTOM_LEFT;
		}
		if ( $svg_el instanceof DOMElement ) {
			$attr = strtolower( trim( $svg_el->getAttribute( 'data-pckz-coordinate-system' ) ) );
			if ( self::COORD_LIGHTBURN_BOTTOM_LEFT === $attr ) {
				return self::COORD_LIGHTBURN_BOTTOM_LEFT;
			}
		}
		return self::COORD_SVG_TOP_LEFT;
	}

	/**
	 * Convert parsed SVG Y to scene mm (bottom-left, Y-up).
	 *
	 * @param float  $y_svg        Y in parsed SVG space.
	 * @param float  $canvas_h     Plate height mm.
	 * @param string $coord_system Coordinate system marker.
	 * @return float
	 */
	public static function svg_y_to_scene_mm( $y_svg, $canvas_h, $coord_system ) {
		if ( self::COORD_LIGHTBURN_BOTTOM_LEFT === $coord_system ) {
			return (float) $y_svg;
		}
		return (float) $canvas_h - (float) $y_svg;
	}

	/**
	 * Map SVG viewBox user units (browser canvas px) → mm plate coordinates.
	 *
	 * @param DOMElement $svg_el    Root SVG.
	 * @param float      $canvas_w  Plate width mm.
	 * @param float      $canvas_h  Plate height mm.
	 * @return array 2×3 matrix [a,b,c,d,e,f].
	 */
	public static function svg_viewbox_to_mm_matrix( $svg_el, $canvas_w, $canvas_h ) {
		if ( ! $svg_el instanceof DOMElement ) {
			return array( 1, 0, 0, 1, 0, 0 );
		}
		$vb = trim( $svg_el->getAttribute( 'viewBox' ) );
		if ( '' === $vb ) {
			return array( 1, 0, 0, 1, 0, 0 );
		}
		$parts = preg_split( '/[\s,]+/', $vb );
		if ( count( $parts ) < 4 ) {
			return array( 1, 0, 0, 1, 0, 0 );
		}
		$vx = (float) $parts[0];
		$vy = (float) $parts[1];
		$vw = (float) $parts[2];
		$vh = (float) $parts[3];
		if ( $vw <= 0 || $vh <= 0 ) {
			return array( 1, 0, 0, 1, 0, 0 );
		}
		$sx = $canvas_w / $vw;
		$sy = $canvas_h / $vh;
		return array(
			$sx,
			0,
			0,
			$sy,
			-$vx * $sx,
			-$vy * $sy,
		);
	}

	/**
	 * @param string $transform Transform attribute.
	 * @return array
	 */
	public static function parse_transform_attr( $transform ) {
		$matrix = array( 1, 0, 0, 1, 0, 0 );
		if ( empty( $transform ) ) {
			return $matrix;
		}
		if ( preg_match_all( '/(matrix|translate|scale)\s*\(([^)]*)\)/i', $transform, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $m ) {
				$type  = strtolower( $m[1] );
				$parts = preg_split( '/[\s,]+/', trim( $m[2] ) );
				$nums  = array();
				foreach ( $parts as $part ) {
					if ( '' !== $part ) {
						$nums[] = (float) $part;
					}
				}
				$local = array( 1, 0, 0, 1, 0, 0 );
				if ( 'matrix' === $type && count( $nums ) >= 6 ) {
					$local = array_slice( $nums, 0, 6 );
				} elseif ( 'translate' === $type ) {
					$local = array( 1, 0, 0, 1, $nums[0] ?? 0, $nums[1] ?? 0 );
				} elseif ( 'scale' === $type ) {
					$sx = $nums[0] ?? 1;
					$sy = isset( $nums[1] ) ? $nums[1] : $sx;
					$local = array( $sx, 0, 0, $sy, 0, 0 );
				}
				$matrix = self::multiply_matrix( $matrix, $local );
			}
		}
		return $matrix;
	}

	/**
	 * @param array $a Matrix A.
	 * @param array $b Matrix B.
	 * @return array
	 */
	public static function multiply_matrix( $a, $b ) {
		return array(
			$a[0] * $b[0] + $a[2] * $b[1],
			$a[1] * $b[0] + $a[3] * $b[1],
			$a[0] * $b[2] + $a[2] * $b[3],
			$a[1] * $b[2] + $a[3] * $b[3],
			$a[0] * $b[4] + $a[2] * $b[5] + $a[4],
			$a[1] * $b[4] + $a[3] * $b[5] + $a[5],
		);
	}

	/**
	 * All path loops from scene (for exporters).
	 *
	 * @param array $scene Scene.
	 * @return array<int,array>
	 */
	public static function path_loops_from_scene( $scene ) {
		$loops = array();
		foreach ( $scene['layers'] ?? array() as $layer ) {
			if ( 'path' !== ( $layer['type'] ?? '' ) || empty( $layer['verts'] ) ) {
				continue;
			}
			foreach ( self::split_path_layer_subpaths( $layer ) as $chunk ) {
				$loops[] = self::path_loop_from_layer_chunk( $chunk );
			}
		}
		return $loops;
	}

	/**
	 * Split a path layer into one layer per SVG subpath (LightBurn renders one contour per shape).
	 *
	 * @param array $layer Path layer.
	 * @return array<int,array>
	 */
	private static function split_path_layer_subpaths( $layer ) {
		$starts = array();
		if ( ! empty( $layer['subpath_starts'] ) && is_array( $layer['subpath_starts'] ) ) {
			foreach ( $layer['subpath_starts'] as $b ) {
				$starts[] = (int) $b;
			}
		}
		$starts = array_values( array_unique( array_filter( $starts, function ( $i ) {
			return $i > 0;
		} ) ) );
		sort( $starts );
		if ( count( $starts ) < 1 ) {
			return array( $layer );
		}

		$verts = $layer['verts'];
		$count = count( $verts );
		$bounds = array_merge( array( 0 ), $starts, array( $count ) );
		$bounds = array_values( array_unique( $bounds ) );
		sort( $bounds );

		$chunks = array();
		$base_id = (string) ( $layer['layer_id'] ?? ( $layer['role'] ?? 'path' ) );
		for ( $i = 0; $i + 1 < count( $bounds ); $i++ ) {
			$start = (int) $bounds[ $i ];
			$end   = (int) $bounds[ $i + 1 ];
			if ( $end - $start < 2 ) {
				continue;
			}
			$slice_verts = array_slice( $verts, $start, $end - $start );
			$slice_prims = array();
			if ( ! empty( $layer['prims'] ) && is_array( $layer['prims'] ) ) {
				foreach ( $layer['prims'] as $prim ) {
					$p0 = (int) ( $prim['p0'] ?? -1 );
					$p1 = (int) ( $prim['p1'] ?? -1 );
					if ( $p0 < $start || $p1 < $start || $p0 >= $end || $p1 >= $end ) {
						continue;
					}
					$slice_prims[] = array(
						'p0' => $p0 - $start,
						'p1' => $p1 - $start,
						't'  => $prim['t'] ?? 'L',
					);
				}
			}
			$chunk = $layer;
			$chunk['verts']           = $slice_verts;
			$chunk['prims']           = $slice_prims;
			$chunk['subpath_starts']  = array();
			$chunk['closed']          = self::chunk_path_is_closed( $slice_verts, $slice_prims );
			$chunk['layer_id']        = $base_id . '-sp' . ( count( $chunks ) + 1 );
			$chunks[] = $chunk;
		}

		return ! empty( $chunks ) ? $chunks : array( $layer );
	}

	/**
	 * Whether a subpath chunk is closed (Z or closing segment to first vertex).
	 *
	 * @param array $verts Vertices.
	 * @param array $prims Primitives.
	 * @return bool
	 */
	private static function chunk_path_is_closed( $verts, $prims ) {
		$n = count( $verts );
		if ( $n < 3 ) {
			return false;
		}
		foreach ( $prims as $prim ) {
			if ( 0 === (int) ( $prim['p1'] ?? -1 ) && ( $n - 1 ) === (int) ( $prim['p0'] ?? -2 ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Build export loop metadata from a (possibly split) path layer chunk.
	 *
	 * @param array $layer Path layer chunk.
	 * @return array
	 */
	private static function path_loop_from_layer_chunk( $layer ) {
		$verts = $layer['verts'];
		$prims = ! empty( $layer['prims'] ) && is_array( $layer['prims'] ) ? $layer['prims'] : array();
		$breaks = array();
		if ( ! empty( $layer['subpath_starts'] ) && is_array( $layer['subpath_starts'] ) ) {
			foreach ( $layer['subpath_starts'] as $b ) {
				$breaks[ (int) $b ] = true;
			}
		}
		if ( empty( $prims ) ) {
			for ( $i = 1; $i < count( $verts ); $i++ ) {
				if ( ! empty( $breaks[ $i ] ) ) {
					continue;
				}
				$prims[] = array( 'p0' => $i - 1, 'p1' => $i, 't' => 'L' );
			}
		}
		if ( ! empty( $layer['closed'] ) && count( $verts ) > 2 ) {
			$close_to = 0;
			$last     = count( $verts ) - 1;
			$has_close = false;
			foreach ( $prims as $prim ) {
				if ( $close_to === (int) ( $prim['p1'] ?? -1 ) && $last === (int) ( $prim['p0'] ?? -2 ) ) {
					$has_close = true;
					break;
				}
			}
			if ( ! $has_close && $last !== $close_to && empty( $breaks[ $close_to ] ) ) {
				$prims[] = array( 'p0' => $last, 'p1' => $close_to, 't' => 'L' );
			}
		}
		return array(
			'verts'          => $verts,
			'prims'          => $prims,
			'closed'         => ! empty( $layer['closed'] ),
			'fill'           => $layer['fill'] ?? '#000000',
			'role'           => $layer['role'] ?? 'engrave',
			'layer_id'       => $layer['layer_id'] ?? ( $layer['role'] ?? 'engrave' ),
			'subpath_starts' => array(),
		);
	}

	/**
	 * Text layers from scene.
	 *
	 * @param array $scene Scene.
	 * @return array
	 */
	public static function text_layers_from_scene( $scene ) {
		$texts = array();
		foreach ( $scene['layers'] ?? array() as $layer ) {
			if ( 'text' === ( $layer['type'] ?? '' ) ) {
				$texts[] = $layer;
			}
		}
		return $texts;
	}
}
