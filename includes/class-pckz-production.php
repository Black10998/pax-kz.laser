<?php
/**
 * Production summary for shop owner (post-order / LightBurn prep).
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PCKZ_Production
 */
class PCKZ_Production {

	/**
	 * Build human-readable production package from customer submission.
	 *
	 * @param array $args Arguments.
	 * @return array
	 */
	public static function build_package( $args ) {
		$selections  = $args['selections'] ?? array();
		$canvas_json = $args['canvas_json'] ?? '';
		$config      = $args['config'] ?? array();
		$preview_url = $args['preview_url'] ?? '';
		$export_url  = $args['export_url'] ?? '';

		$objects          = self::parse_canvas_objects( $canvas_json );
		$layout           = ! empty( $args['layout'] ) && is_array( $args['layout'] ) ? $args['layout'] : self::parse_layout( $canvas_json );
		$production_meta  = self::parse_production_meta( $canvas_json );
		if ( ! empty( $production_meta['layout'] ) ) {
			$meta_layout = $production_meta['layout'];
			if ( ! empty( $meta_layout['objects'] ) || empty( $layout['objects'] ) ) {
				$layout = $meta_layout;
			}
		}
		if ( ! empty( $args['production_vector_svg'] ) ) {
			$layout['production_vector_svg'] = $args['production_vector_svg'];
		} elseif ( ! empty( $production_meta['production_vector_svg'] ) ) {
			$layout['production_vector_svg'] = $production_meta['production_vector_svg'];
		} elseif ( ! empty( $production_meta['layout']['production_vector_svg'] ) ) {
			$layout['production_vector_svg'] = $production_meta['layout']['production_vector_svg'];
		}

		$canvas_mm = $layout['canvas_mm'] ?? array(
			'width'  => (float) ( $config['canvas_width_mm'] ?? 529.1 ),
			'height' => (float) ( $config['canvas_height_mm'] ?? 116 ),
		);
		$strict = ! empty( $args['canonical_scene'] ) || ! empty( $layout['canonical_scene'] );
		if ( ! $strict && class_exists( 'PCKZ_Ledos_Preview' ) ) {
			$layout['objects'] = PCKZ_Ledos_Preview::ensure_production_objects(
				$layout['objects'] ?? array(),
				$selections,
				(float) ( $canvas_mm['width'] ?? 529.1 ),
				(float) ( $canvas_mm['height'] ?? 116 ),
				'bottom-left'
			);
		}
		$lines = array();

		foreach ( $selections as $id => $value ) {
			if ( is_array( $value ) ) {
				$value = wp_json_encode( $value );
			}
			$display = (string) $value;
			if ( in_array( $id, array( 'symbol_links', 'symbol_rechts' ), true ) && $display ) {
				$display = PCKZ_Icons::label_for_slug( $display ) . ' (' . $display . ')';
			}
			if ( 'linien' === $id ) {
				if ( 'none' === $display || 'no' === $display ) {
					$display = 'Keine Linien';
				} elseif ( preg_match( '/^type_(\d+)$/', $display, $m ) ) {
					$display = 'Typ ' . $m[1];
				} elseif ( 'yes' === $display ) {
					$display = 'Linien anzeigen';
				}
			}
			if ( in_array( $id, array( 'preview_mode', 'preview_led' ), true ) ) {
				$display = ( 'night' === $display ) ? 'Nacht' : 'Tag';
			}
			if ( 'led_enabled' === $id ) {
				$display = ( 'yes' === $display ) ? 'Ja' : 'Nein';
			}
			$lines[] = array(
				'label' => self::label_for_option( $id, $config ),
				'value' => $display,
			);
		}

		foreach ( $objects as $obj ) {
			if ( 'text' === ( $obj['type'] ?? '' ) ) {
				$lines[] = array(
					'label' => __( 'Text on product', 'pckz-canonical-engine' ),
					'value' => $obj['text'] ?? '',
				);
				$lines[] = array(
					'label' => __( 'Font', 'pckz-canonical-engine' ),
					'value' => $obj['fontFamily'] ?? '',
				);
				$lines[] = array(
					'label' => __( 'Text color', 'pckz-canonical-engine' ),
					'value' => $obj['fill'] ?? '',
				);
			}
			if ( 'image' === ( $obj['type'] ?? '' ) ) {
				$lines[] = array(
					'label' => __( 'Customer image', 'pckz-canonical-engine' ),
					'value' => $obj['src'] ?? __( 'Included in preview file', 'pckz-canonical-engine' ),
				);
			}
		}

		$lines[] = array(
			'label' => __( 'Canvas size', 'pckz-canonical-engine' ),
			'value' => sprintf(
				'%s × %s mm',
				$config['canvas_width_mm'] ?? '',
				$config['canvas_height_mm'] ?? ''
			),
		);
		$lines[] = array(
			'label' => __( 'Print / engraving area', 'pckz-canonical-engine' ),
			'value' => sprintf(
				'%s × %s mm (offset X %s mm, Y %s mm)',
				$config['safe_zone_w_mm'] ?? '',
				$config['safe_zone_h_mm'] ?? '',
				$config['safe_zone_x_mm'] ?? '',
				$config['safe_zone_y_mm'] ?? ''
			),
		);

		if ( ! empty( $layout['standard'] ) ) {
			$lines[] = array(
				'label' => __( 'Production standard', 'pckz-canonical-engine' ),
				'value' => (string) $layout['standard'],
			);
		}
		if ( ! empty( $layout['coordinate_origin'] ) ) {
			$lines[] = array(
				'label' => __( 'Coordinate origin', 'pckz-canonical-engine' ),
				'value' => (string) $layout['coordinate_origin'],
			);
		}

		if ( ! empty( $layout['objects'] ) ) {
			foreach ( $layout['objects'] as $obj ) {
				$role = $obj['role'] ?? 'object';
				$mm   = $obj['mm'] ?? $obj;
				$x    = $mm['x_mm'] ?? $obj['x_mm'] ?? '';
				$y    = $mm['y_mm'] ?? $obj['y_mm'] ?? '';
				$w    = $mm['width_mm'] ?? $obj['width_mm'] ?? '';
				$h    = $mm['height_mm'] ?? $obj['height_mm'] ?? '';
				$dpx  = ! empty( $obj['design_px'] ) ? wp_json_encode( $obj['design_px'] ) : '';

				if ( 'main-text' === $role || 'text' === $role ) {
					$lines[] = array(
						'label' => __( 'Text (production mm)', 'pckz-canonical-engine' ),
						'value' => sprintf(
							'"%s" · X %s · Y %s · %s×%s mm · %s',
							$obj['text'] ?? '',
							$x,
							$y,
							$w,
							$h,
							$obj['font_family'] ?? ''
						),
					);
					if ( $dpx ) {
						$lines[] = array(
							'label' => __( 'Text design px (1:1)', 'pckz-canonical-engine' ),
							'value' => $dpx,
						);
					}
				} elseif ( 'icon-left' === $role || 'icon-right' === $role ) {
					$lines[] = array(
						'label' => ( 'icon-left' === $role ) ? 'Symbol links (mm)' : 'Symbol rechts (mm)',
						'value' => sprintf(
							'%s · X %s · Y %s · %s×%s mm',
							$obj['symbol'] ?? '',
							$x,
							$y,
							$w,
							$h
						),
					);
				} elseif ( 'lines' === $role ) {
					$lines[] = array(
						'label' => __( 'Lines (mm)', 'pckz-canonical-engine' ),
						'value' => sprintf(
							'%s · X %s · Y %s · %s×%s mm',
							$obj['line_type'] ?? '',
							$x,
							$y,
							$w,
							$h
						),
					);
				} elseif ( 'logo' === $role || 'image' === $role ) {
					$lines[] = array(
						'label' => __( 'Logo position (mm)', 'pckz-canonical-engine' ),
						'value' => sprintf( 'X %s · Y %s · %s×%s mm', $x, $y, $w, $h ),
					);
				}
			}
		}

		$std_spec = $args['std_spec'] ?? array();
		if ( empty( $std_spec ) && class_exists( 'PCKZ_Std_Spec' ) ) {
			$std_spec = PCKZ_Std_Spec::for_product( $config );
		}

		$lightburn = array();
		if ( class_exists( 'PCKZ_Lightburn_Export' ) ) {
			$lightburn = PCKZ_Lightburn_Export::build_package(
				array(
					'layout'      => $layout,
					'selections'  => $selections,
					'config'      => $config,
					'canvas_json' => $canvas_json,
					'std_spec'    => $std_spec,
					'preview_url' => $preview_url,
					'export_url'  => $export_url,
					'design_id'   => (int) ( $args['design_id'] ?? 0 ),
				)
			);
		}

		return array(
			'design_id'              => (int) ( $args['design_id'] ?? 0 ),
			'generated_at'           => current_time( 'mysql' ),
			'lines'                  => $lines,
			'preview_url'            => $preview_url,
			'export_url'             => $export_url,
			'selections'             => $selections,
			'layout'                 => $layout,
			'production_vector_svg'  => $layout['production_vector_svg'] ?? '',
			'lightburn_ready'   => $layout,
			'lightburn_package' => $lightburn,
			'std_spec'          => $std_spec,
			'canvas_json'       => $canvas_json,
			'lightburn_json_url'  => $args['lightburn_json_url'] ?? '',
			'canvas_json_url'    => $args['canvas_json_url'] ?? '',
			'production_svg_url'   => $args['production_svg_url'] ?? '',
			'production_lbrn2_url' => $args['production_lbrn2_url'] ?? '',
			'production_lbrn_url'  => $args['production_lbrn_url'] ?? '',
			'production_dxf_url'   => $args['production_dxf_url'] ?? '',
		);
	}


