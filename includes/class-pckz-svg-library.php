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
}
