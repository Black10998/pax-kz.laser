<?php
/**
 * Master Control — collapsible section navigation.
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

$sections = array(
	'fleet'     => array(
		'label' => __( 'Fleet overview', 'pckz-canonical-engine' ),
		'icon'  => 'dashicons-admin-site-alt3',
	),
	'releases'  => array(
		'label' => __( 'Software updates', 'pckz-canonical-engine' ),
		'icon'  => 'dashicons-update',
	),
	'licenses'  => array(
		'label' => __( 'Licenses & packages', 'pckz-canonical-engine' ),
		'icon'  => 'dashicons-admin-network',
	),
	'management'=> array(
		'label' => __( 'History & records', 'pckz-canonical-engine' ),
		'icon'  => 'dashicons-list-view',
	),
);
?>
<nav class="pckz-license-nav" aria-label="<?php esc_attr_e( 'Master Control sections', 'pckz-canonical-engine' ); ?>">
	<button type="button" class="button pckz-license-nav__toggle" aria-expanded="false" aria-controls="pckz-license-nav-panel">
		<span class="dashicons dashicons-menu" aria-hidden="true"></span>
		<?php esc_html_e( 'Sections', 'pckz-canonical-engine' ); ?>
	</button>
	<div id="pckz-license-nav-panel" class="pckz-license-nav__panel">
		<ul>
			<?php foreach ( $sections as $slug => $meta ) : ?>
				<li>
					<a
						class="pckz-license-nav__link"
						href="#pckz-master-section-<?php echo esc_attr( $slug ); ?>"
						data-pckz-master-section="<?php echo esc_attr( $slug ); ?>"
					>
						<span class="dashicons <?php echo esc_attr( $meta['icon'] ); ?>" aria-hidden="true"></span>
						<?php echo esc_html( $meta['label'] ); ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</nav>