	/**
	 * Build production package from validated canonical scene export.
	 *
	 * @param array $args Arguments.
	 * @return array
	 */
	public static function build_package_from_canonical( $args ) {
		$scene      = $args['canonical_scene'] ?? array();
		$layout     = $args['layout'] ?? PCKZ_Canonical_Scene::to_layout( $scene );
		$selections = $args['selections'] ?? ( $scene['selections'] ?? array() );
		$config     = $args['config'] ?? array();
		$canvas_json = $args['canvas_json'] ?? '';
		$preview_url = $args['preview_url'] ?? '';
		$export_url  = $args['export_url'] ?? '';
		$std_spec    = $args['std_spec'] ?? array();
		$lightburn   = $args['lightburn_package'] ?? array();

		if ( ! empty( $args['production_vector_svg'] ) ) {
			$layout['production_vector_svg'] = (string) $args['production_vector_svg'];
		}
		if ( ! empty( $args['text_plate_paths'] ) ) {
			$layout['text_plate_paths'] = (string) $args['text_plate_paths'];
		}

		$package = self::build_package(
			array(
				'selections'            => $selections,
				'canvas_json'           => $canvas_json,
				'config'                => $config,
				'preview_url'           => $preview_url,
				'export_url'            => $export_url,
				'layout'                => $layout,
				'canonical_scene'       => $scene,
				'std_spec'              => $std_spec,
				'design_id'             => (int) ( $args['design_id'] ?? 0 ),
				'production_vector_svg' => $layout['production_vector_svg'] ?? '',
				'text_plate_paths'      => $layout['text_plate_paths'] ?? '',
			)
		);

		if ( ! empty( $lightburn ) ) {
			$package['lightburn_package'] = $lightburn;
		}

		$package['canonical_scene'] = $scene;
		$package['validation']      = $args['validation'] ?? array( 'status' => 'PASS', 'errors' => array() );
		$package['parity']          = $args['parity'] ?? array( 'status' => 'PASS', 'errors' => array() );
		$package['production_scene'] = $args['production_scene'] ?? array();
		if ( ! empty( $layout['production_vector_svg'] ) ) {
			$package['production_vector_svg'] = $layout['production_vector_svg'];
		}
		if ( ! empty( $layout['text_plate_paths'] ) ) {
			$package['text_plate_paths'] = $layout['text_plate_paths'];
		}

		return $package;
	}

