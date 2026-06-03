<?php
/**
 * Admin vector import: LightBurn LBRN2, SVG, AI, EPS, DXF, PDF → line library SVG.
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PCKZ_Line_Importer
 */
class PCKZ_Line_Importer {

	const MAX_BYTES = 15728640;

	/**
	 * Supported upload extensions (lowercase, no dot).
	 *
	 * @return array<string,string> ext => mime hint for wp_check_filetype
	 */
	public static function allowed_extensions() {
		return array(
			'svg'   => 'image/svg+xml',
			'lbrn2' => 'application/octet-stream',
			'ai'    => 'application/postscript',
			'eps'   => 'application/postscript',
			'dxf'   => 'application/dxf',
			'pdf'   => 'application/pdf',
		);
	}

	/**
	 * Accept attribute for HTML file input.
	 *
	 * @return string
	 */
	public static function accept_attribute() {
		$parts = array();
		foreach ( array_keys( self::allowed_extensions() ) as $ext ) {
			$parts[] = '.' . $ext;
		}
		return implode( ',', $parts );
	}

	/**
	 * Whether PHP may invoke shell commands (exec not disabled).
	 *
	 * @return bool
	 */
	public static function shell_exec_available() {
		return function_exists( 'exec' );
	}

	/**
	 * Whether Python import tooling is available.
	 *
	 * @return bool
	 */
	public static function converter_available() {
		if ( ! self::shell_exec_available() ) {
			return false;
		}
		$python = self::python_binary();
		$script = self::converter_script_path();
		return $python && is_readable( $script );
	}

	/**
	 * Admin notice when vector conversion cannot run (optional import only).
	 *
	 * @return string Empty when converter is available.
	 */
	public static function environment_notice() {
		if ( self::converter_available() ) {
			return '';
		}
		if ( ! self::shell_exec_available() ) {
			return __(
				'Vector conversion is disabled on this server (PHP exec() is not available). The Line Library continues to work. You can still upload SVG files that are already 950×35; LBRN2, AI, EPS, DXF, and PDF import require exec() and Python on the host.',
				'pckz-canonical-engine'
			);
		}
		$script = self::converter_script_path();
		if ( ! is_readable( $script ) ) {
			return __(
				'Vector conversion script is missing on the server. Upload canonical 950×35 SVG only, or reinstall the plugin package.',
				'pckz-canonical-engine'
			);
		}
		return __(
			'Python 3 is required on the server to convert LBRN2, AI, EPS, DXF, and PDF. SVG upload still works when already 950×35.',
			'pckz-canonical-engine'
		);
	}

	/**
	 * File input accept list for current environment.
	 *
	 * @return string
	 */
	public static function accept_attribute_for_environment() {
		if ( self::converter_available() ) {
			return self::accept_attribute();
		}
		return '.svg';
	}

	/**
	 * @return string|false
	 */
	public static function python_binary() {
		if ( ! self::shell_exec_available() ) {
			return false;
		}
		$candidates = array( 'python3', 'python' );
		foreach ( $candidates as $bin ) {
			$path = self::which( $bin );
			if ( $path ) {
				return $path;
			}
		}
		return false;
	}

	/**
	 * @param string $cmd Command name.
	 * @return string|false
	 */
	private static function which( $cmd ) {
		if ( ! self::shell_exec_available() ) {
			return false;
		}
		if ( function_exists( 'escapeshellcmd' ) ) {
			$safe = escapeshellcmd( $cmd );
		} else {
			$safe = preg_replace( '/[^a-zA-Z0-9._-]/', '', $cmd );
		}
		$out = array();
		$code = 1;
		exec( 'command -v ' . $safe . ' 2>/dev/null', $out, $code );
		if ( 0 === $code && ! empty( $out[0] ) ) {
			return trim( $out[0] );
		}
		return false;
	}

