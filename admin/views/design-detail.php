<?php
/**
 * Single saved design — full production / LightBurn package.
 *
 * @package PCKZCanonicalEngine
 *
 * @var array|null $design Design row.
 * @var array      $package Production package.
 */

defined( 'ABSPATH' ) || exit;

$back_url = admin_url( 'admin.php?page=pckz-designs' );
?>
<div class="wrap pckz-admin-wrap">
	<p><a href="<?php echo esc_url( $back_url ); ?>">&larr; <?php esc_html_e( 'Back to designs', 'pckz-canonical-engine' ); ?></a></p>

	<?php if ( empty( $design ) ) : ?>
		<h1><?php esc_html_e( 'Design not found', 'pckz-canonical-engine' ); ?></h1>
	<?php else : ?>
		<h1><?php esc_html_e( 'Design', 'pckz-canonical-engine' ); ?> #<?php echo esc_html( (string) $design['id'] ); ?></h1>
		<p>
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: product title, 2: date */
					__( 'Product: %1$s · Created: %2$s', 'pckz-canonical-engine' ),
					get_the_title( $design['product_id'] ) ?: (string) $design['product_id'],
					$design['created_at'] ?? ''
				)
			);
			?>
		</p>
		<?php
		echo PCKZ_Production::render_admin_production_panel( $package, array( 'design_id' => (int) $design['id'] ) );
		?>
	<?php endif; ?>
</div>
