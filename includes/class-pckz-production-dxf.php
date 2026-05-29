<?php
/**
 * DXF production export (mm units).
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PCKZ_Production_Dxf
 */
class PCKZ_Production_Dxf {

	/**
	 * Build DXF from package.
	 *
	 * @param array $package Package.
	 * @return string|WP_Error
	 */
	public static function build_from_package( $package ) {
		$scene = PCKZ_Production_Scene::from_package( $package );
		if ( is_wp_error( $scene ) ) {
			return $scene;
		}
		return self::build_from_scene( $scene );
	}

	/**
	 * DXF from unified vector scene.
	 *
	 * @param array $scene Scene.
	 * @return string
	 */
	public static function build_from_scene( $scene ) {
		$entities = array();

		foreach ( array( 'safe-zone' => $scene['safe_zone'] ?? null, 'strip-zone' => $scene['strip_zone'] ?? null ) as $layer => $zone ) {
			$box = PCKZ_Production_Geometry::zone_box_mm( $zone );
			if ( $box ) {
				$entities = array_merge( $entities, self::lwpolyline_rect( $box, 'GUIDES' ) );
			}
		}

		foreach ( PCKZ_Production_Scene::path_loops_from_scene( $scene ) as $loop ) {
			if ( count( $loop['verts'] ) < 2 ) {
				continue;
			}
			$pairs = array();
			foreach ( $loop['verts'] as $v ) {
				$pairs[] = PCKZ_Production_Geometry::fmt( $v['x'] );
				$pairs[] = PCKZ_Production_Geometry::fmt( $v['y'] );
			}
			$entities = array_merge( $entities, self::lwpolyline_points( $pairs, 'ENGRAVE', ! empty( $loop['closed'] ) ) );
		}

		foreach ( PCKZ_Production_Scene::text_layers_from_scene( $scene ) as $text ) {
			$label = trim( (string) ( $text['text'] ?? '' ) );
			if ( '' === $label ) {
				continue;
			}
			$entities = array_merge(
				$entities,
				self::dxf_pair(
					'TEXT',
					array(
						'8'  => 'ENGRAVE',
						'10' => PCKZ_Production_Geometry::fmt( $text['x'] ?? 0 ),
						'20' => PCKZ_Production_Geometry::fmt( $text['y'] ?? 0 ),
						'40' => PCKZ_Production_Geometry::fmt( max( 3, (float) ( $text['font_size'] ?? 8 ) ) ),
						'1'  => $label,
						'7'  => sanitize_text_field( $text['font_family'] ?? 'STANDARD' ),
					)
				)
			);
		}

		return self::wrap_dxf( $entities, (float) $scene['canvas_w'], (float) $scene['canvas_h'] );
	}

	/**
	 * @param array $package Package.
	 * @param int   $design_id Design ID.
	 * @return string|WP_Error
	 */
	public static function save_from_package( $package, $design_id = 0 ) {
		$dxf = self::build_from_package( $package );
		if ( is_wp_error( $dxf ) ) {
			return $dxf;
		}
		return PCKZ_Production_Geometry::save_file( 'dxf', 'production', 'dxf', $dxf, $design_id );
	}

