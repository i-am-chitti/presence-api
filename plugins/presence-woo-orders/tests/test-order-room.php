<?php
/**
 * Tests for the order room, which shows who else has an order open.
 *
 * @package Presence_Woo_Orders
 *
 * @group presence
 */

class WP_Test_Presence_Woo_Orders_Order_Room extends WP_Presence_UnitTestCase {

	/**
	 * Someone with the order open.
	 *
	 * @var int
	 */
	private static $first_id;

	/**
	 * Someone else with the same order open.
	 *
	 * @var int
	 */
	private static $second_id;

	/**
	 * Someone who can't edit orders.
	 *
	 * @var int
	 */
	private static $author_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$first_id  = $factory->user->create( array( 'display_name' => 'Ada Admin' ) );
		self::$second_id = $factory->user->create( array( 'display_name' => 'Sam Manager' ) );
		self::$author_id = $factory->user->create( array( 'role' => 'author' ) );

		// WooCommerce gives shop managers this capability, but the suite doesn't load WooCommerce.
		foreach ( array( self::$first_id, self::$second_id ) as $user_id ) {
			get_userdata( $user_id )->add_cap( 'edit_shop_orders' );
		}
	}

	public function set_up() {
		parent::set_up();
		// With HPOS off WooCommerce edits orders on post.php, which needs the post type registered.
		register_post_type( 'shop_order', array( 'show_ui' => true ) );
	}

	public function tear_down() {
		unregister_post_type( 'shop_order' );
		parent::tear_down();
	}

	/**
	 * Sends a Heartbeat tick from an order's screen as a user.
	 *
	 * @param int $user_id  The user.
	 * @param int $order_id The order.
	 * @return array The Heartbeat response.
	 */
	private function tick( $user_id, $order_id ) {
		wp_set_current_user( $user_id );
		return wp_presence_woo_orders_heartbeat_received( array(), array( 'presence-woo-orders' => $order_id ) );
	}

	/**
	 * Enqueues the room's script against an empty queue, since wp_scripts() outlives each test.
	 */
	private function enqueue() {
		wp_deregister_script( 'presence-woo-orders-room' );
		wp_scripts()->queue = array();
		wp_scripts()->done  = array();

		wp_presence_woo_orders_enqueue_order_room();
	}

	/**
	 * @covers ::wp_presence_woo_orders_heartbeat_received
	 */
	public function test_each_person_on_an_order_sees_the_other() {
		$this->assertSame( array(), $this->tick( self::$first_id, 72 )['presence-woo-orders'] );
		$this->assertSame( array( 'Ada Admin' ), $this->tick( self::$second_id, 72 )['presence-woo-orders'] );
		$this->assertSame( array( 'Sam Manager' ), $this->tick( self::$first_id, 72 )['presence-woo-orders'] );
	}

	/**
	 * @covers ::wp_presence_woo_orders_heartbeat_received
	 */
	public function test_people_on_different_orders_dont_see_each_other() {
		$this->tick( self::$first_id, 72 );

		$this->assertSame( array(), $this->tick( self::$second_id, 73 )['presence-woo-orders'] );
	}

	/**
	 * @covers ::wp_presence_woo_orders_heartbeat_received
	 */
	public function test_writes_a_row_in_the_orders_room() {
		$this->tick( self::$first_id, 72 );

		$entries = wp_get_presence( 'wc/order:72' );
		$this->assertCount( 1, $entries );
		$this->assertSame( 'presence-woo-orders-' . self::$first_id, $entries[0]->client_id );
	}

	/**
	 * Another plugin's rows in the same room aren't people on this screen.
	 *
	 * @covers ::wp_presence_woo_orders_heartbeat_received
	 */
	public function test_ignores_other_clients_in_the_room() {
		wp_set_presence( 'wc/order:72', 'other-plugin-' . self::$second_id, array(), array( 'user_id' => self::$second_id ) );

		$this->assertSame( array(), $this->tick( self::$first_id, 72 )['presence-woo-orders'] );
	}

	/**
	 * @covers ::wp_presence_woo_orders_heartbeat_received
	 */
	public function test_ignores_someone_who_cant_edit_orders() {
		$response = $this->tick( self::$author_id, 72 );

		$this->assertArrayNotHasKey( 'presence-woo-orders', $response );
		$this->assertSame( array(), wp_get_presence( 'wc/order:72' ) );
	}

	/**
	 * @covers ::wp_presence_woo_orders_heartbeat_received
	 */
	public function test_ignores_a_tick_without_an_order() {
		$this->assertArrayNotHasKey( 'presence-woo-orders', $this->tick( self::$first_id, 0 ) );
	}

	/**
	 * @covers ::wp_presence_woo_orders_screen_order_id
	 */
	public function test_reads_the_order_from_the_hpos_edit_screen() {
		$_GET['action'] = 'edit';
		$_GET['id']     = '72';

		$this->assertSame( 72, wp_presence_woo_orders_screen_order_id( WP_Screen::get( 'woocommerce_page_wc-orders' ) ) );
	}

	/**
	 * @covers ::wp_presence_woo_orders_screen_order_id
	 */
	public function test_reads_the_order_from_the_post_edit_screen_with_hpos_off() {
		$_GET['post'] = '72';

		$this->assertSame( 72, wp_presence_woo_orders_screen_order_id( WP_Screen::get( 'shop_order' ) ) );
	}

	/**
	 * @covers ::wp_presence_woo_orders_screen_order_id
	 */
	public function test_reads_no_order_from_the_orders_list_or_a_new_order() {
		$this->assertSame( 0, wp_presence_woo_orders_screen_order_id( WP_Screen::get( 'woocommerce_page_wc-orders' ) ) );
		$this->assertSame( 0, wp_presence_woo_orders_screen_order_id( WP_Screen::get( 'shop_order' ) ) );
	}

	/**
	 * @covers ::wp_presence_woo_orders_enqueue_order_room
	 */
	public function test_loads_the_room_on_an_order_edit_screen() {
		wp_set_current_user( self::$first_id );
		set_current_screen( 'woocommerce_page_wc-orders' );
		$_GET['action'] = 'edit';
		$_GET['id']     = '72';

		$this->enqueue();

		$this->assertTrue( wp_script_is( 'presence-woo-orders-room', 'enqueued' ) );
		$this->assertStringContainsString( '"orderId":72', implode( '', wp_scripts()->get_data( 'presence-woo-orders-room', 'before' ) ) );
	}

	/**
	 * @covers ::wp_presence_woo_orders_enqueue_order_room
	 */
	public function test_skips_the_room_off_an_order_or_without_the_capability() {
		wp_set_current_user( self::$first_id );
		set_current_screen( 'woocommerce_page_wc-orders' );
		$this->enqueue();
		$this->assertFalse( wp_script_is( 'presence-woo-orders-room', 'enqueued' ) );

		wp_set_current_user( self::$author_id );
		$_GET['action'] = 'edit';
		$_GET['id']     = '72';
		$this->enqueue();
		$this->assertFalse( wp_script_is( 'presence-woo-orders-room', 'enqueued' ) );
	}
}
