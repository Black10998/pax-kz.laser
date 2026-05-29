<?php
/**
 * Shortcode [product_creator] — works with or without id attribute.
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PCKZ_Shortcode
 */
class PCKZ_Shortcode {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_shortcode( 'product_creator', array( $this, 'render' ) );
		add_shortcode( 'pckzce_creator', array( $this, 'render' ) );
	}

	/**
	 * Render shortcode.
	 *
	 * Usage:
	 *   [product_creator]
	 *   [product_creator id="123"]
	 *   [product_creator slug="license-plate-creator"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'     => 0,
				'slug'   => '',
				'class'  => '',
				'height' => 'auto',
			),
			$atts,
			'product_creator'
		);

		$product_id = PCKZ_Post_Type::resolve_product_id( $atts['id'], $atts['slug'] );

		if ( ! $product_id ) {
			$admin_link = admin_url( 'post-new.php?post_type=' . PCKZ_Post_Type::POST_TYPE );
			return '<div class="pckz-error"><p>' . esc_html__( 'No creator product is available yet.', 'pckz-canonical-engine' ) . '</p>'
				. ( current_user_can( 'manage_options' )
					? '<p><a href="' . esc_url( $admin_link ) . '">' . esc_html__( 'Create a creator product', 'pckz-canonical-engine' ) . '</a></p>'
					: '' )
				. '</div>';
		}

		$wrapper_class = 'pckz-creator-wrapper';
		if ( ! empty( $atts['class'] ) ) {
			$wrapper_class .= ' ' . sanitize_html_class( $atts['class'] );
		}

		$style = '';
		if ( 'auto' !== $atts['height'] && is_numeric( $atts['height'] ) ) {
			$style = ' style="min-height:' . absint( $atts['height'] ) . 'px"';
		}

		return '<div class="' . esc_attr( $wrapper_class ) . '"' . $style . '>' . PCKZ_Public::render_creator( $product_id ) . '</div>';
	}
}
