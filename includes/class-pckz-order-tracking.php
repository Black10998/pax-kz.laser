<?php
/**
 * Public order tracking shortcode.
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PCKZ_Order_Tracking
 */
class PCKZ_Order_Tracking {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_shortcode( 'pckz_order_tracking', array( $this, 'render' ) );
		add_shortcode( 'pckz_bestellung_verfolgen', array( $this, 'render' ) );
	}

	/**
	 * Render tracking form / result.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public function render( $atts ) {
		if ( ! class_exists( 'PCKZ_Commerce' ) ) {
			return '';
		}

		wp_enqueue_style(
			'pckz-tracking',
			PCKZ_Assets::style_url( 'public/css/tracking.css' ),
			array(),
			PCKZ_Assets::version( PCKZ_Assets::style_relative_path( 'public/css/tracking.css' ) )
		);

		$order_number = '';
		if ( isset( $_POST['pckz_order_number'] ) ) {
			$order_number = sanitize_text_field( wp_unslash( $_POST['pckz_order_number'] ) );
		} elseif ( isset( $_GET['order'] ) ) {
			$order_number = sanitize_text_field( wp_unslash( $_GET['order'] ) );
		}

		$order   = null;
		$message = '';
		if ( '' !== $order_number ) {
			$order = PCKZ_Commerce::get_order_for_tracking( $order_number );
			if ( ! $order ) {
				$message = __( 'Keine Bestellung mit dieser Bestellnummer gefunden. Bitte prüfen Sie die Eingabe.', 'pckz-canonical-engine' );
			}
		}

		ob_start();
		include PCKZCE_PLUGIN_DIR . 'public/templates/order-tracking.php';
		return ob_get_clean();
	}
}
