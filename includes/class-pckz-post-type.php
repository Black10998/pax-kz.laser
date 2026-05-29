<?php
/**
 * Creator product custom post type.
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PCKZ_Post_Type
 */
class PCKZ_Post_Type {

	/**
	 * Post type slug.
	 */
	const POST_TYPE = 'pckz_product';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_meta' ), 10, 2 );
	}

	/**
	 * Register CPT.
	 */
	public static function register_post_type() {
		$labels = array(
			'name'               => __( 'Creator Products', 'pckz-canonical-engine' ),
			'singular_name'      => __( 'Creator Product', 'pckz-canonical-engine' ),
			'add_new'            => __( 'Add New', 'pckz-canonical-engine' ),
			'add_new_item'       => __( 'Add Creator Product', 'pckz-canonical-engine' ),
			'edit_item'          => __( 'Edit Creator Product', 'pckz-canonical-engine' ),
			'new_item'           => __( 'New Creator Product', 'pckz-canonical-engine' ),
			'view_item'          => __( 'View Creator Product', 'pckz-canonical-engine' ),
			'search_items'       => __( 'Search Creator Products', 'pckz-canonical-engine' ),
			'not_found'          => __( 'No creator products found', 'pckz-canonical-engine' ),
			'not_found_in_trash' => __( 'No creator products in trash', 'pckz-canonical-engine' ),
			'menu_name'          => __( 'Product Creator', 'pckz-canonical-engine' ),
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => $labels,
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => false,
				'supports'            => array( 'title', 'thumbnail' ),
				'capability_type'     => 'post',
				'has_archive'         => false,
				'rewrite'             => false,
				'exclude_from_search' => true,
			)
		);
	}

	/**
	 * Register meta boxes.
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'pckz_product_config',
			__( 'Product Configuration', 'pckz-canonical-engine' ),
			array( $this, 'render_config_metabox' ),
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Render configuration metabox.
	 *
	 * @param WP_Post $post Post object.
	 */
	public function render_config_metabox( $post ) {
		wp_nonce_field( 'pckz_save_product', 'pckz_product_nonce' );

		$config = self::get_product_config( $post->ID );
		$fonts  = PCKZ_Settings::get_fonts();
		$colors = PCKZ_Settings::get_color_palette();

		include PCKZCE_PLUGIN_DIR . 'admin/views/product-metabox.php';
	}

	/**
	 * Save product meta.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['pckz_product_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pckz_product_nonce'] ) ), 'pckz_save_product' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$config = isset( $_POST['pckz_config'] ) ? wp_unslash( $_POST['pckz_config'] ) : array();
		$config = self::sanitize_config( $config );

		update_post_meta( $post_id, '_pckz_config', $config );
	}

	/**
	 * Sanitize product configuration array.
	 *
	 * @param array $raw Raw input.
	 * @return array
	 */
	public static function sanitize_config( $raw ) {
		if ( ! is_array( $raw ) ) {
			return self::default_config();
		}

		$defaults = self::default_config();

		$config = array(
			'canvas_width_mm'   => max( 10, floatval( $raw['canvas_width_mm'] ?? $defaults['canvas_width_mm'] ) ),
			'canvas_height_mm'  => max( 10, floatval( $raw['canvas_height_mm'] ?? $defaults['canvas_height_mm'] ) ),
			'safe_zone_x_mm'    => max( 0, floatval( $raw['safe_zone_x_mm'] ?? $defaults['safe_zone_x_mm'] ) ),
			'safe_zone_y_mm'    => max( 0, floatval( $raw['safe_zone_y_mm'] ?? $defaults['safe_zone_y_mm'] ) ),
			'safe_zone_w_mm'    => max( 1, floatval( $raw['safe_zone_w_mm'] ?? $defaults['safe_zone_w_mm'] ) ),
			'safe_zone_h_mm'    => max( 1, floatval( $raw['safe_zone_h_mm'] ?? $defaults['safe_zone_h_mm'] ) ),
			'strip_zone_x_mm'   => max( 0, floatval( $raw['strip_zone_x_mm'] ?? $defaults['strip_zone_x_mm'] ) ),
			'strip_zone_y_mm'   => max( 0, floatval( $raw['strip_zone_y_mm'] ?? $defaults['strip_zone_y_mm'] ) ),
			'strip_zone_w_mm'   => max( 1, floatval( $raw['strip_zone_w_mm'] ?? $defaults['strip_zone_w_mm'] ) ),
			'strip_zone_h_mm'   => max( 1, floatval( $raw['strip_zone_h_mm'] ?? $defaults['strip_zone_h_mm'] ) ),
			'dpi'               => max( 72, min( 600, intval( $raw['dpi'] ?? $defaults['dpi'] ) ) ),
			'origin'            => in_array( $raw['origin'] ?? '', array( 'top-left', 'bottom-left' ), true ) ? $raw['origin'] : $defaults['origin'],
			'background_image'  => esc_url_raw( $raw['background_image'] ?? '' ),
			'background_day'    => esc_url_raw( $raw['background_day'] ?? '' ),
			'background_night'  => esc_url_raw( $raw['background_night'] ?? '' ),
			'woo_product_id'    => absint( $raw['woo_product_id'] ?? 0 ),
			'price'             => sanitize_text_field( $raw['price'] ?? '' ),
			'currency'          => sanitize_text_field( $raw['currency'] ?? 'UAH' ),
			'enabled_tools'     => array(),
			'max_upload_mb'     => max( 1, min( 20, intval( $raw['max_upload_mb'] ?? 5 ) ) ),
			'description'       => wp_kses_post( $raw['description'] ?? '' ),
			'ui_layout'         => in_array( $raw['ui_layout'] ?? '', array( 'shop', 'studio' ), true ) ? $raw['ui_layout'] : $defaults['ui_layout'],
			'customer_options'  => PCKZ_Customizer_Options::sanitize_options(
				is_string( $raw['customer_options'] ?? '' ) ? $raw['customer_options'] : ( $raw['customer_options'] ?? array() )
			),
			'use_cloudlift_layout' => ! empty( $raw['use_cloudlift_layout'] ),
			'benefits'          => self::sanitize_benefits( $raw['benefits'] ?? array() ),
		);

		$tools = array( 'text', 'image', 'align', 'layers', 'position' );
		if ( ! empty( $raw['enabled_tools'] ) && is_array( $raw['enabled_tools'] ) ) {
			foreach ( $raw['enabled_tools'] as $tool ) {
				if ( in_array( $tool, $tools, true ) ) {
					$config['enabled_tools'][] = $tool;
				}
			}
		}
		if ( empty( $config['enabled_tools'] ) ) {
			$config['enabled_tools'] = 'shop' === $config['ui_layout'] ? array( 'position' ) : $tools;
		}

		return $config;
	}

	/**
	 * Sanitize benefit bullet lines.
	 *
	 * @param mixed $raw Raw benefits.
	 * @return array
	 */
	public static function sanitize_benefits( $raw ) {
		if ( is_string( $raw ) ) {
			$raw = array_filter( array_map( 'trim', explode( "\n", $raw ) ) );
		}
		if ( ! is_array( $raw ) ) {
			return self::default_config()['benefits'];
		}
		$benefits = array();
		foreach ( $raw as $line ) {
			$line = sanitize_text_field( $line );
			if ( $line ) {
				$benefits[] = $line;
			}
		}
		return ! empty( $benefits ) ? $benefits : self::default_config()['benefits'];
	}

	/**
	 * Default product configuration (license plate frame preset).
	 *
	 * @return array
	 */
	public static function default_config() {
		return array(
			'canvas_width_mm'  => 529.1,
			'canvas_height_mm' => 116,
			'safe_zone_x_mm'   => 5.55,
			'safe_zone_y_mm'   => 13.2,
			'safe_zone_w_mm'   => 518,
			'safe_zone_h_mm'   => 89.6,
			'strip_zone_x_mm'  => 20.94,
			'strip_zone_y_mm'  => 78.48,
			'strip_zone_w_mm'  => 487.37,
			'strip_zone_h_mm'  => 28.83,
			'dpi'              => 300,
			'origin'           => 'bottom-left',
			'background_image' => 'https://ledos.com.ua/cdn/shop/files/4807d1103abe596528a0780a19a917ed.jpg?v=1691396075',
			'background_day'   => 'https://ledos.com.ua/cdn/shop/files/Frame_day3.jpg?v=1691396075',
			'background_night' => 'https://ledos.com.ua/cdn/shop/files/Frame_night3.jpg?v=1691396075',
			'woo_product_id'   => 0,
			'price'            => '',
			'currency'         => 'UAH',
			'enabled_tools'    => array( 'position' ),
			'max_upload_mb'    => 5,
			'description'      => __( 'Customize your license plate frame with text, font, and optional logo. Preview updates live on the product.', 'pckz-canonical-engine' ),
			'ui_layout'          => 'shop',
			'use_cloudlift_layout' => true,
			'customer_options'     => PCKZ_Customizer_Options::default_ledos_options(),
			'benefits'           => array(
				'100 % Personalisierung',
				'Versand innerhalb von 1–5 Tagen',
				'Ein Jahr Garantie',
			),
		);
	}

	/**
	 * Get merged product config.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public static function get_product_config( $post_id ) {
		$stored = get_post_meta( $post_id, '_pckz_config', true );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return wp_parse_args( $stored, self::default_config() );
	}

	/**
	 * Resolve creator product ID from shortcode attributes or site default.
	 *
	 * @param int    $id   Explicit post ID.
	 * @param string $slug Post slug.
	 * @return int Zero if not found.
	 */
	public static function resolve_product_id( $id = 0, $slug = '' ) {
		$id = absint( $id );
		if ( $id && self::is_publishable_product( $id ) ) {
			return $id;
		}

		if ( $slug ) {
			$posts = get_posts(
				array(
					'name'        => sanitize_title( $slug ),
					'post_type'   => self::POST_TYPE,
					'post_status' => 'publish',
					'numberposts' => 1,
					'fields'      => 'ids',
				)
			);
			if ( ! empty( $posts ) ) {
				return (int) $posts[0];
			}
		}

		$default_id = absint( PCKZ_Settings::get( 'default_creator_product_id', 0 ) );
		if ( $default_id && self::is_publishable_product( $default_id ) ) {
			return $default_id;
		}

		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'numberposts'    => 1,
				'orderby'        => 'date',
				'order'          => 'ASC',
				'fields'         => 'ids',
			)
		);

		return ! empty( $posts ) ? (int) $posts[0] : 0;
	}

	/**
	 * Check if post is a published creator product.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function is_publishable_product( $post_id ) {
		$post = get_post( $post_id );
		return $post && self::POST_TYPE === $post->post_type && 'publish' === $post->post_status;
	}

	/**
	 * Get all published creator products for admin selects.
	 *
	 * @return WP_Post[]
	 */
	public static function get_published_products() {
		return get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'numberposts'    => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
	}
}
