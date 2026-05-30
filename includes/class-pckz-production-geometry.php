<?php
/**
 * Shared mm geometry for production exporters (SVG, DXF, LightBurn).
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PCKZ_Production_Geometry
 */
class PCKZ_Production_Geometry {
	/**
	 * Target drawable coverage for symbol icons (prevents oversized outliers).
	 */
	const ICON_TARGET_COVERAGE = 0.82;

	/**
	 * Normalize package into export context.
	 *
	 * @param array $package Production package.
	 * @return array
	 */
	public static function normalize_package( $package, $strict_canonical = false ) {
		$layout = $package['layout'] ?? $package['lightburn_ready'] ?? array();
		$lb     = $package['lightburn_package'] ?? array();
		$strict = $strict_canonical || ! empty( $package['canonical_scene'] ) || ! empty( $layout['canonical_scene'] );

		if ( empty( $layout['objects'] ) && ! empty( $lb['objects'] ) && ! $strict ) {
			$layout['objects'] = $lb['objects'];
		}
		if ( empty( $layout['canvas_mm'] ) && ! empty( $lb['canvas_mm'] ) ) {
			$layout['canvas_mm'] = $lb['canvas_mm'];
		}

		$canvas = $layout['canvas_mm'] ?? PCKZ_Plate_Calibration::default_canvas_mm_array();

		$selections = $package['selections'] ?? $layout['selections'] ?? $lb['selections'] ?? array();
		$canvas_w   = (float) ( $canvas['width'] ?? ( class_exists( 'PCKZ_Plate_Calibration' ) ? PCKZ_Plate_Calibration::TOTAL_WIDTH_MM : 529.1 ) );
		$canvas_h   = (float) ( $canvas['height'] ?? ( class_exists( 'PCKZ_Plate_Calibration' ) ? PCKZ_Plate_Calibration::PLATE_HEIGHT_MM : 116 ) );
		$origin     = 'bottom-left';

		$objects = (array) ( $layout['objects'] ?? array() );
		if ( ! $strict && class_exists( 'PCKZ_Ledos_Preview' ) ) {
			$objects = PCKZ_Ledos_Preview::ensure_production_objects( $objects, $selections, $canvas_w, $canvas_h, $origin );
		}

		return array(
			'package'    => $package,
			'layout'     => array_merge( $layout, array( 'objects' => $objects ) ),
			'lb'         => $lb,
			'selections' => $selections,
			'canvas_w'   => $canvas_w,
			'canvas_h'   => $canvas_h,
			'origin'     => $origin,
			'design_px'  => $layout['design_px'] ?? array( 'width' => 3651, 'height' => 2132 ),
			'objects'    => array_map(
				array( __CLASS__, 'normalize_layout_object' ),
				$objects
			),
			'design_id'  => (int) ( $package['design_id'] ?? $lb['design_id'] ?? 0 ),
		);
	}