	/**
	 * Attach downloadable file URLs to a production package.
	 *
	 * @param array $package   Package from build_package.
	 * @param int   $design_id Design ID.
	 * @return array
	 */
	public static function persist_export_files( $package, $design_id = 0 ) {
		if ( ! class_exists( 'PCKZ_Lightburn_Export' ) ) {
			return $package;
		}

		$lb = $package['lightburn_package'] ?? array();
		if ( ! empty( $lb ) ) {
			$url = PCKZ_Lightburn_Export::save_package_file( $lb, $design_id );
			if ( ! is_wp_error( $url ) ) {
				$package['lightburn_json_url'] = $url;
			}
		}

		if ( ! empty( $package['canvas_json'] ) ) {
			$url = PCKZ_Lightburn_Export::save_canvas_snapshot( $package['canvas_json'], $design_id );
			if ( ! is_wp_error( $url ) ) {
				$package['canvas_json_url'] = $url;
			}
		}

		if ( class_exists( 'PCKZ_Production_Scene' ) ) {
			$fragment = PCKZ_Production_Scene::resolve_text_plate_paths_from_package( $package );
			if ( '' !== $fragment ) {
				$package['text_plate_paths'] = $fragment;
				if ( empty( $package['layout'] ) || ! is_array( $package['layout'] ) ) {
					$package['layout'] = array();
				}
				$package['layout']['text_plate_paths'] = $fragment;
			}
		}

		if ( class_exists( 'PCKZ_Production_Svg' ) ) {
			$svg_url = PCKZ_Production_Svg::save_from_package( $package, $design_id );
			if ( ! is_wp_error( $svg_url ) ) {
				$package['production_svg_url'] = $svg_url;
			} elseif ( empty( $package['production_svg_error'] ) ) {
				$package['production_svg_error'] = $svg_url->get_error_message();
			}
		}

		if ( class_exists( 'PCKZ_Production_Lbrn2' ) ) {
			$lbrn2 = PCKZ_Production_Lbrn2::save_from_package( $package, $design_id );
			if ( is_wp_error( $lbrn2 ) ) {
				$package['production_lbrn2_error'] = $lbrn2->get_error_message();
			} else {
				$package['production_lbrn2_url'] = $lbrn2;
			}
		}

		// DXF and legacy .lbrn disabled until SVG/LBRN2 pipeline is stable.

		return $package;
	}