	/**
	 * @return string
	 */
	public static function converter_script_path() {
		return trailingslashit( PCKZCE_PLUGIN_DIR ) . 'tools/pckz-line-import-convert.py';
	}

	/**
	 * Import uploaded vector file into line library.
	 *
	 * @param array $file $_FILES row.
	 * @param array $args Optional: label, preserve_colors, fill_color, connected_right.
	 * @return array|WP_Error { slug, format }
	 */
	public static function import_upload( $file, $args = array() ) {
		if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error( 'no_file', __( 'Keine Datei empfangen.', 'pckz-canonical-engine' ) );
		}
		if ( ! empty( $file['error'] ) && UPLOAD_ERR_OK !== (int) $file['error'] ) {
			return new WP_Error( 'upload_error', __( 'Datei-Upload fehlgeschlagen.', 'pckz-canonical-engine' ) );
		}
		if ( ! empty( $file['size'] ) && (int) $file['size'] > self::MAX_BYTES ) {
			return new WP_Error(
				'too_large',
				__( 'Die Datei ist zu groß (max. 15 MB).', 'pckz-canonical-engine' )
			);
		}

		$name = isset( $file['name'] ) ? sanitize_file_name( wp_unslash( $file['name'] ) ) : '';
		$ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		$allowed = self::allowed_extensions();
		if ( ! $ext || ! isset( $allowed[ $ext ] ) ) {
			return new WP_Error(
				'bad_type',
				__( 'Dateityp nicht unterstützt. Erlaubt: LBRN2, SVG, AI, EPS, DXF, PDF.', 'pckz-canonical-engine' )
			);
		}

		if ( 'svg' !== $ext && ! self::converter_available() ) {
			$notice = self::environment_notice();
			return new WP_Error(
				'no_converter',
				$notice ? $notice : __( 'Vektor-Import ist in dieser Umgebung nicht verfügbar.', 'pckz-canonical-engine' )
			);
		}

		$slug = PCKZ_Line_Library::next_upload_slug();
		$label = isset( $args['label'] ) ? sanitize_text_field( $args['label'] ) : '';
		if ( '' === $label ) {
			$stem = pathinfo( $name, PATHINFO_FILENAME );
			$label = $stem ? ucwords( str_replace( array( '-', '_' ), ' ', $stem ) ) : PCKZ_Line_Library::default_label_for_slug( $slug );
		}

		if ( 'svg' === $ext ) {
			$contents = file_get_contents( $file['tmp_name'] );
			if ( false === $contents ) {
				return new WP_Error( 'read_fail', __( 'SVG konnte nicht gelesen werden.', 'pckz-canonical-engine' ) );
			}
			if ( ! PCKZ_Line_Library::is_safe_svg( $contents ) ) {
				return new WP_Error( 'unsafe_svg', __( 'SVG enthält nicht erlaubte Inhalte.', 'pckz-canonical-engine' ) );
			}
			if ( ! self::is_canonical_line_svg( $contents ) ) {
				$converted = self::convert_temp_file( $file['tmp_name'], $ext, $args );
				if ( is_wp_error( $converted ) ) {
					return $converted;
				}
				$contents = $converted;
			}
			return PCKZ_Line_Library::store_custom_line_svg(
				$contents,
				$slug,
				$label,
				self::source_key_for_format( $ext ),
				$args
			);
		}

		$svg_body = self::convert_temp_file( $file['tmp_name'], $ext, $args );
		if ( is_wp_error( $svg_body ) ) {
			return $svg_body;
		}
		if ( ! PCKZ_Line_Library::is_safe_svg( $svg_body ) ) {
			return new WP_Error( 'unsafe_svg', __( 'Konvertiertes SVG ist nicht gültig.', 'pckz-canonical-engine' ) );
		}

