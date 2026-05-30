<?php
/**
 * LightBurn project export (.lbrn legacy XML + .lbrn2, mm, Y-up).
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PCKZ_Production_Lbrn2
 */
class PCKZ_Production_Lbrn2 {

	/**
	 * Build LightBurn project XML (legacy verbose format — opens in LightBurn).
	 *
	 * @param array $package Production package.
	 * @return string|WP_Error
	 */
	public static function build_from_package( $package ) {
		$scene = self::prepare_scene_for_lbrn2_export( $package );
		if ( is_wp_error( $scene ) ) {
			return $scene;
		}
		return self::build_lbrn2_from_scene( $scene );
	}

	/**
	 * Resolve production scene for LBRN2 and always merge browser text_plate_paths.
	 *
	 * Cached production_scene snapshots may omit text-engrave paths (icons/lines only).
	 * LBRN2 must re-apply text_plate_paths from the package/layout before writing shapes.
	 *
	 * @param array $package Production package.
	 * @return array|WP_Error
	 */
	private static function prepare_scene_for_lbrn2_export( $package ) {
		return PCKZ_Production_Scene::prepare_export_scene( $package );
	}

	/**
	 * LightBurn .lbrn2 from unified vector scene.
	 *
	 * @param array $scene Scene.
	 * @return string|WP_Error
	 */
	public static function build_lbrn2_from_scene( $scene ) {
		$parts   = array();
		$parts[] = '<?xml version="1.0" encoding="UTF-8"?>';
		$parts[] = '<LightBurnProject AppVersion="1.7.08" FormatVersion="1" MaterialHeight="0" MirrorX="False" MirrorY="False">';

		$parts[] = self::cut_setting( 0, 'Guides', true );
		$parts[] = self::cut_setting( 1, 'Engrave', false );

		$zone = PCKZ_Production_Geometry::zone_box_mm( $scene['safe_zone'] ?? null );
		if ( $zone ) {
			$parts[] = self::shape_rect( $zone, 0 );
		}
		$zone = PCKZ_Production_Geometry::zone_box_mm( $scene['strip_zone'] ?? null );
		if ( $zone ) {
			$parts[] = self::shape_rect( $zone, 0 );
		}

		$engrave_count = 0;
		$idx           = 0;
		foreach ( $scene['layers'] ?? array() as $layer ) {
			if ( 'ellipse' === ( $layer['type'] ?? '' ) ) {
				$label = $layer['layer_id'] ?? ( $layer['role'] ?? 'ellipse' );
				$shape = self::shape_ellipse( $layer, 1, $label . '-' . ( ++$idx ) );
				if ( $shape ) {
					$parts[] = $shape;
					++$engrave_count;
				}
				continue;
			}
		}

		foreach ( PCKZ_Production_Scene::path_loops_from_scene( $scene ) as $loop ) {
			$label = $loop['layer_id'] ?? ( $loop['role'] ?? 'path' );
			$shape = self::shape_path(
				array(
					'verts'          => $loop['verts'],
					'prims'          => $loop['prims'],
					'closed'         => $loop['closed'],
					'subpath_starts' => $loop['subpath_starts'] ?? array(),
				),
				1,
				$label . '-' . ( ++$idx )
			);
			if ( $shape ) {
				$parts[] = $shape;
				++$engrave_count;
			}
		}


		if ( $engrave_count < 1 ) {
			return new WP_Error(
				'empty_export',
				__( 'No engraving artwork exported. Re-save the design after the preview has finished loading.', 'pckz-canonical-engine' )
			);
		}

		$parts[] = '</LightBurnProject>';
		$xml     = implode( "\n", array_filter( $parts ) );
		if ( false !== strpos( $xml, 'Type="Text"' ) ) {
			return new WP_Error( 'font_text_forbidden', __( 'LBRN2 export must not contain font text objects.', 'pckz-canonical-engine' ) );
		}
		return $xml;
	}