	/**
	 * Parse layout block from canvas JSON meta.
	 *
	 * @param string $canvas_json Canvas JSON.
	 * @return array
	 */
	/**
	 * Parse production block from canvas meta.
	 *
	 * @param string $canvas_json Canvas JSON.
	 * @return array
	 */
	private static function parse_production_meta( $canvas_json ) {
		if ( empty( $canvas_json ) ) {
			return array();
		}
		$data = json_decode( $canvas_json, true );
		if ( ! is_array( $data ) || empty( $data['pckzMeta'] ) ) {
			return array();
		}
		return $data['pckzMeta'];
	}

	/**
	 * Parse layout block from canvas JSON meta.
	 *
	 * @param string $canvas_json Canvas JSON.
	 * @return array
	 */
	private static function parse_layout( $canvas_json ) {
		if ( empty( $canvas_json ) ) {
			return array();
		}
		$data = json_decode( $canvas_json, true );
		if ( ! is_array( $data ) || empty( $data['pckzMeta']['layout'] ) ) {
			return array();
		}
		return $data['pckzMeta']['layout'];
	}

	/**
	 * Parse design objects from canvas JSON string.
	 *
	 * @param string $canvas_json Canvas JSON.
	 * @return array
	 */
	private static function parse_canvas_objects( $canvas_json ) {
		if ( empty( $canvas_json ) ) {
			return array();
		}
		$data = json_decode( $canvas_json, true );
		if ( ! is_array( $data ) || empty( $data['objects'] ) ) {
			return array();
		}

		$parsed = array();
		foreach ( $data['objects'] as $obj ) {
			if ( ! empty( $obj['text'] ) ) {
				$parsed[] = array(
					'type'       => 'text',
					'text'       => $obj['text'],
					'fontFamily' => $obj['fontFamily'] ?? '',
					'fill'       => $obj['fill'] ?? '',
				);
			} elseif ( ! empty( $obj['src'] ) ) {
				$parsed[] = array(
					'type' => 'image',
					'src'  => $obj['src'],
				);
			}
		}
		return $parsed;
	}

