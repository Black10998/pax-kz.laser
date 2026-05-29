<?php
/**
 * About the developer — PAXDesign (German).
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap pckz-admin-wrap pckz-about-wrap">
	<h1><?php esc_html_e( 'Über den Entwickler', 'pckz-canonical-engine' ); ?></h1>

	<div class="pckz-about-card">
		<div class="pckz-about-card__header">
			<span class="pckz-about-card__logo" aria-hidden="true">PAX</span>
			<div>
				<h2 class="pckz-about-card__title"><?php echo esc_html( PCKZ_Branding::AUTHOR_NAME ); ?></h2>
				<p class="pckz-about-card__subtitle"><?php echo esc_html( PCKZ_Branding::DEVELOPER_LABEL ); ?></p>
			</div>
		</div>

		<div class="pckz-about-card__body">
			<p>
				<?php esc_html_e( 'PAXDesign ist spezialisiert auf professionelle Webentwicklung, individuelle Konfiguratoren, E-Commerce-Lösungen, WordPress-Systeme, Automatisierungen und moderne digitale Produkte.', 'pckz-canonical-engine' ); ?>
			</p>
			<p>
				<?php esc_html_e( 'Diese Software wurde von Ahmad Alkhalaf entwickelt und wird kontinuierlich weiterentwickelt, optimiert und gepflegt.', 'pckz-canonical-engine' ); ?>
			</p>
		</div>

		<dl class="pckz-about-card__meta">
			<dt><?php esc_html_e( 'Weitere Informationen', 'pckz-canonical-engine' ); ?></dt>
			<dd>
				<a href="<?php echo esc_url( PCKZ_Branding::WEBSITE_URL ); ?>" target="_blank" rel="noopener noreferrer">
					<?php echo esc_html( PCKZ_Branding::WEBSITE_URL ); ?>
				</a>
			</dd>
			<dt><?php esc_html_e( 'Support', 'pckz-canonical-engine' ); ?></dt>
			<dd>
				<a href="<?php echo esc_url( 'mailto:' . PCKZ_Branding::SUPPORT_EMAIL ); ?>">
					<?php echo esc_html( PCKZ_Branding::SUPPORT_EMAIL ); ?>
				</a>
			</dd>
			<dt><?php esc_html_e( 'Plugin-Version', 'pckz-canonical-engine' ); ?></dt>
			<dd><code><?php echo esc_html( PCKZ_Branding::version_label() ); ?></code></dd>
		</dl>

		<p class="pckz-about-card__actions">
			<a class="button button-primary" href="<?php echo esc_url( PCKZ_Branding::WEBSITE_URL ); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Website besuchen', 'pckz-canonical-engine' ); ?>
			</a>
			<a class="button" href="<?php echo esc_url( 'mailto:' . PCKZ_Branding::SUPPORT_EMAIL ); ?>">
				<?php esc_html_e( 'Support kontaktieren', 'pckz-canonical-engine' ); ?>
			</a>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=pckz-settings' ) ); ?>">
				<?php esc_html_e( 'Einstellungen', 'pckz-canonical-engine' ); ?>
			</a>
		</p>
	</div>
</div>
