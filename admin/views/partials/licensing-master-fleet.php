<?php
/**
 * Master Control — fleet overview, security alerts, installation monitor.
 *
 * @package PCKZCanonicalEngine
 *
 * @var array $fleet_rows
 * @var array $stats
 * @var array $security_events
 * @var array $security_event_groups
 * @var array $release_meta
 */

defined( 'ABSPATH' ) || exit;

$fleet_sort    = isset( $_GET['pckz_fleet_sort'] ) ? sanitize_key( wp_unslash( $_GET['pckz_fleet_sort'] ) ) : 'health';
$fleet_filter  = isset( $_GET['pckz_fleet_filter'] ) ? sanitize_key( wp_unslash( $_GET['pckz_fleet_filter'] ) ) : '';
$fleet_rows    = is_array( $fleet_rows ?? null ) ? $fleet_rows : array();
$security_events       = is_array( $security_events ?? null ) ? $security_events : array();
$security_event_groups = is_array( $security_event_groups ?? null ) ? $security_event_groups : array();
if ( empty( $security_event_groups ) && ! empty( $security_events ) && class_exists( 'PCKZ_Master_Control' ) ) {
	$security_event_groups = PCKZ_Master_Control::group_security_events( $security_events );
}
$alert_recent_groups = array();
$alert_older_groups  = array();
$recent_cutoff       = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );
foreach ( $security_event_groups as $group ) {
	if ( ! empty( $group['latest_at'] ) && $group['latest_at'] >= $recent_cutoff ) {
		$alert_recent_groups[] = $group;
	} else {
		$alert_older_groups[] = $group;
	}
}
$release_meta    = is_array( $release_meta ?? null ) ? $release_meta : array();
$stats           = is_array( $stats ?? null ) ? $stats : array();

/*
 * Defensive fallbacks. Closures should be supplied by the parent
 * licensing-dashboard.php view, but if any partial is rendered standalone
 * (or a future refactor reorders includes), avoid a fatal blank page.
 */
if ( ! isset( $format_datetime ) || ! is_callable( $format_datetime ) ) {
	$format_datetime = static function ( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return '—';
		}
		$ts = strtotime( $raw );
		return $ts ? gmdate( 'Y-m-d H:i:s', $ts ) . ' UTC' : $raw;
	};
}
if ( ! isset( $badge_class ) || ! is_callable( $badge_class ) ) {
	$badge_class = static function ( $status ) {
		return 'is-muted';
	};
}
if ( ! isset( $status_label ) || ! is_callable( $status_label ) ) {
	$status_label = static function ( $status ) {
		return ucwords( str_replace( '_', ' ', sanitize_key( (string) $status ) ) );
	};
}

$health_label = static function ( $health ) {
	$map = array(
		'success' => __( 'Healthy', 'pckz-canonical-engine' ),
		'warning' => __( 'Attention', 'pckz-canonical-engine' ),
		'danger'  => __( 'Critical', 'pckz-canonical-engine' ),
		'muted'   => __( 'Unknown', 'pckz-canonical-engine' ),
	);
	return $map[ $health ] ?? $health;
};

$filtered = array();
foreach ( $fleet_rows as $row ) {
	if ( 'offline' === $fleet_filter && ! empty( $row['online'] ) ) {
		continue;
	}
	if ( 'warnings' === $fleet_filter && (int) ( $row['alert_count'] ?? 0 ) < 1 ) {
		continue;
	}
	if ( 'updates' === $fleet_filter && 'update_available' !== ( $row['update_status'] ?? '' ) ) {
		continue;
	}
	if ( 'inactive' === $fleet_filter && ! empty( $row['install']['plugin_active'] ) ) {
		continue;
	}
	$filtered[] = $row;
}

