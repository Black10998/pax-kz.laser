<?php
/**
 * Font library: defaults, uploads, visibility, labels (CMS).
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PCKZ_Font_Library
 */
class PCKZ_Font_Library {

	const OPTION_DISABLED   = 'pckz_font_disabled_ids';
	const OPTION_CUSTOM     = 'pckz_font_custom';
	const OPTION_LABELS     = 'pckz_font_labels';
	const UPLOAD_SUBDIR     = 'pckz-canonical-engine/fonts';
	const FONT_CACHE_PREFIX = 'pckzce_gfont_v2_';

	/**
	 * Category labels for admin / UI.
	 *
	 * @return array<string,string>
	 */
	public static function categories() {
		return array(
			'luxury'    => __( 'Luxus', 'pckz-canonical-engine' ),
			'elegant'   => __( 'Elegant', 'pckz-canonical-engine' ),
			'premium'   => __( 'Premium', 'pckz-canonical-engine' ),
			'modern'    => __( 'Modern', 'pckz-canonical-engine' ),
			'classic'   => __( 'Klassisch', 'pckz-canonical-engine' ),
			'sport'     => __( 'Sport', 'pckz-canonical-engine' ),
			'technical' => __( 'Technisch', 'pckz-canonical-engine' ),
			'display'   => __( 'Display', 'pckz-canonical-engine' ),
			'custom'    => __( 'Eigene Uploads', 'pckz-canonical-engine' ),
		);
	}

