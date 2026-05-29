<?php
/**
 * Compact PAXDesign branding panel (settings / dashboard).
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

$show_version = isset( $show_version ) ? (bool) $show_version : true;
?>
<aside class="pckz-developer-panel" aria-label="<?php esc_attr_e( 'Entwickler und Support', 'pckz-canonical-engine' ); ?>">
	<p class="pckz-developer-panel__brand">
		<strong><?php echo esc_html( PCKZ_Branding::AUTHOR_NAME ); ?></strong>
		<span class="pckz-developer-panel__by"><?php echo esc_html( PCKZ_Branding::DEVELOPER_LABEL ); ?></span>
	</p>
	<ul class="pckz-developer-panel__links">
		<li>
			<span class="pckz-developer-panel__label"><?php esc_html_e( 'Website', 'pckz-canonical-engine' ); ?></span>
			<a href="<?php echo esc_url( PCKZ_Branding::WEBSITE_URL ); ?>" target="_blank" rel="noopener noreferrer">
				<?php echo esc_html( PCKZ_Branding::WEBSITE_URL ); ?>
			</a>
		</li>
		<li>
			<span class="pckz-developer-panel__label"><?php esc_html_e( 'Support', 'pckz-canonical-engine' ); ?></span>
			<a href="<?php echo esc_url( 'mailto:' . PCKZ_Branding::SUPPORT_EMAIL ); ?>">
				<?php echo esc_html( PCKZ_Branding::SUPPORT_EMAIL ); ?>
			</a>
		</li>
	</ul>
	<?php if ( $show_version ) : ?>
		<p class="pckz-developer-panel__version">
			<?php esc_html_e( 'Plugin-Version', 'pckz-canonical-engine' ); ?>:
			<code><?php echo esc_html( PCKZ_Branding::version_label() ); ?></code>
		</p>
	<?php endif; ?>
	<p class="pckz-developer-panel__about-link">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=pckz-about' ) ); ?>">
			<?php esc_html_e( 'Über den Entwickler', 'pckz-canonical-engine' ); ?>
		</a>
	</p>
</aside>
