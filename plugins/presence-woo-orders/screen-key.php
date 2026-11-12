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
 * Returns the order an order edit screen shows, with HPOS on or off.
 *
 * @param WP_Screen $screen The screen.
 * @return int The order ID, or 0 when the screen doesn't edit an order.
 */
function wp_presence_woo_orders_screen_order_id( $screen ) {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Only reads which order the screen shows.
	if ( 'woocommerce_page_wc-orders' === $screen->id ) {
		// The Orders list shares this screen, so only an edit with an order ID counts.
		$order_id = isset( $_GET['action'], $_GET['id'] ) && 'edit' === $_GET['action'] ? absint( $_GET['id'] ) : 0;
	} elseif ( 'shop_order' === $screen->id && 'post' === $screen->base ) {
		$order_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
	} else {
		$order_id = 0;
	}
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	return $order_id;
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
	if ( 'woocommerce_page_wc-orders' !== $screen->id ) {
		return $key;
	}

	$order_id = wp_presence_woo_orders_screen_order_id( $screen );

	return $order_id ? wp_presence_woo_orders_screen_key( $order_id ) : $key;
}

/**
 * Whether WooCommerce stores orders in its own tables (HPOS).
 *
 * @return bool True when HPOS is on.
 */
function wp_presence_woo_orders_hpos_enabled() {
	return class_exists( \Automattic\WooCommerce\Utilities\OrderUtil::class ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
}

/**
 * Marks an HPOS order's screen stale whenever the order saves, including refunds.
 *
 * Every save counts, not only ones from wp-admin, since a payment or the REST API can change an order someone has open.
 *
 * @param int $order_id Order ID.
 */
function wp_presence_woo_orders_on_update_order( $order_id ) {
	// With HPOS off the order is a post, whose screen Presence API already marks stale.
	if ( ! wp_presence_woo_orders_hpos_enabled() ) {
		return;
	}

	wp_presence_bump_screen_revision( wp_presence_woo_orders_screen_key( $order_id ) );
}