	/**
	 * Legacy verbose LightBurn XML (.lbrn) with direct V/P path children.
	 *
	 * @param array $package Production package.
	 * @return string|WP_Error
	 */
	public static function build_legacy_from_package( $package ) {
		if ( ! empty( $package['production_scene'] ) && is_array( $package['production_scene'] ) ) {
			return self::build_lbrn_legacy_from_scene( $package['production_scene'] );
		}
		$scene = PCKZ_Production_Scene::from_package( $package );
		if ( is_wp_error( $scene ) ) {
			return $scene;
		}
		return self::build_lbrn_legacy_from_scene( $scene );
	}

	/**
	 * Legacy .lbrn from unified scene (V/P path format).
	 *
	 * @param array $scene Scene.
	 * @return string|WP_Error
	 */
	public static function build_lbrn_legacy_from_scene( $scene ) {
		$parts   = array();
		$parts[] = '<?xml version="1.0" encoding="UTF-8"?>';
		$parts[] = '<LightBurnProject AppVersion="1.7.08" FormatVersion="0" MaterialHeight="0" MirrorX="False" MirrorY="False">';

		$parts[] = self::cut_setting( 0, 'Guides', true );
		$parts[] = self::cut_setting( 1, 'Engrave', false );

		$zone = PCKZ_Production_Geometry::zone_box_mm( $scene['safe_zone'] ?? null );
		if ( $zone ) {
			$parts[] = self::shape_rect( $zone, 0 );
		}
		$zone = PCKZ_Production_Geometry::zone_box_mm( $scene['strip_zone'] ?? null );
		if ( $zone ) {
			$parts[] = self::shape_rect( $zone, 0 );
		}

		$engrave_count = 0;
		$idx           = 0;
		foreach ( PCKZ_Production_Scene::path_loops_from_scene( $scene ) as $loop ) {
			$shape = self::shape_path_legacy(
				array(
					'verts'  => $loop['verts'],
					'prims'  => $loop['prims'],
					'closed' => $loop['closed'],
				),
				1,
				( $loop['role'] ?? 'path' ) . '-' . ( ++$idx )
			);
			if ( $shape ) {
				$parts[] = $shape;
				++$engrave_count;
			}
		}


		if ( $engrave_count < 1 ) {
			return new WP_Error( 'empty_export', __( 'No engraving artwork exported.', 'pckz-canonical-engine' ) );
		}

		$parts[] = '</LightBurnProject>';
		return implode( "\n", array_filter( $parts ) );
	}

	/**
	 * @param array $text Text layer from scene.
	 * @return string
	 */
	private static function shape_text_from_scene( $text ) {
		$label = trim( (string) ( $text['text'] ?? '' ) );
		if ( '' === $label ) {
			return '';
		}
		$font = self::lightburn_font_name( $text['font_family'] ?? 'Russo One' );
		return sprintf(
			'<Shape Type="Text" CutIndex="1" Text="%s" Font="%s" Height="%s" Align="Center" VAlign="Middle" HasBackupPath="0">' . "\n" .
			'<XForm>%s</XForm>' . "\n" .
			'</Shape>',
			esc_attr( $label ),
			esc_attr( $font ),
			PCKZ_Production_Geometry::fmt( max( 3, (float) ( $text['font_size'] ?? 8 ) ) ),
			self::xform( (float) ( $text['x'] ?? 0 ), (float) ( $text['y'] ?? 0 ) )
		);
	}

	/**
	 * @param array $package Package.
	 * @param int   $design_id Design ID.
	 * @return string|WP_Error
	 */
	public static function save_from_package( $package, $design_id = 0 ) {
		$xml = self::build_from_package( $package );
		if ( is_wp_error( $xml ) ) {
			return $xml;
		}

		return PCKZ_Production_Geometry::save_file( 'lightburn', 'production', 'lbrn2', $xml, $design_id );
	}

	/**
	 * @param int    $index Index.
	 * @param string $name  Name.
	 * @param bool   $guide Guide layer.
	 * @return string
	 */
	private static function cut_setting( $index, $name, $guide ) {
		$power = $guide ? 5 : 80;
		$speed = $guide ? 200 : 60;
		return sprintf(
			'<CutSetting Index="%d" Name="%s" Type="Cut" Speed="%d" MaxPower="%d" MinPower="%d" Priority="%d" Color="0"/>',
			(int) $index,
			esc_attr( $name ),
			(int) $speed,
			(int) $power,
			(int) $power,
			(int) $index
		);
	}

