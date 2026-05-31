<?php
/**
 * Admin page hero partial.
 *
 * @package PCKZCanonicalEngine
 *
 * @var string $hero_title
 * @var string $hero_description
 * @var string $hero_badge Optional badge text.
 * @var string $hero_badge_class Optional badge modifier class.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="pckz-page-hero">
	<div class="pckz-page-hero__content">
		<?php if ( ! empty( $hero_title ) ) : ?>
			<h1><?php echo esc_html( $hero_title ); ?></h1>
		<?php endif; ?>
		<?php if ( ! empty( $hero_description ) ) : ?>
			<p><?php echo esc_html( $hero_description ); ?></p>
		<?php endif; ?>
	</div>
	<?php if ( ! empty( $hero_badge ) ) : ?>
		<span class="pckz-page-badge <?php echo esc_attr( $hero_badge_class ?? 'is-muted' ); ?>">
			<?php echo esc_html( $hero_badge ); ?>
		</span>
	<?php endif; ?>
</div>
