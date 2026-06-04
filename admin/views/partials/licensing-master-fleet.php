<?php
/**
 * Master Control — fleet overview, security alerts, installation monitor.
 *
 * @package PCKZCanonicalEngine
 *
 * @var array $fleet_rows
 * @var array $stats
 * @var array $security_events
 * @var array $release_meta
 */

defined( 'ABSPATH' ) || exit;

$fleet_sort    = isset( $_GET['pckz_fleet_sort'] ) ? sanitize_key( wp_unslash( $_GET['pckz_fleet_sort'] ) ) : 'health';
$fleet_filter  = isset( $_GET['pckz_fleet_filter'] ) ? sanitize_key( wp_unslash( $_GET['pckz_fleet_filter'] ) ) : '';
$fleet_rows    = is_array( $fleet_rows ?? null ) ? $fleet_rows : array();
$security_events = is_array( $security_events ?? null ) ? $security_events : array();
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

<section class="pckz-license-card pckz-license-card--full pckz-fleet-dashboard" id="pckz-fleet-dashboard">
	<h2><?php esc_html_e( 'Licensed Installations — Fleet Overview', 'pckz-canonical-engine' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Central monitor for all customer installations: online status, versions, license health, updates, and security signals.', 'pckz-canonical-engine' ); ?></p>

	<?php if ( empty( $fleet_rows ) ) : ?>
		<div class="notice notice-info inline pckz-fleet-empty">
			<p>
				<strong><?php esc_html_e( 'No customer installations have checked in yet.', 'pckz-canonical-engine' ); ?></strong>
			</p>
			<p>
				<?php esc_html_e( 'As soon as a licensed client site authenticates with this master, it will appear here with health, version, sync status, and security alerts. To get started:', 'pckz-canonical-engine' ); ?>
			</p>
			<ol class="pckz-fleet-empty__steps">
				<li><?php esc_html_e( 'Create a license below (Create License card).', 'pckz-canonical-engine' ); ?></li>
				<li><?php esc_html_e( 'Generate a customer package (Customer Packages card) and deliver the ZIP to the client.', 'pckz-canonical-engine' ); ?></li>
				<li><?php esc_html_e( 'Once installed, the client checks in automatically and is listed here.', 'pckz-canonical-engine' ); ?></li>
			</ol>
		</div>
	<?php endif; ?>

	<div class="pckz-fleet-stats">
		<article class="pckz-fleet-stat pckz-fleet-stat--success">
			<span class="pckz-fleet-stat__value"><?php echo esc_html( number_format_i18n( (int) ( $stats['fleet_online'] ?? 0 ) ) ); ?></span>
			<span class="pckz-fleet-stat__label"><?php esc_html_e( 'Online', 'pckz-canonical-engine' ); ?></span>
		</article>
		<article class="pckz-fleet-stat pckz-fleet-stat--muted">
			<span class="pckz-fleet-stat__value"><?php echo esc_html( number_format_i18n( (int) ( $stats['fleet_offline'] ?? 0 ) ) ); ?></span>
			<span class="pckz-fleet-stat__label"><?php esc_html_e( 'Offline', 'pckz-canonical-engine' ); ?></span>
		</article>
		<article class="pckz-fleet-stat pckz-fleet-stat--warning">
			<span class="pckz-fleet-stat__value"><?php echo esc_html( number_format_i18n( (int) ( $stats['fleet_updates_pending'] ?? 0 ) ) ); ?></span>
			<span class="pckz-fleet-stat__label"><?php esc_html_e( 'Updates pending', 'pckz-canonical-engine' ); ?></span>
		</article>
		<article class="pckz-fleet-stat pckz-fleet-stat--danger">
			<span class="pckz-fleet-stat__value"><?php echo esc_html( number_format_i18n( (int) ( $stats['fleet_critical_alerts'] ?? 0 ) ) ); ?></span>
			<span class="pckz-fleet-stat__label"><?php esc_html_e( 'Critical alerts', 'pckz-canonical-engine' ); ?></span>
		</article>
		<article class="pckz-fleet-stat">
			<span class="pckz-fleet-stat__value"><?php echo esc_html( number_format_i18n( (int) ( $stats['fleet_plugin_inactive'] ?? 0 ) ) ); ?></span>
			<span class="pckz-fleet-stat__label"><?php esc_html_e( 'Plugin inactive', 'pckz-canonical-engine' ); ?></span>
		</article>
		<article class="pckz-fleet-stat">
			<span class="pckz-fleet-stat__value"><?php echo esc_html( ! empty( $release_meta['version'] ) ? $release_meta['version'] : '—' ); ?></span>
			<span class="pckz-fleet-stat__label"><?php esc_html_e( 'Latest release', 'pckz-canonical-engine' ); ?></span>
		</article>
	</div>

	<?php if ( ! empty( $security_events ) ) : ?>
		<div class="pckz-fleet-alerts">
			<h3><?php esc_html_e( 'Security & monitoring alerts', 'pckz-canonical-engine' ); ?></h3>
			<ul class="pckz-fleet-alert-list">
				<?php foreach ( $security_events as $event ) : ?>
					<?php
					$sev          = sanitize_key( (string) ( $event['severity'] ?? 'warning' ) );
					$event_context = json_decode( (string) ( $event['context'] ?? '{}' ), true );
					$event_context = is_array( $event_context ) ? $event_context : array();
					$signal_labels = array();
					if ( ! empty( $event_context['signals'] ) && is_array( $event_context['signals'] ) ) {
						$signal_labels = array_values( array_filter( array_map( 'sanitize_key', $event_context['signals'] ) ) );
					}
					?>
					<li class="pckz-fleet-alert pckz-fleet-alert--<?php echo esc_attr( $sev ); ?>">
						<strong><?php echo esc_html( $event['message'] ?? '' ); ?></strong>
						<span class="description">
							<?php echo esc_html( $event['event_type'] ?? '' ); ?>
							<?php if ( ! empty( $event['domain'] ) ) : ?>
								· <?php echo esc_html( $event['domain'] ); ?>
							<?php endif; ?>
							· <?php echo esc_html( $format_datetime( $event['created_at'] ?? '' ) ); ?>
						</span>
						<?php if ( ! empty( $signal_labels ) ) : ?>
							<span class="description"><?php esc_html_e( 'Signals:', 'pckz-canonical-engine' ); ?> <?php echo esc_html( implode( ', ', $signal_labels ) ); ?></span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
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

	<div class="pckz-license-table-wrap pckz-fleet-table-wrap">
		<table class="widefat striped pckz-license-table pckz-fleet-table">
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
							<td>
								<strong><?php echo esc_html( $install['domain'] ?? '' ); ?></strong>
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
