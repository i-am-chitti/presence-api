<?php
/**
 * Playground demo helpers for Presence for WooCommerce Orders.
 *
 * Loaded as a must-use plugin by the WooCommerce orders blueprint. A Playground
 * viewer is one person, so this plays Sam Manager: it keeps Sam on the demo
 * order, and adds a button that changes the order's status as Sam.
 *
 * This file ships only with that blueprint; it is not part of the plugin.
 *
 * @package Presence_API_Demo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PRESENCE_DEMO_WOO_ORDERS_NONCE_ACTION = 'presence_demo_woo_orders_change_status';

/**
 * Creates Sam Manager and an order to open, once.
 *
 * @return int The demo order's ID.
 */
function presence_demo_woo_orders_seed() {
	if ( ! get_user_by( 'login', 'presence-demo-sam' ) ) {
		wp_insert_user(
			array(
				'user_login'   => 'presence-demo-sam',
				'user_pass'    => wp_generate_password(),
				'user_email'   => 'sam@example.com',
				'display_name' => 'Sam Manager',
				'role'         => 'shop_manager',
			)
		);
	}

	$order_id = (int) get_option( 'presence_demo_woo_order' );
	if ( $order_id && wc_get_order( $order_id ) ) {
		return $order_id;
	}

	$product = new WC_Product_Simple();
	$product->set_name( 'Presence hoodie' );
	$product->set_regular_price( '45' );
	$product->save();

	$order = wc_create_order();
	$order->add_product( $product, 2 );
	$order->set_address(
		array(
			'first_name' => 'Rosa',
			'last_name'  => 'Customer',
			'email'      => 'rosa@example.com',
			'country'    => 'US',
		),
		'billing'
	);
	$order->calculate_totals();
	$order->set_status( 'on-hold' );
	$order->save();

	update_option( 'presence_demo_woo_order', $order->get_id() );

	return $order->get_id();
}

/**
 * Returns Sam Manager's user ID, or 0 before the blueprint has seeded.
 *
 * @return int
 */
function presence_demo_woo_orders_sam_id() {
	$user = get_user_by( 'login', 'presence-demo-sam' );
	return $user ? (int) $user->ID : 0;
}

/**
 * Opens the demo order from `?presence_demo=order`.
 */
function presence_demo_woo_orders_open() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only the blueprint's landing page sets it.
	if ( ! isset( $_GET['presence_demo'] ) || 'order' !== $_GET['presence_demo'] || ! current_user_can( 'edit_shop_orders' ) ) {
		return;
	}

	$order = wc_get_order( (int) get_option( 'presence_demo_woo_order' ) );
	if ( $order ) {
		wp_safe_redirect( $order->get_edit_order_url() );
		exit;
	}
}
add_action( 'admin_init', 'presence_demo_woo_orders_open' );

/**
 * Keeps Sam on the demo order while the viewer has it open.
 *
 * @param array $response Heartbeat response.
 * @param array $data     Heartbeat data.
 * @return array The response.
 */
function presence_demo_woo_orders_keep_sam( $response, $data ) {
	$order_id = absint( $data['presence-woo-orders'] ?? 0 );
	$sam_id   = presence_demo_woo_orders_sam_id();

	if ( $sam_id && $order_id && (int) get_option( 'presence_demo_woo_order' ) === $order_id && function_exists( 'wp_presence_woo_orders_room' ) ) {
		wp_set_presence( wp_presence_woo_orders_room( $order_id ), 'presence-woo-orders-' . $sam_id, array(), array( 'user_id' => $sam_id ) );
	}

	return $response;
}
// Before the plugin's own handler, so Sam is in the list it answers with.
add_filter( 'heartbeat_received', 'presence_demo_woo_orders_keep_sam', 5, 2 );

/**
 * Renders the "change the status as Sam" button on the demo order.
 */
function presence_demo_woo_orders_render_button() {
	if ( ! function_exists( 'wp_presence_woo_orders_screen_order_id' ) ) {
		return;
	}

	$order_id = wp_presence_woo_orders_screen_order_id( get_current_screen() );
	if ( ! $order_id || (int) get_option( 'presence_demo_woo_order' ) !== $order_id || ! presence_demo_woo_orders_sam_id() ) {
		return;
	}
	?>
	<div class="notice notice-info">
		<p>
			<strong><?php esc_html_e( 'Demo:', 'presence-api' ); ?></strong>
			<?php esc_html_e( 'Sam Manager has this order open too. Change its status as Sam, as if from the Orders list, and a notice will warn you within seconds.', 'presence-api' ); ?>
		</p>
		<p>
			<button type="button" class="button" data-presence-demo-woo-order="<?php echo esc_attr( (string) $order_id ); ?>">
				<?php esc_html_e( 'Change the status as Sam Manager', 'presence-api' ); ?>
			</button>
		</p>
	</div>
	<script>
	(function () {
		const button = document.querySelector('[data-presence-demo-woo-order]');
		button.addEventListener('click', function () {
			const body = new FormData();
			body.append('action', 'presence_demo_woo_orders_change_status');
			body.append('order_id', button.dataset.presenceDemoWooOrder);
			body.append('_wpnonce', <?php echo wp_json_encode( wp_create_nonce( PRESENCE_DEMO_WOO_ORDERS_NONCE_ACTION ) ); ?>);
			button.disabled = true;
			fetch(<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>, { method: 'POST', credentials: 'same-origin', body: body })
				.then(function () {
					button.disabled = false;
					if (window.wp && window.wp.heartbeat && typeof window.wp.heartbeat.connectNow === 'function') {
						window.wp.heartbeat.connectNow();
					}
				});
		});
	})();
	</script>
	<?php
}
add_action( 'admin_notices', 'presence_demo_woo_orders_render_button' );

/**
 * AJAX: switches the demo order between On hold and Processing, as Sam.
 */
function presence_demo_woo_orders_change_status() {
	check_ajax_referer( PRESENCE_DEMO_WOO_ORDERS_NONCE_ACTION );

	$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
	$order    = wc_get_order( $order_id );
	$sam_id   = presence_demo_woo_orders_sam_id();
	if ( ! $order || (int) get_option( 'presence_demo_woo_order' ) !== $order_id || ! $sam_id || ! current_user_can( 'edit_shop_orders' ) ) {
		wp_send_json_error( null, 403 );
	}

	$viewer_id = get_current_user_id();
	wp_set_current_user( $sam_id );
	$order->update_status( $order->has_status( 'processing' ) ? 'on-hold' : 'processing' );
	wp_set_current_user( $viewer_id );

	wp_send_json_success();
}
add_action( 'wp_ajax_presence_demo_woo_orders_change_status', 'presence_demo_woo_orders_change_status' );
