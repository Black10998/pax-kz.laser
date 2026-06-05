<?php
/**
 * Master Control — collapsible section navigation.
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

$sections = array(
	'overview' => array(
		'label' => __( 'Dashboard', 'pckz-canonical-engine' ),
		'icon'  => 'dashicons-dashboard',
	),
	'fleet'    => array(
		'label' => __( 'Customer fleet', 'pckz-canonical-engine' ),
		'icon'  => 'dashicons-admin-site-alt3',
	),
	'releases' => array(
		'label' => __( 'Software updates', 'pckz-canonical-engine' ),
		'icon'  => 'dashicons-update',
	),
	'licenses' => array(
		'label' => __( 'Licenses & delivery', 'pckz-canonical-engine' ),
		'icon'  => 'dashicons-admin-network',
	),
	'records'  => array(
		'label' => __( 'Activity & logs', 'pckz-canonical-engine' ),
		'icon'  => 'dashicons-list-view',
	),
);
?>
<nav class="pckz-license-nav pckz-mc-nav" aria-label="<?php esc_attr_e( 'Master Control sections', 'pckz-canonical-engine' ); ?>">
	<button type="button" class="button pckz-license-nav__toggle" aria-expanded="false" aria-controls="pckz-license-nav-panel">
		<span class="dashicons dashicons-menu" aria-hidden="true"></span>
		<?php esc_html_e( 'Navigate', 'pckz-canonical-engine' ); ?>
	</button>
	<div id="pckz-license-nav-panel" class="pckz-license-nav__panel">
		<p class="pckz-mc-nav__title"><?php esc_html_e( 'Master Control', 'pckz-canonical-engine' ); ?></p>
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
