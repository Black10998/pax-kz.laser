<?php
/**
 * Automatic shipment tracking synchronization.
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PCKZ_Shipment_Tracking
 */
class PCKZ_Shipment_Tracking {

	const CRON_HOOK = 'pckz_shipment_tracking_sync';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'cron_schedules', array( $this, 'register_sync_interval' ) );
		add_action( 'init', array( $this, 'ensure_sync_schedule' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'sync_due_orders' ) );
	}

	/**
	 * Register dynamic cron interval based on settings.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public function register_sync_interval( $schedules ) {
		$key = self::schedule_key();
		if ( isset( $schedules[ $key ] ) ) {
			return $schedules;
		}
		$minutes = self::sync_interval_minutes();
		$schedules[ $key ] = array(
			'interval' => $minutes * ( defined( 'MINUTE_IN_SECONDS' ) ? MINUTE_IN_SECONDS : 60 ),
			'display'  => sprintf(
				/* translators: %d: minutes */
				__( 'Every %d minutes (Shipment tracking sync)', 'pckz-canonical-engine' ),
				$minutes
			),
		);
		return $schedules;
	}

	/**
	 * Ensure cron hook is scheduled only when auto sync is configured.
	 */
	public function ensure_sync_schedule() {
		if ( ! self::is_auto_sync_provider_ready() ) {
			wp_clear_scheduled_hook( self::CRON_HOOK );
			return;
		}

		$desired_schedule = self::schedule_key();
		$scheduled_time   = wp_next_scheduled( self::CRON_HOOK );
		$scheduled_name   = '';
		if ( function_exists( 'wp_get_scheduled_event' ) ) {
			$event = wp_get_scheduled_event( self::CRON_HOOK );
			if ( $event && ! empty( $event->schedule ) ) {
				$scheduled_name = (string) $event->schedule;
			}
		}

		if ( ! $scheduled_time ) {
			wp_schedule_event( time() + 120, $desired_schedule, self::CRON_HOOK );
			return;
		}

		if ( $scheduled_name && $scheduled_name !== $desired_schedule ) {
			wp_clear_scheduled_hook( self::CRON_HOOK );
			wp_schedule_event( time() + 120, $desired_schedule, self::CRON_HOOK );
		}
	}

	/**
	 * Sync eligible shipment rows on cron.
	 */
	public static function sync_due_orders() {
		if ( ! class_exists( 'PCKZ_Commerce' ) ) {
			return;
		}
		$orders = PCKZ_Commerce::list_orders( 300 );
		$synced = 0;
		foreach ( $orders as $order ) {
			if ( $synced >= 25 ) {
				break;
			}
			$status = PCKZ_Commerce::normalize_status_code( $order['status'] ?? '' );
			if ( ! in_array( $status, array( 'ready_to_ship', 'shipped', 'completed' ), true ) ) {
				continue;
			}
			$payload = PCKZ_Commerce::customer_shipping_summary( $order );
			if ( empty( $payload['auto_sync'] ) || empty( $payload['tracking_number'] ) ) {
				continue;
			}
			if ( ! self::should_sync_now( $payload ) ) {
				continue;
			}
			$result = self::sync_order_now( (int) $order['id'] );
			if ( ! is_wp_error( $result ) ) {
				++$synced;
			}
		}
	}

	/**
	 * Force one order synchronization.
	 *
	 * @param int|array $order_ref Order ID or order row.
	 * @param bool      $force     Ignore sync interval lock.
	 * @return array|WP_Error
	 */
	public static function sync_order_now( $order_ref, $force = false ) {
		$order = is_array( $order_ref ) ? $order_ref : PCKZ_Commerce::get_order( absint( $order_ref ) );
		if ( ! is_array( $order ) || empty( $order['id'] ) ) {
			return new WP_Error( 'order_not_found', __( 'Order not found for shipment sync.', 'pckz-canonical-engine' ) );
		}

		$payload = PCKZ_Commerce::customer_shipping_summary( $order );
		if ( empty( $payload['tracking_number'] ) ) {
			return new WP_Error( 'tracking_number_missing', __( 'Tracking number is missing.', 'pckz-canonical-engine' ) );
		}
		if ( ! $force && ! self::should_sync_now( $payload ) ) {
			return $payload;
		}
		if ( ! self::is_auto_sync_provider_ready() ) {
			$error = new WP_Error( 'tracking_provider_not_ready', __( 'Automatic shipment tracking is not configured (missing API key or disabled).', 'pckz-canonical-engine' ) );
			self::persist_sync_error( $order, $payload, $error->get_error_message() );
			return $error;
		}

		$carrier_slug = self::resolve_carrier_slug( (string) $payload['tracking_number'], (string) ( $payload['carrier_slug'] ?? '' ), (string) ( $payload['carrier'] ?? '' ) );
		if ( '' === $carrier_slug ) {
			$error = new WP_Error( 'carrier_not_detected', __( 'Carrier could not be detected. Set the carrier code manually.', 'pckz-canonical-engine' ) );
			self::persist_sync_error( $order, $payload, $error->get_error_message() );
			return $error;
		}

		$snapshot = self::fetch_aftership_snapshot( $carrier_slug, (string) $payload['tracking_number'] );
		if ( is_wp_error( $snapshot ) ) {
			self::persist_sync_error( $order, $payload, $snapshot->get_error_message() );
			return $snapshot;
		}

		$payload['carrier_slug'] = $carrier_slug;
		$payload['carrier']      = ! empty( $snapshot['carrier'] ) ? (string) $snapshot['carrier'] : ( $payload['carrier'] ?: self::carrier_label_from_slug( $carrier_slug ) );
		if ( ! empty( $snapshot['tracking_url'] ) ) {
			$payload['tracking_url'] = (string) $snapshot['tracking_url'];
		}
		if ( ! empty( $snapshot['shipment_status'] ) ) {
			$payload['shipment_status'] = (string) $snapshot['shipment_status'];
		}
		if ( ! empty( $snapshot['current_location'] ) ) {
			$payload['current_location'] = (string) $snapshot['current_location'];
		}
		if ( ! empty( $snapshot['estimated_delivery'] ) ) {
			$payload['estimated_delivery'] = (string) $snapshot['estimated_delivery'];
		}
		if ( ! empty( $snapshot['events'] ) && is_array( $snapshot['events'] ) ) {
			$payload['events'] = $snapshot['events'];
		}
		$payload['last_synced_at'] = current_time( 'mysql' );
		$payload['sync_error']     = '';

		$saved = PCKZ_Commerce::save_order_shipment_tracking( (int) $order['id'], $payload );
		if ( ! is_array( $saved ) ) {
			return new WP_Error( 'tracking_save_failed', __( 'Could not save synchronized tracking payload.', 'pckz-canonical-engine' ) );
		}
		PCKZ_Commerce::sync_shipment_payload_to_wc_order( $order, $saved );
		return $saved;
	}

	/**
	 * Check if sync should run now.
	 *
	 * @param array $payload Shipment payload.
	 * @return bool
	 */
	private static function should_sync_now( $payload ) {
		$last = ! empty( $payload['last_synced_at'] ) ? strtotime( (string) $payload['last_synced_at'] ) : 0;
		if ( ! $last ) {
			return true;
		}
		return ( time() - $last ) >= ( self::sync_interval_minutes() * ( defined( 'MINUTE_IN_SECONDS' ) ? MINUTE_IN_SECONDS : 60 ) );
	}

	/**
	 * Save sync error for visibility in admin.
	 *
	 * @param array  $order   Commerce order row.
	 * @param array  $payload Current payload.
	 * @param string $message Error text.
	 */
	private static function persist_sync_error( $order, $payload, $message ) {
		$payload['last_synced_at'] = current_time( 'mysql' );
		$payload['sync_error']     = sanitize_text_field( (string) $message );
		$saved = PCKZ_Commerce::save_order_shipment_tracking( (int) ( $order['id'] ?? 0 ), $payload );
		if ( is_array( $saved ) ) {
			PCKZ_Commerce::sync_shipment_payload_to_wc_order( $order, $saved );
		}
	}

	/**
	 * Resolve carrier slug from saved payload, heuristics, or API detection.
	 *
	 * @param string $tracking_number Tracking number.
	 * @param string $carrier_slug    Existing carrier slug.
	 * @param string $carrier_label   Existing carrier label.
	 * @return string
	 */
	private static function resolve_carrier_slug( $tracking_number, $carrier_slug = '', $carrier_label = '' ) {
		$tracking_number = sanitize_text_field( (string) $tracking_number );
		$carrier_slug    = sanitize_key( (string) $carrier_slug );
		if ( '' !== $carrier_slug ) {
			return $carrier_slug;
		}
		$heuristic = self::heuristic_carrier_slug( $tracking_number, $carrier_label );
		if ( '' !== $heuristic ) {
			return $heuristic;
		}
		if ( ! PCKZ_Settings::get( 'tracking_auto_detect_carrier', true ) ) {
			return '';
		}
		$api_key = self::aftership_api_key();
		if ( '' === $api_key ) {
			return '';
		}
		return self::detect_carrier_slug_via_aftership( $tracking_number, $api_key );
	}

	/**
	 * Lightweight carrier detection from label or tracking format.
	 *
	 * @param string $tracking_number Tracking number.
	 * @param string $carrier_label   Carrier label.
	 * @return string
	 */
	private static function heuristic_carrier_slug( $tracking_number, $carrier_label = '' ) {
		$tracking_number = strtoupper( preg_replace( '/\s+/', '', (string) $tracking_number ) );
		$label = strtolower( (string) $carrier_label );
		$label_map = array(
			'austrian-post' => array( 'österreichische post', 'oesterreichische post', 'austrian post', 'post at', 'post.at' ),
			'dhl'           => array( 'dhl' ),
			'dpd'           => array( 'dpd' ),
			'gls'           => array( 'gls' ),
			'ups'           => array( 'ups', 'united parcel service' ),
			'fedex'         => array( 'fedex', 'federal express' ),
		);
		foreach ( $label_map as $slug => $needles ) {
			foreach ( $needles as $needle ) {
				if ( false !== strpos( $label, $needle ) ) {
					return $slug;
				}
			}
		}

		if ( preg_match( '/^[A-Z]{2}\d{9}AT$/', $tracking_number ) ) {
			return 'austrian-post';
		}
		if ( preg_match( '/^1Z[0-9A-Z]{16}$/', $tracking_number ) ) {
			return 'ups';
		}
		if ( preg_match( '/^\d{12}$/', $tracking_number ) ) {
			return 'fedex';
		}
		if ( preg_match( '/^\d{14}$/', $tracking_number ) ) {
			return 'dpd';
		}
		if ( preg_match( '/^\d{10,11}$/', $tracking_number ) ) {
			return 'dhl';
		}
		return '';
	}

	/**
	 * Carrier detection via AfterShip detect endpoint.
	 *
	 * @param string $tracking_number Tracking number.
	 * @param string $api_key         API key.
	 * @return string
	 */
	private static function detect_carrier_slug_via_aftership( $tracking_number, $api_key ) {
		$data = self::aftership_get(
			'/couriers/detect',
			array(
				'tracking_number' => $tracking_number,
			),
			$api_key
		);
		if ( is_wp_error( $data ) ) {
			return '';
		}
		$couriers = is_array( $data['couriers'] ?? null ) ? $data['couriers'] : array();
		if ( empty( $couriers[0]['slug'] ) ) {
			return '';
		}
		return sanitize_key( (string) $couriers[0]['slug'] );
	}

	/**
	 * Fetch tracking snapshot from AfterShip.
	 *
	 * @param string $carrier_slug    Carrier slug.
	 * @param string $tracking_number Tracking number.
	 * @return array|WP_Error
	 */
	private static function fetch_aftership_snapshot( $carrier_slug, $tracking_number ) {
		$api_key = self::aftership_api_key();
		if ( '' === $api_key ) {
			return new WP_Error( 'aftership_api_key_missing', __( 'AfterShip API key is missing.', 'pckz-canonical-engine' ) );
		}
		$data = self::aftership_get(
			'/trackings/' . rawurlencode( (string) $carrier_slug ) . '/' . rawurlencode( (string) $tracking_number ),
			array(),
			$api_key
		);
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$tracking = is_array( $data['tracking'] ?? null ) ? $data['tracking'] : array();
		if ( empty( $tracking ) ) {
			return new WP_Error( 'aftership_tracking_missing', __( 'Carrier response did not contain tracking payload.', 'pckz-canonical-engine' ) );
		}
		return self::map_aftership_tracking_payload( $tracking );
	}

	/**
	 * Convert AfterShip payload to local shipment structure.
	 *
	 * @param array $tracking Raw AfterShip tracking object.
	 * @return array
	 */
	private static function map_aftership_tracking_payload( $tracking ) {
		$events = array();
		$checkpoints = is_array( $tracking['checkpoints'] ?? null ) ? $tracking['checkpoints'] : array();
		foreach ( $checkpoints as $checkpoint ) {
			if ( ! is_array( $checkpoint ) ) {
				continue;
			}
			$location_parts = array_filter(
				array(
					sanitize_text_field( (string) ( $checkpoint['city'] ?? '' ) ),
					sanitize_text_field( (string) ( $checkpoint['country_name'] ?? '' ) ),
				)
			);
			$events[] = array(
				'date'     => sanitize_text_field( (string) ( $checkpoint['checkpoint_time'] ?? '' ) ),
				'status'   => sanitize_text_field( (string) ( $checkpoint['tag'] ?? '' ) ),
				'location' => implode( ', ', $location_parts ),
				'message'  => sanitize_text_field( (string) ( $checkpoint['message'] ?? '' ) ),
			);
		}

		$latest = ! empty( $checkpoints ) ? end( $checkpoints ) : array();
		if ( ! is_array( $latest ) ) {
			$latest = array();
		}
		$latest_location = array_filter(
			array(
				sanitize_text_field( (string) ( $latest['city'] ?? '' ) ),
				sanitize_text_field( (string) ( $latest['country_name'] ?? '' ) ),
			)
		);
		$tracking_url = $tracking['tracking_url'] ?? '';
		if ( is_array( $tracking_url ) ) {
			$tracking_url = reset( $tracking_url );
		}

		return array(
			'carrier'            => sanitize_text_field( (string) ( $tracking['courier_name'] ?? '' ) ),
			'shipment_status'    => sanitize_text_field( (string) ( $tracking['tag'] ?? ( $latest['tag'] ?? '' ) ) ),
			'current_location'   => implode( ', ', $latest_location ),
			'estimated_delivery' => sanitize_text_field( (string) ( $tracking['expected_delivery'] ?? '' ) ),
			'tracking_url'       => esc_url_raw( (string) $tracking_url ),
			'events'             => PCKZ_Commerce::normalize_tracking_events( $events ),
		);
	}

	/**
	 * Perform authenticated GET request to AfterShip.
	 *
	 * @param string $path   API path.
	 * @param array  $query  Query args.
	 * @param string $api_key API key.
	 * @return array|WP_Error
	 */
	private static function aftership_get( $path, $query = array(), $api_key = '' ) {
		$api_key = '' !== $api_key ? $api_key : self::aftership_api_key();
		if ( '' === $api_key ) {
			return new WP_Error( 'aftership_api_key_missing', __( 'AfterShip API key is missing.', 'pckz-canonical-engine' ) );
		}
		$url = 'https://api.aftership.com/v4' . $path;
		if ( ! empty( $query ) ) {
			$url = add_query_arg( $query, $url );
		}
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 20,
				'headers' => array(
					'aftership-api-key' => $api_key,
					'Content-Type'      => 'application/json',
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$http_code = (int) wp_remote_retrieve_response_code( $response );
		$body      = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			return new WP_Error( 'aftership_bad_json', __( 'AfterShip response is not valid JSON.', 'pckz-canonical-engine' ) );
		}
		$meta_code = (int) ( $body['meta']['code'] ?? $http_code );
		if ( $http_code < 200 || $http_code >= 300 || $meta_code < 200 || $meta_code >= 300 ) {
			$message = sanitize_text_field( (string) ( $body['meta']['message'] ?? __( 'Unknown carrier API error.', 'pckz-canonical-engine' ) ) );
			return new WP_Error( 'aftership_request_failed', $message );
		}
		return is_array( $body['data'] ?? null ) ? $body['data'] : array();
	}

	/**
	 * Build cron schedule key.
	 *
	 * @return string
	 */
	private static function schedule_key() {
		return 'pckz_tracking_sync_' . self::sync_interval_minutes() . 'm';
	}

	/**
	 * Sync interval in minutes.
	 *
	 * @return int
	 */
	private static function sync_interval_minutes() {
		return max( 5, min( 240, absint( PCKZ_Settings::get( 'tracking_sync_interval_minutes', 30 ) ) ) );
	}

	/**
	 * Whether auto sync is enabled and key is configured.
	 *
	 * @return bool
	 */
	private static function is_auto_sync_provider_ready() {
		if ( ! PCKZ_Settings::get( 'tracking_auto_sync_enabled', false ) ) {
			return false;
		}
		return '' !== self::aftership_api_key();
	}

	/**
	 * Read API key.
	 *
	 * @return string
	 */
	private static function aftership_api_key() {
		return trim( (string) PCKZ_Settings::get( 'tracking_aftership_api_key', '' ) );
	}

	/**
	 * Human-readable default labels.
	 *
	 * @param string $slug Carrier slug.
	 * @return string
	 */
	private static function carrier_label_from_slug( $slug ) {
		$labels = array(
			'austrian-post' => 'Österreichische Post',
			'dhl'           => 'DHL',
			'dpd'           => 'DPD',
			'gls'           => 'GLS',
			'ups'           => 'UPS',
			'fedex'         => 'FedEx',
		);
		$slug = sanitize_key( (string) $slug );
		return $labels[ $slug ] ?? strtoupper( $slug );
	}
}