	/**
	 * Sort objects for z-order.
	 *
	 * @param array $objects Objects.
	 * @return array
	 */
	public static function sort_objects( $objects ) {
		$order = array(
			'lines'         => 10,
			'line-overlay'  => 10,
			'strip-lines'   => 10,
			'strip-line'    => 10,
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
	 * Object box in mm (bottom-left origin) + derived centers.
	 *
	 * @param array  $obj      Object.
	 * @param float  $canvas_w Canvas W mm.
	 * @param float  $canvas_h Canvas H mm.
	 * @param string $origin   Coordinate origin.
	 * @return array|null
	 */
	public static function object_box_mm( $obj, $canvas_w, $canvas_h, $origin ) {
		$mm = $obj['mm'] ?? $obj;
		$x  = isset( $mm['x_mm'] ) ? (float) $mm['x_mm'] : null;
		$y  = isset( $mm['y_mm'] ) ? (float) $mm['y_mm'] : null;
		$w  = isset( $mm['width_mm'] ) ? (float) $mm['width_mm'] : null;
		$h  = isset( $mm['height_mm'] ) ? (float) $mm['height_mm'] : null;

		if ( ( null === $x || null === $y ) && isset( $mm['center_x_mm'], $mm['center_y_mm'] ) ) {
			$cx = (float) $mm['center_x_mm'];
			$cy = (float) $mm['center_y_mm'];
			if ( ( null === $w || $w <= 0 ) && ! empty( $obj['design_px'] ) ) {
				$size = self::design_px_to_mm_size( $obj['design_px'], $canvas_w, $canvas_h );
				$w    = $size['w'];
				$h    = $size['h'];
			}
			$w = max( 0.5, (float) ( $w ?? 10 ) );
			$h = max( 0.5, (float) ( $h ?? 10 ) );
			if ( null === $x ) {
				$x = $cx - $w / 2;
			}
			if ( null === $y ) {
				$y = $cy - $h / 2;
			}
		}

		if ( ( null === $x || null === $y ) && ! empty( $obj['design_px'] ) ) {
			$dpx   = $obj['design_px'];
			$dw    = class_exists( 'PCKZ_Ledos_Preview' ) ? (float) PCKZ_Ledos_Preview::DESIGN_WIDTH : 3651;
			$dh    = class_exists( 'PCKZ_Ledos_Preview' ) ? (float) PCKZ_Ledos_Preview::DESIGN_HEIGHT : 2132;
			$dx    = (float) ( $dpx['x'] ?? $dpx['left'] ?? 0 );
			$dy    = (float) ( $dpx['y'] ?? $dpx['top'] ?? 0 );
			$dw_px = (float) ( $dpx['width'] ?? $dpx['w'] ?? 0 );
			$dh_px = (float) ( $dpx['height'] ?? $dpx['h'] ?? 0 );
			if ( $dw > 0 && $dh > 0 && $dw_px > 0 && $dh_px > 0 ) {
				$x     = ( $dx / $dw ) * $canvas_w;
				$w     = ( $dw_px / $dw ) * $canvas_w;
				$h     = ( $dh_px / $dh ) * $canvas_h;
				$y_top = ( $dy / $dh ) * $canvas_h;
				$y     = ( 'bottom-left' === $origin ) ? ( $canvas_h - $y_top - $h ) : $y_top;
			}
		}

		if ( null === $x || null === $y || null === $w || null === $h || $w <= 0 || $h <= 0 ) {
			return null;
		}

		$center_x = (float) ( $mm['center_x_mm'] ?? ( $x + $w / 2 ) );
		$center_y = (float) ( $mm['center_y_mm'] ?? ( $y + $h / 2 ) );

		return array(
			'x'        => $x,
			'y'        => $y,
			'width'    => $w,
			'height'   => $h,
			'center_x' => $center_x,
			'center_y' => $center_y,
			'y_svg'    => self::mm_y_to_svg_top( $y, $h, $canvas_h, $origin ),
		);
	}


	/**
	 * Normalize mm record to lightburn-mm-bottom-left bbox (preserves explicit corners).
	 *
	 * @param array $mm Mm record.
	 * @return array|null
	 */
	public static function bbox_from_mm( $mm ) {
		if ( empty( $mm ) || ! is_array( $mm ) ) {
			return null;
		}
		$x = isset( $mm['x_mm'] ) ? (float) $mm['x_mm'] : null;
		$y = isset( $mm['y_mm'] ) ? (float) $mm['y_mm'] : null;
		$w = isset( $mm['width_mm'] ) ? (float) $mm['width_mm'] : null;
		$h = isset( $mm['height_mm'] ) ? (float) $mm['height_mm'] : null;
		if ( null === $x || null === $y || null === $w || null === $h || $w <= 0 || $h <= 0 ) {
			return null;
		}
		return array(
			'x_mm'        => round( $x, 3 ),
			'y_mm'        => round( $y, 3 ),
			'width_mm'    => round( $w, 3 ),
			'height_mm'   => round( $h, 3 ),
			'center_x_mm' => round( (float) ( $mm['center_x_mm'] ?? ( $x + $w / 2 ) ), 3 ),
			'center_y_mm' => round( (float) ( $mm['center_y_mm'] ?? ( $y + $h / 2 ) ), 3 ),
		);
	}

	/**
	 * Zone rectangle in mm (bottom-left).
	 *
	 * @param array|null $zone Zone.
	 * @return array|null
	 */
	public static function zone_box_mm( $zone ) {
		if ( empty( $zone ) || ! is_array( $zone ) ) {
			return null;
		}
		$w = (float) ( $zone['w'] ?? $zone['width_mm'] ?? 0 );
		$h = (float) ( $zone['h'] ?? $zone['height_mm'] ?? 0 );
		if ( $w <= 0 || $h <= 0 ) {
			return null;
		}
		return array(
			'x'      => (float) ( $zone['x'] ?? $zone['x_mm'] ?? 0 ),
			'y'      => (float) ( $zone['y'] ?? $zone['y_mm'] ?? 0 ),
			'width'  => $w,
			'height' => $h,
		);
	}

	/**
	 * @param array $design_px Design px.
	 * @param float $canvas_w  W.
	 * @param float $canvas_h  H.
	 * @return array
	 */
	public static function design_px_to_mm_size( $design_px, $canvas_w, $canvas_h ) {
		$dw = class_exists( 'PCKZ_Ledos_Preview' ) ? (float) PCKZ_Ledos_Preview::DESIGN_WIDTH : 3651;
		$dh = class_exists( 'PCKZ_Ledos_Preview' ) ? (float) PCKZ_Ledos_Preview::DESIGN_HEIGHT : 2132;
		$w  = (float) ( $design_px['width'] ?? $design_px['w'] ?? 0 );
		$h  = (float) ( $design_px['height'] ?? $design_px['h'] ?? 0 );
		return array(
			'w' => $dw > 0 ? ( $w / $dw ) * $canvas_w : 10,
			'h' => $dh > 0 ? ( $h / $dh ) * $canvas_h : 10,
		);
	}

	/**
	 * Bottom-left mm Y → SVG top-left Y.
	 *
	 * @param float  $y_mm     Y bottom.
	 * @param float  $height   H.
	 * @param float  $canvas_h Canvas H.
	 * @param string $origin   Origin.
	 * @return float
	 */
	public static function mm_y_to_svg_top( $y_mm, $height, $canvas_h, $origin ) {
		if ( 'top-left' === $origin ) {
			return $y_mm;
		}
		return $canvas_h - $y_mm - $height;
	}


	/**
	 * Normalize line type key (type-1 → type_1, yes → type_1).
	 *
	 * @param string $line_type Line type.
	 * @return string
	 */
	public static function normalize_line_type_key( $line_type ) {
		$lt = sanitize_key( (string) $line_type );
		if ( 'yes' === $lt ) {
			return 'type_1';
		}
		if ( preg_match( '/^type[-_](\d+)$/', $lt, $m ) ) {
			return 'type_' . $m[1];
		}
		return $lt;
	}

	/**
	 * Resolve SVG markup for a layout object (local plugin files first, then remote).
	 *
	 * @param array $obj        Layout object.
	 * @param array $selections Selections.
	 * @return string
	 */
	public static function resolve_svg_body_for_object( $obj, $selections = array() ) {
		if ( ! empty( $obj['svg_source'] ) ) {
			return (string) $obj['svg_source'];
		}

		$candidates = array();
		foreach ( array( 'svg_url', 'svg_ref' ) as $key ) {
			if ( ! empty( $obj[ $key ] ) ) {
				$candidates[] = (string) $obj[ $key ];
			}
		}

		$local_url = self::asset_url_for_object( $obj, $selections, true );
		if ( $local_url ) {
			$candidates[] = $local_url;
		}

		$resolved_url = self::asset_url_for_object( $obj, $selections );
		if ( $resolved_url ) {
			$candidates[] = $resolved_url;
		}

		$candidates = array_values( array_unique( array_filter( $candidates ) ) );
		foreach ( $candidates as $url ) {
			$body = self::fetch_svg_body( $url );
			if ( '' !== trim( $body ) ) {
				return $body;
			}
		}

		return '';
	}

	/**
	 * Resolve asset URL for icon/line object.
	 *
	 * @param array  $obj         Object.
	 * @param array  $selections  Selections.
	 * @return string
	 */
	public static function asset_url_for_object( $obj, $selections = array(), $local_only = false ) {
		$role = $obj['role'] ?? '';
		if ( in_array( $role, array( 'icon-left', 'icon-right', 'icon-bg-left', 'icon-bg-right' ), true ) ) {
			$slug = $obj['symbol'] ?? '';
			if ( ( '' === $slug || 'none' === $slug ) && ! empty( $selections ) ) {
				$slug = ( 'icon-left' === $role || 'icon-bg-left' === $role )
					? ( $selections['symbol_links'] ?? '' )
					: ( $selections['symbol_rechts'] ?? '' );
			}
			if ( $slug && 'none' !== $slug && class_exists( 'PCKZ_Icons' ) ) {
				$local = PCKZ_Icons::icon_url( $slug, 'white' ) ?: PCKZ_Icons::icon_url( $slug, 'black' );
				if ( $local ) {
					return $local;
				}
			}
			if ( $local_only ) {
				return '';
			}
			if ( ! empty( $obj['svg_url'] ) ) {
				return $obj['svg_url'];
			}
			if ( $slug && 'none' !== $slug && class_exists( 'PCKZ_Ledos_Preview' ) ) {
				$cat = PCKZ_Ledos_Preview::icon_catalog();
				if ( ! empty( $cat[ $slug ]['url'] ) ) {
					return $cat[ $slug ]['url'];
				}
			}
		}
		if ( in_array( $role, array( 'lines', 'strip-lines', 'strip-line', 'line-overlay' ), true ) ) {
			if ( $local_only ) {
				return '';
			}
			$lt = self::normalize_line_type_key( $obj['line_type'] ?? '' );
			if ( ( '' === $lt || 'none' === $lt || 'no' === $lt ) && ! empty( $selections['linien'] ) ) {
				$lt = self::normalize_line_type_key( $selections['linien'] );
			}
			if ( $lt && 'none' !== $lt && class_exists( 'PCKZ_Ledos_Preview' ) ) {
				$lines = PCKZ_Ledos_Preview::line_types();
				if ( ! empty( $lines[ $lt ] ) ) {
					return $lines[ $lt ];
				}
			}
			if ( ! empty( $obj['svg_url'] ) ) {
				return $obj['svg_url'];
			}
		}
		return '';
	}

	/**
	 * Fetch SVG file body.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function fetch_svg_body( $url ) {
		if ( empty( $url ) ) {
			return '';
		}
		$cache_key = 'pckz_svg_body_' . md5( $url );
		$cached    = get_transient( $cache_key );
		if ( is_string( $cached ) ) {
			return $cached;
		}

		$body = '';
		if ( function_exists( 'home_url' ) && ( 0 === strpos( $url, home_url() ) || 0 === strpos( $url, PCKZCE_PLUGIN_URL ) ) ) {
			$path = str_replace( PCKZCE_PLUGIN_URL, PCKZCE_PLUGIN_DIR, $url );
			if ( function_exists( 'content_url' ) ) {
				$path = str_replace( content_url(), WP_CONTENT_DIR, $path );
			}
			if ( function_exists( 'home_url' ) ) {
				$path = str_replace( home_url(), ABSPATH, $path );
			}
			if ( is_readable( $path ) ) {
				$body = file_get_contents( $path );
			}
		}
		if ( '' === $body ) {
			$response = wp_remote_get(
				$url,
				array(
					'timeout'   => 25,
					'sslverify' => true,
				)
			);
			if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
				$body = wp_remote_retrieve_body( $response );
			}
		}
		if ( $body ) {
			set_transient( $cache_key, $body, DAY_IN_SECONDS );
		}
		return $body;
	}

	/**
	 * Parse SVG path "d" in raw viewBox coordinates (SVG Y-down).
	 *
	 * @param string $d Path data.
	 * @return array{verts:array,prims:array,closed:bool}
	 */
	public static function parse_svg_path_to_verts( $d, $ox = 0, $oy = 0, $scale_x = 1, $scale_y = 1, $canvas_h = 0 ) {
		unset( $canvas_h );
		$d     = trim( (string) $d );
		$d     = html_entity_decode( $d, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$d     = preg_replace( '/,\s*/', ' ', $d );
		$d     = preg_replace( '/\s+/', ' ', $d );
		$verts = array();
		$prims = array();
		$cx    = 0.0;
		$cy    = 0.0;
		$sx    = 0.0;
		$sy    = 0.0;
		$lx    = 0.0;
		$ly    = 0.0;
		$i     = 0;
		$closed = false;
		$subpath_starts = array();

		$push = function ( $x, $y ) use ( &$verts, &$i, $ox, $oy, $scale_x, $scale_y ) {
			$verts[] = array(
				'x' => $ox + $x * $scale_x,
				'y' => $oy + $y * $scale_y,
			);
			$idx = $i;
			++$i;
			return $idx;
		};

		$line_to = function ( $nx, $ny ) use ( &$verts, &$prims, &$i, &$cx, &$cy, $push ) {
			if ( $i < 1 ) {
				$push( $nx, $ny );
				$cx = $nx;
				$cy = $ny;
				return;
			}
			$p0      = $i - 1;
			$p1      = $push( $nx, $ny );
			$prims[] = array( 'p0' => $p0, 'p1' => $p1, 't' => 'L' );
			$cx      = $nx;
			$cy      = $ny;
		};

		$sample_cubic = function ( $x0, $y0, $x1, $y1, $x2, $y2, $x3, $y3 ) use ( $line_to ) {
			$steps = 12;
			for ( $s = 1; $s <= $steps; $s++ ) {
				$t  = $s / $steps;
				$mt = 1 - $t;
				$nx = $mt * $mt * $mt * $x0 + 3 * $mt * $mt * $t * $x1 + 3 * $mt * $t * $t * $x2 + $t * $t * $t * $x3;
				$ny = $mt * $mt * $mt * $y0 + 3 * $mt * $mt * $t * $y1 + 3 * $mt * $t * $t * $y2 + $t * $t * $t * $y3;
				$line_to( $nx, $ny );
			}
		};

		$sample_quad = function ( $x0, $y0, $x1, $y1, $x2, $y2 ) use ( $line_to ) {
			$steps = 10;
			for ( $s = 1; $s <= $steps; $s++ ) {
				$t  = $s / $steps;
				$mt = 1 - $t;
				$nx = $mt * $mt * $x0 + 2 * $mt * $t * $x1 + $t * $t * $x2;
				$ny = $mt * $mt * $y0 + 2 * $mt * $t * $y1 + $t * $t * $y2;
				$line_to( $nx, $ny );
			}
		};

		if ( ! preg_match_all( '/([MmLlHhVvCcSsQqTtAaZz])([^MmLlHhVvCcSsQqTtAaZz]*)/', $d, $segments, PREG_SET_ORDER ) ) {
			return array( 'verts' => array(), 'prims' => array(), 'closed' => false );
		}

		foreach ( $segments as $seg ) {
			$letter = $seg[1];
			preg_match_all( '/-?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][-+]?\d+)?/', $seg[2], $m );
			$nums   = array_map( 'floatval', $m[0] ?? array() );
			$rel    = ( $letter === strtolower( $letter ) );
			$type   = strtoupper( $letter );

			if ( 'M' === $type ) {
				for ( $n = 0; $n + 1 < count( $nums ); $n += 2 ) {
					$nx = $rel ? $cx + $nums[ $n ] : $nums[ $n ];
					$ny = $rel ? $cy + $nums[ $n + 1 ] : $nums[ $n + 1 ];
					if ( 0 === $n && 0 === $i ) {
						$push( $nx, $ny );
						$subpath_start_idx = 0;
					} elseif ( 0 === $n ) {
						// New subpath — pen up (do not connect to previous contour).
						$push( $nx, $ny );
						$subpath_start_idx = $i - 1;
						$subpath_starts[] = $subpath_start_idx;
					} else {
						$line_to( $nx, $ny );
					}
					$cx = $nx;
					$cy = $ny;
					$sx = $cx;
					$sy = $cy;
				}
			} elseif ( 'L' === $type ) {
				for ( $n = 0; $n + 1 < count( $nums ); $n += 2 ) {
					$nx = $rel ? $cx + $nums[ $n ] : $nums[ $n ];
					$ny = $rel ? $cy + $nums[ $n + 1 ] : $nums[ $n + 1 ];
					$line_to( $nx, $ny );
				}
			} elseif ( 'H' === $type ) {
				foreach ( $nums as $nx ) {
					$nx = $rel ? $cx + $nx : $nx;
					$line_to( $nx, $cy );
				}
			} elseif ( 'V' === $type ) {
				foreach ( $nums as $ny ) {
					$ny = $rel ? $cy + $ny : $ny;
					$line_to( $cx, $ny );
				}
			} elseif ( 'C' === $type ) {
				for ( $n = 0; $n + 5 < count( $nums ); $n += 6 ) {
					$x1 = $rel ? $cx + $nums[ $n ] : $nums[ $n ];
					$y1 = $rel ? $cy + $nums[ $n + 1 ] : $nums[ $n + 1 ];
					$x2 = $rel ? $cx + $nums[ $n + 2 ] : $nums[ $n + 2 ];
					$y2 = $rel ? $cy + $nums[ $n + 3 ] : $nums[ $n + 3 ];
					$x3 = $rel ? $cx + $nums[ $n + 4 ] : $nums[ $n + 4 ];
					$y3 = $rel ? $cy + $nums[ $n + 5 ] : $nums[ $n + 5 ];
					$sample_cubic( $cx, $cy, $x1, $y1, $x2, $y2, $x3, $y3 );
					$lx = $x2;
					$ly = $y2;
					$cx = $x3;
					$cy = $y3;
				}
			} elseif ( 'S' === $type ) {
				for ( $n = 0; $n + 3 < count( $nums ); $n += 4 ) {
					$x1 = $cx + ( $cx - $lx );
					$y1 = $cy + ( $cy - $ly );
					$x2 = $rel ? $cx + $nums[ $n ] : $nums[ $n ];
					$y2 = $rel ? $cy + $nums[ $n + 1 ] : $nums[ $n + 1 ];
					$x3 = $rel ? $cx + $nums[ $n + 2 ] : $nums[ $n + 2 ];
					$y3 = $rel ? $cy + $nums[ $n + 3 ] : $nums[ $n + 3 ];
					$sample_cubic( $cx, $cy, $x1, $y1, $x2, $y2, $x3, $y3 );
					$lx = $x2;
					$ly = $y2;
					$cx = $x3;
					$cy = $y3;
				}
			} elseif ( 'Q' === $type ) {
				for ( $n = 0; $n + 3 < count( $nums ); $n += 4 ) {
					$x1 = $rel ? $cx + $nums[ $n ] : $nums[ $n ];
					$y1 = $rel ? $cy + $nums[ $n + 1 ] : $nums[ $n + 1 ];
					$x2 = $rel ? $cx + $nums[ $n + 2 ] : $nums[ $n + 2 ];
					$y2 = $rel ? $cy + $nums[ $n + 3 ] : $nums[ $n + 3 ];
					$sample_quad( $cx, $cy, $x1, $y1, $x2, $y2 );
					$lx = $x1;
					$ly = $y1;
					$cx = $x2;
					$cy = $y2;
				}
			} elseif ( 'T' === $type ) {
				for ( $n = 0; $n + 1 < count( $nums ); $n += 2 ) {
					$x1 = $cx + ( $cx - $lx );
					$y1 = $cy + ( $cy - $ly );
					$x2 = $rel ? $cx + $nums[ $n ] : $nums[ $n ];
					$y2 = $rel ? $cy + $nums[ $n + 1 ] : $nums[ $n + 1 ];
					$sample_quad( $cx, $cy, $x1, $y1, $x2, $y2 );
					$lx = $x1;
					$ly = $y1;
					$cx = $x2;
					$cy = $y2;
				}
			} elseif ( 'A' === $type ) {
				for ( $n = 0; $n + 6 < count( $nums ); $n += 7 ) {
					$x = $rel ? $cx + $nums[ $n + 5 ] : $nums[ $n + 5 ];
					$y = $rel ? $cy + $nums[ $n + 6 ] : $nums[ $n + 6 ];
					$line_to( $x, $y );
				}
			} elseif ( 'Z' === $type && $i > 1 ) {
				$prims[] = array( 'p0' => $i - 1, 'p1' => $subpath_start_idx, 't' => 'L' );
				$cx     = $sx;
				$cy     = $sy;
				$closed = true;
			}
		}

		return array(
			'verts'  => $verts,
			'prims'  => $prims,
			'closed' => $closed,
			'subpath_starts' => $subpath_starts,
		);
	}

	/**
	 * Map SVG path into mm box (bottom-left origin, Y-up).
	 *
	 * @param string $d    Path d attribute.
	 * @param float  $vb_w ViewBox width.
	 * @param float  $vb_h ViewBox height.
	 * @param array  $box  Target box x,y,width,height (mm).
	 * @return array{verts:array,prims:array,closed:bool}
	 */
	public static function map_path_to_box_mm( $d, $vb_w, $vb_h, $box, $uniform_scale = true, $draw_bounds = null ) {
		$vb_w = max( 0.001, (float) $vb_w );
		$vb_h = max( 0.001, (float) $vb_h );
		$raw  = self::parse_svg_path_to_verts( $d, 0, 0, 1, 1, 0 );
		$mm   = array();

		if ( $uniform_scale ) {
			$draw = self::sanitize_svg_draw_bounds( $draw_bounds, $vb_w, $vb_h );
			if ( $draw ) {
				$draw_w    = max( 0.001, (float) $draw['width'] );
				$draw_h    = max( 0.001, (float) $draw['height'] );
				$scale     = min( $box['width'] / $draw_w, $box['height'] / $draw_h );
				$content_w = $draw_w * $scale;
				$content_h = $draw_h * $scale;
				$target_x  = $box['x'] + ( $box['width'] - $content_w ) / 2;
				$target_y  = $box['y'] + ( $box['height'] - $content_h ) / 2;
				$draw_x    = (float) $draw['x'];
				$draw_y    = (float) $draw['y'];
				foreach ( $raw['verts'] as $v ) {
					$nx = ( $v['x'] - $draw_x ) / $draw_w;
					$ny = ( $v['y'] - $draw_y ) / $draw_h;
					$mm[] = array(
						'x' => $target_x + $nx * $content_w,
						'y' => $target_y + ( 1 - $ny ) * $content_h,
					);
				}
			} else {
				$scale      = min( $box['width'] / $vb_w, $box['height'] / $vb_h );
				$content_w  = $vb_w * $scale;
				$content_h  = $vb_h * $scale;
				$offset_x   = $box['x'] + ( $box['width'] - $content_w ) / 2;
				$offset_y   = $box['y'] + ( $box['height'] - $content_h ) / 2;
				foreach ( $raw['verts'] as $v ) {
					$mm[] = array(
						'x' => $offset_x + ( $v['x'] / $vb_w ) * $content_w,
						'y' => $offset_y + ( 1 - ( $v['y'] / $vb_h ) ) * $content_h,
					);
				}
			}
		} else {
			foreach ( $raw['verts'] as $v ) {
				$mm[] = array(
					'x' => $box['x'] + ( $v['x'] / $vb_w ) * $box['width'],
					'y' => $box['y'] + ( 1 - ( $v['y'] / $vb_h ) ) * $box['height'],
				);
			}
		}

		return array(
			'verts'  => $mm,
			'prims'  => $raw['prims'],
			'closed' => ! empty( $raw['closed'] ),
		);
	}

	/**
	 * Validate + normalize optional drawable bounds in SVG coordinates.
	 *
	 * @param array|null $bounds Candidate bounds.
	 * @param float      $vb_w   SVG viewBox width.
	 * @param float      $vb_h   SVG viewBox height.
	 * @return array<string,float>|null
	 */
	public static function sanitize_svg_draw_bounds( $bounds, $vb_w, $vb_h ) {
		if ( ! is_array( $bounds ) ) {
			return null;
		}
		$vb_w  = max( 0.001, (float) $vb_w );
		$vb_h  = max( 0.001, (float) $vb_h );
		$x     = isset( $bounds['x'] ) ? (float) $bounds['x'] : 0.0;
		$y     = isset( $bounds['y'] ) ? (float) $bounds['y'] : 0.0;
		$width = isset( $bounds['width'] ) ? (float) $bounds['width'] : 0.0;
		$height = isset( $bounds['height'] ) ? (float) $bounds['height'] : 0.0;
		if ( $width <= 0 || $height <= 0 ) {
			return null;
		}
		$max_w = max( $width, $vb_w );
		$max_h = max( $height, $vb_h );
		$x     = max( -$max_w, min( $x, $max_w ) );
		$y     = max( -$max_h, min( $y, $max_h ) );
		return array(
			'x'      => $x,
			'y'      => $y,
			'width'  => max( 0.001, $width ),
			'height' => max( 0.001, $height ),
		);
	}

	/**
	 * Optional drawable bounds shipped from browser layout metadata.
	 *
	 * @param array $obj  Layout object.
	 * @param float $vb_w ViewBox width.
	 * @param float $vb_h ViewBox height.
	 * @return array<string,float>|null
	 */
	public static function svg_draw_bounds_for_object( $obj, $vb_w, $vb_h ) {
		if ( ! is_array( $obj ) || empty( $obj['svg_draw_bounds'] ) || ! is_array( $obj['svg_draw_bounds'] ) ) {
			return null;
		}
		$bounds = self::sanitize_svg_draw_bounds( $obj['svg_draw_bounds'], $vb_w, $vb_h );
		if ( ! $bounds ) {
			return null;
		}
		$role = sanitize_key( (string) ( $obj['role'] ?? '' ) );
		if ( ! empty( $obj['svg_draw_bounds_normalized'] ) ) {
			return $bounds;
		}
		return self::normalize_icon_draw_bounds( $bounds, $vb_w, $vb_h, $role );
	}

	/**
	 * Normalize icon draw bounds by coverage target to avoid outlier sizing.
	 *
	 * @param array  $bounds Draw bounds.
	 * @param float  $vb_w   Viewbox width.
	 * @param float  $vb_h   Viewbox height.
	 * @param string $role   Object role.
	 * @return array<string,float>
	 */
	public static function normalize_icon_draw_bounds( $bounds, $vb_w, $vb_h, $role ) {
		if ( ! in_array( $role, array( 'icon-left', 'icon-right' ), true ) ) {
			return $bounds;
		}
		$vb_w = max( 0.001, (float) $vb_w );
		$vb_h = max( 0.001, (float) $vb_h );
		$bw   = max( 0.001, (float) ( $bounds['width'] ?? 0 ) );
		$bh   = max( 0.001, (float) ( $bounds['height'] ?? 0 ) );
		$cx   = (float) ( $bounds['x'] ?? 0 ) + $bw / 2;
		$cy   = (float) ( $bounds['y'] ?? 0 ) + $bh / 2;
		$coverage = sqrt( max( 0.0001, ( $bw / $vb_w ) * ( $bh / $vb_h ) ) );
		$target   = max( 0.1, (float) self::ICON_TARGET_COVERAGE );
		$adjust   = max( 0.7, min( 1.45, $coverage / $target ) );
		if ( abs( $adjust - 1 ) < 0.001 ) {
			return $bounds;
		}
		$nw = max( 0.001, $bw * $adjust );
		$nh = max( 0.001, $bh * $adjust );
		return array(
			'x'      => $cx - $nw / 2,
			'y'      => $cy - $nh / 2,
			'width'  => $nw,
			'height' => $nh,
		);
	}

	/**
	 * Infer drawable bounds from parsed SVG path definitions.
	 *
	 * @param array $defs Path defs from extract_paths_from_svg().
	 * @param float $vb_w ViewBox width.
	 * @param float $vb_h ViewBox height.
	 * @return array<string,float>|null
	 */
	public static function infer_svg_draw_bounds_from_defs( $defs, $vb_w, $vb_h ) {
		if ( empty( $defs ) || ! is_array( $defs ) ) {
			return null;
		}
		$min_x = INF;
		$min_y = INF;
		$max_x = -INF;
		$max_y = -INF;
		foreach ( $defs as $def ) {
			foreach ( self::split_path_subpaths( (string) ( $def['d'] ?? '' ) ) as $sub ) {
				$raw = self::parse_svg_path_to_verts( $sub, 0, 0, 1, 1, 0 );
				if ( empty( $raw['verts'] ) || ! is_array( $raw['verts'] ) ) {
					continue;
				}
				foreach ( $raw['verts'] as $v ) {
					if ( ! isset( $v['x'], $v['y'] ) ) {
						continue;
					}
					$min_x = min( $min_x, (float) $v['x'] );
					$min_y = min( $min_y, (float) $v['y'] );
					$max_x = max( $max_x, (float) $v['x'] );
					$max_y = max( $max_y, (float) $v['y'] );
				}
			}
		}
		if ( ! is_finite( $min_x ) || ! is_finite( $min_y ) || ! is_finite( $max_x ) || ! is_finite( $max_y ) ) {
			return null;
		}
		return self::sanitize_svg_draw_bounds(
			array(
				'x'      => $min_x,
				'y'      => $min_y,
				'width'  => max( 0.001, $max_x - $min_x ),
				'height' => max( 0.001, $max_y - $min_y ),
			),
			$vb_w,
			$vb_h
		);
	}

	/**
	 * Uniform scale + offsets to fit SVG viewBox into mm box (preserve aspect ratio).
	 *
	 * @param float $vb_w ViewBox width.
	 * @param float $vb_h ViewBox height.
	 * @param array $box  Target box (x,y,width,height) bottom-left mm.
	 * @return array{scale:float,offset_x:float,offset_y:float,content_w:float,content_h:float}
	 */
	public static function fit_transform_for_box( $vb_w, $vb_h, $box ) {
		$vb_w = max( 0.001, (float) $vb_w );
		$vb_h = max( 0.001, (float) $vb_h );
		$scale = min( $box['width'] / $vb_w, $box['height'] / $vb_h );
		$content_w = $vb_w * $scale;
		$content_h = $vb_h * $scale;
		return array(
			'scale'      => $scale,
			'offset_x'   => $box['x'] + ( $box['width'] - $content_w ) / 2,
			'offset_y'   => $box['y'] + ( $box['height'] - $content_h ) / 2,
			'content_w'  => $content_w,
			'content_h'  => $content_h,
		);
	}

	/**
	 * ViewBox dimensions from SVG markup.
	 *
	 * @param string $svg_body SVG file content.
	 * @return array{w:float,h:float}
	 */
	public static function svg_viewbox_size( $svg_body ) {
		$vb_w = 100.0;
		$vb_h = 100.0;
		if ( ! preg_match( '/<svg\b([^>]*)>/is', $svg_body, $m ) ) {
			return array( 'w' => $vb_w, 'h' => $vb_h );
		}
		$attrs = $m[1];
		if ( preg_match( '/viewBox\s*=\s*["\']([^"\']+)["\']/i', $attrs, $vm ) ) {
			$p = preg_split( '/\s+/', trim( $vm[1] ) );
			if ( count( $p ) >= 4 ) {
				$vb_w = max( 0.001, (float) $p[2] );
				$vb_h = max( 0.001, (float) $p[3] );
			}
		} elseif ( preg_match( '/width\s*=\s*["\']([\d.]+)/i', $attrs, $wm ) && preg_match( '/height\s*=\s*["\']([\d.]+)/i', $attrs, $hm ) ) {
			$vb_w = max( 0.001, (float) $wm[1] );
			$vb_h = max( 0.001, (float) $hm[1] );
		}
		return array( 'w' => $vb_w, 'h' => $vb_h );
	}

	/**
	 * Whether object should use embedded SVG (line overlays) vs flattened paths.
	 *
	 * @param array $obj Layout object.
	 * @return bool
	 */
	public static function prefer_embedded_svg( $obj ) {
		$role = $obj['role'] ?? '';
		return in_array( $role, array( 'lines', 'strip-lines', 'strip-line', 'line-overlay' ), true );
	}

	/**
	 * Split SVG path data into independent subpaths (each moveto starts new path).
	 *
	 * @param string $d Path d attribute.
	 * @return string[]
	 */
	public static function split_path_subpaths( $d ) {
		$d = trim( (string) $d );
		if ( '' === $d ) {
			return array();
		}
		$chunks = preg_split( '/(?=[Mm])/', $d );
		$out    = array();
		foreach ( $chunks as $chunk ) {
			$chunk = trim( $chunk );
			if ( '' !== $chunk ) {
				$out[] = $chunk;
			}
		}
		return $out ?: array( $d );
	}

	/**
	 * Parse SVG transform attribute to 2D matrix [a,b,c,d,e,f].
	 *
	 * @param string $transform Transform attribute.
	 * @return array
	 */
	public static function parse_transform_matrix( $transform ) {
		$identity = array( 1, 0, 0, 1, 0, 0 );
		if ( empty( $transform ) ) {
			return $identity;
		}
		if ( preg_match( '/matrix\s*\(\s*([^)]+)\)/i', $transform, $m ) ) {
			$parts = preg_split( '/[\s,]+/', trim( $m[1] ) );
			$nums  = array();
			foreach ( $parts as $p ) {
				if ( '' !== $p ) {
					$nums[] = (float) $p;
				}
			}
			if ( count( $nums ) >= 6 ) {
				return array_slice( $nums, 0, 6 );
			}
		}
		if ( preg_match( '/translate\s*\(\s*([^)]+)\)/i', $transform, $m ) ) {
			$parts = preg_split( '/[\s,]+/', trim( $m[1] ) );
			$tx    = isset( $parts[0] ) ? (float) $parts[0] : 0;
			$ty    = isset( $parts[1] ) ? (float) $parts[1] : 0;
			return array( 1, 0, 0, 1, $tx, $ty );
		}
		return $identity;
	}

	/**
	 * Apply SVG matrix to a point.
	 *
	 * @param array $matrix Matrix.
	 * @param float $x      X.
	 * @param float $y      Y.
	 * @return array{x:float,y:float}
	 */
	public static function apply_transform_point( $matrix, $x, $y ) {
		list( $a, $b, $c, $d, $e, $f ) = $matrix;
		return array(
			'x' => $a * $x + $c * $y + $e,
			'y' => $b * $x + $d * $y + $f,
		);
	}

	/**
	 * Build closed path "d" for ellipse in SVG coordinates.
	 *
	 * @param float $cx Center X.
	 * @param float $cy Center Y.
	 * @param float $rx Radius X.
	 * @param float $ry Radius Y.
	 * @param array $matrix Transform.
	 * @param int   $steps  Segments.
	 * @return string
	 */
	public static function ellipse_to_path_d( $cx, $cy, $rx, $ry, $matrix = null, $steps = 24 ) {
		$matrix = $matrix ?: array( 1, 0, 0, 1, 0, 0 );
		$pts    = array();
		for ( $i = 0; $i <= $steps; $i++ ) {
			$a   = ( 2 * M_PI * $i ) / $steps;
			$p   = self::apply_transform_point( $matrix, $cx + $rx * cos( $a ), $cy + $ry * sin( $a ) );
			$pts[] = self::fmt( $p['x'] ) . ' ' . self::fmt( $p['y'] );
		}
		return 'M ' . $pts[0] . ' L ' . implode( ' L ', array_slice( $pts, 1 ) ) . ' Z';
	}

	/**
	 * Extract all vector primitives from SVG (paths, ellipses, rects, lines, polygons).
	 *
	 * @param string $svg_body SVG file content.
	 * @return array<int,array{d:string,vb_w:float,vb_h:float}>
	 */
	public static function extract_paths_from_svg( $svg_body ) {
		$paths = array();
		if ( ! preg_match( '/<svg\b([^>]*)>(.*)<\/svg>/is', $svg_body, $m ) ) {
			return $paths;
		}

		$vb_w = 100;
		$vb_h = 100;
		if ( preg_match( '/viewBox\s*=\s*["\']([^"\']+)["\']/i', $m[1], $vm ) ) {
			$p = preg_split( '/\s+/', trim( $vm[1] ) );
			if ( count( $p ) >= 4 ) {
				$vb_w = max( 0.001, (float) $p[2] );
				$vb_h = max( 0.001, (float) $p[3] );
			}
		} elseif ( preg_match( '/width\s*=\s*["\']([\d.]+)/i', $m[1], $wm ) && preg_match( '/height\s*=\s*["\']([\d.]+)/i', $m[1], $hm ) ) {
			$vb_w = max( 0.001, (float) $wm[1] );
			$vb_h = max( 0.001, (float) $hm[1] );
		}

		$inner = $m[2];

		if ( preg_match_all( '/<path\b([^>]*)\/?>/i', $inner, $pm, PREG_SET_ORDER ) ) {
			foreach ( $pm as $path_el ) {
				$attrs = $path_el[1];
				if ( ! preg_match( '/\bd\s*=\s*["\']([^"\']+)["\']/i', $attrs, $dm ) ) {
					continue;
				}
				foreach ( self::split_path_subpaths( $dm[1] ) as $sub ) {
					$paths[] = array(
						'd'    => $sub,
						'vb_w' => $vb_w,
						'vb_h' => $vb_h,
					);
				}
			}
		}

		if ( preg_match_all( '/<ellipse\b([^>]*)\/?>/i', $inner, $em, PREG_SET_ORDER ) ) {
			foreach ( $em as $el ) {
				$attrs = $el[1];
				$cx    = 0.0;
				$cy    = 0.0;
				$rx    = 1.0;
				$ry    = 1.0;
				if ( preg_match( '/\bcx\s*=\s*["\']([\d.]+)/i', $attrs, $a ) ) {
					$cx = (float) $a[1];
				}
				if ( preg_match( '/\bcy\s*=\s*["\']([\d.]+)/i', $attrs, $a ) ) {
					$cy = (float) $a[1];
				}
				if ( preg_match( '/\brx\s*=\s*["\']([\d.]+)/i', $attrs, $a ) ) {
					$rx = (float) $a[1];
				}
				if ( preg_match( '/\bry\s*=\s*["\']([\d.]+)/i', $attrs, $a ) ) {
					$ry = (float) $a[1];
				}
				$matrix = array( 1, 0, 0, 1, 0, 0 );
				if ( preg_match( '/\btransform\s*=\s*["\']([^"\']+)["\']/i', $attrs, $tm ) ) {
					$matrix = self::parse_transform_matrix( $tm[1] );
				}
				$paths[] = array(
					'd'    => self::ellipse_to_path_d( $cx, $cy, max( 0.01, $rx ), max( 0.01, $ry ), $matrix ),
					'vb_w' => $vb_w,
					'vb_h' => $vb_h,
				);
			}
		}

		if ( preg_match_all( '/<circle\b([^>]*)\/?>/i', $inner, $cm, PREG_SET_ORDER ) ) {
			foreach ( $cm as $el ) {
				$attrs = $el[1];
				$cx    = 0.0;
				$cy    = 0.0;
				$r     = 1.0;
				if ( preg_match( '/\bcx\s*=\s*["\']([\d.]+)/i', $attrs, $a ) ) {
					$cx = (float) $a[1];
				}
				if ( preg_match( '/\bcy\s*=\s*["\']([\d.]+)/i', $attrs, $a ) ) {
					$cy = (float) $a[1];
				}
				if ( preg_match( '/\br\s*=\s*["\']([\d.]+)/i', $attrs, $a ) ) {
					$r = (float) $a[1];
				}
				$matrix = array( 1, 0, 0, 1, 0, 0 );
				if ( preg_match( '/\btransform\s*=\s*["\']([^"\']+)["\']/i', $attrs, $tm ) ) {
					$matrix = self::parse_transform_matrix( $tm[1] );
				}
				$paths[] = array(
					'd'    => self::ellipse_to_path_d( $cx, $cy, max( 0.01, $r ), max( 0.01, $r ), $matrix ),
					'vb_w' => $vb_w,
					'vb_h' => $vb_h,
				);
			}
		}

		if ( preg_match_all( '/<rect\b([^>]*)\/?>/i', $inner, $rm, PREG_SET_ORDER ) ) {
			foreach ( $rm as $rect ) {
				$attrs  = $rect[1];
				$rx     = 0.0;
				$ry     = 0.0;
				$rw     = $vb_w;
				$rh     = $vb_h;
				$matrix = array( 1, 0, 0, 1, 0, 0 );
				if ( preg_match( '/\bx\s*=\s*["\']([\d.]+)/i', $attrs, $xm ) ) {
					$rx = (float) $xm[1];
				}
				if ( preg_match( '/\by\s*=\s*["\']([\d.]+)/i', $attrs, $ym ) ) {
					$ry = (float) $ym[1];
				}
				if ( preg_match( '/\bwidth\s*=\s*["\']([\d.]+)/i', $attrs, $wm ) ) {
					$rw = (float) $wm[1];
				}
				if ( preg_match( '/\bheight\s*=\s*["\']([\d.]+)/i', $attrs, $hm ) ) {
					$rh = (float) $hm[1];
				}
				if ( preg_match( '/\btransform\s*=\s*["\']([^"\']+)["\']/i', $attrs, $tm ) ) {
					$matrix = self::parse_transform_matrix( $tm[1] );
				}
				$corners = array(
					array( $rx, $ry ),
					array( $rx + $rw, $ry ),
					array( $rx + $rw, $ry + $rh ),
					array( $rx, $ry + $rh ),
				);
				$pts     = array();
				foreach ( $corners as $c ) {
					$p     = self::apply_transform_point( $matrix, $c[0], $c[1] );
					$pts[] = self::fmt( $p['x'] ) . ' ' . self::fmt( $p['y'] );
				}
				$paths[] = array(
					'd'    => 'M ' . $pts[0] . ' L ' . $pts[1] . ' L ' . $pts[2] . ' L ' . $pts[3] . ' Z',
					'vb_w' => $vb_w,
					'vb_h' => $vb_h,
				);
			}
		}

		if ( preg_match_all( '/<line\b([^>]*)\/?>/i', $inner, $lm, PREG_SET_ORDER ) ) {
			foreach ( $lm as $line ) {
				$attrs  = $line[1];
				$x1     = 0.0;
				$y1     = 0.0;
				$x2     = $vb_w;
				$y2     = $vb_h;
				$matrix = array( 1, 0, 0, 1, 0, 0 );
				if ( preg_match( '/\bx1\s*=\s*["\']([\d.]+)/i', $attrs, $a ) ) {
					$x1 = (float) $a[1];
				}
				if ( preg_match( '/\by1\s*=\s*["\']([\d.]+)/i', $attrs, $a ) ) {
					$y1 = (float) $a[1];
				}
				if ( preg_match( '/\bx2\s*=\s*["\']([\d.]+)/i', $attrs, $a ) ) {
					$x2 = (float) $a[1];
				}
				if ( preg_match( '/\by2\s*=\s*["\']([\d.]+)/i', $attrs, $a ) ) {
					$y2 = (float) $a[1];
				}
				if ( preg_match( '/\btransform\s*=\s*["\']([^"\']+)["\']/i', $attrs, $tm ) ) {
					$matrix = self::parse_transform_matrix( $tm[1] );
				}
				$p1 = self::apply_transform_point( $matrix, $x1, $y1 );
				$p2 = self::apply_transform_point( $matrix, $x2, $y2 );
				$paths[] = array(
					'd'    => sprintf( 'M %s %s L %s %s', self::fmt( $p1['x'] ), self::fmt( $p1['y'] ), self::fmt( $p2['x'] ), self::fmt( $p2['y'] ) ),
					'vb_w' => $vb_w,
					'vb_h' => $vb_h,
				);
			}
		}

		if ( preg_match_all( '/<polygon\b([^>]*)\/?>/i', $inner, $pgm, PREG_SET_ORDER ) ) {
			foreach ( $pgm as $el ) {
				if ( preg_match( '/\bpoints\s*=\s*["\']([^"\']+)["\']/i', $el[1], $pm ) ) {
					$coords = preg_split( '/[\s,]+/', trim( $pm[1] ) );
					$pairs  = array();
					for ( $i = 0; $i + 1 < count( $coords ); $i += 2 ) {
						$pairs[] = self::fmt( (float) $coords[ $i ] ) . ' ' . self::fmt( (float) $coords[ $i + 1 ] );
					}
					if ( count( $pairs ) >= 2 ) {
						$paths[] = array(
							'd'    => 'M ' . implode( ' L ', $pairs ) . ' Z',
							'vb_w' => $vb_w,
							'vb_h' => $vb_h,
						);
					}
				}
			}
		}

		return $paths;
	}

	/**
	 * Map all SVG primitives for a layout object into mm path loops (bottom-left origin).
	 *
	 * @param array $obj         Layout object (may include svg_source from browser).
	 * @param array $box         Target mm box.
	 * @param array $selections  Selections fallback.
	 * @return array<int,array{verts:array,prims:array,closed:bool}>
	 */
	public static function paths_for_object_mm( $obj, $box, $selections = array() ) {
		$loops = array();
		$body  = self::resolve_svg_body_for_object( $obj, $selections );

		if ( '' === $body ) {
			return $loops;
		}

		$defs = self::extract_paths_from_svg( $body );
		if ( empty( $defs ) ) {
			return $loops;
		}
		$vb_w = (float) $defs[0]['vb_w'];
		$vb_h = (float) $defs[0]['vb_h'];
		$role = sanitize_key( (string) ( $obj['role'] ?? '' ) );
		$draw_bounds = self::svg_draw_bounds_for_object( $obj, $vb_w, $vb_h );
		if ( ! $draw_bounds && in_array( $role, array( 'icon-left', 'icon-right', 'icon-bg-left', 'icon-bg-right' ), true ) ) {
			$draw_bounds = self::infer_svg_draw_bounds_from_defs( $defs, $vb_w, $vb_h );
		}
		if ( $draw_bounds ) {
			$draw_bounds = self::normalize_icon_draw_bounds( $draw_bounds, $vb_w, $vb_h, $role );
		}
		foreach ( $defs as $def ) {
			foreach ( self::split_path_subpaths( $def['d'] ) as $sub ) {
				$mapped = self::map_path_to_box_mm( $sub, $def['vb_w'], $def['vb_h'], $box, true, $draw_bounds );
				if ( count( $mapped['verts'] ) >= 2 ) {
					$loops[] = $mapped;
				}
			}
		}
		return $loops;
	}

	/**
	 * @param string $url        Asset URL.
	 * @param array  $box        Target mm box.
	 * @return array<int,array{verts:array,prims:array,closed:bool}>
	 */
	public static function paths_for_asset_mm( $url, $box ) {
		return self::paths_for_object_mm( array( 'svg_url' => $url ), $box, array() );
	}

	/**
	 * Convert mm verts (bottom-left) to SVG path d (top-left canvas).
	 *
	 * @param array $verts    Vertices mm Y-up.
	 * @param bool  $closed   Closed path.
	 * @param float $canvas_h Canvas height mm.
	 * @return string
	 */
	public static function mm_verts_to_svg_path_d( $verts, $closed, $canvas_h ) {
		if ( count( $verts ) < 2 ) {
			return '';
		}
		$parts = array();
		foreach ( $verts as $i => $v ) {
			$ysvg = $canvas_h - (float) $v['y'];
			$cmd  = 0 === $i ? 'M' : 'L';
			$parts[] = $cmd . ' ' . self::fmt( $v['x'] ) . ' ' . self::fmt( $ysvg );
		}
		if ( $closed ) {
			$parts[] = 'Z';
		}
		return implode( ' ', $parts );
	}

	/**
	 * Convert bottom-left mm vertices to SVG path d (lightburn-mm-bottom-left viewBox).
	 *
	 * @param array $verts  Vertices mm Y-up from bottom-left origin.
	 * @param bool  $closed Closed path.
	 * @return string
	 */
	public static function mm_verts_to_lightburn_svg_path_d( $verts, $closed ) {
		if ( count( $verts ) < 2 ) {
			return '';
		}
		$parts = array();
		foreach ( $verts as $i => $v ) {
			$cmd     = 0 === $i ? 'M' : 'L';
			$parts[] = $cmd . ' ' . self::fmt( $v['x'] ) . ' ' . self::fmt( $v['y'] );
		}
		if ( $closed ) {
			$parts[] = 'Z';
		}
		return implode( ' ', $parts );
	}

	/**
	 * Normalize layout object role aliases.
	 *
	 * @param array $obj Object.
	 * @return array
	 */
	public static function normalize_layout_object( $obj ) {
		$role = $obj['role'] ?? '';
		if ( in_array( $role, array( 'strip-lines', 'strip-line', 'line-overlay' ), true ) ) {
			$obj['role'] = 'lines';
		}
		if ( in_array( $role, array( 'icon-bg-left', 'icon-bg-right' ), true ) ) {
			// Keep role for export z-order.
		}
		if ( 'main-text' === $role ) {
			$obj['role'] = 'text';
		}
		if ( empty( $obj['font_family'] ) && ! empty( $obj['fontFamily'] ) ) {
			$obj['font_family'] = $obj['fontFamily'];
		}
		return $obj;
	}

	/**
	 * @param string $value Color.
	 * @return string
	 */
	public static function resolve_hex( $value ) {
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
	 * @param float $n Number.
	 * @return string
	 */
	public static function fmt( $n ) {
		return rtrim( rtrim( sprintf( '%.4f', (float) $n ), '0' ), '.' );
	}

	/**
	 * Save file under uploads/pckz-canonical-engine/{subdir}/.
	 *
	 * @param string $subdir    Subdirectory.
	 * @param string $prefix    Filename prefix.
	 * @param string $ext       Extension.
	 * @param string $contents  File contents.
	 * @param int    $design_id Design ID.
	 * @return string|WP_Error
	 */
	public static function save_file( $subdir, $prefix, $ext, $contents, $design_id = 0 ) {
		$upload_dir = wp_upload_dir();
		$dir        = $upload_dir['basedir'] . '/pckz-canonical-engine/' . trim( $subdir, '/' );
		wp_mkdir_p( $dir );

		$name = sanitize_file_name( $prefix );
		if ( $design_id ) {
			$name .= '-design-' . (int) $design_id;
		}
		$name .= '-' . wp_generate_uuid4() . '.' . ltrim( $ext, '.' );

		$path = trailingslashit( $dir ) . $name;
		if ( false === file_put_contents( $path, $contents ) ) {
			return new WP_Error( 'write_error', __( 'Could not write production file.', 'pckz-canonical-engine' ) );
		}

		return str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $path );
	}
}
