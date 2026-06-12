<?php
/**
 * Smoke: customer shipping summary resolves tracking number/status/events.
 *
 * @package PCKZCanonicalEngine
 */

require_once dirname( __DIR__ ) . '/tests/smoke-bootstrap.php';

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return max( 0, (int) $value );
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
}
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		return (string) $url;
	}
}
if ( ! function_exists( 'date_i18n' ) ) {
	function date_i18n( $format, $timestamp ) {
		return gmdate( $format, (int) $timestamp );
	}
}

class PCKZ_Smoke_Date {
	public function date_i18n( $format ) {
		return gmdate( $format, 1717516800 );
	}
}

class PCKZ_Smoke_Shipping_Item {
	public function get_name() {
		return 'Oesterreichische Post';
	}
}

class PCKZ_Smoke_WC_Order {
	private $meta = array();

	public function __construct( $meta ) {
		$this->meta = (array) $meta;
	}

	public function get_meta( $key, $single = true ) {
		unset( $single );
		return $this->meta[ $key ] ?? '';
	}

	public function get_items( $type = 'line_item' ) {
		if ( 'shipping' !== $type ) {
			return array();
		}
		return array( new PCKZ_Smoke_Shipping_Item() );
	}

	public function get_date_completed() {
		return new PCKZ_Smoke_Date();
	}
}

if ( ! function_exists( 'wc_get_order' ) ) {
	function wc_get_order( $id ) {
		unset( $id );
		$meta = array(
			'_tracking_number'      => 'AB123456789AT',
			'_tracking_provider'    => 'Oesterreichische Post',
			'_tracking_url'         => 'https://post.example.test/track/AB123456789AT',
			'_tracking_status'      => 'In Zustellung',
			'_tracking_location'    => 'Wien',
			'_estimated_delivery'   => '05.06.2026',
			'_pckz_tracking_events' => wp_json_encode(
				array(
					array(
						'date'     => '04.06.2026 08:45',
						'status'   => 'Im Verteilzentrum',
						'location' => 'Wien',
						'message'  => 'Sendung sortiert',
					),
				)
			),
		);
		return new PCKZ_Smoke_WC_Order( $meta );
	}
}

require_once dirname( __DIR__ ) . '/includes/class-pckz-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-pckz-commerce.php';

$summary = PCKZ_Commerce::customer_shipping_summary(
	array(
		'wc_order_id' => 55,
	)
);

if ( 'AB123456789AT' !== (string) ( $summary['tracking_number'] ?? '' ) ) {
	fwrite( STDERR, "FAIL: tracking number missing\n" );
	exit( 1 );
}
if ( 'In Zustellung' !== (string) ( $summary['shipment_status'] ?? '' ) ) {
	fwrite( STDERR, "FAIL: shipment status missing\n" );
	exit( 1 );
}
if ( empty( $summary['events'] ) || 'Im Verteilzentrum' !== (string) ( $summary['events'][0]['status'] ?? '' ) ) {
	fwrite( STDERR, "FAIL: tracking events not parsed\n" );
	exit( 1 );
}
if ( empty( $summary['has_data'] ) ) {
	fwrite( STDERR, "FAIL: has_data should be true\n" );
	exit( 1 );
}

echo "customer-shipment-tracking-smoke: OK\n";
exit( 0 );
