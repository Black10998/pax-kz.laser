<?php
/**
 * LightBurn-ready production package (1:1 manufacturing JSON).
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PCKZ_Lightburn_Export
 */
class PCKZ_Lightburn_Export {

	const PACKAGE_VERSION = '1.0';

	/**
	 * Build complete LightBurn / laser production package.
	 *
	 * @param array $args Arguments (layout, selections, config, canvas_json, preview_url, export_url, design_id).
	 * @return array
	 */
	public static function build_package( $args ) {
		$layout      = $args['layout'] ?? array();
		$selections  = $args['selections'] ?? array();
		$config      = $args['config'] ?? array();
		$canvas_json = $args['canvas_json'] ?? '';
		$std_spec    = $args['std_spec'] ?? array();

		if ( empty( $std_spec ) && class_exists( 'PCKZ_Std_Spec' ) ) {
			$std_spec = PCKZ_Std_Spec::for_product( $config );
		}

		$canvas_data = array();
		if ( ! empty( $canvas_json ) ) {
			$decoded = json_decode( $canvas_json, true );
			if ( is_array( $decoded ) ) {
				$canvas_data = $decoded;
			}
		}

		$line_types = class_exists( 'PCKZ_Ledos_Preview' ) ? PCKZ_Ledos_Preview::line_types() : array();
		$icon_cat   = class_exists( 'PCKZ_Ledos_Preview' ) ? PCKZ_Ledos_Preview::icon_catalog() : array();

		$objects_export = array();
		if ( ! empty( $layout['objects'] ) && is_array( $layout['objects'] ) ) {
			foreach ( $layout['objects'] as $obj ) {
				$objects_export[] = self::format_object_for_lightburn( $obj, $line_types, $icon_cat );
			}
		}

		$linien = $selections['linien'] ?? 'none';
		$line_svg = '';
		if ( 'none' !== $linien && ! empty( $line_types[ $linien ] ) ) {
			$line_svg = $line_types[ $linien ];
		}

		return array(
			'format'           => 'pckz-lightburn-package',
			'package_version'  => self::PACKAGE_VERSION,
			'plugin_version'   => PCKZCE_VERSION,
			'generated_at'     => current_time( 'mysql' ),
			'design_id'        => (int) ( $args['design_id'] ?? 0 ),
			'units'            => 'mm',
			'coordinate_origin' => $layout['coordinate_origin'] ?? ( $std_spec['coordinate_origin'] ?? 'bottom-left' ),
			'engine'           => $layout['engine'] ?? 'cloudlift-3651',
			'standard'         => $layout['standard'] ?? ( $std_spec['standard'] ?? '' ),
			'dpi'              => (int) ( $layout['dpi'] ?? ( $std_spec['dpi'] ?? 300 ) ),
			'canvas_mm'        => $layout['canvas_mm'] ?? ( $std_spec['canvas_mm'] ?? array() ),
			'design_px'        => $layout['design_px'] ?? ( $std_spec['design_space_px'] ?? array() ),
			'safe_zone_mm'     => $layout['safe_zone_mm'] ?? ( $std_spec['safe_zone_mm'] ?? null ),
			'strip_zone_mm'    => $layout['strip_zone_mm'] ?? ( $std_spec['strip_zone_mm'] ?? null ),
			'layer_refs'       => $layout['layer_refs'] ?? ( class_exists( 'PCKZ_Ledos_Preview' ) ? PCKZ_Ledos_Preview::layer_refs() : array() ),
			'background_fit'   => $layout['background_fit_canvas'] ?? null,
			'selections'       => $selections,
			'colors'           => self::extract_colors( $selections, $layout ),
			'line_overlay'     => array(
				'type'    => $linien,
				'svg_url' => $line_svg,
			),
			'objects'          => $objects_export,
			'layout'           => $layout,
			'std_spec'         => $std_spec,
			'preview_url'      => $args['preview_url'] ?? '',
			'export_url'       => $args['export_url'] ?? '',
			'canvas_objects'   => $canvas_data['objects'] ?? array(),
			'canvas_meta'      => $canvas_data['pckzMeta'] ?? array(),
		);
	}