	/**
	 * @param array $box   Zone box mm.
	 * @param int   $index Cut index.
	 * @return string
	 */
	/**
	 * @param array  $layer Ellipse layer from scene.
	 * @param int    $index Cut index.
	 * @param string $comment Comment.
	 * @return string
	 */
	private static function shape_ellipse( $layer, $index, $comment ) {
		return sprintf(
			'<!-- %s -->' . "\n" .
			'<Shape Type="Ellipse" CutIndex="%d" Rx="%s" Ry="%s">' . "\n" .
			'<XForm>%s</XForm>' . "\n" .
			'</Shape>',
			esc_attr( $comment ),
			(int) $index,
			PCKZ_Production_Geometry::fmt( $layer['rx'] ?? 1 ),
			PCKZ_Production_Geometry::fmt( $layer['ry'] ?? 1 ),
			self::xform( (float) ( $layer['cx'] ?? 0 ), (float) ( $layer['cy'] ?? 0 ) )
		);
	}

	/**
	 * @param array $box   Zone box mm.
	 * @param int   $index Cut index.
	 * @return string
	 */
	private static function shape_rect( $box, $index ) {
		$tx = $box['x'] + $box['width'] / 2;
		$ty = $box['y'] + $box['height'] / 2;
		return sprintf(
			'<Shape Type="Rect" CutIndex="%d" W="%s" H="%s" Locked="1">' . "\n" .
			'<XForm>%s</XForm>' . "\n" .
			'</Shape>',
			(int) $index,
			PCKZ_Production_Geometry::fmt( $box['width'] ),
			PCKZ_Production_Geometry::fmt( $box['height'] ),
			self::xform( $tx, $ty )
		);
	}

	/**
	 * @param array $obj Object.
	 * @param array $ctx Context.
	 * @return string[]
	 */
	private static function render_object_shapes( $obj, $ctx ) {
		$obj  = PCKZ_Production_Geometry::normalize_layout_object( $obj );
		$role = $obj['role'] ?? '';

		if ( in_array( $role, array( 'text', 'main-text' ), true ) ) {
			return self::shape_text_shapes( $obj, $ctx );
		}

		if ( in_array( $role, array( 'icon-left', 'icon-right', 'icon-bg-left', 'icon-bg-right', 'lines' ), true ) ) {
			return self::shape_paths_from_asset( $obj, $ctx );
		}

		return array();
	}

	/**
	 * @param array $obj Object.
	 * @param array $ctx Context.
	 * @return string
	 */
	/**
	 * @param array $obj Object.
	 * @param array $ctx Context.
	 * @return string[]
	 */
	private static function shape_text_shapes( $obj, $ctx ) {
		$box = PCKZ_Production_Geometry::object_box_mm( $obj, $ctx['canvas_w'], $ctx['canvas_h'], $ctx['origin'] );
		if ( ! $box ) {
			return array();
		}
		$text = trim( (string) ( $obj['text'] ?? '' ) );
		if ( '' === $text ) {
			return array();
		}

		$design_h  = (float) ( $ctx['design_px']['height'] ?? 2132 );
		$font_px   = (float) ( $obj['font_size_px'] ?? 55 );
		$height_mm = $design_h > 0 ? ( $font_px / $design_h ) * $ctx['canvas_h'] : 8;
		$height_mm = max( 3, min( $box['height'] * 0.9, $height_mm ) );

		$font = self::lightburn_font_name( $obj['font_family'] ?? $obj['fontFamily'] ?? 'Arial' );

		$shapes   = array();

		$text_path = self::text_bounds_path( $box, 1 );
		if ( $text_path ) {
			$shapes[] = $text_path;
		}

		$shapes[] = sprintf(
			'<Shape Type="Text" CutIndex="1" Text="%s" Font="%s" Height="%s" Align="Center" VAlign="Middle" HasBackupPath="0">' . "\n" .
			'<XForm>%s</XForm>' . "\n" .
			'</Shape>',
			esc_attr( $text ),
			esc_attr( self::lightburn_font_name( $font ) ),
			PCKZ_Production_Geometry::fmt( $height_mm ),
			self::xform( $box['center_x'], $box['center_y'] )
		);

		if ( ! empty( $obj['stroke'] ) && ! empty( $obj['stroke_width'] ) ) {
			$stroke_mm = (float) $obj['stroke_width'] * ( $ctx['canvas_h'] / max( 1, $design_h ) );
			$shapes[] = self::shape_rect_stroke( $box, 1, max( 0.15, $stroke_mm ) );
		}

		return $shapes;
	}