	/**
	 * Public wrapper for option labels (cart display).
	 *
	 * @param string $id     Option ID.
	 * @param array  $config Product config.
	 * @return string
	 */
	public static function label_for_option_public( $id, $config ) {
		return self::label_for_option( $id, $config );
	}

	/**
	 * Resolve option label from product config.
	 *
	 * @param string $id     Option ID.
	 * @param array  $config Product config.
	 * @return string
	 */
	private static function label_for_option( $id, $config ) {
		if ( ! empty( $config['customer_options'] ) ) {
			foreach ( $config['customer_options'] as $option ) {
				if ( ( $option['id'] ?? '' ) === $id ) {
					return $option['label'] ?? $id;
				}
			}
		}
		return ucwords( str_replace( '_', ' ', $id ) );
	}

	/**
	 * Render HTML table for admin / email.
	 *
	 * @param array $package Production package.
	 * @return string
	 */
	public static function render_html_table( $package ) {
		if ( empty( $package['lines'] ) ) {
			return '';
		}

		$html = '<table class="pckz-production-table" style="width:100%;border-collapse:collapse">';
		$html .= '<tbody>';
		foreach ( $package['lines'] as $row ) {
			$html .= '<tr>';
			$html .= '<th style="text-align:left;padding:8px;border-bottom:1px solid #ddd;width:40%">' . esc_html( $row['label'] ) . '</th>';
			$html .= '<td style="padding:8px;border-bottom:1px solid #ddd">' . esc_html( $row['value'] ) . '</td>';
			$html .= '</tr>';
		}
		$html .= '</tbody></table>';

		if ( ! empty( $package['preview_url'] ) ) {
			$html .= '<p><a href="' . esc_url( $package['preview_url'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'Download customer preview (PNG)', 'pckz-canonical-engine' ) . '</a></p>';
			$html .= '<p><img src="' . esc_url( $package['preview_url'] ) . '" alt="" style="max-width:100%;border-radius:8px;margin-top:8px"></p>';
		}
		if ( ! empty( $package['export_url'] ) ) {
			$html .= '<p><a href="' . esc_url( $package['export_url'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'Download production file (PNG)', 'pckz-canonical-engine' ) . '</a></p>';
		}

		return $html;
	}

	/**
	 * Full admin production panel (LightBurn JSON, coordinates, downloads).
	 *
	 * @param array $package Package from build_package or order meta.
	 * @param array $context Optional context (design_id).
	 * @return string
	 */
	public static function render_admin_production_panel( $package, $context = array() ) {
		if ( empty( $package ) || ! is_array( $package ) ) {
			return '<p>' . esc_html__( 'No production data available.', 'pckz-canonical-engine' ) . '</p>';
		}

		$layout   = $package['layout'] ?? $package['lightburn_ready'] ?? array();
		$lb       = $package['lightburn_package'] ?? array();
		$lbrn2_url  = $package['production_lbrn2_url'] ?? '';
		$lbrn_url   = $package['production_lbrn_url'] ?? '';
		$svg_url    = $package['production_svg_url'] ?? '';
		$dxf_url    = $package['production_dxf_url'] ?? '';
		$lb_url     = $package['lightburn_json_url'] ?? '';
		$canvas_url = $package['canvas_json_url'] ?? '';
		$preview    = $package['preview_url'] ?? '';
		$export     = $package['export_url'] ?? '';

		$html  = '<div class="pckz-production-panel">';

		$html .= '<div class="pckz-production-panel__downloads">';
		$html .= '<h4>' . esc_html__( 'Manufacturing files (import SVG in LightBurn for 1:1 preview match)', 'pckz-canonical-engine' ) . '</h4>';
		$html .= '<ul class="pckz-download-list">';
		if ( $svg_url ) {
			$html .= '<li><a class="button button-primary" href="' . esc_url( $svg_url ) . '" download target="_blank" rel="noopener">' . esc_html__( 'Download Production SVG (recommended — 1:1 with preview)', 'pckz-canonical-engine' ) . '</a></li>';
		}
		if ( $lbrn2_url ) {
			$html .= '<li><a class="button button-primary" href="' . esc_url( $lbrn2_url ) . '" download target="_blank" rel="noopener">' . esc_html__( 'Download LightBurn project (.lbrn2, derived from SVG)', 'pckz-canonical-engine' ) . '</a></li>';
		}
		if ( $lb_url ) {
			$html .= '<li><a class="button" href="' . esc_url( $lb_url ) . '" download target="_blank" rel="noopener">' . esc_html__( 'Download technical JSON', 'pckz-canonical-engine' ) . '</a></li>';
		}
		if ( $canvas_url ) {
			$html .= '<li><a class="button" href="' . esc_url( $canvas_url ) . '" download target="_blank" rel="noopener">' . esc_html__( 'Download canvas / production JSON', 'pckz-canonical-engine' ) . '</a></li>';
		}
		if ( $preview ) {
			$html .= '<li><a class="button" href="' . esc_url( $preview ) . '" target="_blank" rel="noopener">' . esc_html__( 'Customer preview (PNG)', 'pckz-canonical-engine' ) . '</a></li>';
		}
		if ( $export ) {
			$html .= '<li><a class="button" href="' . esc_url( $export ) . '" target="_blank" rel="noopener">' . esc_html__( 'Production export (PNG)', 'pckz-canonical-engine' ) . '</a></li>';
		}
		$html .= '</ul>';
		if ( ! $lbrn2_url && ! $svg_url ) {
			$html .= '<p class="description">' . esc_html__( 'Re-save the design after the preview loads. Import the Production SVG in LightBurn for an exact match; .lbrn2 is generated from the same snapshot.', 'pckz-canonical-engine' ) . '</p>';
		}
		$html .= '</div>';

		if ( ! empty( $layout['design_px'] ) || ! empty( $layout['canvas_mm'] ) ) {
			$dpx = $layout['design_px'] ?? array();
			$cmm = $layout['canvas_mm'] ?? array();
			$html .= '<div class="pckz-production-panel__spec">';
			$html .= '<h4>' . esc_html__( 'Canvas & scaling', 'pckz-canonical-engine' ) . '</h4>';
			$html .= '<table class="widefat striped"><tbody>';
			$html .= '<tr><th>' . esc_html__( 'Design space (px, 1:1)', 'pckz-canonical-engine' ) . '</th><td>' . esc_html( ( $dpx['width'] ?? '' ) . ' × ' . ( $dpx['height'] ?? '' ) ) . '</td></tr>';
			$html .= '<tr><th>' . esc_html__( 'Physical canvas (mm)', 'pckz-canonical-engine' ) . '</th><td>' . esc_html( ( $cmm['width'] ?? '' ) . ' × ' . ( $cmm['height'] ?? '' ) . ' mm' ) . '</td></tr>';
			$html .= '<tr><th>' . esc_html__( 'Coordinate origin', 'pckz-canonical-engine' ) . '</th><td>' . esc_html( $layout['coordinate_origin'] ?? '' ) . '</td></tr>';
			$html .= '<tr><th>' . esc_html__( 'DPI', 'pckz-canonical-engine' ) . '</th><td>' . esc_html( (string) ( $layout['dpi'] ?? '' ) ) . '</td></tr>';
			if ( ! empty( $layout['safe_zone_mm'] ) ) {
				$sz = $layout['safe_zone_mm'];
				$html .= '<tr><th>' . esc_html__( 'Safe zone (mm)', 'pckz-canonical-engine' ) . '</th><td>' . esc_html( self::format_zone_mm( $sz ) ) . '</td></tr>';
			}
			if ( ! empty( $layout['strip_zone_mm'] ) ) {
				$sz = $layout['strip_zone_mm'];
				$html .= '<tr><th>' . esc_html__( 'Strip zone (mm)', 'pckz-canonical-engine' ) . '</th><td>' . esc_html( self::format_zone_mm( $sz ) ) . '</td></tr>';
			}
			$html .= '</tbody></table></div>';
		}

		$objects = $layout['objects'] ?? ( $lb['objects'] ?? array() );
		if ( ! empty( $objects ) ) {
			$html .= '<div class="pckz-production-panel__objects">';
			$html .= '<h4>' . esc_html__( 'Manufacturing objects (exact positions)', 'pckz-canonical-engine' ) . '</h4>';
			$html .= '<table class="widefat striped pckz-objects-table"><thead><tr>';
			$html .= '<th>' . esc_html__( 'Role', 'pckz-canonical-engine' ) . '</th>';
			$html .= '<th>' . esc_html__( 'X mm', 'pckz-canonical-engine' ) . '</th>';
			$html .= '<th>' . esc_html__( 'Y mm', 'pckz-canonical-engine' ) . '</th>';
			$html .= '<th>' . esc_html__( 'W×H mm', 'pckz-canonical-engine' ) . '</th>';
			$html .= '<th>' . esc_html__( 'Design px', 'pckz-canonical-engine' ) . '</th>';
			$html .= '<th>' . esc_html__( 'Details', 'pckz-canonical-engine' ) . '</th>';
			$html .= '</tr></thead><tbody>';
			foreach ( $objects as $obj ) {
				$mm  = $obj['mm'] ?? $obj;
				$dpx = $obj['design_px'] ?? array();
				$detail = self::object_detail_string( $obj );
				$html .= '<tr>';
				$html .= '<td>' . esc_html( $obj['role'] ?? '' ) . '</td>';
				$html .= '<td>' . esc_html( (string) ( $mm['x_mm'] ?? '' ) ) . '</td>';
				$html .= '<td>' . esc_html( (string) ( $mm['y_mm'] ?? '' ) ) . '</td>';
				$html .= '<td>' . esc_html( ( $mm['width_mm'] ?? '' ) . ' × ' . ( $mm['height_mm'] ?? '' ) ) . '</td>';
				$html .= '<td><code style="font-size:11px">' . esc_html( wp_json_encode( $dpx ) ) . '</code></td>';
				$html .= '<td>' . esc_html( $detail ) . '</td>';
				$html .= '</tr>';
			}
			$html .= '</tbody></table></div>';
		}

		$html .= '<details class="pckz-production-panel__json"><summary>' . esc_html__( 'Full production JSON (expand)', 'pckz-canonical-engine' ) . '</summary>';
		$html .= '<textarea readonly rows="16" class="large-text code" style="font-family:monospace;font-size:11px">';
		$export_json = ! empty( $lb ) ? $lb : $layout;
		$html .= esc_textarea( wp_json_encode( $export_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		$html .= '</textarea></details>';

		if ( $preview ) {
			$html .= '<p><img src="' . esc_url( $preview ) . '" alt="" class="pckz-production-preview-img"></p>';
		}

		$html .= '</div>';
		return $html;
	}

	/**
	 * Admin font block: name, category, live preview sample.
	 *
	 * @param string $font_family CSS font family.
	 * @param string $sample_text Text to render.
	 * @return string
	 */
	public static function render_admin_font_block( $font_family, $sample_text = '' ) {
		$font_family = trim( (string) $font_family );
		$sample_text = trim( (string) $sample_text );
		if ( '' === $font_family ) {
			return '<p class="description">' . esc_html__( 'No font selected.', 'pckz-canonical-engine' ) . '</p>';
		}
		if ( '' === $sample_text ) {
			$sample_text = $font_family;
		}
		$entry    = class_exists( 'PCKZ_Font_Library' ) ? PCKZ_Font_Library::find_by_family( $font_family ) : null;
		$category = $entry ? PCKZ_Font_Library::category_label( $entry ) : '—';
		$style    = sprintf( 'font-family:%s,sans-serif;font-size:28px;line-height:1.3;margin:8px 0 0', esc_attr( $font_family ) );

		$html  = '<dl class="pckz-detail-dl">';
		$html .= '<div class="pckz-detail-dl__row"><dt>' . esc_html__( 'Font', 'pckz-canonical-engine' ) . '</dt><dd>' . esc_html( $font_family ) . '</dd></div>';
		$html .= '<div class="pckz-detail-dl__row"><dt>' . esc_html__( 'Category', 'pckz-canonical-engine' ) . '</dt><dd>' . esc_html( $category ) . '</dd></div>';
		$html .= '<div class="pckz-detail-dl__row"><dt>' . esc_html__( 'Preview', 'pckz-canonical-engine' ) . '</dt><dd><span class="pckz-admin-font-preview" style="' . esc_attr( $style ) . '">' . esc_html( $sample_text ) . '</span></dd></div>';
		$html .= '</dl>';
		return $html;
	}

	/**
	 * Resolve display value for a design selection key.
	 *
	 * @param string $key         Selection key.
	 * @param mixed  $value       Raw value.
	 * @param array  $config      Product config.
	 * @return string
	 */
	public static function format_selection_value( $key, $value, $config = array() ) {
		if ( is_array( $value ) ) {
			$value = wp_json_encode( $value );
		}
		$display = (string) $value;
		if ( in_array( $key, array( 'symbol_links', 'symbol_rechts' ), true ) && $display ) {
			$display = PCKZ_Icons::label_for_slug( $display ) . ' (' . $display . ')';
		}
		if ( 'linien' === $key ) {
			if ( 'none' === $display || 'no' === $display ) {
				$display = 'Keine Linien';
			} elseif ( preg_match( '/^type_(\d+)$/', $display, $m ) ) {
				$display = 'Typ ' . $m[1];
			} elseif ( 'yes' === $display ) {
				$display = 'Linien anzeigen';
			}
		}
		if ( in_array( $key, array( 'preview_mode', 'preview_led' ), true ) ) {
			$display = ( 'night' === $display ) ? 'Nacht' : 'Tag';
		}
		if ( 'led_enabled' === $key ) {
			$display = ( 'yes' === $display ) ? 'Ja' : 'Nein';
		}
		if ( 'font_family' === $key && $display ) {
			return $display;
		}
		unset( $config );
		return $display;
	}

	/**
	 * Format zone mm array for display.
	 *
	 * @param array $zone Zone.
	 * @return string
	 */
	private static function format_zone_mm( $zone ) {
		if ( isset( $zone['x'], $zone['y'], $zone['w'], $zone['h'] ) ) {
			return sprintf( 'X %s · Y %s · %s×%s mm', $zone['x'], $zone['y'], $zone['w'], $zone['h'] );
		}
		return sprintf(
			'X %s · Y %s · %s×%s mm',
			$zone['x_mm'] ?? $zone['x'] ?? '',
			$zone['y_mm'] ?? $zone['y'] ?? '',
			$zone['width_mm'] ?? $zone['w'] ?? '',
			$zone['height_mm'] ?? $zone['h'] ?? ''
		);
	}

	/**
	 * Human-readable object detail line.
	 *
	 * @param array $obj Object.
	 * @return string
	 */
	private static function object_detail_string( $obj ) {
		$role = $obj['role'] ?? '';
		if ( in_array( $role, array( 'text', 'main-text' ), true ) ) {
			return sprintf(
				'"%s" · %s · %s',
				$obj['text'] ?? '',
				$obj['font_family'] ?? '',
				$obj['fill'] ?? ''
			);
		}
		if ( in_array( $role, array( 'icon-left', 'icon-right' ), true ) ) {
			return ( $obj['symbol'] ?? '' ) . ' · ' . ( $obj['fill'] ?? '' );
		}
		if ( 'lines' === $role ) {
			return ( $obj['line_type'] ?? '' ) . ' · ' . ( $obj['fill'] ?? '' );
		}
		return '';
	}
}
