<?php
/**
 * A presence room per WooCommerce order, so each person on an order sees who else has it open.
 *
 * Follows the "Custom rooms" pattern in the Presence API README.
 *
 * @package Presence_Woo_Orders
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the room for an order.
 *
 * @param int $order_id Order ID.
 * @return string The room.
 */
function wp_presence_woo_orders_room( $order_id ) {
	return 'wc/order:' . (int) $order_id;
}

/**
 * Loads the script that joins the order's room on an order edit screen.
 */
function wp_presence_woo_orders_enqueue_order_room() {
	$screen   = get_current_screen();
	$order_id = $screen ? wp_presence_woo_orders_screen_order_id( $screen ) : 0;

	if ( ! $order_id || ! current_user_can( 'edit_shop_orders' ) ) {
		return;
	}

	wp_enqueue_script(
		'presence-woo-orders-room',
		plugins_url( 'order-room.js', __FILE__ ),
		array( 'jquery', 'heartbeat', 'wp-i18n' ),
		WP_PRESENCE_VERSION,
		true
	);
	wp_set_script_translations( 'presence-woo-orders-room', 'presence-woo-orders' );
	wp_add_inline_script(
		'presence-woo-orders-room',
		sprintf( 'window.presenceWooOrdersRoom = %s;', wp_json_encode( array( 'orderId' => $order_id ) ) ),
		'before'
	);
}

/**
 * Joins the order's room on each Heartbeat tick and answers with who else is in it.
 *
 * @param array $response The Heartbeat response.
 * @param array $data     The data the screen sent.
 * @return array The Heartbeat response.
 */
function wp_presence_woo_orders_heartbeat_received( $response, $data ) {
	$order_id = absint( $data['presence-woo-orders'] ?? 0 );
	if ( ! $order_id || ! current_user_can( 'edit_shop_orders' ) ) {
		return $response;
	}

	$user_id = get_current_user_id();
	$entries = wp_presence_exchange(
		wp_presence_woo_orders_room( $order_id ),
		'presence-woo-orders-' . $user_id,
		array(),
		array(
			'user_id'       => $user_id,
			'client_prefix' => 'presence-woo-orders-',
		)
	);

	$response['presence-woo-orders'] = array();
	foreach ( $entries as $entry ) {
		if ( (int) $entry->user_id !== $user_id ) {
			$response['presence-woo-orders'][] = get_the_author_meta( 'display_name', $entry->user_id );
		}
	}

	return $response;
}
