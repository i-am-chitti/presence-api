<?php
/**
 * Plugin Name: Presence for WooCommerce Orders
 * Description: Warns you when a WooCommerce order changes while you have it open, using the Presence API's public functions.
 * Requires Plugins: presence-api, woocommerce
 * Text Domain: presence-woo-orders
 *
 * @package Presence_Woo_Orders
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Without this, WooCommerce lists the plugin as incompatible and won't let a site switch HPOS on.
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);
