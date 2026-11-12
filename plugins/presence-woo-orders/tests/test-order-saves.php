<?php
/**
 * Tests for marking an order's screen stale when it saves.
 *
 * @package Presence_Woo_Orders
 *
 * @group presence
 */

use Automattic\WooCommerce\Utilities\OrderUtil;

if ( ! class_exists( OrderUtil::class ) ) {
	require_once __DIR__ . '/class-order-util.php';
}

class WP_Test_Presence_Woo_Orders_Order_Saves extends WP_Presence_UnitTestCase {

	/**
	 * An administrator with the order open.
	 *
	 * @var int
	 */
	private static $admin_id;

	/**
	 * Someone else who changes the order.
	 *
	 * @var int
	 */
	private static $manager_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id   = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$manager_id = $factory->user->create( array( 'role' => 'editor' ) );
	}

	public function set_up() {
		parent::set_up();
		// The base class's TRUNCATE commits, so a revision saved by an earlier test outlives its rollback.
		delete_option( 'wp_presence_screen_revisions' );
		OrderUtil::$enabled = true;
	}

	public function tear_down() {
		OrderUtil::$enabled = false;
		parent::tear_down();
	}

	/**
	 * @covers ::wp_presence_woo_orders_on_update_order
	 */
	public function test_marks_the_order_screen_stale_with_who_saved_it() {
		wp_set_current_user( self::$manager_id );

		do_action( 'woocommerce_update_order', 72 );

		$revision = wp_presence_get_screen_revision( 'wc/order/72' );
		$this->assertNotNull( $revision );
		$this->assertSame( self::$manager_id, (int) $revision['actor_id'] );
	}

	/**
	 * A payment arrives with no one logged in, and still changes the order.
	 *
	 * @covers ::wp_presence_woo_orders_on_update_order
	 */
	public function test_marks_the_order_screen_stale_when_no_one_is_logged_in() {
		wp_set_current_user( 0 );

		do_action( 'woocommerce_update_order', 72 );

		$this->assertNotNull( wp_presence_get_screen_revision( 'wc/order/72' ) );
	}

	/**
	 * @covers ::wp_presence_woo_orders_on_update_order
	 */
	public function test_leaves_orders_alone_with_hpos_off() {
		OrderUtil::$enabled = false;
		wp_set_current_user( self::$manager_id );

		do_action( 'woocommerce_update_order', 72 );

		$this->assertNull( wp_presence_get_screen_revision( 'wc/order/72' ) );
	}

	/**
	 * Someone with the order open hears about another person's save on their next Heartbeat.
	 *
	 * @covers ::wp_presence_woo_orders_on_update_order
	 */
	public function test_heartbeat_tells_whoever_has_the_order_open() {
		wp_set_current_user( self::$manager_id );
		do_action( 'woocommerce_update_order', 72 );

		wp_set_current_user( self::$admin_id );
		$response = wp_presence_screen_heartbeat_received(
			array(),
			array( 'presence-screen-ping' => array( 'key' => 'wc/order/72' ) ),
			'woocommerce_page_wc-orders'
		);

		$this->assertSame( self::$manager_id, (int) $response['presence-screen-rev']['actor_id'] );
		$this->assertFalse( $response['presence-screen-rev']['actor_is_me'] );
	}
}