	/**
	 * @param array $entities Entities.
	 * @param float $w        Canvas W.
	 * @param float $h        Canvas H.
	 * @return string
	 */
	private static function wrap_dxf( $entities, $w, $h ) {
		$lines   = array();
		$lines[] = '0';
		$lines[] = 'SECTION';
		$lines[] = '2';
		$lines[] = 'HEADER';
		$lines[] = '9';
		$lines[] = '$INSUNITS';
		$lines[] = '70';
		$lines[] = '4';
		$lines[] = '9';
		$lines[] = '$EXTMIN';
		$lines[] = '10';
		$lines[] = '0';
		$lines[] = '20';
		$lines[] = '0';
		$lines[] = '9';
		$lines[] = '$EXTMAX';
		$lines[] = '10';
		$lines[] = PCKZ_Production_Geometry::fmt( $w );
		$lines[] = '20';
		$lines[] = PCKZ_Production_Geometry::fmt( $h );
		$lines[] = '0';
		$lines[] = 'ENDSEC';
		$lines[] = '0';
		$lines[] = 'SECTION';
		$lines[] = '2';
		$lines[] = 'ENTITIES';
		$lines = array_merge( $lines, $entities );
		$lines[] = '0';
		$lines[] = 'ENDSEC';
		$lines[] = '0';
		$lines[] = 'EOF';
		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * @param array  $obj Object.
	 * @param array  $ctx Context.
	 * @return array
	 */
	private static function object_entities( $obj, $ctx ) {
		$role = $obj['role'] ?? '';
		$box  = PCKZ_Production_Geometry::object_box_mm( $obj, $ctx['canvas_w'], $ctx['canvas_h'], $ctx['origin'] );
		if ( ! $box ) {
			return array();
		}

		if ( in_array( $role, array( 'text', 'main-text' ), true ) ) {
			$obj  = PCKZ_Production_Geometry::normalize_layout_object( $obj );
			$text = trim( (string) ( $obj['text'] ?? '' ) );
			if ( '' === $text ) {
				return array();
			}
			$design_h = (float) ( $ctx['design_px']['height'] ?? 2132 );
			$font_px  = (float) ( $obj['font_size_px'] ?? 55 );
			$h_mm     = $design_h > 0 ? ( $font_px / $design_h ) * $ctx['canvas_h'] : 8;
			return self::dxf_pair(
				'TEXT',
				array(
					'8'  => 'ENGRAVE',
					'10' => PCKZ_Production_Geometry::fmt( $box['center_x'] ),
					'20' => PCKZ_Production_Geometry::fmt( $box['center_y'] ),
					'40' => PCKZ_Production_Geometry::fmt( max( 3, $h_mm ) ),
					'1'  => $text,
					'7'  => sanitize_text_field( $obj['font_family'] ?? $obj['fontFamily'] ?? 'STANDARD' ),
				)
			);
		}

		if ( in_array( $role, array( 'icon-left', 'icon-right', 'icon-bg-left', 'icon-bg-right', 'lines', 'strip-lines', 'strip-line', 'line-overlay' ), true ) ) {
			$obj   = PCKZ_Production_Geometry::normalize_layout_object( $obj );
			$url   = PCKZ_Production_Geometry::asset_url_for_object( $obj, $ctx['selections'] );
			$ents  = array();
			if ( $url ) {
				$loops = PCKZ_Production_Geometry::paths_for_object_mm( $obj, $box, $ctx['selections'] );
				foreach ( $loops as $loop ) {
					if ( count( $loop['verts'] ) < 2 ) {
						continue;
					}
					$pairs = array();
					foreach ( $loop['verts'] as $v ) {
						$pairs[] = PCKZ_Production_Geometry::fmt( $v['x'] );
						$pairs[] = PCKZ_Production_Geometry::fmt( $v['y'] );
					}
					$chunk = self::lwpolyline_points( $pairs, 'ENGRAVE', ! empty( $loop['closed'] ) );
					$ents  = array_merge( $ents, $chunk );
				}
			}
			if ( ! empty( $ents ) ) {
				return $ents;
			}
			return self::lwpolyline_rect( $box, 'ENGRAVE' );
		}

		return array();
	}

	/**
	 * @param array  $box   Box mm.
	 * @param string $layer Layer.
	 * @return array
	 */
	private static function lwpolyline_rect( $box, $layer ) {
		$x1 = $box['x'];
		$y1 = $box['y'];
		$x2 = $box['x'] + $box['width'];
		$y2 = $box['y'] + $box['height'];
		return self::lwpolyline_points(
			array(
				PCKZ_Production_Geometry::fmt( $x1 ),
				PCKZ_Production_Geometry::fmt( $y1 ),
				PCKZ_Production_Geometry::fmt( $x2 ),
				PCKZ_Production_Geometry::fmt( $y1 ),
				PCKZ_Production_Geometry::fmt( $x2 ),
				PCKZ_Production_Geometry::fmt( $y2 ),
				PCKZ_Production_Geometry::fmt( $x1 ),
				PCKZ_Production_Geometry::fmt( $y2 ),
			),
			$layer,
			true
		);
	}

	/**
	 * @param array  $coords  Flat x,y pairs as strings.
	 * @param string $layer   Layer.
	 * @param bool   $closed  Closed.
	 * @return array
	 */
	private static function lwpolyline_points( $coords, $layer, $closed ) {
		$n     = (int) ( count( $coords ) / 2 );
		$lines = self::dxf_pair(
			'LWPOLYLINE',
			array(
				'8'    => $layer,
				'90'   => (string) $n,
				'70'   => $closed ? '1' : '0',
			)
		);
		for ( $i = 0; $i < $n; $i++ ) {
			$lines[] = '10';
			$lines[] = $coords[ $i * 2 ];
			$lines[] = '20';
			$lines[] = $coords[ $i * 2 + 1 ];
		}
		return $lines;
	}

	/**
	 * @param string $type   Entity type.
	 * @param array  $fields Code => value.
	 * @return array
	 */
	private static function dxf_pair( $type, $fields ) {
		$out   = array( '0', $type );
		foreach ( $fields as $code => $value ) {
			$out[] = (string) $code;
			$out[] = (string) $value;
		}
		return $out;
	}
}
