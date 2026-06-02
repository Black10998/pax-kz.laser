<?php
/**
 * Shared SVG fetch/validation helpers for asset libraries.
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PCKZ_Svg_Library
 */
class PCKZ_Svg_Library {

	const MAX_BYTES = 2097152;

	/**
	 * Validate remote SVG URL.
	 *
	 * @param string $url Raw URL.
	 * @return string|WP_Error Sanitized URL.
	 */
	public static function validate_remote_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return new WP_Error( 'empty_url', __( 'Bitte eine SVG-URL angeben.', 'pckz-canonical-engine' ) );
		}
		$url = esc_url_raw( $url );
		if ( ! $url || ! preg_match( '#^https?://#i', $url ) ) {
			return new WP_Error( 'bad_url', __( 'Nur http(s)-URLs sind erlaubt.', 'pckz-canonical-engine' ) );
		}
		if ( function_exists( 'wp_http_validate_url' ) ) {
			$validated = wp_http_validate_url( $url );
			if ( ! $validated ) {
				return new WP_Error( 'blocked_url', __( 'Diese URL ist nicht erlaubt.', 'pckz-canonical-engine' ) );
			}
			$url = $validated;
		}
		$path = (string) ( function_exists( 'wp_parse_url' ) ? wp_parse_url( $url, PHP_URL_PATH ) : parse_url( $url, PHP_URL_PATH ) );
		if ( $path && ! preg_match( '/\.svg(?:$|[?#])/i', $path ) ) {
			return new WP_Error( 'not_svg_url', __( 'Die URL muss auf eine .svg-Datei verweisen.', 'pckz-canonical-engine' ) );
		}
		return $url;
	}

	/**
	 * Download SVG body from URL.
	 *
	 * @param string $url Remote SVG URL.
	 * @return string|WP_Error SVG source.
	 */
	public static function fetch_from_url( $url ) {
		$url = self::validate_remote_url( $url );
		if ( is_wp_error( $url ) ) {
			return $url;
		}
		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 20,
				'redirection'=> 3,
				'headers'    => array(
					'Accept' => 'image/svg+xml,text/xml,application/xml,text/plain,*/*',
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'fetch_fail', __( 'SVG konnte nicht heruntergeladen werden.', 'pckz-canonical-engine' ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'fetch_http', __( 'SVG-Download fehlgeschlagen (HTTP-Fehler).', 'pckz-canonical-engine' ) );
		}
		$content_type = (string) wp_remote_retrieve_header( $response, 'content-type' );
		if ( $content_type && ! preg_match( '#(svg|xml|text/plain)#i', $content_type ) ) {
			return new WP_Error( 'bad_content_type', __( 'Die URL liefert keinen gültigen SVG-Inhalt.', 'pckz-canonical-engine' ) );
		}
		$body = (string) wp_remote_retrieve_body( $response );
		if ( '' === $body ) {
			return new WP_Error( 'empty_body', __( 'Die heruntergeladene SVG-Datei ist leer.', 'pckz-canonical-engine' ) );
		}
		if ( strlen( $body ) > self::MAX_BYTES ) {
			return new WP_Error( 'too_large', __( 'SVG-Datei ist zu groß.', 'pckz-canonical-engine' ) );
		}
		return $body;
	}

	/**
	 * Basic SVG safety check.
	 *
	 * @param string $svg SVG source.
	 * @return bool
	 */
	public static function is_safe_svg( $svg ) {
		if ( stripos( $svg, '<script' ) !== false || stripos( $svg, '<?php' ) !== false ) {
			return false;
		}
		return (bool) preg_match( '/<svg[\s>]/i', $svg );
	}

	/**
	 * Filename stem from URL path.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function basename_from_url( $url ) {
		$path = (string) ( function_exists( 'wp_parse_url' ) ? wp_parse_url( $url, PHP_URL_PATH ) : parse_url( $url, PHP_URL_PATH ) );
		$base = $path ? pathinfo( $path, PATHINFO_FILENAME ) : '';
		return sanitize_title( $base );
	}

	/**
	 * Whether an SVG should keep its native colors (multi-color artwork).
	 *
	 * @param string $svg SVG source.
	 * @return bool
	 */
	public static function svg_should_preserve_colors( $svg ) {
		if ( ! is_string( $svg ) || '' === trim( $svg ) ) {
			return false;
		}
		if ( preg_match( '/<(?:linear|radial)Gradient|url\s*\(\s*#/i', $svg ) ) {
			return true;
		}
		$colors = self::collect_svg_paint_colors( $svg );
		return count( $colors ) >= 2;
	}

	/**
	 * Collect distinct non-transparent paint colors from SVG markup.
	 *
	 * @param string $svg SVG source.
	 * @return string[]
	 */
	public static function collect_svg_paint_colors( $svg ) {
		$colors = array();
		$patterns = array(
			'/(?:fill|stroke)\s*=\s*["\']([^"\']+)["\']/i',
			'/(?:fill|stroke)\s*:\s*([^;"\'\s]+)/i',
		);
		foreach ( $patterns as $pattern ) {
			if ( ! preg_match_all( $pattern, $svg, $matches ) ) {
				continue;
			}
			foreach ( $matches[1] as $raw ) {
				$color = self::normalize_svg_color_token( $raw );
				if ( $color ) {
					$colors[ $color ] = true;
				}
			}
		}
		return array_keys( $colors );
	}

	/**
	 * @param string $raw Raw color token.
	 * @return string Normalized color or empty string.
	 */
	private static function normalize_svg_color_token( $raw ) {
		$color = strtolower( trim( (string) $raw ) );
		if ( '' === $color ) {
			return '';
		}
		if ( in_array( $color, array( 'none', 'transparent', 'currentcolor', 'inherit' ), true ) ) {
			return '';
		}
		if ( 0 === strpos( $color, 'url(' ) ) {
			return '';
		}
		return $color;
	}

	/**
	 * Resolve icon color mode for library storage/catalog.
	 *
	 * @param string $svg SVG source.
	 * @return string preserve|tintable
	 */
	public static function icon_color_mode_for_svg( $svg ) {
		return self::svg_should_preserve_colors( $svg ) ? 'preserve' : 'tintable';
	}

	/**
	 * Whether a line SVG should keep native colors in customer preview.
	 *
	 * @param string $svg SVG source.
	 * @return bool
	 */
	public static function svg_line_should_preserve_colors( $svg ) {
		if ( self::svg_should_preserve_colors( $svg ) ) {
			return true;
		}
		foreach ( self::collect_svg_paint_colors( $svg ) as $color ) {
			if ( self::is_chromatic_svg_paint_color( $color ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Resolve line color mode for library storage/catalog.
	 *
	 * @param string $svg SVG source.
	 * @return string preserve|tintable
	 */
	public static function line_color_mode_for_svg( $svg ) {
		return self::svg_line_should_preserve_colors( $svg ) ? 'preserve' : 'tintable';
	}

	/**
	 * @param string $color Normalized color token.
	 * @return bool
	 */
	private static function is_chromatic_svg_paint_color( $color ) {
		$color = strtolower( trim( (string) $color ) );
		if ( '' === $color ) {
			return false;
		}
		if ( in_array( $color, array( 'none', 'transparent', 'currentcolor', 'inherit', 'white', 'black' ), true ) ) {
			return false;
		}
		if ( preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/', $color, $m ) ) {
			$hex = 3 === strlen( $m[1] )
				? $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2]
				: $m[1];
			$r   = hexdec( substr( $hex, 0, 2 ) );
			$g   = hexdec( substr( $hex, 2, 2 ) );
			$b   = hexdec( substr( $hex, 4, 2 ) );
			return ( max( $r, $g, $b ) - min( $r, $g, $b ) ) > 8;
		}
		$achromatic = array( 'white', 'black', 'silver', 'gray', 'grey', 'lightgray', 'lightgrey', 'darkgray', 'darkgrey' );
		return ! in_array( $color, $achromatic, true );
	}

	/** Standard Cloudlift line artboard (matches bundled type_21–71). */
	const LINE_ARTBOARD_W = 950;
	const LINE_ARTBOARD_H = 35;

	/**
	 * Reference artboard size for line normalization.
	 *
	 * @param bool $half When true, left/right half width.
	 * @return array{width:float,height:float}
	 */
	public static function line_artboard_size( $half = false ) {
		return array(
			'width'  => $half ? self::LINE_ARTBOARD_W / 2 : self::LINE_ARTBOARD_W,
			'height' => self::LINE_ARTBOARD_H,
		);
	}

	/**
	 * Parse SVG root viewBox / width / height.
	 *
	 * @param string $svg SVG markup.
	 * @return array{x:float,y:float,width:float,height:float}
	 */
	public static function parse_svg_viewbox( $svg ) {
		$x = 0.0;
		$y = 0.0;
		$w = self::LINE_ARTBOARD_W;
		$h = self::LINE_ARTBOARD_H;
		if ( ! preg_match( '/<svg\b([^>]*)>/i', (string) $svg, $m ) ) {
			return compact( 'x', 'y' ) + array( 'width' => $w, 'height' => $h );
		}
		$attrs = $m[1];
		if ( preg_match( '/viewBox\s*=\s*["\']([^"\']+)["\']/i', $attrs, $vm ) ) {
			$p = preg_split( '/[\s,]+/', trim( $vm[1] ) );
			if ( count( $p ) >= 4 ) {
				$x = (float) $p[0];
				$y = (float) $p[1];
				$w = max( 0.001, (float) $p[2] );
				$h = max( 0.001, (float) $p[3] );
			}
		} else {
			if ( preg_match( '/width\s*=\s*["\']([\d.]+)/i', $attrs, $wm ) ) {
				$w = max( 0.001, (float) $wm[1] );
			}
			if ( preg_match( '/height\s*=\s*["\']([\d.]+)/i', $attrs, $hm ) ) {
				$h = max( 0.001, (float) $hm[1] );
			}
		}
		return array(
			'x'      => $x,
			'y'      => $y,
			'width'  => $w,
			'height' => $h,
		);
	}

	/**
	 * Extract inner SVG markup (without outer svg wrapper).
	 *
	 * @param string $svg SVG markup.
	 * @return string
	 */
	public static function svg_inner_markup( $svg ) {
		if ( preg_match( '/<svg\b[^>]*>([\s\S]*)<\/svg>/i', (string) $svg, $m ) ) {
			return trim( $m[1] );
		}
		return trim( (string) $svg );
	}

	/** When drawable width is below this fraction of viewBox width, picker preview scales artwork up (display only). */
	const LINE_PICKER_WIDTH_COVERAGE = 0.88;

	/**
	 * Infer drawable bounds for line SVG (path geometry, not empty viewBox margins).
	 *
	 * @param string $svg SVG markup.
	 * @return array{x:float,y:float,width:float,height:float}|null
	 */
	public static function infer_line_draw_bounds( $svg ) {
		if ( ! class_exists( 'PCKZ_Production_Geometry' ) ) {
			return null;
		}
		$viewbox = self::parse_svg_viewbox( $svg );
		$defs    = PCKZ_Production_Geometry::extract_paths_from_svg( (string) $svg );
		if ( empty( $defs ) ) {
			return null;
		}
		return PCKZ_Production_Geometry::infer_svg_draw_bounds_from_defs(
			$defs,
			$viewbox['width'],
			$viewbox['height']
		);
	}

	/**
	 * Build normalized line preview SVG on standard 950×35 artboard (viewBox fit).
	 *
	 * @param string $svg       Source SVG.
	 * @param bool   $connected Mirror right-side continuation for picker preview.
	 * @return string
	 */
	public static function normalize_line_svg_for_preview( $svg, $connected = false ) {
		$inner   = self::svg_inner_markup( $svg );
		$viewbox = self::parse_svg_viewbox( $svg );
		$board   = self::line_artboard_size( false );
		$half    = self::line_artboard_size( true );
		$target  = $connected ? $half : $board;

		$scale = min(
			$target['width'] / max( 0.001, $viewbox['width'] ),
			$target['height'] / max( 0.001, $viewbox['height'] )
		);
		$content_w = $viewbox['width'] * $scale;
		$content_h = $viewbox['height'] * $scale;
		$offset_x  = ( $target['width'] - $content_w ) / 2 - ( $viewbox['x'] * $scale );
		$offset_y  = ( $target['height'] - $content_h ) / 2 - ( $viewbox['y'] * $scale );

		return self::compose_line_preview_svg( $inner, $board, $half, $connected, $scale, $offset_x, $offset_y );
	}

	/**
	 * Picker-only preview: scale drawable artwork to fill artboard width like CDN type_1–20.
	 * Does not modify source SVG files; export/plate continue to use original assets.
	 *
	 * @param string $svg       Source SVG.
	 * @param bool   $connected Mirror right-side continuation.
	 * @return string
	 */
	public static function normalize_line_svg_for_picker_preview( $svg, $connected = false ) {
		$inner   = self::svg_inner_markup( $svg );
		$viewbox = self::parse_svg_viewbox( $svg );
		$board   = self::line_artboard_size( false );
		$half    = self::line_artboard_size( true );
		$target  = $connected ? $half : $board;
		$draw    = self::infer_line_draw_bounds( $svg );

		$use_draw = false;
		if ( $draw && $viewbox['width'] > 0 ) {
			$coverage = (float) $draw['width'] / (float) $viewbox['width'];
			if ( $coverage < self::LINE_PICKER_WIDTH_COVERAGE ) {
				$use_draw = true;
			}
		}

		if ( ! $use_draw ) {
			return self::normalize_line_svg_for_preview( $svg, $connected );
		}

		$draw_w = max( 0.001, (float) $draw['width'] );
		$draw_h = max( 0.001, (float) $draw['height'] );
		// Match CDN type_1–20 picker look: fill available width (height may clip slightly in thumb CSS).
		$scale     = ( 0.96 * $target['width'] ) / $draw_w;
		$content_w = $draw_w * $scale;
		$content_h = $draw_h * $scale;
		$offset_x  = ( $target['width'] - $content_w ) / 2 - ( (float) $draw['x'] * $scale );
		$offset_y  = ( $target['height'] - $content_h ) / 2 - ( (float) $draw['y'] * $scale );

		return self::compose_line_preview_svg( $inner, $board, $half, $connected, $scale, $offset_x, $offset_y );
	}

	/**
	 * Wrap scaled line inner markup on the standard artboard.
	 *
	 * @param string $inner      SVG inner markup.
	 * @param array  $board      Full artboard size.
	 * @param array  $half       Half artboard size.
	 * @param bool   $connected  Mirror for connected lines.
	 * @param float  $scale      Uniform scale.
	 * @param float  $offset_x   Translate X.
	 * @param float  $offset_y   Translate Y.
	 * @return string
	 */
	private static function compose_line_preview_svg( $inner, $board, $half, $connected, $scale, $offset_x, $offset_y ) {
		$placed = sprintf(
			'<g transform="translate(%s,%s) scale(%s)">%s</g>',
			self::svg_num( $offset_x ),
			self::svg_num( $offset_y ),
			self::svg_num( $scale ),
			$inner
		);

		if ( $connected ) {
			$body = $placed . sprintf(
				'<g transform="translate(%s,0) scale(-1,1)">%s</g>',
				self::svg_num( $half['width'] ),
				$placed
			);
		} else {
			$body = $placed;
		}

		return sprintf(
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %s %s" fill="none">%s</svg>',
			self::svg_num( $board['width'] ),
			self::svg_num( $board['height'] ),
			$body
		);
	}

	/**
	 * @param float $n Number.
	 * @return string
	 */
	private static function svg_num( $n ) {
		$n = round( (float) $n, 4 );
		$s = rtrim( rtrim( sprintf( '%.4F', $n ), '0' ), '.' );
		return '' === $s ? '0' : $s;
	}
}
