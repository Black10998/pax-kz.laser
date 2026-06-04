<?php
/**
 * Master Control fleet dashboard, security events, and installation health.
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PCKZ_Master_Control
 */
class PCKZ_Master_Control {

	const TABLE_EVENTS           = 'pckz_license_security_events';
	const ONLINE_THRESHOLD_SEC   = 900;
	const STALE_SYNC_SEC         = 604800;
	const STALE_CHECKIN_SEC      = 1209600;

	/**
	 * Create / upgrade master-control tables and columns.
	 */
	public static function upgrade_schema() {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$upgrade_file = ABSPATH . 'wp-admin/includes/upgrade.php';
		if ( is_readable( $upgrade_file ) ) {
			require_once $upgrade_file;
		} elseif ( ! function_exists( 'dbDelta' ) ) {
			/**
			 * @param string $sql SQL.
			 */
			function dbDelta( $sql ) { // phpcs:ignore WordPress.NamingConventions
				global $wpdb;
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$wpdb->query( $sql );
			}
		}
		$events  = $wpdb->prefix . self::TABLE_EVENTS;
		$sql     = "CREATE TABLE {$events} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			license_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			installation_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			domain VARCHAR(191) NOT NULL DEFAULT '',
			install_uuid VARCHAR(96) NOT NULL DEFAULT '',
			event_type VARCHAR(64) NOT NULL DEFAULT '',
			severity VARCHAR(16) NOT NULL DEFAULT 'warning',
			message TEXT NULL,
			context LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY license_id (license_id),
			KEY installation_id (installation_id),
			KEY event_type (event_type),
			KEY created_at (created_at)
		) {$charset};";
		dbDelta( $sql );

