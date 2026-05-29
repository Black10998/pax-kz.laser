<?php
/**
 * Frontend assets and rendering.
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PCKZ_Public
 */
class PCKZ_Public {

	/**
	 * Track if creator assets were enqueued.
	 *
	 * @var bool
	 */
	private static $assets_enqueued = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	/**
	 * Register (not enqueue) frontend assets.
	 */
	public function register_assets() {
		// Assets are enqueued on demand via PCKZ_Assets when the shortcode renders.
	}

	/**
	 * Enqueue creator assets for a product.
	 *
	 * @param int   $product_id Creator product post ID.
	 * @param array $config     Product configuration.
	 */
	public static function enqueue_creator( $product_id, $config ) {
		if ( self::$assets_enqueued ) {
			return;
		}
		PCKZ_Assets::enqueue_creator( $product_id, $config );
		self::$assets_enqueued = true;
	}

	/**
	 * Render creator markup.
	 *
	 * @param int $product_id Creator product ID.
	 * @return string
	 */
	public static function render_creator( $product_id ) {
		$post = get_post( $product_id );
		if ( ! $post || PCKZ_Post_Type::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			return '<p class="pckz-error">' . esc_html__( 'Creator product not found.', 'pckz-canonical-engine' ) . '</p>';
		}

		$config = PCKZ_Post_Type::get_product_config( $product_id );
		self::enqueue_creator( $product_id, $config );

		ob_start();
		include PCKZCE_PLUGIN_DIR . 'public/templates/creator.php';
		return ob_get_clean();
	}
}
