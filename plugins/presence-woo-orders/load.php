<?php
/**
 * Plugin Name: Presence for WooCommerce Orders
 * Description: Shows who else has a WooCommerce order open, and warns you when it changes, using the Presence API's public functions.
 * Requires Plugins: presence-api, woocommerce
 * Text Domain: presence-woo-orders
 *
 * @package Presence_Woo_Orders
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'plugins_loaded',
	function () {
		if ( ! function_exists( 'wp_get_presence' ) ) {
			return;
		}

		require_once __DIR__ . '/screen-key.php';
		require_once __DIR__ . '/order-room.php';

		add_filter( 'wp_presence_current_screen_key', 'wp_presence_woo_orders_current_screen_key', 10, 2 );
		add_action( 'woocommerce_update_order', 'wp_presence_woo_orders_on_update_order' );
		add_action( 'admin_enqueue_scripts', 'wp_presence_woo_orders_enqueue_order_room' );
		add_filter( 'heartbeat_received', 'wp_presence_woo_orders_heartbeat_received', 10, 2 );
	}
);

// Without this, WooCommerce lists the plugin as incompatible and won't let a site switch HPOS on.
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);