	/**
	 * Format single layout object for LightBurn import reference.
	 *
	 * @param array $obj         Layout object.
	 * @param array $line_types  Line type URLs.
	 * @param array $icon_cat    Icon catalog.
	 * @return array
	 */
	private static function format_object_for_lightburn( $obj, $line_types, $icon_cat ) {
		$role = $obj['role'] ?? 'object';
		$mm   = $obj['mm'] ?? array();
		$dpx  = $obj['design_px'] ?? array();

		$out = array(
			'role'        => $role,
			'alignment'   => $obj['alignment'] ?? 'center',
			'x_mm'        => $mm['x_mm'] ?? null,
			'y_mm'        => $mm['y_mm'] ?? null,
			'width_mm'    => $mm['width_mm'] ?? null,
			'height_mm'   => $mm['height_mm'] ?? null,
			'center_x_mm' => $mm['center_x_mm'] ?? null,
			'center_y_mm' => $mm['center_y_mm'] ?? null,
			'design_px'   => array(
				'left'   => $dpx['left'] ?? null,
				'top'    => $dpx['top'] ?? null,
				'width'  => $dpx['width'] ?? null,
				'height' => $dpx['height'] ?? null,
				'angle'  => $dpx['angle'] ?? 0,
			),
		);

		if ( in_array( $role, array( 'text', 'main-text' ), true ) ) {
			$out['text']         = $obj['text'] ?? '';
			$out['font_family']  = $obj['font_family'] ?? '';
			$out['font_size_px'] = $obj['font_size_px'] ?? null;
			$out['fill']         = $obj['fill'] ?? '';
			$out['stroke']       = $obj['stroke'] ?? '';
			$out['stroke_width'] = $obj['stroke_width'] ?? 0;
			$out['text_align']   = $obj['text_align'] ?? 'center';
		}

		if ( in_array( $role, array( 'icon-left', 'icon-right' ), true ) ) {
			$symbol = $obj['symbol'] ?? '';
			$out['symbol']   = $symbol;
			$out['fill']     = $obj['fill'] ?? '';
			$out['svg_url']  = ! empty( $icon_cat[ $symbol ]['url'] ) ? $icon_cat[ $symbol ]['url'] : '';
			$out['tintable'] = ! empty( $icon_cat[ $symbol ]['tintable'] );
		}

		if ( 'lines' === $role ) {
			$lt = $obj['line_type'] ?? 'none';
			$out['line_type'] = $lt;
			$out['fill']      = $obj['fill'] ?? '';
			$out['svg_url']   = ! empty( $line_types[ $lt ] ) ? $line_types[ $lt ] : '';
		}

		if ( in_array( $role, array( 'logo', 'image' ), true ) ) {
			$out['src'] = $obj['src'] ?? '';
		}

		return $out;
	}

	/**
	 * Extract color map from selections.
	 *
	 * @param array $selections Selections.
	 * @param array $layout     Layout.
	 * @return array
	 */
	private static function extract_colors( $selections, $layout ) {
		$keys = array( 'text_color', 'icon_color_left', 'icon_color_right', 'line_color' );
		$out  = array();
		foreach ( $keys as $key ) {
			if ( ! empty( $selections[ $key ] ) ) {
				$val = $selections[ $key ];
				$out[ $key ] = array(
					'value' => $val,
					'hex'   => class_exists( 'PCKZ_Ledos_Preview' )
						? PCKZ_Ledos_Preview::hex_for_color_value( $val )
						: $val,
				);
			}
		}
		return $out;
	}

	/**
	 * Persist package JSON under uploads and return public URL.
	 *
	 * @param array $package   Package data.
	 * @param int   $design_id Design ID (0 for order-only).
	 * @return string|WP_Error
	 */
	public static function save_package_file( $package, $design_id = 0 ) {
		$upload_dir = wp_upload_dir();
		$dir        = $upload_dir['basedir'] . '/pckz-canonical-engine/lightburn';
		wp_mkdir_p( $dir );

		$filename = 'lightburn';
		if ( $design_id ) {
			$filename .= '-design-' . $design_id;
		}
		$filename .= '-' . wp_generate_uuid4() . '.json';

		$filepath = trailingslashit( $dir ) . $filename;
		$json     = wp_json_encode( $package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		if ( false === file_put_contents( $filepath, $json ) ) {
			return new WP_Error( 'write_error', __( 'Could not write LightBurn package.', 'pckz-canonical-engine' ) );
		}

		return str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $filepath );
	}

	/**
	 * Save canvas JSON snapshot for admin download.
	 *
	 * @param string $canvas_json Canvas JSON string.
	 * @param int    $design_id   Design ID.
	 * @return string|WP_Error
	 */
	public static function save_canvas_snapshot( $canvas_json, $design_id = 0 ) {
		if ( empty( $canvas_json ) ) {
			return new WP_Error( 'empty', __( 'No canvas data.', 'pckz-canonical-engine' ) );
		}

		$upload_dir = wp_upload_dir();
		$dir        = $upload_dir['basedir'] . '/pckz-canonical-engine/canvas';
		wp_mkdir_p( $dir );

		$filename = 'canvas';
		if ( $design_id ) {
			$filename .= '-design-' . $design_id;
		}
		$filename .= '-' . wp_generate_uuid4() . '.json';

		$filepath = trailingslashit( $dir ) . $filename;
		if ( false === file_put_contents( $filepath, $canvas_json ) ) {
			return new WP_Error( 'write_error', __( 'Could not write canvas snapshot.', 'pckz-canonical-engine' ) );
		}

		return str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $filepath );
	}
}