	/**
	 * Vector path tracing text bounding box (visible even without system font).
	 *
	 * @param array $box   Text box mm.
	 * @param int   $index Cut index.
	 * @return string
	 */
	private static function text_bounds_path( $box, $index ) {
		$parsed = array(
			'verts'  => array(
				array( 'x' => $box['x'], 'y' => $box['y'] ),
				array( 'x' => $box['x'] + $box['width'], 'y' => $box['y'] ),
				array( 'x' => $box['x'] + $box['width'], 'y' => $box['y'] + $box['height'] ),
				array( 'x' => $box['x'], 'y' => $box['y'] + $box['height'] ),
			),
			'prims'  => array(
				array( 'p0' => 0, 'p1' => 1, 't' => 'L' ),
				array( 'p0' => 1, 'p1' => 2, 't' => 'L' ),
				array( 'p0' => 2, 'p1' => 3, 't' => 'L' ),
				array( 'p0' => 3, 'p1' => 0, 't' => 'L' ),
			),
			'closed' => true,
		);
		return self::shape_path( $parsed, $index, 'text-bounds' );
	}

	/**
	 * Outline rect for text stroke simulation.
	 *
	 * @param array $box       Box.
	 * @param int   $index     Cut index.
	 * @param float $stroke_mm Stroke width mm.
	 * @return string
	 */
	private static function shape_rect_stroke( $box, $index, $stroke_mm ) {
		$tx = $box['center_x'];
		$ty = $box['center_y'];
		return sprintf(
			'<Shape Type="Rect" CutIndex="%d" W="%s" H="%s">' . "\n" .
			'<XForm>%s</XForm>' . "\n" .
			'</Shape>',
			(int) $index,
			PCKZ_Production_Geometry::fmt( $box['width'] + $stroke_mm ),
			PCKZ_Production_Geometry::fmt( $box['height'] + $stroke_mm ),
			self::xform( $tx, $ty )
		);
	}

	/**
	 * @param string $font Font family.
	 * @return string
	 */
	private static function lightburn_font_name( $font ) {
		$font = trim( (string) $font );
		if ( '' === $font ) {
			return 'Russo One';
		}
		return $font;
	}

	/**
	 * @param array $obj Object.
	 * @param array $ctx Context.
	 * @return string[]
	 */
	private static function shape_paths_from_asset( $obj, $ctx ) {
		$url = PCKZ_Production_Geometry::asset_url_for_object( $obj, $ctx['selections'] );
		$box = PCKZ_Production_Geometry::object_box_mm( $obj, $ctx['canvas_w'], $ctx['canvas_h'], $ctx['origin'] );
		if ( ! $box ) {
			return array();
		}

		if ( ! $url ) {
			return array( self::shape_rect_fill( $box, 1, $obj['role'] ?? 'asset' ) );
		}

		$loops = PCKZ_Production_Geometry::paths_for_object_mm( $obj, $box, $ctx['selections'] );
		if ( empty( $loops ) ) {
			return array( self::shape_rect_fill( $box, 1, $obj['role'] ?? 'asset' ) );
		}

		$out = array();
		foreach ( $loops as $idx => $parsed ) {
			$shape = self::shape_path( $parsed, 1, ( $obj['role'] ?? 'path' ) . '-' . ( $idx + 1 ) );
			if ( $shape ) {
				$out[] = $shape;
			}
		}

		return $out ?: array( self::shape_rect_fill( $box, 1, $obj['role'] ?? 'asset' ) );
	}