usort(
	$filtered,
	static function ( $a, $b ) use ( $fleet_sort ) {
		switch ( $fleet_sort ) {
			case 'domain':
				return strcmp( (string) ( $a['install']['domain'] ?? '' ), (string) ( $b['install']['domain'] ?? '' ) );
			case 'version':
				return version_compare( (string) ( $b['install']['plugin_version'] ?? '' ), (string) ( $a['install']['plugin_version'] ?? '' ) );
			case 'checkin':
				return strcmp( (string) ( $b['install']['last_check_in'] ?? '' ), (string) ( $a['install']['last_check_in'] ?? '' ) );
			case 'health':
			default:
				$order = array( 'danger' => 0, 'warning' => 1, 'success' => 2, 'muted' => 3 );
				$ha    = $order[ $a['health'] ?? 'muted' ] ?? 9;
				$hb    = $order[ $b['health'] ?? 'muted' ] ?? 9;
				return $ha <=> $hb;
		}
	}
);

$fleet_base_url = admin_url( 'admin.php?page=pckz-license-server' );
?>

<section class="pckz-mc-section pckz-fleet-dashboard" id="pckz-fleet-dashboard">
	<header class="pckz-mc-section__header">
		<div>
			<h2><?php esc_html_e( 'Customer fleet', 'pckz-canonical-engine' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Live health of licensed customer sites: online status, versions, updates, and security signals.', 'pckz-canonical-engine' ); ?></p>
		</div>
		<a class="button button-secondary" href="#pckz-master-section-overview"><?php esc_html_e( 'View dashboard', 'pckz-canonical-engine' ); ?></a>
	</header>

	<?php if ( empty( $fleet_rows ) ) : ?>
		<div class="notice notice-info inline pckz-fleet-empty">
			<p><strong><?php esc_html_e( 'No customer installations have checked in yet.', 'pckz-canonical-engine' ); ?></strong></p>
			<p><?php esc_html_e( 'When a licensed client site authenticates with this master, it appears here automatically.', 'pckz-canonical-engine' ); ?></p>
			<ol class="pckz-fleet-empty__steps">
				<li><a href="#pckz-master-section-licenses"><?php esc_html_e( 'Create a license', 'pckz-canonical-engine' ); ?></a></li>
				<li><a href="#pckz-master-section-licenses"><?php esc_html_e( 'Generate a client package and deliver the ZIP', 'pckz-canonical-engine' ); ?></a></li>
				<li><?php esc_html_e( 'The client installs the package — check-in starts automatically', 'pckz-canonical-engine' ); ?></li>
			</ol>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $security_event_groups ) ) : ?>
		<div class="pckz-fleet-alerts" id="pckz-fleet-alerts">
			<div class="pckz-fleet-alerts__header">
				<h3><?php esc_html_e( 'Security & monitoring alerts', 'pckz-canonical-engine' ); ?></h3>
				<div class="pckz-fleet-alerts__actions">
					<a class="button" href="<?php echo esc_url( $fleet_base_url ); ?>"><?php esc_html_e( 'Refresh', 'pckz-canonical-engine' ); ?></a>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pckz-fleet-alerts__clear-form">
						<?php wp_nonce_field( 'pckzce_clear_security_events', 'pckzce_clear_events_nonce' ); ?>
						<input type="hidden" name="action" value="pckzce_clear_security_events">
						<input type="hidden" name="redirect_section" value="fleet">
						<input type="hidden" name="clear_mode" value="resolved">
						<button type="submit" class="button"><?php esc_html_e( 'Clear resolved alerts', 'pckz-canonical-engine' ); ?></button>
					</form>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pckz-fleet-alerts__clear-form" data-pckz-confirm="<?php esc_attr_e( 'Delete all stored security alerts?', 'pckz-canonical-engine' ); ?>">
						<?php wp_nonce_field( 'pckzce_clear_security_events', 'pckzce_clear_events_nonce' ); ?>
						<input type="hidden" name="action" value="pckzce_clear_security_events">
						<input type="hidden" name="redirect_section" value="fleet">
						<input type="hidden" name="clear_mode" value="all">
						<button type="submit" class="button button-link-delete"><?php esc_html_e( 'Clear all alerts', 'pckz-canonical-engine' ); ?></button>
					</form>
				</div>
			</div>
			<?php
			$render_alert_groups = static function ( $groups ) use ( $format_datetime ) {
				if ( empty( $groups ) ) {
					return;
				}
				echo '<ul class="pckz-fleet-alert-list">';
				foreach ( $groups as $group ) {
					$sev             = sanitize_key( (string) ( $group['severity'] ?? 'warning' ) );
					$event_type_raw  = sanitize_key( (string) ( $group['event_type'] ?? '' ) );
					$event_type_text = class_exists( 'PCKZ_Master_Control' )
						? PCKZ_Master_Control::event_type_label( $event_type_raw )
						: $event_type_raw;
					$context         = is_array( $group['context'] ?? null ) ? $group['context'] : array();
					$count           = max( 1, (int) ( $group['count'] ?? 1 ) );
					$is_release_alert = in_array(
						$event_type_raw,
						array( 'download_package_validation_failed', 'release_package_validation_failed' ),
						true
					);
					?>
					<li class="pckz-fleet-alert pckz-fleet-alert--<?php echo esc_attr( $sev ); ?>">
						<strong><?php echo esc_html( $group['message'] ?? '' ); ?></strong>
						<?php if ( $count > 1 ) : ?>
							<span class="pckz-fleet-alert__count"><?php echo esc_html( sprintf( __( '×%d', 'pckz-canonical-engine' ), $count ) ); ?></span>
						<?php endif; ?>
						<?php if ( $is_release_alert ) : ?>
							<ul class="pckz-fleet-alert__meta-list">
								<?php if ( ! empty( $context['archive_filename'] ) || ! empty( $context['zip_filename'] ) ) : ?>
									<li><strong><?php esc_html_e( 'ZIP filename:', 'pckz-canonical-engine' ); ?></strong> <?php echo esc_html( (string) ( $context['archive_filename'] ?? $context['zip_filename'] ?? '' ) ); ?></li>
								<?php endif; ?>
								<?php if ( ! empty( $context['version'] ) ) : ?>
									<li><strong><?php esc_html_e( 'Version:', 'pckz-canonical-engine' ); ?></strong> <?php echo esc_html( (string) $context['version'] ); ?></li>
								<?php endif; ?>
								<li><strong><?php esc_html_e( 'Detected:', 'pckz-canonical-engine' ); ?></strong> <?php echo esc_html( $format_datetime( $group['latest_at'] ?? ( $context['detected_at'] ?? '' ) ) ); ?></li>
								<?php if ( ! empty( $context['validation_rule'] ) ) : ?>
									<li><strong><?php esc_html_e( 'Rule:', 'pckz-canonical-engine' ); ?></strong> <code><?php echo esc_html( (string) $context['validation_rule'] ); ?></code></li>
								<?php endif; ?>
								<?php if ( ! empty( $context['recommended_action'] ) ) : ?>
									<li><strong><?php esc_html_e( 'Recommended action:', 'pckz-canonical-engine' ); ?></strong> <?php echo esc_html( (string) $context['recommended_action'] ); ?></li>
								<?php endif; ?>
							</ul>
						<?php else : ?>
						<span class="description">
							<?php echo esc_html( $event_type_text ); ?>
							<?php if ( ! empty( $group['domain'] ) ) : ?>
								· <?php echo esc_html( $group['domain'] ); ?>
							<?php elseif ( 'rate_limit_exceeded' === $event_type_raw && ! empty( $context['scope'] ) ) : ?>
								· <?php echo esc_html( sprintf( __( 'Endpoint: %s', 'pckz-canonical-engine' ), $context['scope'] ) ); ?>
							<?php endif; ?>
							· <?php echo esc_html( $format_datetime( $group['latest_at'] ?? '' ) ); ?>
						</span>
						<?php endif; ?>
					</li>
					<?php
				}
				echo '</ul>';
			};
			?>
			<?php if ( ! empty( $alert_recent_groups ) ) : ?>
				<div class="pckz-fleet-alerts__recent">
					<h4><?php esc_html_e( 'Recent (24 hours)', 'pckz-canonical-engine' ); ?></h4>
					<?php $render_alert_groups( $alert_recent_groups ); ?>
				</div>
			<?php endif; ?>
			<?php if ( ! empty( $alert_older_groups ) ) : ?>
				<details class="pckz-fleet-alerts__older">
					<summary>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: number of alert groups */
								__( 'Older alerts (%d groups)', 'pckz-canonical-engine' ),
								count( $alert_older_groups )
							)
						);
						?>
					</summary>
					<?php $render_alert_groups( $alert_older_groups ); ?>
				</details>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<form method="get" class="pckz-fleet-toolbar">
		<input type="hidden" name="page" value="pckz-license-server">
		<label>
			<span class="screen-reader-text"><?php esc_html_e( 'Filter', 'pckz-canonical-engine' ); ?></span>
			<select name="pckz_fleet_filter">
				<option value=""><?php esc_html_e( 'All installations', 'pckz-canonical-engine' ); ?></option>
				<option value="offline" <?php selected( $fleet_filter, 'offline' ); ?>><?php esc_html_e( 'Offline only', 'pckz-canonical-engine' ); ?></option>
				<option value="warnings" <?php selected( $fleet_filter, 'warnings' ); ?>><?php esc_html_e( 'With warnings', 'pckz-canonical-engine' ); ?></option>
				<option value="updates" <?php selected( $fleet_filter, 'updates' ); ?>><?php esc_html_e( 'Update available', 'pckz-canonical-engine' ); ?></option>
				<option value="inactive" <?php selected( $fleet_filter, 'inactive' ); ?>><?php esc_html_e( 'Plugin inactive', 'pckz-canonical-engine' ); ?></option>
			</select>
		</label>
		<label>
			<span class="screen-reader-text"><?php esc_html_e( 'Sort', 'pckz-canonical-engine' ); ?></span>
			<select name="pckz_fleet_sort">
				<option value="health" <?php selected( $fleet_sort, 'health' ); ?>><?php esc_html_e( 'Sort: health', 'pckz-canonical-engine' ); ?></option>
				<option value="checkin" <?php selected( $fleet_sort, 'checkin' ); ?>><?php esc_html_e( 'Sort: last check-in', 'pckz-canonical-engine' ); ?></option>
				<option value="domain" <?php selected( $fleet_sort, 'domain' ); ?>><?php esc_html_e( 'Sort: domain', 'pckz-canonical-engine' ); ?></option>
				<option value="version" <?php selected( $fleet_sort, 'version' ); ?>><?php esc_html_e( 'Sort: version', 'pckz-canonical-engine' ); ?></option>
			</select>
		</label>
		<button type="submit" class="button"><?php esc_html_e( 'Apply', 'pckz-canonical-engine' ); ?></button>
		<?php if ( $fleet_filter || 'health' !== $fleet_sort ) : ?>
			<a class="button button-link" href="<?php echo esc_url( $fleet_base_url ); ?>"><?php esc_html_e( 'Reset', 'pckz-canonical-engine' ); ?></a>
		<?php endif; ?>
	</form>

	<div class="pckz-mc-table-wrap pckz-fleet-table-wrap">
		<table class="widefat striped pckz-mc-table pckz-fleet-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Health', 'pckz-canonical-engine' ); ?></th>
					<th><?php esc_html_e( 'Domain', 'pckz-canonical-engine' ); ?></th>
					<th><?php esc_html_e( 'License', 'pckz-canonical-engine' ); ?></th>
					<th><?php esc_html_e( 'Version', 'pckz-canonical-engine' ); ?></th>
					<th><?php esc_html_e( 'Online', 'pckz-canonical-engine' ); ?></th>
					<th><?php esc_html_e( 'Last sync', 'pckz-canonical-engine' ); ?></th>
					<th><?php esc_html_e( 'Last activity', 'pckz-canonical-engine' ); ?></th>
					<th><?php esc_html_e( 'Update', 'pckz-canonical-engine' ); ?></th>
					<th><?php esc_html_e( 'Warnings', 'pckz-canonical-engine' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $filtered ) ) : ?>
					<tr><td colspan="9"><?php esc_html_e( 'No installations match this filter.', 'pckz-canonical-engine' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $filtered as $row ) : ?>
						<?php
						$install = $row['install'] ?? array();
						$license = $row['license'] ?? array();
						$health  = $row['health'] ?? 'muted';
						?>
						<tr class="pckz-fleet-row pckz-fleet-row--<?php echo esc_attr( $health ); ?>">
							<td>
								<span class="pckz-health-dot pckz-health-dot--<?php echo esc_attr( $health ); ?>" title="<?php echo esc_attr( $health_label( $health ) ); ?>"></span>
								<?php echo esc_html( $health_label( $health ) ); ?>
							</td>
							<td data-label="<?php esc_attr_e( 'Domain', 'pckz-canonical-engine' ); ?>">
								<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'pckz-license-server', 'pckz_install_s' => rawurlencode( (string) ( $install['domain'] ?? '' ) ) ), admin_url( 'admin.php' ) ) . '#pckz-master-section-records' ); ?>">
									<strong><?php echo esc_html( $install['domain'] ?? '' ); ?></strong>
								</a>
								<?php if ( ! empty( $install['site_name'] ) ) : ?>
									<br><span class="description"><?php echo esc_html( $install['site_name'] ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<?php echo esc_html( $license['label'] ?? sprintf( '#%d', (int) ( $license['id'] ?? 0 ) ) ); ?>
								<br><span class="pckz-license-badge <?php echo esc_attr( $badge_class( $license['status'] ?? '' ) ); ?>"><?php echo esc_html( $status_label( $license['status'] ?? '' ) ); ?></span>
							</td>
							<td><?php echo esc_html( $install['plugin_version'] ?? '—' ); ?></td>
							<td>
								<?php if ( ! empty( $row['online'] ) ) : ?>
									<span class="pckz-status-pill pckz-status-pill--online"><?php esc_html_e( 'Online', 'pckz-canonical-engine' ); ?></span>
								<?php else : ?>
									<span class="pckz-status-pill pckz-status-pill--offline"><?php esc_html_e( 'Offline', 'pckz-canonical-engine' ); ?></span>
								<?php endif; ?>
								<?php if ( empty( $install['plugin_active'] ) || '1' !== (string) $install['plugin_active'] ) : ?>
									<br><span class="description"><?php esc_html_e( 'Plugin off', 'pckz-canonical-engine' ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $format_datetime( $install['last_check_in'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( $format_datetime( $install['last_asset_sync'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( $format_datetime( $install['last_activity_at'] ?? $install['updated_at'] ?? '' ) ); ?></td>
							<td>
								<?php
								$ust = sanitize_key( (string) ( $row['update_status'] ?? '' ) );
								if ( 'update_available' === $ust ) {
									echo '<span class="pckz-license-badge is-warning">' . esc_html__( 'Update', 'pckz-canonical-engine' ) . '</span>';
								} elseif ( 'up_to_date' === $ust ) {
									echo '<span class="pckz-license-badge is-success">' . esc_html__( 'Current', 'pckz-canonical-engine' ) . '</span>';
								} else {
									echo '—';
								}
								?>
							</td>
							<td>
								<?php if ( ! empty( $row['alerts'] ) ) : ?>
									<ul class="pckz-fleet-warnings">
										<?php foreach ( array_slice( $row['alerts'], 0, 2 ) as $alert ) : ?>
											<li class="pckz-fleet-warnings__item pckz-fleet-warnings__item--<?php echo esc_attr( $alert['severity'] ?? 'warning' ); ?>">
												<?php echo esc_html( $alert['message'] ?? '' ); ?>
											</li>
										<?php endforeach; ?>
									</ul>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</section>
