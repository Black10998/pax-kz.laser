<?php
/**
 * LightBurn-ready production SVG (mm units, vector layout).
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PCKZ_Production_Svg
 */
class PCKZ_Production_Svg {

	const SVG_VERSION = '1.0';

	/**
	 * Build production SVG document from saved production package.
	 *
	 * @param array $package Package from PCKZ_Production::build_package().
	 * @return string|WP_Error
	 */
	public static function build_from_package( $package ) {
		$master = PCKZ_Production_Scene::get_master_svg( $package );
		if ( '' === trim( $master ) ) {
			return new WP_Error(
				'empty_export',
				__( 'No WYSIWYG vector snapshot. Re-save the design after the preview has fully loaded.', 'pckz-canonical-engine' )
			);
		}
		return self::wrap_master_svg( $master, $package );
	}

	/**
	 * Pass through browser Fabric SVG unchanged; only add metadata and guide zones.
	 *
	 * @param string $master  Fabric-exported SVG (mm viewBox).
	 * @param array  $package Package.
	 * @return string
	 */
	public static function wrap_master_svg( $master, $package = array() ) {
		$layout = $package['layout'] ?? $package['lightburn_ready'] ?? array();
		$canvas = $layout['canvas_mm'] ?? ( class_exists( 'PCKZ_Plate_Calibration' )
			? PCKZ_Plate_Calibration::default_canvas_mm_array()
			: array( 'width' => 529.1, 'height' => 116 ) );
		$w      = (float) ( $canvas['width'] ?? ( class_exists( 'PCKZ_Plate_Calibration' ) ? PCKZ_Plate_Calibration::TOTAL_WIDTH_MM : 529.1 ) );
		$h      = (float) ( $canvas['height'] ?? ( class_exists( 'PCKZ_Plate_Calibration' ) ? PCKZ_Plate_Calibration::PLATE_HEIGHT_MM : 116 ) );
		$origin = $layout['coordinate_origin'] ?? 'bottom-left';

		$meta_block  = '<title>' . esc_xml( __( 'PCKZ Canonical Engine — Production', 'pckz-canonical-engine' ) ) . '</title>';
		$meta_block .= '<desc>' . esc_xml(
			sprintf(
				__( 'WYSIWYG Fabric export · PCKZ %1$s · Design %2$s', 'pckz-canonical-engine' ),
				PCKZCE_VERSION,
				(int) ( $package['design_id'] ?? 0 )
			)
		) . '</desc>';
		$meta_block .= '<metadata id="pckz-meta"><pckz:export xmlns:pckz="https://pckz-canonical-engine.local/export" format="fabric-wysiwyg" units="mm"/></metadata>';

		$guides  = '<g id="pckz-guides" fill="none" stroke="#9a9a9a" stroke-width="0.15" opacity="0.65">';
		$guides .= self::render_zone_rect( $layout['safe_zone_mm'] ?? null, 'safe-zone', $w, $h, $origin );
		$guides .= self::render_zone_rect( $layout['strip_zone_mm'] ?? null, 'strip-zone', $w, $h, $origin );
		$guides .= '</g>';

		$out = $master;
		if ( ! preg_match( '/xmlns="http:\/\/www\.w3\.org\/2000\/svg"/i', $out ) ) {
			$out = preg_replace( '/<svg\b/i', '<svg xmlns="http://www.w3.org/2000/svg"', $out, 1 );
		}
		$out = preg_replace( '/(<svg[^>]*>)/i', '$1' . $meta_block . $guides, $out, 1 );
		return $out;
	}

	/**
	 * Save SVG to uploads and return public URL.
	 *
	 * @param array $package   Production package.
	 * @param int   $design_id Design ID.
	 * @return string|WP_Error
	 */
	public static function save_from_package( $package, $design_id = 0 ) {
		$markup = self::build_from_package( $package );
		if ( is_wp_error( $markup ) ) {
			return $markup;
		}
		return self::save_markup( $markup, $design_id );
	}