	/**
	 * @param array  $box   Box.
	 * @param int    $index Cut index.
	 * @param string $label Label.
	 * @return string
	 */
	private static function shape_rect_fill( $box, $index, $label ) {
		$tx = $box['x'] + $box['width'] / 2;
		$ty = $box['y'] + $box['height'] / 2;
		return sprintf(
			'<!-- %s placeholder -->' . "\n" .
			'<Shape Type="Rect" CutIndex="%d" W="%s" H="%s">' . "\n" .
			'<XForm>%s</XForm>' . "\n" .
			'</Shape>',
			esc_attr( $label ),
			(int) $index,
			PCKZ_Production_Geometry::fmt( $box['width'] ),
			PCKZ_Production_Geometry::fmt( $box['height'] ),
			self::xform( $tx, $ty )
		);
	}

	/**
	 * @param array  $parsed Parsed path.
	 * @param int    $cut_index Layer.
	 * @param string $comment Comment.
	 * @return string
	 */
	private static function shape_path( $parsed, $cut_index, $comment ) {
		$prims = self::ensure_path_primitives( $parsed );
		if ( count( $parsed['verts'] ) < 2 ) {
			return '';
		}

		return sprintf(
			'<!-- %s -->' . "\n" .
			'<Shape Type="Path" CutIndex="%d" IsClosed="%d">' . "\n" .
			'<XForm>1 0 0 1 0 0</XForm>' . "\n" .
			'<VertList>%s</VertList>' . "\n" .
			'<PrimList>%s</PrimList>' . "\n" .
			'</Shape>',
			esc_attr( $comment ),
			(int) $cut_index,
			! empty( $parsed['closed'] ) ? 1 : 0,
			self::encode_vert_list_condensed( $parsed['verts'] ),
			self::encode_prim_list_condensed( $prims )
		);
	}

	/**
	 * Legacy path shape with V/P child elements (readable .lbrn).
	 *
	 * @param array  $parsed    Parsed path.
	 * @param int    $cut_index Cut layer.
	 * @param string $comment   Comment.
	 * @return string
	 */
	private static function shape_path_legacy( $parsed, $cut_index, $comment ) {
		$prims = self::ensure_path_primitives( $parsed );
		if ( count( $parsed['verts'] ) < 2 ) {
			return '';
		}

		$lines = array();
		$lines[] = sprintf( '<!-- %s -->', esc_attr( $comment ) );
		$lines[] = sprintf(
			'<Shape Type="Path" CutIndex="%d" IsClosed="%d">',
			(int) $cut_index,
			! empty( $parsed['closed'] ) ? 1 : 0
		);
		$lines[] = '<XForm>1 0 0 1 0 0</XForm>';
		foreach ( $parsed['verts'] as $v ) {
			$lines[] = sprintf(
				'<V vx="%s" vy="%s"/>',
				PCKZ_Production_Geometry::fmt( $v['x'] ),
				PCKZ_Production_Geometry::fmt( $v['y'] )
			);
		}
		foreach ( $prims as $p ) {
			$lines[] = sprintf(
				'<P T="%s" p0="%d" p1="%d"/>',
				esc_attr( $p['t'] ),
				(int) $p['p0'],
				(int) $p['p1']
			);
		}
		$lines[] = '</Shape>';
		return implode( "\n", $lines );
	}