	/**
	 * Built-in default fonts.
	 *
	 * @return array<string,array>
	 */
	public static function default_catalog() {
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}
		$file  = PCKZCE_PLUGIN_DIR . 'includes/font-library-defaults.php';
		$cache = is_readable( $file ) ? include $file : array();
		if ( ! is_array( $cache ) ) {
			$cache = array();
		}
		foreach ( $cache as $id => $row ) {
			$cache[ $id ]['id']       = $id;
			$cache[ $id ]['builtin']  = true;
			$cache[ $id ]['source']   = $row['source'] ?? 'google';
			$cache[ $id ]['category'] = $row['category'] ?? 'modern';
		}
		return $cache;
	}

	/**
	 * Custom uploaded fonts from options.
	 *
	 * @return array<string,array>
	 */
	public static function custom_catalog() {
		$raw = get_option( self::OPTION_CUSTOM, array() );
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out = array();
		foreach ( $raw as $id => $row ) {
			$id = sanitize_key( $id );
			if ( ! $id || ! is_array( $row ) ) {
				continue;
			}
			$file = $row['file'] ?? '';
			if ( ! $file || ! is_readable( self::upload_dir() . '/' . $file ) ) {
				continue;
			}
			$family = ! empty( $row['family'] ) ? sanitize_text_field( $row['family'] ) : $id;
			$out[ $id ] = array(
				'id'       => $id,
				'label'    => sanitize_text_field( $row['label'] ?? $family ),
				'category' => 'custom',
				'family'   => $family,
				'source'   => 'upload',
				'file'     => sanitize_file_name( $file ),
				'sample'   => sanitize_text_field( $row['sample'] ?? 'ABC 123' ),
				'builtin'  => false,
			);
		}
		return $out;
	}

	/**
	 * Label overrides.
	 *
	 * @return array<string,string>
	 */
	public static function label_overrides() {
		$raw = get_option( self::OPTION_LABELS, array() );
		return is_array( $raw ) ? $raw : array();
	}

	/**
	 * Disabled font IDs.
	 *
	 * @return string[]
	 */
	public static function disabled_ids() {
		$raw = get_option( self::OPTION_DISABLED, array() );
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out = array();
		foreach ( $raw as $id ) {
			$id = sanitize_key( $id );
			if ( $id ) {
				$out[] = $id;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Full merged catalog (defaults + custom + label overrides).
	 *
	 * @return array<string,array>
	 */
	public static function all_entries() {
		$entries = array_merge( self::default_catalog(), self::custom_catalog() );
		$labels  = self::label_overrides();
		foreach ( $entries as $id => $row ) {
			if ( ! empty( $labels[ $id ] ) ) {
				$entries[ $id ]['label'] = sanitize_text_field( $labels[ $id ] );
			}
		}
		return $entries;
	}

	/**
	 * Find catalog entry by CSS font-family name (case-insensitive).
	 *
	 * @param string $family Font family.
	 * @return array|null
	 */
	public static function find_by_family( $family ) {
		$needle = strtolower( trim( (string) $family ) );
		if ( '' === $needle ) {
			return null;
		}
		foreach ( self::all_entries() as $row ) {
			$candidate = strtolower( trim( (string) ( $row['family'] ?? '' ) ) );
			if ( $candidate && $candidate === $needle ) {
				return $row;
			}
		}
		return null;
	}

	/**
	 * German category label for a font row.
	 *
	 * @param array $row Font row.
	 * @return string
	 */
	public static function category_label( $row ) {
		$key = (string) ( $row['category'] ?? '' );
		$map = self::categories();
		return $map[ $key ] ?? $key;
	}

	/**
	 * @param string $id Font ID.
	 * @return bool
	 */
	public static function is_visible( $id ) {
		return ! in_array( sanitize_key( $id ), self::disabled_ids(), true );
	}

	/**
	 * Customer-facing fonts (legacy shape: family + label + meta).
	 *
	 * @return array<int,array>
	 */
	public static function get_customer_fonts() {
		$list = array();
		$maps = self::build_font_file_maps();
		foreach ( self::all_entries() as $id => $row ) {
			if ( ! self::is_visible( $id ) ) {
				continue;
			}
			if ( empty( $maps['byId'][ $id ] ) ) {
				continue;
			}
			$list[] = array(
				'id'       => $id,
				'family'   => $row['family'] ?? '',
				'label'    => $row['label'] ?? $row['family'] ?? $id,
				'category' => $row['category'] ?? 'modern',
				'sample'   => $row['sample'] ?? 'Aa Bb 123',
				'source'   => $row['source'] ?? 'google',
			);
		}
		usort(
			$list,
			function ( $a, $b ) {
				$cat = strcmp( $a['category'] ?? '', $b['category'] ?? '' );
				return 0 !== $cat ? $cat : strcmp( $a['label'] ?? '', $b['label'] ?? '' );
			}
		);
		return $list;
	}

	/**
	 * First font shown in the customer font picker (category, then label).
	 *
	 * @return string Font family name.
	 */
	public static function default_customer_font_family() {
		$fonts = self::get_customer_fonts();
		if ( ! empty( $fonts[0]['family'] ) && is_string( $fonts[0]['family'] ) ) {
			return $fonts[0]['family'];
		}
		return 'Russo One';
	}

	/**
	 * Upload directory (creates if needed).
	 *
	 * @return string
	 */
	public static function upload_dir() {
		$upload = wp_upload_dir();
		$dir    = trailingslashit( $upload['basedir'] ) . self::UPLOAD_SUBDIR;
		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		return $dir;
	}

	/**
	 * Public URL base for uploaded fonts.
	 *
	 * @return string
	 */
	public static function upload_url_base() {
		$upload = wp_upload_dir();
		return trailingslashit( $upload['baseurl'] ) . self::UPLOAD_SUBDIR;
	}

	/**
	 * URL for uploaded font file.
	 *
	 * @param string $file Filename.
	 * @return string
	 */
	public static function font_file_url( $file ) {
		$file = sanitize_file_name( $file );
		if ( ! $file ) {
			return '';
		}
		return esc_url_raw( self::upload_url_base() . '/' . $file );
	}

	/**
	 * Build Google Fonts CSS URL for enabled google-source fonts.
	 *
	 * @return string
	 */
	public static function google_fonts_css_url() {
		$ids = array();
		foreach ( self::all_entries() as $id => $row ) {
			if ( ! self::is_visible( $id ) ) {
				continue;
			}
			if ( 'google' !== ( $row['source'] ?? '' ) || empty( $row['google_id'] ) ) {
				continue;
			}
			$ids[] = $row['google_id'];
		}
		$ids = array_values( array_unique( $ids ) );
		if ( empty( $ids ) ) {
			return '';
		}
		return 'https://fonts.googleapis.com/css2?family=' . implode( '&family=', $ids ) . '&display=swap';
	}

	/**
	 * Inline @font-face CSS for uploaded fonts.
	 *
	 * @return string
	 */
	public static function uploaded_fonts_css() {
		$css = '';
		foreach ( self::custom_catalog() as $id => $row ) {
			if ( ! self::is_visible( $id ) ) {
				continue;
			}
			$url    = self::font_file_url( $row['file'] ?? '' );
			$family = $row['family'] ?? $id;
			if ( ! $url ) {
				continue;
			}
			$format = 'woff2';
			$ext    = strtolower( pathinfo( $row['file'], PATHINFO_EXTENSION ) );
			if ( 'ttf' === $ext ) {
				$format = 'truetype';
			} elseif ( 'otf' === $ext ) {
				$format = 'opentype';
			} elseif ( 'woff' === $ext ) {
				$format = 'woff';
			}
			$css .= '@font-face{font-family:' . self::css_family( $family ) . ';src:url("' . esc_url( $url ) . '") format("' . $format . '");font-display:swap;}';
		}
		return $css;
	}

	/**
	 * @param string $family Font family.
	 * @return string
	 */
	private static function css_family( $family ) {
		return '"' . str_replace( '"', '\\"', $family ) . '"';
	}

	/**
	 * Build OpenType binary URL maps for all visible catalog fonts.
	 *
	 * @return array{byFamily: array<string,string>, byId: array<string,string>}
	 */
	/** @var array|null Request-level cache for font URL maps. */
	private static $font_maps_cache = null;

	/**
	 * Drop in-memory font URL map (after catalog/cache changes).
	 */
	public static function reset_font_file_maps_cache() {
		self::$font_maps_cache = null;
	}

	public static function build_font_file_maps() {
		if ( null !== self::$font_maps_cache ) {
			return self::$font_maps_cache;
		}

		$by_family = array();
		$by_id     = array();

		foreach ( self::all_entries() as $id => $row ) {
			if ( ! self::is_visible( $id ) ) {
				continue;
			}
			$url = self::export_url_for_frontend( $id, $row );
			if ( ! $url ) {
				continue;
			}
			$by_id[ $id ] = $url;
			$family       = trim( (string) ( $row['family'] ?? '' ) );
			if ( '' !== $family ) {
				$by_family[ strtolower( $family ) ] = $url;
			}
		}

		self::$font_maps_cache = array(
			'byFamily' => $by_family,
			'byId'     => $by_id,
		);
		return self::$font_maps_cache;
	}

	/**
	 * Font files map for opentype.js (lowercase CSS family name => binary URL).
	 *
	 * @return array<string,string>
	 */
	public static function font_files_for_js() {
		$maps = self::build_font_file_maps();
		return $maps['byFamily'];
	}

	/**
	 * Font files by catalog ID (for picker / export lookup).
	 *
	 * @return array<string,string>
	 */
	public static function font_files_by_id_for_js() {
		$maps = self::build_font_file_maps();
		return $maps['byId'];
	}

	/**
	 * Resolve a downloadable font binary for OpenType.js (Google CSS → gstatic, or upload).
	 *
	 * @param string $id  Font catalog ID.
	 * @param array  $row Catalog row.
	 * @return string
	 */
	public static function resolve_font_binary_url( $id, $row ) {
		if ( ! empty( $row['file'] ) ) {
			$url = self::font_file_url( $row['file'] );
			return self::is_export_safe_binary_url( $url ) ? $url : '';
		}
		if ( 'google' === ( $row['source'] ?? '' ) ) {
			return self::resolve_google_font_binary_url( $id, $row );
		}
		return '';
	}

	/**
	 * Whether a remote/binary URL is safe for OpenType.js export (TTF/OTF/WOFF, not WOFF2).
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	public static function is_export_safe_binary_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return false;
		}
		if ( preg_match( '/\.woff2(\?|$)/i', $url ) ) {
			return false;
		}
		return (bool) preg_match( '/\.(ttf|otf|woff)(\?|$)/i', $url );
	}

	/**
	 * Frontend OpenType.js URL (same-origin proxy for Google fonts).
	 *
	 * @param string $id  Font ID.
	 * @param array  $row Catalog row.
	 * @return string
	 */
	public static function export_url_for_frontend( $id, $row ) {
		$id = sanitize_key( $id );
		if ( ! $id ) {
			return '';
		}
		$binary = self::resolve_font_binary_url( $id, $row );
		if ( ! self::is_export_safe_binary_url( $binary ) ) {
			return '';
		}
		if ( 'google' === ( $row['source'] ?? '' ) ) {
			return add_query_arg(
				array(
					'action'  => 'pckzce_font_file',
					'font_id' => $id,
					'nonce'   => wp_create_nonce( 'pckzce_font_file' ),
				),
				admin_url( 'admin-ajax.php' )
			);
		}
		return $binary;
	}

	/**
	 * Resolve Google Fonts CSS to a gstatic woff2/woff/ttf URL (cached).
	 *
	 * @param string $id  Font ID.
	 * @param array  $row Catalog row.
	 * @return string
	 */
	public static function resolve_google_font_binary_url( $id, $row ) {
		$id = sanitize_key( $id );
		if ( ! $id ) {
			return '';
		}

		$cache_key = self::FONT_CACHE_PREFIX . $id;
		$cached    = get_transient( $cache_key );
		if ( is_string( $cached ) && '' !== $cached ) {
			if ( self::is_export_safe_binary_url( $cached ) ) {
				return $cached;
			}
			delete_transient( $cache_key );
		}
		// Drop legacy v1 transients that may still hold subset woff2 URLs.
		delete_transient( 'pckzce_gfont_bin_' . $id );

		$google_id = trim( (string) ( $row['google_id'] ?? '' ) );
		if ( '' === $google_id && ! empty( $row['family'] ) ) {
			$google_id = str_replace( ' ', '+', trim( (string) $row['family'] ) );
		}
		if ( '' === $google_id ) {
			return '';
		}

		$css_url  = 'https://fonts.googleapis.com/css2?family=' . $google_id . '&display=swap';
		// TTF/OTF for OpenType.js (woff2 is preview-only via Google CSS in the browser).
		$response = wp_remote_get(
			$css_url,
			array(
				'timeout' => 20,
				'headers' => array(
					'User-Agent' => 'Mozilla/5.0 (Linux; U; Android 2.2; en-us; Droid Build/FRG22D) AppleWebKit/533.1 (KHTML, like Gecko) Version/4.0 Mobile Safari/533.1',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return '';
		}

		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return '';
		}

		$url = self::parse_google_fonts_css_binary_url( wp_remote_retrieve_body( $response ) );
		if ( $url && self::is_export_safe_binary_url( $url ) ) {
			set_transient( $cache_key, $url, 30 * DAY_IN_SECONDS );
			return $url;
		}

		return '';
	}

	/**
	 * Pick the best @font-face binary URL from a Google Fonts CSS response.
	 *
	 * @param string $css CSS body.
	 * @return string
	 */
	public static function parse_google_fonts_css_binary_url( $css ) {
		if ( ! is_string( $css ) || '' === $css ) {
			return '';
		}

		$candidates = array();
		if ( preg_match_all(
			'#(?:/\*\s*([^*]+?)\s*\*/\s*)?@font-face\s*\{([^}]+)\}#is',
			$css,
			$faces,
			PREG_SET_ORDER
		) ) {
			foreach ( $faces as $face ) {
				$comment = strtolower( trim( (string) ( $face[1] ?? '' ) ) );
				$block   = (string) ( $face[2] ?? '' );
				if ( ! preg_match( '#url\((https://fonts\.gstatic\.com/[^)]+\.(woff2|woff|ttf|otf))\)#i', $block, $match ) ) {
					continue;
				}
				$weight = 400;
				if ( preg_match( '/font-weight:\s*(\d+)/i', $block, $wm ) ) {
					$weight = (int) $wm[1];
				}
				$candidates[] = array(
					'url'          => $match[1],
					'ext'          => strtolower( $match[2] ),
					'weight'       => $weight,
					'subset_score' => self::google_font_subset_score( $comment, $block ),
				);
			}
		}

		if ( empty( $candidates ) ) {
			return '';
		}

		$weight_rank = array( 700 => 0, 600 => 1, 500 => 2, 400 => 3 );
		$ext_rank    = array( 'ttf' => 0, 'otf' => 1, 'woff' => 2, 'woff2' => 3 );

		usort(
			$candidates,
			function ( $a, $b ) use ( $weight_rank, $ext_rank ) {
				if ( $a['subset_score'] !== $b['subset_score'] ) {
					return $a['subset_score'] - $b['subset_score'];
				}
				$wa = $weight_rank[ $a['weight'] ] ?? ( 10 + abs( 700 - $a['weight'] ) );
				$wb = $weight_rank[ $b['weight'] ] ?? ( 10 + abs( 700 - $b['weight'] ) );
				if ( $wa !== $wb ) {
					return $wa - $wb;
				}
				$ea = $ext_rank[ $a['ext'] ] ?? 9;
				$eb = $ext_rank[ $b['ext'] ] ?? 9;
				if ( $ea !== $eb ) {
					return $ea - $eb;
				}
				return $b['weight'] - $a['weight'];
			}
		);

		return $candidates[0]['url'];
	}

	/**
	 * Rank @font-face subset for export (Latin plate text). Lower is better.
	 *
	 * @param string $comment CSS comment before @font-face (e.g. "latin").
	 * @param string $block   @font-face rule body.
	 * @return int
	 */
	private static function google_font_subset_score( $comment, $block ) {
		if ( 'latin' === $comment ) {
			return 0;
		}
		if ( false !== strpos( $comment, 'latin-ext' ) ) {
			return 1;
		}
		if ( preg_match( '/unicode-range:\s*([^;]+)/i', $block, $um ) ) {
			$range = $um[1];
			if ( preg_match( '/U\+0000-00FF/i', $range ) ) {
				return 0;
			}
			if ( preg_match( '/U\+0100-02BA/i', $range ) || preg_match( '/U\+0100-024F/i', $range ) ) {
				return 1;
			}
			return 10;
		}
		return 2;
	}

	/**
	 * Clear cached Google font binary URLs (after catalog changes).
	 */
	public static function clear_google_font_cache() {
		foreach ( array_keys( self::default_catalog() ) as $id ) {
			$id = sanitize_key( $id );
			delete_transient( self::FONT_CACHE_PREFIX . $id );
			delete_transient( 'pckzce_gfont_bin_' . $id );
		}
		self::reset_font_file_maps_cache();
	}

	/**
	 * Stream export-safe font binary for OpenType.js (admin-ajax proxy).
	 *
	 * @param string $font_id Font catalog ID.
	 * @return true|WP_Error
	 */
	public static function stream_font_binary( $font_id ) {
		$font_id = sanitize_key( $font_id );
		$entries = self::all_entries();
		if ( empty( $entries[ $font_id ] ) || ! self::is_visible( $font_id ) ) {
			return new WP_Error( 'font_missing', __( 'Font not found.', 'pckz-canonical-engine' ), array( 'status' => 404 ) );
		}
		$row = $entries[ $font_id ];
		$url = self::resolve_font_binary_url( $font_id, $row );
		if ( ! self::is_export_safe_binary_url( $url ) ) {
			return new WP_Error( 'font_unsafe', __( 'Font binary is not export-safe.', 'pckz-canonical-engine' ), array( 'status' => 404 ) );
		}

		if ( ! empty( $row['file'] ) && is_readable( self::upload_dir() . '/' . sanitize_file_name( $row['file'] ) ) ) {
			$path = self::upload_dir() . '/' . sanitize_file_name( $row['file'] );
			$body = file_get_contents( $path );
			if ( false === $body || '' === $body ) {
				return new WP_Error( 'font_read_fail', __( 'Could not read font file.', 'pckz-canonical-engine' ), array( 'status' => 500 ) );
			}
			self::send_font_binary_headers( $path );
			echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return true;
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 30,
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error( 'font_remote_fail', __( 'Remote font fetch failed.', 'pckz-canonical-engine' ), array( 'status' => 502 ) );
		}
		$body = wp_remote_retrieve_body( $response );
		if ( '' === $body ) {
			return new WP_Error( 'font_empty', __( 'Font file is empty.', 'pckz-canonical-engine' ), array( 'status' => 502 ) );
		}
		self::send_font_binary_headers( $url );
		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return true;
	}

	/**
	 * @param string $path_or_url File path or URL (for extension).
	 */
	private static function send_font_binary_headers( $path_or_url ) {
		$ext  = strtolower( pathinfo( parse_url( $path_or_url, PHP_URL_PATH ) ?? '', PATHINFO_EXTENSION ) );
		$mime = 'font/ttf';
		if ( 'otf' === $ext ) {
			$mime = 'font/otf';
		} elseif ( 'woff' === $ext ) {
			$mime = 'font/woff';
		}
		if ( ! headers_sent() ) {
			header( 'Content-Type: ' . $mime );
			header( 'Access-Control-Allow-Origin: *' );
			header( 'Cache-Control: public, max-age=86400' );
		}
	}

	/**
	 * Pre-resolve Google font binaries (admin / activation warm-up).
	 *
	 * @return array{resolved:int, failed:string[]}
	 */
	public static function warm_google_font_cache() {
		$resolved = 0;
		$failed   = array();
		foreach ( self::default_catalog() as $id => $row ) {
			if ( 'google' !== ( $row['source'] ?? '' ) || ! self::is_visible( $id ) ) {
				continue;
			}
			$url = self::resolve_google_font_binary_url( $id, $row );
			if ( $url ) {
				++$resolved;
			} else {
				$failed[] = $id;
			}
		}
		self::reset_font_file_maps_cache();
		return array(
			'resolved' => $resolved,
			'failed'   => $failed,
		);
	}

	/**
	 * Upload font file from $_FILES.
	 *
	 * @param array $file File array.
	 * @return array|WP_Error
	 */
	public static function handle_upload( $file ) {
		if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error( 'no_file', __( 'Keine Schriftdatei empfangen.', 'pckz-canonical-engine' ) );
		}
		$allowed = array(
			'woff2' => 'font/woff2',
			'woff'  => 'font/woff',
			'ttf'   => 'font/ttf',
			'otf'   => 'font/otf',
		);
		$check   = wp_check_filetype( $file['name'], $allowed );
		if ( empty( $check['ext'] ) ) {
			return new WP_Error( 'bad_type', __( 'Erlaubt: WOFF2, WOFF, TTF, OTF.', 'pckz-canonical-engine' ) );
		}
		$base     = sanitize_title( pathinfo( $file['name'], PATHINFO_FILENAME ) );
		$slug     = sanitize_key( $base );
		if ( ! $slug ) {
			$slug = 'font-' . wp_generate_password( 6, false, false );
		}
		$existing = self::all_entries();
		if ( isset( $existing[ $slug ] ) ) {
			$slug .= '-' . substr( wp_generate_uuid4(), 0, 6 );
		}
		$filename = $slug . '.' . $check['ext'];
		$dest     = self::upload_dir() . '/' . $filename;
		if ( ! move_uploaded_file( $file['tmp_name'], $dest ) ) {
			return new WP_Error( 'move_fail', __( 'Upload fehlgeschlagen.', 'pckz-canonical-engine' ) );
		}
		$label  = isset( $_POST['font_upload_label'] ) ? sanitize_text_field( wp_unslash( $_POST['font_upload_label'] ) ) : ucwords( str_replace( '-', ' ', $base ) );
		$family = isset( $_POST['font_upload_family'] ) ? sanitize_text_field( wp_unslash( $_POST['font_upload_family'] ) ) : $label;
		$custom = get_option( self::OPTION_CUSTOM, array() );
		if ( ! is_array( $custom ) ) {
			$custom = array();
		}
		$custom[ $slug ] = array(
			'label'  => $label,
			'family' => $family,
			'file'   => $filename,
			'sample' => 'ABC 123',
		);
		update_option( self::OPTION_CUSTOM, $custom );
		self::reset_font_file_maps_cache();
		return array( 'id' => $slug, 'slug' => $slug );
	}

	/**
	 * Delete custom uploaded font.
	 *
	 * @param string $id Font ID.
	 * @return bool|WP_Error
	 */
	public static function delete_custom( $id ) {
		$id = sanitize_key( $id );
		$custom = get_option( self::OPTION_CUSTOM, array() );
		if ( ! is_array( $custom ) || empty( $custom[ $id ] ) ) {
			return new WP_Error( 'not_custom', __( 'Nur hochgeladene Schriften können gelöscht werden.', 'pckz-canonical-engine' ) );
		}
		$file = $custom[ $id ]['file'] ?? '';
		unset( $custom[ $id ] );
		update_option( self::OPTION_CUSTOM, $custom );
		if ( $file ) {
			$path = self::upload_dir() . '/' . sanitize_file_name( $file );
			if ( is_readable( $path ) ) {
				if ( function_exists( 'wp_delete_file' ) ) {
					wp_delete_file( $path );
				} else {
					unlink( $path );
				}
			}
		}
		return true;
	}

	/**
	 * Save admin form: disabled list + labels.
	 *
	 * @param array $enabled_ids Enabled font IDs.
	 * @param array $labels      ID => label.
	 */
	public static function save_admin_state( $enabled_ids, $labels ) {
		$all      = array_keys( self::all_entries() );
		$enabled  = array_map( 'sanitize_key', (array) $enabled_ids );
		$disabled = array();
		foreach ( $all as $id ) {
			if ( ! in_array( $id, $enabled, true ) ) {
				$disabled[] = $id;
			}
		}
		update_option( self::OPTION_DISABLED, $disabled );
		$clean_labels = array();
		if ( is_array( $labels ) ) {
			foreach ( $labels as $id => $label ) {
				$id = sanitize_key( $id );
				$label = sanitize_text_field( $label );
				if ( $id && $label ) {
					$clean_labels[ $id ] = $label;
				}
			}
		}
		update_option( self::OPTION_LABELS, $clean_labels );
		self::clear_google_font_cache();
	}
}