		$installs = $wpdb->prefix . 'pckz_license_installations';
		$cols     = $wpdb->get_col( "DESC {$installs}", 0 );
		if ( ! is_array( $cols ) ) {
			$cols = array();
		}
		$add = array(
			'plugin_active'    => "ALTER TABLE {$installs} ADD plugin_active TINYINT(1) NOT NULL DEFAULT 1",
			'last_asset_sync'  => "ALTER TABLE {$installs} ADD last_asset_sync DATETIME NULL",
			'asset_revision'   => "ALTER TABLE {$installs} ADD asset_revision VARCHAR(64) NOT NULL DEFAULT ''",
			'update_status'    => "ALTER TABLE {$installs} ADD update_status VARCHAR(32) NOT NULL DEFAULT ''",
			'license_status'   => "ALTER TABLE {$installs} ADD license_status VARCHAR(32) NOT NULL DEFAULT ''",
			'site_name'        => "ALTER TABLE {$installs} ADD site_name VARCHAR(191) NOT NULL DEFAULT ''",
			'last_activity_at' => "ALTER TABLE {$installs} ADD last_activity_at DATETIME NULL",
		);
		foreach ( $add as $col => $sql_alter ) {
			if ( ! in_array( $col, $cols, true ) ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$wpdb->query( $sql_alter );
			}
		}
	}

	/**
	 * Log a security / monitoring event (master only).
	 *
	 * @param string $type     Event type slug.
	 * @param string $message  Human-readable message.
	 * @param array  $context  Extra context.
	 * @param string $severity info|warning|critical.
	 */
	public static function log_event( $type, $message, $context = array(), $severity = 'warning' ) {
		if ( ! class_exists( 'PCKZ_Licensing' ) || ! PCKZ_Licensing::is_master_mode() ) {
			return;
		}
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_EVENTS;
		$wpdb->insert(
			$table,
			array(
				'license_id'      => absint( $context['license_id'] ?? 0 ),
				'installation_id' => absint( $context['installation_id'] ?? 0 ),
				'domain'          => sanitize_text_field( (string) ( $context['domain'] ?? '' ) ),
				'install_uuid'    => sanitize_text_field( (string) ( $context['install_uuid'] ?? '' ) ),
				'event_type'      => sanitize_key( $type ),
				'severity'        => in_array( $severity, array( 'info', 'warning', 'critical' ), true ) ? $severity : 'warning',
				'message'         => sanitize_text_field( $message ),
				'context'         => wp_json_encode( is_array( $context ) ? $context : array() ),
				'created_at'      => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Recent security events for dashboard.
	 *
	 * @param int $limit Max rows.
	 * @return array
	 */
	public static function recent_events( $limit = 40 ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_EVENTS;
		$limit = max( 1, min( 200, absint( $limit ) ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY id DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		) ?: array();
	}

	/**
	 * Whether installation is considered online.
	 *
	 * @param array $install Installation row.
	 * @return bool
	 */
	public static function is_online( $install ) {
		$raw = $install['last_check_in'] ?? '';
		if ( '' === trim( (string) $raw ) ) {
			return false;
		}
		$ts = strtotime( $raw );
		return $ts && ( time() - $ts ) <= self::ONLINE_THRESHOLD_SEC;
	}

	/**
	 * Health status: success|warning|danger|muted.
	 *
	 * @param array $install Installation row.
	 * @param array $license License row or null.
	 * @param array $release_meta Published release meta.
	 * @return string
	 */
	public static function health_status( $install, $license, $release_meta ) {
		if ( empty( $install['plugin_active'] ) || '1' !== (string) $install['plugin_active'] ) {
			return 'danger';
		}
		if ( $license && 'active' !== (string) ( $license['status'] ?? '' ) ) {
			return 'danger';
		}
		if ( 'blocked' === (string) ( $install['status'] ?? '' ) ) {
			return 'danger';
		}
		if ( ! self::is_online( $install ) ) {
			return 'warning';
		}
		$alerts = self::installation_alerts( $install, $license, $release_meta );
		foreach ( $alerts as $alert ) {
			if ( 'critical' === ( $alert['severity'] ?? '' ) ) {
				return 'danger';
			}
		}
		foreach ( $alerts as $alert ) {
			if ( 'warning' === ( $alert['severity'] ?? '' ) ) {
				return 'warning';
			}
		}
		return 'success';
	}

	/**
	 * Alerts for one installation row.
	 *
	 * @param array      $install      Installation.
	 * @param array|null $license      License.
	 * @param array      $release_meta Release meta.
	 * @return array<int,array{severity:string,code:string,message:string}>
	 */
	public static function installation_alerts( $install, $license, $release_meta ) {
		$alerts = array();
		if ( empty( $install['plugin_active'] ) || '1' !== (string) $install['plugin_active'] ) {
			$alerts[] = array(
				'severity' => 'critical',
				'code'     => 'plugin_inactive',
				'message'  => __( 'Plugin deactivated on client site.', 'pckz-canonical-engine' ),
			);
		}
		if ( $license && 'active' !== (string) ( $license['status'] ?? '' ) ) {
			$alerts[] = array(
				'severity' => 'critical',
				'code'     => 'license_' . sanitize_key( (string) $license['status'] ),
				'message'  => sprintf(
					/* translators: %s: license status */
					__( 'License status: %s', 'pckz-canonical-engine' ),
					(string) $license['status']
				),
			);
		}
		if ( ! empty( $install['last_error'] ) ) {
			$alerts[] = array(
				'severity' => 'warning',
				'code'     => 'last_error',
				'message'  => (string) $install['last_error'],
			);
		}
		$tamper = json_decode( (string) ( $install['tamper_signals'] ?? '[]' ), true );
		if ( is_array( $tamper ) && ! empty( $tamper ) ) {
			$tamper = array_values( array_filter( array_map( 'sanitize_key', $tamper ) ) );
			$label  = implode( ', ', array_slice( $tamper, 0, 4 ) );
			if ( count( $tamper ) > 4 ) {
				$label .= ', +' . ( count( $tamper ) - 4 );
			}
			$alerts[] = array(
				'severity' => 'warning',
				'code'     => 'tamper_signals',
				'message'  => sprintf(
					/* translators: %s: comma-separated tamper signal slugs */
					__( 'Tamper/integrity signals: %s', 'pckz-canonical-engine' ),
					$label
				),
			);
		}
		if ( ! self::is_online( $install ) ) {
			$alerts[] = array(
				'severity' => 'warning',
				'code'     => 'offline',
				'message'  => __( 'No recent heartbeat (offline).', 'pckz-canonical-engine' ),
			);
		}
		$check_ts = strtotime( (string) ( $install['last_check_in'] ?? '' ) );
		if ( $check_ts && ( time() - $check_ts ) > self::STALE_CHECKIN_SEC ) {
			$alerts[] = array(
				'severity' => 'critical',
				'code'     => 'stale_checkin',
				'message'  => __( 'No synchronization for an extended period.', 'pckz-canonical-engine' ),
			);
		}
		$sync_ts = strtotime( (string) ( $install['last_asset_sync'] ?? '' ) );
		if ( ! $sync_ts && $check_ts && ( time() - $check_ts ) > self::STALE_SYNC_SEC ) {
			$alerts[] = array(
				'severity' => 'warning',
				'code'     => 'asset_sync_never',
				'message'  => __( 'Asset sync has not completed yet.', 'pckz-canonical-engine' ),
			);
		} elseif ( $sync_ts && ( time() - $sync_ts ) > self::STALE_SYNC_SEC ) {
			$alerts[] = array(
				'severity' => 'warning',
				'code'     => 'asset_sync_stale',
				'message'  => __( 'Asset sync is outdated.', 'pckz-canonical-engine' ),
			);
		}
		$latest = sanitize_text_field( (string) ( $release_meta['version'] ?? '' ) );
		$installed = sanitize_text_field( (string) ( $install['plugin_version'] ?? '' ) );
		if ( $latest && $installed && version_compare( $latest, $installed, '>' ) ) {
			$severity = ( ! empty( $release_meta['update_severity'] ) && 'critical' === $release_meta['update_severity'] ) ? 'critical' : 'info';
			$alerts[] = array(
				'severity' => $severity,
				'code'     => 'update_available',
				'message'  => sprintf(
					/* translators: 1: installed, 2: latest */
					__( 'Update available: %1$s → %2$s', 'pckz-canonical-engine' ),
					$installed,
					$latest
				),
			);
		}
		return $alerts;
	}

	/**
	 * Enrich installation rows for fleet dashboard.
	 *
	 * @param array $installs     Raw DB rows.
	 * @param array $license_map  license_id => license row.
	 * @param array $release_meta Release meta.
	 * @return array
	 */
	public static function enrich_fleet_rows( $installs, $license_map, $release_meta ) {
		$rows = array();
		foreach ( $installs as $install ) {
			$license_id = (int) ( $install['license_id'] ?? 0 );
			$license    = $license_map[ $license_id ] ?? null;
			$alerts     = self::installation_alerts( $install, $license, $release_meta );
			$rows[]     = array(
				'install'        => $install,
				'license'        => $license,
				'online'         => self::is_online( $install ),
				'health'         => self::health_status( $install, $license, $release_meta ),
				'alerts'         => $alerts,
				'alert_count'    => count( $alerts ),
				'update_status'  => sanitize_key( (string) ( $install['update_status'] ?? '' ) ),
			);
		}
		return $rows;
	}

	/**
	 * Aggregate fleet statistics.
	 *
	 * @param array $fleet_rows Enriched rows.
	 * @param array $stats      Existing stats from licensing page.
	 * @return array
	 */
	public static function fleet_stats( $fleet_rows, $stats ) {
		$online = 0;
		$offline = 0;
		$warnings = 0;
		$critical = 0;
		$updates = 0;
		$inactive_plugin = 0;
		foreach ( $fleet_rows as $row ) {
			if ( ! empty( $row['online'] ) ) {
				++$online;
			} else {
				++$offline;
			}
			if ( empty( $row['install']['plugin_active'] ) || '1' !== (string) $row['install']['plugin_active'] ) {
				++$inactive_plugin;
			}
			if ( 'update_available' === ( $row['update_status'] ?? '' ) ) {
				++$updates;
			}
			foreach ( $row['alerts'] as $alert ) {
				if ( 'critical' === ( $alert['severity'] ?? '' ) ) {
					++$critical;
				} elseif ( 'warning' === ( $alert['severity'] ?? '' ) ) {
					++$warnings;
				}
			}
		}
		$stats['fleet_online']          = $online;
		$stats['fleet_offline']         = $offline;
		$stats['fleet_warnings']        = $warnings;
		$stats['fleet_critical_alerts'] = $critical;
		$stats['fleet_updates_pending'] = $updates;
		$stats['fleet_plugin_inactive'] = $inactive_plugin;
		return $stats;
	}

	/**
	 * Run master-side checks after check-in (duplicate UUID, etc.).
	 *
	 * @param array $validated Validated payload from licensing.
	 * @param array $payload   Raw client payload.
	 */
	public static function after_client_check_in( $validated, $payload ) {
		if ( ! PCKZ_Licensing::is_master_mode() ) {
			return;
		}
		global $wpdb;
		$install     = $validated['install'] ?? array();
		$license     = $validated['license'] ?? array();
		$install_id  = (int) ( $install['id'] ?? 0 );
		$uuid        = sanitize_text_field( (string) ( $validated['install_uuid'] ?? '' ) );
		$domain      = sanitize_text_field( (string) ( $validated['domain'] ?? '' ) );

		if ( empty( $payload['plugin_active'] ) && ! empty( $install['plugin_active'] ) ) {
			self::log_event(
				'plugin_deactivated',
				__( 'Client reported plugin deactivation.', 'pckz-canonical-engine' ),
				array(
					'license_id'      => (int) ( $license['id'] ?? 0 ),
					'installation_id' => $install_id,
					'domain'          => $domain,
					'install_uuid'    => $uuid,
				),
				'critical'
			);
		}

		$dupes = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT domain) FROM {$wpdb->prefix}pckz_license_installations WHERE install_uuid = %s AND status = %s",
				$uuid,
				'active'
			)
		);
		if ( (int) $dupes > 1 ) {
			self::log_event(
				'duplicate_uuid_domains',
				__( 'Same installation UUID active on multiple domains.', 'pckz-canonical-engine' ),
				array(
					'install_uuid' => $uuid,
					'domain'       => $domain,
					'count'        => (int) $dupes,
				),
				'critical'
			);
		}
	}

	/**
	 * Log failed check-in.
	 *
	 * @param array  $payload Client payload.
	 * @param string $reason  Error reason.
	 */
	public static function log_check_in_denied( $payload, $reason ) {
		self::log_event(
			'check_in_denied',
			$reason,
			array(
				'domain'       => sanitize_text_field( (string) ( $payload['domain'] ?? '' ) ),
				'install_uuid' => sanitize_text_field( (string) ( $payload['install_uuid'] ?? '' ) ),
			),
			'warning'
		);
	}

	/**
	 * Compute update_status string for DB storage.
	 *
	 * @param string $installed Installed version.
	 * @param array  $release_meta Release meta.
	 * @return string
	 */
	public static function compute_update_status( $installed, $release_meta ) {
		$latest = sanitize_text_field( (string) ( $release_meta['version'] ?? '' ) );
		if ( ! $latest || ! $installed ) {
			return '';
		}
		if ( version_compare( $latest, $installed, '>' ) ) {
			return 'update_available';
		}
		return 'up_to_date';
	}

	/**
	 * Register hooks.
	 */
	public static function register_hooks() {
		add_action( 'pckzce_asset_catalog_changed', array( __CLASS__, 'on_asset_catalog_changed' ) );
	}

	/**
	 * Bump asset manifest when catalog changes on master.
	 */
	public static function on_asset_catalog_changed() {
		if ( class_exists( 'PCKZ_Asset_Sync' ) ) {
			PCKZ_Asset_Sync::bump_manifest_revision();
		}
	}
}