	/**
	 * @param array $parsed Path data.
	 * @return array
	 */
	private static function ensure_path_primitives( $parsed ) {
		$verts  = $parsed['verts'];
		$prims  = $parsed['prims'] ?? array();
		$closed = ! empty( $parsed['closed'] );

		if ( empty( $prims ) ) {
			$breaks = array();
			if ( ! empty( $parsed['subpath_starts'] ) && is_array( $parsed['subpath_starts'] ) ) {
				foreach ( $parsed['subpath_starts'] as $b ) {
					$breaks[ (int) $b ] = true;
				}
			}
			for ( $i = 1; $i < count( $verts ); $i++ ) {
				if ( ! empty( $breaks[ $i ] ) ) {
					continue;
				}
				$prims[] = array( 'p0' => $i - 1, 'p1' => $i, 't' => 'L' );
			}
			if ( $closed && count( $verts ) > 2 && empty( $breaks[0] ) ) {
				$prims[] = array( 'p0' => count( $verts ) - 1, 'p1' => 0, 't' => 'L' );
			}
		}
		return $prims;
	}

	/**
	 * LightBurn .lbrn2 condensed vertex list (e.g. V10 20V30 40).
	 *
	 * @param array $verts Vertices.
	 * @return string
	 */
	private static function encode_vert_list_condensed( $verts ) {
		$parts = array();
		foreach ( $verts as $v ) {
			$parts[] = 'V' . PCKZ_Production_Geometry::fmt( $v['x'] ) . ' ' . PCKZ_Production_Geometry::fmt( $v['y'] );
		}
		return implode( '', $parts );
	}

	/**
	 * LightBurn .lbrn2 condensed primitive list (e.g. L0 1L1 2).
	 *
	 * @param array $prims Primitives.
	 * @return string
	 */
	private static function encode_prim_list_condensed( $prims ) {
		$parts = array();
		foreach ( $prims as $p ) {
			$t = ( isset( $p['t'] ) && 'B' === strtoupper( (string) $p['t'] ) ) ? 'B' : 'L';
			$parts[] = $t . (int) $p['p0'] . ' ' . (int) $p['p1'];
		}
		return implode( '', $parts );
	}

	/**
	 * @param array $obj Object.
	 * @param array $ctx Context.
	 * @return string[]
	 */
	private static function render_object_shapes_legacy( $obj, $ctx ) {
		$obj  = PCKZ_Production_Geometry::normalize_layout_object( $obj );
		$role = $obj['role'] ?? '';

		if ( in_array( $role, array( 'text', 'main-text' ), true ) ) {
			return self::shape_text_shapes( $obj, $ctx );
		}

		if ( in_array( $role, array( 'icon-left', 'icon-right', 'icon-bg-left', 'icon-bg-right', 'lines' ), true ) ) {
			return self::shape_paths_from_asset_legacy( $obj, $ctx );
		}

		return array();
	}

	/**
	 * @param array $obj Object.
	 * @param array $ctx Context.
	 * @return string[]
	 */
	private static function shape_paths_from_asset_legacy( $obj, $ctx ) {
		$url = PCKZ_Production_Geometry::asset_url_for_object( $obj, $ctx['selections'] );
		$box = PCKZ_Production_Geometry::object_box_mm( $obj, $ctx['canvas_w'], $ctx['canvas_h'], $ctx['origin'] );
		if ( ! $box ) {
			return array();
		}

		if ( ! $url ) {
			return array( self::shape_rect_fill( $box, 1, $obj['role'] ?? 'asset' ) );
		}

		$loops = PCKZ_Production_Geometry::paths_for_object_mm( $obj, $box, $ctx['selections'] );
		if ( empty( $loops ) ) {
			return array( self::shape_rect_fill( $box, 1, $obj['role'] ?? 'asset' ) );
		}

		$out = array();
		foreach ( $loops as $idx => $parsed ) {
			$shape = self::shape_path_legacy( $parsed, 1, ( $obj['role'] ?? 'path' ) . '-' . ( $idx + 1 ) );
			if ( $shape ) {
				$out[] = $shape;
			}
		}

		return $out ?: array( self::shape_rect_fill( $box, 1, $obj['role'] ?? 'asset' ) );
	}

	/**
	 * @param float $tx X mm.
	 * @param float $ty Y mm (from bottom).
	 * @return string
	 */
	private static function xform( $tx, $ty ) {
		return sprintf( '1 0 0 1 %s %s', PCKZ_Production_Geometry::fmt( $tx ), PCKZ_Production_Geometry::fmt( $ty ) );
	}
}