	/**
	 * Write SVG string to disk.
	 *
	 * @param string $markup    SVG content.
	 * @param int    $design_id Design ID.
	 * @return string|WP_Error
	 */
	public static function save_markup( $markup, $design_id = 0 ) {
		$upload_dir = wp_upload_dir();
		$dir        = $upload_dir['basedir'] . '/pckz-canonical-engine/production-svg';
		wp_mkdir_p( $dir );

		$filename = 'production';
		if ( $design_id ) {
			$filename .= '-design-' . (int) $design_id;
		}
		$filename .= '-' . wp_generate_uuid4() . '.svg';

		$filepath = trailingslashit( $dir ) . $filename;
		if ( false === file_put_contents( $filepath, $markup ) ) {
			return new WP_Error( 'write_error', __( 'Could not write production SVG.', 'pckz-canonical-engine' ) );
		}

		return str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $filepath );
	}

	/**
	 * Sort objects: lines → icons → text.
	 *
	 * @param array $objects Objects.
	 * @return array
	 */
	private static function sort_objects_for_render( $objects ) {
		$order = array(
			'lines'         => 10,
			'line-overlay'  => 10,
			'strip-lines'   => 10,
			'icon-bg-left'  => 18,
			'icon-bg-right' => 19,
			'icon-left'     => 20,
			'icon-right'    => 21,
			'text'          => 30,
			'main-text'     => 30,
		);
		usort(
			$objects,
			function ( $a, $b ) use ( $order ) {
				$ra = $order[ $a['role'] ?? '' ] ?? 15;
				$rb = $order[ $b['role'] ?? '' ] ?? 15;
				return $ra - $rb;
			}
		);
		return $objects;
	}

	/**
	 * @param array $ordered Objects.
	 * @param string $role  Role.
	 * @return bool
	 */
	private static function object_already_rendered( $ordered, $role ) {
		foreach ( $ordered as $obj ) {
			if ( ( $obj['role'] ?? '' ) === $role ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Render one layout object.
	 *
	 * @param array  $obj         Object.
	 * @param float  $canvas_w    Canvas width mm.
	 * @param float  $canvas_h    Canvas height mm.
	 * @param string $origin      Origin.
	 * @param array  $design_px   Design space.
	 * @param array  $selections  Selections.
	 * @return string
	 */
	private static function render_object( $obj, $canvas_w, $canvas_h, $origin, $design_px, $selections ) {
		$obj  = PCKZ_Production_Geometry::normalize_layout_object( $obj );
		$role = $obj['role'] ?? '';
		if ( in_array( $role, array( 'text', 'main-text' ), true ) ) {
			return self::render_text( $obj, $canvas_w, $canvas_h, $origin, $design_px );
		}
		if ( in_array( $role, array( 'icon-left', 'icon-right', 'icon-bg-left', 'icon-bg-right', 'lines' ), true ) ) {
			$url  = PCKZ_Production_Geometry::asset_url_for_object( $obj, $selections );
			$fill = self::resolve_hex( $obj['fill'] ?? ( 'lines' === $role ? ( $selections['line_color'] ?? '' ) : '' ) );
			if ( PCKZ_Production_Geometry::prefer_embedded_svg( $obj ) ) {
				return self::render_embedded_svg( $url, $obj, $canvas_w, $canvas_h, $origin, $fill, $selections );
			}
			return self::render_vector_paths( $url, $obj, $canvas_w, $canvas_h, $origin, $fill, $selections );
		}
		return '';
	}

	/**
	 * Render text element.
	 *
	 * @param array $obj       Object.
	 * @param float $canvas_w  W mm.
	 * @param float $canvas_h  H mm.
	 * @param string $origin   Origin.
	 * @param array $design_px Design px.
	 * @return string
	 */
	private static function render_text( $obj, $canvas_w, $canvas_h, $origin, $design_px ) {
		$box = self::object_box_mm( $obj, $canvas_w, $canvas_h, $origin );
		if ( ! $box ) {
			return '';
		}

		$text = trim( (string) ( $obj['text'] ?? '' ) );
		if ( '' === $text ) {
			return '';
		}

		$cx = $box['center_x'];
		$cy = $box['center_y_svg'];
		$fill = self::resolve_hex( $obj['fill'] ?? '#000000' );
		$font = esc_attr( $obj['font_family'] ?? $obj['fontFamily'] ?? 'Arial' );

		$design_h = (float) ( $design_px['height'] ?? 2132 );
		$font_px  = (float) ( $obj['font_size_px'] ?? 55 );
		$font_mm  = $design_h > 0 ? ( $font_px / $design_h ) * $canvas_h : 8;
		$font_mm  = max( 3, min( $box['height'] * 0.95, $font_mm ) );

		$stroke = '';
		if ( ! empty( $obj['stroke'] ) && ! empty( $obj['stroke_width'] ) ) {
			$sw = (float) $obj['stroke_width'] * ( $canvas_h / max( 1, $design_h ) );
			$stroke = sprintf(
				' stroke="%s" stroke-width="%s" paint-order="stroke fill"',
				esc_attr( self::resolve_hex( $obj['stroke'] ) ),
				self::fmt( max( 0.1, $sw ) )
			);
		}

		return sprintf(
			'<text id="pckz-text" x="%s" y="%s" font-family="%s" font-size="%s" fill="%s" text-anchor="middle" dominant-baseline="middle"%s>%s</text>',
			self::fmt( $cx ),
			self::fmt( $cy ),
			$font,
			self::fmt( $font_mm ),
			esc_attr( $fill ),
			$stroke,
			esc_xml( $text )
		);
	}

	/**
	 * Render vector paths at mm position (same geometry as LightBurn export).
	 *
	 * @param string $url       SVG URL.
	 * @param array  $obj       Layout object.
	 * @param float  $canvas_w  W.
	 * @param float  $canvas_h  H.
	 * @param string $origin    Origin.
	 * @param string $fill_hex  Tint color.
	 * @return string
	 */
	private static function render_vector_paths( $url, $obj, $canvas_w, $canvas_h, $origin, $fill_hex, $selections = array() ) {
		if ( empty( $url ) && empty( $obj['svg_source'] ) ) {
			return '';
		}

		$box = PCKZ_Production_Geometry::object_box_mm( $obj, $canvas_w, $canvas_h, $origin );
		if ( ! $box ) {
			return '';
		}

		$role  = esc_attr( $obj['role'] ?? 'asset' );
		$loops = PCKZ_Production_Geometry::paths_for_object_mm( $obj, $box, $selections );
		if ( empty( $loops ) ) {
			$y_svg = PCKZ_Production_Geometry::mm_y_to_svg_top( $box['y'], $box['height'], $canvas_h, $origin );
			return sprintf(
				'<rect id="pckz-%s-fallback" x="%s" y="%s" width="%s" height="%s" fill="none" stroke="%s" stroke-width="0.2"/>',
				$role,
				PCKZ_Production_Geometry::fmt( $box['x'] ),
				PCKZ_Production_Geometry::fmt( $y_svg ),
				PCKZ_Production_Geometry::fmt( $box['width'] ),
				PCKZ_Production_Geometry::fmt( $box['height'] ),
				esc_attr( $fill_hex )
			);
		}

		$parts = array();
		$parts[] = sprintf( '<g id="pckz-%s" fill="%s" stroke="none">', $role, esc_attr( $fill_hex ) );
		foreach ( $loops as $idx => $loop ) {
			$d = PCKZ_Production_Geometry::mm_verts_to_svg_path_d( $loop['verts'], ! empty( $loop['closed'] ), $canvas_h );
			if ( $d ) {
				$parts[] = sprintf(
					'<path id="pckz-%s-%d" d="%s" fill="%s" fill-rule="evenodd"/>',
					$role,
					(int) $idx,
					esc_attr( $d ),
					esc_attr( $fill_hex )
				);
			}
		}
		$parts[] = '</g>';
		return implode( "\n", $parts );
	}

	/**
	 * Embed line-type SVG at mm position (preserves ellipse layout vs flattened fills).
	 *
	 * @param string $url         Asset URL.
	 * @param array  $obj         Layout object.
	 * @param float  $canvas_w    Canvas W mm.
	 * @param float  $canvas_h    Canvas H mm.
	 * @param string $origin      Coordinate origin.
	 * @param string $fill_hex    Tint color.
	 * @param array  $selections  Selections.
	 * @return string
	 */
	private static function render_embedded_svg( $url, $obj, $canvas_w, $canvas_h, $origin, $fill_hex, $selections = array() ) {
		$body = $obj['svg_source'] ?? '';
		if ( '' === $body && $url ) {
			$body = PCKZ_Production_Geometry::fetch_svg_body( $url );
		}
		if ( '' === $body ) {
			return self::render_vector_paths( $url, $obj, $canvas_w, $canvas_h, $origin, $fill_hex, $selections );
		}

		$box = PCKZ_Production_Geometry::object_box_mm( $obj, $canvas_w, $canvas_h, $origin );
		if ( ! $box ) {
			return '';
		}

		$vb     = PCKZ_Production_Geometry::svg_viewbox_size( $body );
		$fit    = PCKZ_Production_Geometry::fit_transform_for_box( $vb['w'], $vb['h'], $box );
		$y_svg  = PCKZ_Production_Geometry::mm_y_to_svg_top( $fit['offset_y'], $fit['content_h'], $canvas_h, $origin );
		$inner  = self::extract_svg_drawable_inner( $body );
		if ( '' === $inner ) {
			return self::render_vector_paths( $url, $obj, $canvas_w, $canvas_h, $origin, $fill_hex, $selections );
		}

		$role = esc_attr( $obj['role'] ?? 'lines' );
		$sx   = $fit['content_w'] / $vb['w'];
		$sy   = $fit['content_h'] / $vb['h'];

		return sprintf(
			'<g id="pckz-%s" fill="%s" stroke="none">' . "\n" .
			'<g transform="translate(%s %s) scale(%s %s)">%s</g>' . "\n" .
			'</g>',
			$role,
			esc_attr( $fill_hex ),
			PCKZ_Production_Geometry::fmt( $fit['offset_x'] ),
			PCKZ_Production_Geometry::fmt( $y_svg ),
			PCKZ_Production_Geometry::fmt( $sx ),
			PCKZ_Production_Geometry::fmt( $sy ),
			$inner
		);
	}

	/**
	 * Drawable SVG content without outer svg wrapper.
	 *
	 * @param string $body SVG file.
	 * @return string
	 */
	private static function extract_svg_drawable_inner( $body ) {
		$body = preg_replace( '/<\?xml[^?]*\?>/i', '', $body );
		$body = preg_replace( '/<!DOCTYPE[^>]*>/i', '', $body );
		if ( ! preg_match( '/<svg\b[^>]*>(.*)<\/svg>/is', $body, $m ) ) {
			return '';
		}
		return self::sanitize_svg_fragment( trim( $m[1] ) );
	}

	/**
	 * Normalize object to SVG box (top-left mm coords for SVG).
	 *
	 * @param array  $obj      Object.
	 * @param float  $canvas_w Canvas W.
	 * @param float  $canvas_h Canvas H.
	 * @param string $origin   Origin.
	 * @return array|null
	 */
	private static function object_box_mm( $obj, $canvas_w, $canvas_h, $origin ) {
		$mm = $obj['mm'] ?? $obj;
		$x  = isset( $mm['x_mm'] ) ? (float) $mm['x_mm'] : null;
		$y  = isset( $mm['y_mm'] ) ? (float) $mm['y_mm'] : null;
		$w  = isset( $mm['width_mm'] ) ? (float) $mm['width_mm'] : null;
		$h  = isset( $mm['height_mm'] ) ? (float) $mm['height_mm'] : null;

		if ( isset( $mm['center_x_mm'], $mm['center_y_mm'] ) ) {
			$cx = (float) $mm['center_x_mm'];
			$cy = (float) $mm['center_y_mm'];
			if ( ( null === $w || $w <= 0 ) && ! empty( $obj['design_px'] ) ) {
				$dpx    = $obj['design_px'];
				$design_w = (float) ( $dpx['width'] ?? $dpx['w'] ?? 0 );
				$design_h = (float) ( $dpx['height'] ?? $dpx['h'] ?? 0 );
				$dw       = class_exists( 'PCKZ_Ledos_Preview' ) ? (float) PCKZ_Ledos_Preview::DESIGN_WIDTH : 3651;
				$dh       = class_exists( 'PCKZ_Ledos_Preview' ) ? (float) PCKZ_Ledos_Preview::DESIGN_HEIGHT : 2132;
				$w        = $design_w > 0 ? ( $design_w / $dw ) * $canvas_w : 10;
				$h        = $design_h > 0 ? ( $design_h / $dh ) * $canvas_h : 10;
			}
			$w = max( 0.5, (float) ( $w ?? 10 ) );
			$h = max( 0.5, (float) ( $h ?? 10 ) );
			$x = $cx - $w / 2;
			$y = $cy - $h / 2;
		}

		if ( ( null === $x || null === $y ) && ! empty( $obj['design_px'] ) ) {
			$dpx = $obj['design_px'];
			$dw  = class_exists( 'PCKZ_Ledos_Preview' ) ? (float) PCKZ_Ledos_Preview::DESIGN_WIDTH : 3651;
			$dh  = class_exists( 'PCKZ_Ledos_Preview' ) ? (float) PCKZ_Ledos_Preview::DESIGN_HEIGHT : 2132;
			$dx  = (float) ( $dpx['x'] ?? $dpx['left'] ?? 0 );
			$dy  = (float) ( $dpx['y'] ?? $dpx['top'] ?? 0 );
			$dw_px = (float) ( $dpx['width'] ?? $dpx['w'] ?? 0 );
			$dh_px = (float) ( $dpx['height'] ?? $dpx['h'] ?? 0 );
			if ( $dw > 0 && $dh > 0 && $dw_px > 0 && $dh_px > 0 ) {
				$x = ( $dx / $dw ) * $canvas_w;
				$w = ( $dw_px / $dw ) * $canvas_w;
				$h = ( $dh_px / $dh ) * $canvas_h;
				$y_top = ( $dy / $dh ) * $canvas_h;
				if ( 'bottom-left' === $origin ) {
					$y = $canvas_h - $y_top - $h;
				} else {
					$y = $y_top;
				}
			}
		}

		if ( null === $x || null === $y || null === $w || null === $h || $w <= 0 || $h <= 0 ) {
			return null;
		}

		$y_svg  = self::mm_y_to_svg( $y, $h, $canvas_h, $origin );
		$cy_svg = ( 'bottom-left' === $origin ) ? ( $canvas_h - (float) ( $mm['center_y_mm'] ?? ( $y + $h / 2 ) ) ) : ( $y + $h / 2 );

		return array(
			'x'            => $x,
			'y'            => $y_svg,
			'width'        => $w,
			'height'       => $h,
			'center_x'     => $x + $w / 2,
			'center_y_svg' => $cy_svg,
			'scale_x'      => $w,
			'scale_y'      => $h,
		);
	}

	/**
	 * Convert bottom-left mm Y to SVG top-left Y.
	 *
	 * @param float  $y_mm     Y from bottom (or top per origin).
	 * @param float  $height   Object height.
	 * @param float  $canvas_h Canvas height.
	 * @param string $origin   Origin.
	 * @return float
	 */
	private static function mm_y_to_svg( $y_mm, $height, $canvas_h, $origin ) {
		if ( 'top-left' === $origin ) {
			return $y_mm;
		}
		return $canvas_h - $y_mm - $height;
	}

	/**
	 * Guide rectangle for safe/strip zones.
	 *
	 * @param array|null $zone     Zone.
	 * @param string     $id       Element id.
	 * @param float      $canvas_w W.
	 * @param float      $canvas_h H.
	 * @param string     $origin   Origin.
	 * @return string
	 */
	private static function render_zone_rect( $zone, $id, $canvas_w, $canvas_h, $origin ) {
		if ( empty( $zone ) || ! is_array( $zone ) ) {
			return '';
		}
		$x = (float) ( $zone['x'] ?? $zone['x_mm'] ?? 0 );
		$y = (float) ( $zone['y'] ?? $zone['y_mm'] ?? 0 );
		$w = (float) ( $zone['w'] ?? $zone['width_mm'] ?? 0 );
		$h = (float) ( $zone['h'] ?? $zone['height_mm'] ?? 0 );
		if ( $w <= 0 || $h <= 0 ) {
			return '';
		}
		$y_svg = self::mm_y_to_svg( $y, $h, $canvas_h, $origin );
		return sprintf(
			'<rect id="%s" x="%s" y="%s" width="%s" height="%s" stroke-dasharray="1.5,1"/>',
			esc_attr( $id ),
			self::fmt( $x ),
			self::fmt( $y_svg ),
			self::fmt( $w ),
			self::fmt( $h )
		);
	}

	/**
	 * Fetch remote/local SVG and return inner markup (normalized 0..1 scale group content).
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private static function fetch_svg_inner( $url ) {
		$cache_key = 'pckz_svg_' . md5( $url );
		$cached    = get_transient( $cache_key );
		if ( is_string( $cached ) ) {
			return $cached;
		}

		$body = '';
		if ( 0 === strpos( $url, home_url() ) || 0 === strpos( $url, PCKZCE_PLUGIN_URL ) ) {
			$path = str_replace( PCKZCE_PLUGIN_URL, PCKZCE_PLUGIN_DIR, $url );
			$path = str_replace( content_url(), WP_CONTENT_DIR, $path );
			if ( is_readable( $path ) ) {
				$body = file_get_contents( $path );
			}
		}
		if ( '' === $body ) {
			$response = wp_remote_get(
				$url,
				array(
					'timeout'   => 20,
					'sslverify' => true,
				)
			);
			if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
				$body = wp_remote_retrieve_body( $response );
			}
		}

		if ( '' === $body ) {
			return '';
		}

		$inner = self::extract_svg_inner( $body );
		if ( $inner ) {
			set_transient( $cache_key, $inner, DAY_IN_SECONDS );
		}
		return $inner;
	}

	/**
	 * Extract drawable content from SVG file.
	 *
	 * @param string $body SVG file content.
	 * @return string
	 */
	private static function extract_svg_inner( $body ) {
		$body = preg_replace( '/<\?xml[^?]*\?>/i', '', $body );
		$body = preg_replace( '/<!DOCTYPE[^>]*>/i', '', $body );
		if ( ! preg_match( '/<svg\b([^>]*)>(.*)<\/svg>/is', $body, $m ) ) {
			return '';
		}

		$attrs  = $m[1];
		$inner  = $m[2];
		$inner  = self::sanitize_svg_fragment( $inner );

		$vb_w = 100;
		$vb_h = 100;
		if ( preg_match( '/viewBox\s*=\s*["\']([^"\']+)["\']/i', $attrs, $vm ) ) {
			$parts = preg_split( '/\s+/', trim( $vm[1] ) );
			if ( count( $parts ) >= 4 ) {
				$vb_w = max( 0.001, (float) $parts[2] );
				$vb_h = max( 0.001, (float) $parts[3] );
			}
		} elseif ( preg_match( '/width\s*=\s*["\']([\d.]+)/i', $attrs, $wm ) && preg_match( '/height\s*=\s*["\']([\d.]+)/i', $attrs, $hm ) ) {
			$vb_w = max( 0.001, (float) $wm[1] );
			$vb_h = max( 0.001, (float) $hm[1] );
		}

		return sprintf(
			'<g transform="scale(%s,%s)">%s</g>',
			self::fmt( 1 / $vb_w ),
			self::fmt( 1 / $vb_h ),
			$inner
		);
	}

	/**
	 * Strip unsafe nodes from SVG fragment.
	 *
	 * @param string $fragment Markup.
	 * @return string
	 */
	private static function sanitize_svg_fragment( $fragment ) {
		$fragment = preg_replace( '/<script\b[^>]*>.*?<\/script>/is', '', $fragment );
		$fragment = preg_replace( '/<foreignObject\b[^>]*>.*?<\/foreignObject>/is', '', $fragment );
		return trim( $fragment );
	}

	/**
	 * Apply customer color to SVG paths.
	 *
	 * @param string $fragment SVG inner.
	 * @param string $hex      Color.
	 * @return string
	 */
	private static function apply_fill_to_svg_fragment( $fragment, $hex ) {
		if ( empty( $hex ) ) {
			return $fragment;
		}
		$hex = esc_attr( $hex );
		$fragment = preg_replace( '/fill\s*=\s*["\'][^"\']*["\']/i', 'fill="' . $hex . '"', $fragment );
		if ( false === stripos( $fragment, 'fill=' ) ) {
			$fragment = '<g fill="' . $hex . '">' . $fragment . '</g>';
		}
		return $fragment;
	}

	/**
	 * Resolve swatch or hex to hex color.
	 *
	 * @param string $value Color value.
	 * @return string
	 */
	private static function resolve_hex( $value ) {
		if ( empty( $value ) ) {
			return '#000000';
		}
		if ( preg_match( '/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', (string) $value ) ) {
			return sanitize_hex_color( $value ) ?: '#000000';
		}
		if ( class_exists( 'PCKZ_Ledos_Preview' ) ) {
			$hex = PCKZ_Ledos_Preview::hex_for_color_value( $value );
			if ( $hex ) {
				return $hex;
			}
		}
		return '#000000';
	}

	/**
	 * Format float for SVG output.
	 *
	 * @param float $n Number.
	 * @return string
	 */
	private static function fmt( $n ) {
		return rtrim( rtrim( sprintf( '%.4f', (float) $n ), '0' ), '.' );
	}
}
