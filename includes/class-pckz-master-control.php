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
		$type = sanitize_key( (string) $type );
		if ( 'rate_limit_exceeded' === $type ) {
			$ip    = sanitize_text_field( (string) ( $context['ip'] ?? '' ) );
			$scope = sanitize_key( (string) ( $context['scope'] ?? '' ) );
			$dedupe = 'pckzce_rl_log_' . md5( $ip . '|' . $scope );
			if ( get_transient( $dedupe ) ) {
				return;
			}
			set_transient( $dedupe, 1, 5 * MINUTE_IN_SECONDS );
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
		$limit = max( 1, min( 500, absint( $limit ) ) );
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
	 * Group repeated security events for a compact dashboard.
	 *
	 * @param array $events Raw event rows.
	 * @return array
	 */
	public static function group_security_events( $events ) {
		if ( ! is_array( $events ) ) {
			return array();
		}
		$groups = array();
		foreach ( $events as $event ) {
			if ( ! is_array( $event ) ) {
				continue;
			}
			$type    = sanitize_key( (string) ( $event['event_type'] ?? 'unknown' ) );
			$context = json_decode( (string) ( $event['context'] ?? '{}' ), true );
			$context = is_array( $context ) ? $context : array();
			$key     = $type;
			if ( 'rate_limit_exceeded' === $type ) {
				$key = $type . '|' . sanitize_key( (string) ( $context['scope'] ?? 'unknown' ) );
			} elseif ( ! empty( $event['domain'] ) ) {
				$key = $type . '|' . sanitize_text_field( (string) $event['domain'] );
			}
			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array(
					'event_type' => $type,
					'severity'   => sanitize_key( (string) ( $event['severity'] ?? 'warning' ) ),
					'message'    => sanitize_text_field( (string) ( $event['message'] ?? '' ) ),
					'domain'     => sanitize_text_field( (string) ( $event['domain'] ?? '' ) ),
					'context'    => $context,
					'count'      => 0,
					'latest_at'  => (string) ( $event['created_at'] ?? '' ),
					'oldest_at'  => (string) ( $event['created_at'] ?? '' ),
					'samples'    => array(),
				);
			}
			$groups[ $key ]['count']++;
			$created = (string) ( $event['created_at'] ?? '' );
			if ( $created > $groups[ $key ]['latest_at'] ) {
				$groups[ $key ]['latest_at'] = $created;
			}
			if ( '' === $groups[ $key ]['oldest_at'] || $created < $groups[ $key ]['oldest_at'] ) {
				$groups[ $key ]['oldest_at'] = $created;
			}
			if ( count( $groups[ $key ]['samples'] ) < 3 ) {
				$groups[ $key ]['samples'][] = $event;
			}
		}
		$out = array_values( $groups );
		usort(
			$out,
			static function ( $a, $b ) {
				return strcmp( (string) ( $b['latest_at'] ?? '' ), (string) ( $a['latest_at'] ?? '' ) );
			}
		);
		return $out;
	}

	/**
	 * Delete security events (all or selected types).
	 *
	 * @param array $types Optional event_type slugs; empty clears all rows.
	 */
	public static function clear_security_events( $types = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_EVENTS;
		if ( empty( $types ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "DELETE FROM {$table}" );
			return;
		}
		$placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$types        = array_map( 'sanitize_key', $types );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE event_type IN ({$placeholders})", ...$types ) );
	}

	/**
	 * Human-readable event type label for dashboard views.
	 *
	 * @param string $event_type Event type slug.
	 * @return string
	 */
	public static function event_type_label( $event_type ) {
		$event_type = sanitize_key( (string) $event_type );
		$labels = array(
			'tamper_signal_reported'       => __( 'Tamper signal reported', 'pckz-canonical-engine' ),
			'integrity_mismatch'           => __( 'Integrity mismatch', 'pckz-canonical-engine' ),
			'tamper_signals_acknowledged'  => __( 'Tamper signals acknowledged', 'pckz-canonical-engine' ),
			'download_package_validation_failed' => __( 'Protected package validation failed', 'pckz-canonical-engine' ),
			'client_update_failed'         => __( 'Client update failed', 'pckz-canonical-engine' ),
			'client_update_success'        => __( 'Client update success', 'pckz-canonical-engine' ),
			'check_in_denied'              => __( 'Check-in denied', 'pckz-canonical-engine' ),
			'rate_limit_exceeded'          => __( 'Rate limit exceeded', 'pckz-canonical-engine' ),
			'master_ip_denied'             => __( 'Master admin IP denied', 'pckz-canonical-engine' ),
			'plugin_deactivated'         => __( 'Plugin deactivated on client', 'pckz-canonical-engine' ),
		);
		if ( isset( $labels[ $event_type ] ) ) {
			return $labels[ $event_type ];
		}
		return ucwords( str_replace( '_', ' ', $event_type ) );
	}

	/**
	 * Canonical tamper signal catalog used by Master Control UI.
	 *
	 * @return array<string,array{title:string,why:string,detected:string,update_impact:string}>
	 */
	public static function tamper_signal_catalog() {
		return array(
			'mu_plugins_present' => array(
				'title'         => __( 'Must-use plugins detected', 'pckz-canonical-engine' ),
				'why'           => __( 'WordPress reports active MU plugins, which can inject runtime behavior before normal plugins load.', 'pckz-canonical-engine' ),
				'detected'      => __( 'Triggered when wp-content/mu-plugins exists.', 'pckz-canonical-engine' ),
				'update_impact' => __( 'Informational only by default; does not block updates unless custom hardening rules enforce it.', 'pckz-canonical-engine' ),
			),
			'wp_debug_enabled' => array(
				'title'         => __( 'WP_DEBUG enabled', 'pckz-canonical-engine' ),
				'why'           => __( 'Debug mode can expose stack traces and diagnostics in production.', 'pckz-canonical-engine' ),
				'detected'      => __( 'Triggered when WP_DEBUG is true.', 'pckz-canonical-engine' ),
				'update_impact' => __( 'Informational only; does not block protected updates by itself.', 'pckz-canonical-engine' ),
			),
			'hash_hmac_missing' => array(
				'title'         => __( 'HMAC functions unavailable', 'pckz-canonical-engine' ),
				'why'           => __( 'Secure request signing relies on hash_hmac support.', 'pckz-canonical-engine' ),
				'detected'      => __( 'Triggered when hash_hmac() is unavailable in PHP.', 'pckz-canonical-engine' ),
				'update_impact' => __( 'Can become blocking when signed request enforcement is enabled.', 'pckz-canonical-engine' ),
			),
			'plugin_dir_writable' => array(
				'title'         => __( 'Plugin directory writable', 'pckz-canonical-engine' ),
				'why'           => __( 'Writable plugin paths increase tamper surface for unexpected file changes.', 'pckz-canonical-engine' ),
				'detected'      => __( 'Triggered when plugin base directory is writable.', 'pckz-canonical-engine' ),
				'update_impact' => __( 'Informational in normal operation; not a direct update blocker.', 'pckz-canonical-engine' ),
			),
			'plugin_main_missing' => array(
				'title'         => __( 'Plugin main file missing', 'pckz-canonical-engine' ),
				'why'           => __( 'Core plugin bootstrap file could not be read.', 'pckz-canonical-engine' ),
				'detected'      => __( 'Triggered when pckz-canonical-engine.php is not readable.', 'pckz-canonical-engine' ),
				'update_impact' => __( 'High risk and likely blocking for stable updates/activation.', 'pckz-canonical-engine' ),
			),
			'critical_runtime_file_missing' => array(
				'title'         => __( 'Critical runtime file missing', 'pckz-canonical-engine' ),
				'why'           => __( 'At least one critical runtime file listed in integrity targets is not readable.', 'pckz-canonical-engine' ),
				'detected'      => __( 'Triggered by integrity target scan mismatch.', 'pckz-canonical-engine' ),
				'update_impact' => __( 'Can become blocking under strict integrity policies.', 'pckz-canonical-engine' ),
			),
			'release_manifest_unavailable' => array(
				'title'         => __( 'Release manifest unavailable', 'pckz-canonical-engine' ),
				'why'           => __( 'No release manifest was found in the installed package.', 'pckz-canonical-engine' ),
				'detected'      => __( 'Triggered when RELEASE_MANIFEST.json is missing from plugin root.', 'pckz-canonical-engine' ),
				'update_impact' => __( 'Informational unless manifest-required policy is enforced for this installation.', 'pckz-canonical-engine' ),
			),
			'release_manifest_mismatch' => array(
				'title'         => __( 'Release manifest mismatch', 'pckz-canonical-engine' ),
				'why'           => __( 'One or more runtime files differ from release manifest checksums.', 'pckz-canonical-engine' ),
				'detected'      => __( 'Triggered when integrity hash comparison against manifest fails.', 'pckz-canonical-engine' ),
				'update_impact' => __( 'Can become blocking when strict integrity policy is enabled.', 'pckz-canonical-engine' ),
			),
			'release_manifest_invalid' => array(
				'title'         => __( 'Release manifest invalid', 'pckz-canonical-engine' ),
				'why'           => __( 'Manifest exists but is malformed or incomplete.', 'pckz-canonical-engine' ),
				'detected'      => __( 'Triggered when RELEASE_MANIFEST.json cannot be parsed or validated.', 'pckz-canonical-engine' ),
				'update_impact' => __( 'Can delay trust validation; may be blocking if manifest validation is required.', 'pckz-canonical-engine' ),
			),
		);
	}

	/**
	 * Resolve UI detail fields for a tamper signal code.
	 *
	 * @param string $signal Signal code.
	 * @return array{code:string,title:string,why:string,detected:string,update_impact:string}
	 */
	public static function tamper_signal_detail( $signal ) {
		$signal = sanitize_key( (string) $signal );
		$catalog = self::tamper_signal_catalog();
		$detail = $catalog[ $signal ] ?? array(
			'title'         => __( 'Custom tamper signal', 'pckz-canonical-engine' ),
			'why'           => __( 'The client reported a custom integrity/tamper indicator.', 'pckz-canonical-engine' ),
			'detected'      => __( 'Triggered by client-side security telemetry.', 'pckz-canonical-engine' ),
			'update_impact' => __( 'Informational unless strict integrity/signature policies classify it as blocking.', 'pckz-canonical-engine' ),
		);
		$detail['code'] = $signal;
		return $detail;
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
			$labels = array();
			foreach ( array_slice( $tamper, 0, 3 ) as $signal ) {
				$labels[] = self::tamper_signal_detail( $signal )['title'];
			}
			$label = implode( ', ', $labels );
			if ( count( $tamper ) > 3 ) {
				$label .= ', +' . ( count( $tamper ) - 3 );
			}
			$alerts[] = array(
				'severity' => 'warning',
				'code'     => 'tamper_signals',
				'message'  => sprintf(
					/* translators: %s: comma-separated tamper signal labels */
					__( 'Tamper/integrity signals detected: %s', 'pckz-canonical-engine' ),
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
