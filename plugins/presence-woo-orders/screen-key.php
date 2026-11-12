<?php
/**
 * Screen keys for WooCommerce orders, so Presence API's stale-screen notice watches them.
 *
 * @package Presence_Woo_Orders
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the screen key for an order.
 *
 * @param int $order_id Order ID.
 * @return string The screen key.
 */
function wp_presence_woo_orders_screen_key( $order_id ) {
	return 'wc/order/' . (int) $order_id;
}

/**
 * Gives the HPOS order edit screen its order's key.
 *
 * With HPOS off the order is edited on `post.php`, which Presence API already keys as `post/{id}`.
 *
 * @param string    $key    Screen key Presence API computed, or '' when none applies.
 * @param WP_Screen $screen Current screen.
 * @return string The screen key.
 */
function wp_presence_woo_orders_current_screen_key( $key, $screen ) {
	// The Orders list shares this screen, so only an edit with an order ID counts.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only reads which order the screen shows.
	if ( 'woocommerce_page_wc-orders' !== $screen->id || ! isset( $_GET['action'], $_GET['id'] ) || 'edit' !== $_GET['action'] ) {
		return $key;
	}

	$order_id = absint( $_GET['id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only reads which order the screen shows.

	return $order_id ? wp_presence_woo_orders_screen_key( $order_id ) : $key;
}
