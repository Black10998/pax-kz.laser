<?php
/**
 * Smoke: Master Control menu registration uses admin capability and fallback.
 *
 * @package PCKZCanonicalEngine
 */

require_once dirname( __DIR__ ) . '/tests/smoke-bootstrap.php';

$GLOBALS['pckz_smoke_actions']       = array();
$GLOBALS['pckz_smoke_submenu_calls'] = array();

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['pckz_smoke_actions'][] = array(
			'hook'          => $hook,
			'callback'      => $callback,
			'priority'      => (int) $priority,
			'accepted_args' => (int) $accepted_args,
		);
	}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		unset( $hook, $callback, $priority, $accepted_args );
	}
}
if ( ! function_exists( 'add_submenu_page' ) ) {
	function add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback = '' ) {
		$GLOBALS['pckz_smoke_submenu_calls'][] = array(
			'parent'     => $parent_slug,
			'page_title' => $page_title,
			'menu_title' => $menu_title,
			'capability' => $capability,
			'menu_slug'  => $menu_slug,
			'callback'   => $callback,
		);
		// Simulate failed attachment when parent menu is unavailable.
		if ( 'pckz-canonical-engine' === $parent_slug ) {
			return false;
		}
		return 'toplevel_page_' . $menu_slug;
	}
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $value ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) );
	}
}
if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( (string) $url, $component );
	}
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $v ) {
		return abs( (int) $v );
	}
}
if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) {
		return 'https://example.test' . $path;
	}
}

require_once dirname( __DIR__ ) . '/includes/class-pckz-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-pckz-licensing.php';

$licensing = new PCKZ_Licensing();

$menu_action = null;
foreach ( $GLOBALS['pckz_smoke_actions'] as $action ) {
	if ( 'admin_menu' === ( $action['hook'] ?? '' ) && is_array( $action['callback'] ?? null ) && 'register_admin_menu' === ( $action['callback'][1] ?? '' ) ) {
		$menu_action = $action;
		break;
	}
}
if ( ! is_array( $menu_action ) ) {
	fwrite( STDERR, "register_admin_menu action not registered\n" );
	exit( 1 );
}
if ( (int) ( $menu_action['priority'] ?? 0 ) < 10 ) {
	fwrite( STDERR, "register_admin_menu priority should run after parent menu registration\n" );
	exit( 1 );
}

// Verify fallback attachment and capability consistency.
$ref = new ReflectionClass( 'PCKZ_Licensing' );
$obj = $ref->newInstanceWithoutConstructor();
$obj->register_admin_menu();

$calls = $GLOBALS['pckz_smoke_submenu_calls'];
if ( count( $calls ) < 2 ) {
	fwrite( STDERR, "Expected primary + fallback submenu registrations\n" );
	exit( 1 );
}
if ( 'pckz-canonical-engine' !== (string) ( $calls[0]['parent'] ?? '' ) ) {
	fwrite( STDERR, "Primary parent slug mismatch\n" );
	exit( 1 );
}
if ( 'options-general.php' !== (string) ( $calls[1]['parent'] ?? '' ) ) {
	fwrite( STDERR, "Fallback parent slug mismatch\n" );
	exit( 1 );
}
if ( 'manage_options' !== (string) ( $calls[0]['capability'] ?? '' ) || 'manage_options' !== (string) ( $calls[1]['capability'] ?? '' ) ) {
	fwrite( STDERR, "Capability should remain manage_options on all registrations\n" );
	exit( 1 );
}
if ( 'pckz-license-server' !== (string) ( $calls[0]['menu_slug'] ?? '' ) || 'pckz-license-server' !== (string) ( $calls[1]['menu_slug'] ?? '' ) ) {
	fwrite( STDERR, "Menu slug mismatch for Master Control\n" );
	exit( 1 );
}

echo "master-control-menu-permission-smoke: OK\n";
exit( 0 );