		return PCKZ_Line_Library::store_custom_line_svg(
			$svg_body,
			$slug,
			$label,
			self::source_key_for_format( $ext ),
			$args
		);
	}

	/**
	 * @param string $contents SVG source.
	 * @return bool
	 */
	public static function is_canonical_line_svg( $contents ) {
		return (bool) preg_match( '/viewBox\s*=\s*["\']0\s+0\s+950\s+35["\']/i', $contents );
	}

	/**
	 * @param string $tmp_path Temp upload path.
	 * @param string $ext      File extension.
	 * @param array  $args     Import options.
	 * @return string|WP_Error SVG body.
	 */
	private static function convert_temp_file( $tmp_path, $ext, $args ) {
		if ( ! self::shell_exec_available() ) {
			return new WP_Error(
				'no_shell',
				__(
					'Vektor-Konvertierung ist auf diesem Server deaktiviert (PHP exec() nicht verfügbar). Nur fertige 950×35-SVG-Dateien können hochgeladen werden.',
					'pckz-canonical-engine'
				)
			);
		}
		if ( ! self::converter_available() ) {
			return new WP_Error(
				'no_converter',
				__( 'Vektor-Konverter (Python) ist auf dem Server nicht verfügbar.', 'pckz-canonical-engine' )
			);
		}

		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) {
			return new WP_Error( 'upload_dir', $upload_dir['error'] );
		}
		$tmp_out = trailingslashit( $upload_dir['basedir'] ) . 'pckz-line-import-' . wp_generate_password( 12, false ) . '.svg';

		$python = self::python_binary();
		$script = self::converter_script_path();
		$fill   = isset( $args['fill_color'] ) ? sanitize_text_field( $args['fill_color'] ) : '';
		if ( '' === $fill && empty( $args['preserve_colors'] ) ) {
			$fill = 'white';
		}
		if ( '' === $fill ) {
			$fill = 'white';
		}

		$cmd = escapeshellarg( $python ) . ' ' . escapeshellarg( $script )
			. ' ' . escapeshellarg( $tmp_path )
			. ' ' . escapeshellarg( $tmp_out )
			. ' --fill-color ' . escapeshellarg( $fill );

		$out = array();
		$code = 1;
		exec( $cmd . ' 2>&1', $out, $code );
		if ( 0 !== $code || ! is_readable( $tmp_out ) ) {
			$msg = trim( implode( "\n", $out ) );
			if ( '' === $msg ) {
				$msg = __( 'Konvertierung fehlgeschlagen.', 'pckz-canonical-engine' );
			}
			if ( is_readable( $tmp_out ) ) {
				wp_delete_file( $tmp_out );
			}
			return new WP_Error( 'convert_fail', $msg );
		}

		$body = file_get_contents( $tmp_out );
		wp_delete_file( $tmp_out );
		if ( false === $body || '' === $body ) {
			return new WP_Error( 'convert_empty', __( 'Konvertierung lieferte eine leere SVG-Datei.', 'pckz-canonical-engine' ) );
		}
		return $body;
	}

	/**
	 * @param string $ext Extension.
	 * @return string Manifest source key.
	 */
	public static function source_key_for_format( $ext ) {
		$map = array(
			'svg'   => 'import_svg',
			'lbrn2' => 'import_lbrn2',
			'ai'    => 'import_ai',
			'eps'   => 'import_eps',
			'dxf'   => 'import_dxf',
			'pdf'   => 'import_pdf',
		);
		return $map[ $ext ] ?? 'import_vector';
	}

	/**
	 * Human-readable format label for admin UI.
	 *
	 * @param string $source Manifest source key.
	 * @return string
	 */
	public static function format_label_for_source( $source ) {
		$labels = array(
			'upload'       => 'SVG',
			'url'          => 'URL',
			'import_svg'   => 'SVG',
			'import_lbrn2' => 'LightBurn',
			'import_ai'    => 'Illustrator',
			'import_eps'   => 'EPS',
			'import_dxf'   => 'DXF',
			'import_pdf'   => 'PDF',
			'import_vector'=> 'Vector',
		);
		return $labels[ $source ] ?? $source;
	}
}
