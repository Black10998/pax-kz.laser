<?php
/**
 * Uninstall PCKZ Canonical Engine.
 *
 * @package PCKZCanonicalEngine
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'pckz_settings' );

global $wpdb;
$table = $wpdb->prefix . 'pckz_designs';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

$posts = get_posts(
	array(
		'post_type'      => 'pckz_product',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);

foreach ( $posts as $post_id ) {
	wp_delete_post( $post_id, true );
}
