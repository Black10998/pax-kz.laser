<?php
/**
 * Settings section navigation partial.
 *
 * @package PCKZCanonicalEngine
 *
 * @var string $active_section Current section slug.
 */

defined( 'ABSPATH' ) || exit;

$active_section = isset( $active_section ) ? sanitize_key( (string) $active_section ) : 'general';

$sections = array(
	'general'  => array(
		'label' => __( 'General', 'pckz-canonical-engine' ),
		'icon'  => 'dashicons-admin-settings',
	),
	'frontend' => array(
		'label' => __( 'Frontend Display', 'pckz-canonical-engine' ),
		'icon'  => 'dashicons-visibility',
	),
	'pricing'  => array(
		'label' => __( 'Pricing', 'pckz-canonical-engine' ),
		'icon'  => 'dashicons-tag',
	),
	'paypal'   => array(
		'label' => __( 'PayPal', 'pckz-canonical-engine' ),
		'icon'  => 'dashicons-money-alt',
	),
	'payments' => array(
		'label' => __( 'Stripe / Payments', 'pckz-canonical-engine' ),
		'icon'  => 'dashicons-cart',
	),
	'shipment' => array(
		'label' => __( 'Shipment Tracking', 'pckz-canonical-engine' ),
		'icon'  => 'dashicons-location-alt',
	),
	'licensing'=> array(
		'label' => __( 'Licensing', 'pckz-canonical-engine' ),
		'icon'  => 'dashicons-shield',
	),
	'export'   => array(
		'label' => __( 'Export', 'pckz-canonical-engine' ),
		'icon'  => 'dashicons-download',
	),
);

$base_url = admin_url( 'admin.php?page=pckz-settings' );
?>
<nav class="pckz-settings-nav" aria-label="<?php esc_attr_e( 'Settings sections', 'pckz-canonical-engine' ); ?>">
	<ul>
		<?php foreach ( $sections as $slug => $meta ) : ?>
			<li>
				<a
					class="pckz-settings-nav__link <?php echo $active_section === $slug ? 'is-active' : ''; ?>"
					href="<?php echo esc_url( add_query_arg( 'section', $slug, $base_url ) ); ?>#pckz-section-<?php echo esc_attr( $slug ); ?>"
					data-pckz-settings-section="<?php echo esc_attr( $slug ); ?>"
				>
					<span class="dashicons <?php echo esc_attr( $meta['icon'] ); ?>" aria-hidden="true"></span>
					<?php echo esc_html( $meta['label'] ); ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
	<p class="pckz-settings-nav__tip">
		<?php esc_html_e( 'Changes apply after you click Save Settings at the bottom of the page.', 'pckz-canonical-engine' ); ?>
	</p>
</nav>
