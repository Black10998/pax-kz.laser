<?php
/**
 * Master Control — dashboard overview KPIs and quick navigation.
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

$release_meta = is_array( $release_meta ?? null ) ? $release_meta : array();
$stats        = is_array( $stats ?? null ) ? $stats : array();
$live_version = sanitize_text_field( (string) ( $release_meta['version'] ?? '' ) );
?>

<section class="pckz-mc-section pckz-mc-overview" id="pckz-mc-overview">
	<header class="pckz-mc-section__header">
		<div>
			<h2><?php esc_html_e( 'Dashboard', 'pckz-canonical-engine' ); ?></h2>
			<p class="description"><?php esc_html_e( 'At-a-glance health of licenses, customer sites, updates, and protected downloads.', 'pckz-canonical-engine' ); ?></p>
		</div>
	</header>

	<div class="pckz-mc-kpi-grid">
		<a class="pckz-mc-kpi pckz-mc-kpi--link" href="#pckz-master-section-licenses">
			<span class="pckz-mc-kpi__value"><?php echo esc_html( number_format_i18n( (int) ( $stats['licenses_total'] ?? 0 ) ) ); ?></span>
			<span class="pckz-mc-kpi__label"><?php esc_html_e( 'Licenses', 'pckz-canonical-engine' ); ?></span>
			<span class="pckz-mc-kpi__meta"><?php echo esc_html( sprintf( __( '%d active', 'pckz-canonical-engine' ), (int) ( $stats['licenses_active'] ?? 0 ) ) ); ?></span>
		</a>
		<a class="pckz-mc-kpi pckz-mc-kpi--link" href="#pckz-master-section-records">
			<span class="pckz-mc-kpi__value"><?php echo esc_html( number_format_i18n( (int) ( $stats['installations_total'] ?? 0 ) ) ); ?></span>
			<span class="pckz-mc-kpi__label"><?php esc_html_e( 'Installations', 'pckz-canonical-engine' ); ?></span>
			<span class="pckz-mc-kpi__meta"><?php echo esc_html( sprintf( __( '%d active · %d blocked', 'pckz-canonical-engine' ), (int) ( $stats['installations_active'] ?? 0 ), (int) ( $stats['installations_blocked'] ?? 0 ) ) ); ?></span>
		</a>
		<a class="pckz-mc-kpi pckz-mc-kpi--link pckz-mc-kpi--success" href="#pckz-master-section-fleet">
			<span class="pckz-mc-kpi__value"><?php echo esc_html( number_format_i18n( (int) ( $stats['fleet_online'] ?? 0 ) ) ); ?></span>
			<span class="pckz-mc-kpi__label"><?php esc_html_e( 'Online now', 'pckz-canonical-engine' ); ?></span>
			<span class="pckz-mc-kpi__meta"><?php echo esc_html( sprintf( __( '%d offline', 'pckz-canonical-engine' ), (int) ( $stats['fleet_offline'] ?? 0 ) ) ); ?></span>
		</a>
		<a class="pckz-mc-kpi pckz-mc-kpi--link pckz-mc-kpi--warning" href="#pckz-master-section-fleet">
			<span class="pckz-mc-kpi__value"><?php echo esc_html( number_format_i18n( (int) ( $stats['fleet_updates_pending'] ?? 0 ) ) ); ?></span>
			<span class="pckz-mc-kpi__label"><?php esc_html_e( 'Pending updates', 'pckz-canonical-engine' ); ?></span>
			<span class="pckz-mc-kpi__meta"><?php esc_html_e( 'Sites behind live release', 'pckz-canonical-engine' ); ?></span>
		</a>
		<a class="pckz-mc-kpi pckz-mc-kpi--link pckz-mc-kpi--danger" href="#pckz-master-section-fleet">
			<span class="pckz-mc-kpi__value"><?php echo esc_html( number_format_i18n( (int) ( $stats['fleet_critical_alerts'] ?? 0 ) ) ); ?></span>
			<span class="pckz-mc-kpi__label"><?php esc_html_e( 'Critical alerts', 'pckz-canonical-engine' ); ?></span>
			<span class="pckz-mc-kpi__meta"><?php esc_html_e( 'Security & monitoring', 'pckz-canonical-engine' ); ?></span>
		</a>
		<a class="pckz-mc-kpi pckz-mc-kpi--link" href="#pckz-master-section-releases">
			<span class="pckz-mc-kpi__value"><?php echo esc_html( $live_version ? $live_version : '—' ); ?></span>
			<span class="pckz-mc-kpi__label"><?php esc_html_e( 'Live client release', 'pckz-canonical-engine' ); ?></span>
			<span class="pckz-mc-kpi__meta"><?php esc_html_e( 'Published update version', 'pckz-canonical-engine' ); ?></span>
		</a>
		<a class="pckz-mc-kpi pckz-mc-kpi--link" href="#pckz-master-section-records">
			<span class="pckz-mc-kpi__value"><?php echo esc_html( number_format_i18n( (int) ( $stats['downloads_total'] ?? 0 ) ) ); ?></span>
			<span class="pckz-mc-kpi__label"><?php esc_html_e( 'Protected downloads', 'pckz-canonical-engine' ); ?></span>
			<span class="pckz-mc-kpi__meta"><?php echo esc_html( sprintf( __( '%d in last 24h', 'pckz-canonical-engine' ), (int) ( $stats['downloads_24h'] ?? 0 ) ) ); ?></span>
		</a>
		<div class="pckz-mc-kpi">
			<span class="pckz-mc-kpi__value"><?php echo esc_html( number_format_i18n( (int) ( $stats['fleet_plugin_inactive'] ?? 0 ) ) ); ?></span>
			<span class="pckz-mc-kpi__label"><?php esc_html_e( 'Inactive plugin', 'pckz-canonical-engine' ); ?></span>
			<span class="pckz-mc-kpi__meta"><?php esc_html_e( 'Client sites with plugin off', 'pckz-canonical-engine' ); ?></span>
		</div>
	</div>

	<div class="pckz-mc-quick-actions">
		<h3><?php esc_html_e( 'Quick tasks', 'pckz-canonical-engine' ); ?></h3>
		<div class="pckz-mc-quick-actions__grid">
			<a class="button button-primary" href="#pckz-master-section-licenses"><?php esc_html_e( 'Create license', 'pckz-canonical-engine' ); ?></a>
			<a class="button button-secondary" href="#pckz-master-section-licenses"><?php esc_html_e( 'Generate client package', 'pckz-canonical-engine' ); ?></a>
			<a class="button button-secondary" href="#pckz-master-section-releases"><?php esc_html_e( 'Publish software update', 'pckz-canonical-engine' ); ?></a>
			<a class="button button-secondary" href="#pckz-master-section-storage"><?php esc_html_e( 'Manage release storage', 'pckz-canonical-engine' ); ?></a>
			<a class="button button-secondary" href="#pckz-master-section-fleet"><?php esc_html_e( 'Review fleet health', 'pckz-canonical-engine' ); ?></a>
			<a class="button button-secondary" href="#pckz-master-section-records"><?php esc_html_e( 'View download history', 'pckz-canonical-engine' ); ?></a>
		</div>
	</div>
</section>
