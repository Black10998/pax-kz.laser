<?php
/**
 * Admin dashboard view.
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

$default_id = PCKZ_Post_Type::resolve_product_id();
$hero_title       = __( 'PCKZ Canonical Engine', 'pckz-canonical-engine' );
$hero_description = __( 'Quick start, statistics, and embed instructions for your product configurator.', 'pckz-canonical-engine' );
$hero_badge       = __( 'Dashboard', 'pckz-canonical-engine' );
$hero_badge_class = 'is-success';
?>
<div class="wrap pckz-admin-wrap">
	<?php include PCKZCE_PLUGIN_DIR . 'admin/views/partials/page-hero.php'; ?>

	<div class="pckz-dashboard-grid">
		<div class="pckz-panel">
			<header class="pckz-panel__header">
				<h2><?php esc_html_e( 'Quick Start', 'pckz-canonical-engine' ); ?></h2>
			</header>
			<div class="pckz-panel__body">
				<ol>
					<li><?php esc_html_e( 'Install & activate the plugin (see INSTALL.md in the plugin folder).', 'pckz-canonical-engine' ); ?></li>
					<li><?php esc_html_e( 'Upload day/night preview images on your Creator Product.', 'pckz-canonical-engine' ); ?></li>
					<li><?php esc_html_e( 'Configure PayPal and pricing under Settings.', 'pckz-canonical-engine' ); ?></li>
					<li><?php esc_html_e( 'Create a WordPress page and add the shortcode below.', 'pckz-canonical-engine' ); ?></li>
				</ol>
			</div>
		</div>

		<div class="pckz-panel">
			<header class="pckz-panel__header">
				<h2><?php esc_html_e( 'Statistics', 'pckz-canonical-engine' ); ?></h2>
			</header>
			<div class="pckz-panel__body">
				<p class="pckz-stat">
					<span class="pckz-stat-number"><?php echo esc_html( (string) $published ); ?></span>
					<span class="pckz-stat-label"><?php esc_html_e( 'Published creator products', 'pckz-canonical-engine' ); ?></span>
				</p>
				<?php if ( $default_id ) : ?>
					<p class="description">
						<?php
						printf(
							esc_html__( 'Default product for [product_creator]: #%d', 'pckz-canonical-engine' ),
							(int) $default_id
						);
						?>
					</p>
				<?php endif; ?>
			</div>
		</div>

		<div class="pckz-panel pckz-panel--wide">
			<header class="pckz-panel__header">
				<h2><?php esc_html_e( 'Embed on any page', 'pckz-canonical-engine' ); ?></h2>
			</header>
			<div class="pckz-panel__body">
				<p><?php esc_html_e( 'Create a new page in WordPress → Pages → Add New, then paste:', 'pckz-canonical-engine' ); ?></p>
				<code class="pckz-code">[product_creator]</code>
				<p class="description"><?php esc_html_e( 'No ID required — loads your default creator product automatically. Optional: [product_creator id="123"] or [product_creator slug="license-plate-creator"]', 'pckz-canonical-engine' ); ?></p>
				<p>
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . PCKZ_Post_Type::POST_TYPE ) ); ?>">
						<?php esc_html_e( 'Add Creator Product', 'pckz-canonical-engine' ); ?>
					</a>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=pckz-settings' ) ); ?>">
						<?php esc_html_e( 'Settings', 'pckz-canonical-engine' ); ?>
					</a>
				</p>
			</div>
		</div>

		<div class="pckz-panel pckz-panel--wide">
			<header class="pckz-panel__header">
				<h2><?php esc_html_e( 'Included in this plugin', 'pckz-canonical-engine' ); ?></h2>
			</header>
			<div class="pckz-panel__body">
				<ul>
					<li><?php esc_html_e( 'Admin panel (Products, Settings, Saved Designs, Orders)', 'pckz-canonical-engine' ); ?></li>
					<li><?php esc_html_e( 'Frontend shop customizer with live preview', 'pckz-canonical-engine' ); ?></li>
					<li><?php esc_html_e( 'PayPal checkout and order production summary', 'pckz-canonical-engine' ); ?></li>
					<li><?php esc_html_e( 'Font and icon libraries with export-safe delivery', 'pckz-canonical-engine' ); ?></li>
				</ul>
			</div>
		</div>

		<div class="pckz-panel pckz-panel--wide">
			<div class="pckz-panel__body">
				<?php PCKZ_Branding::render_settings_panel( true ); ?>
			</div>
		</div>
	</div>
</div>
