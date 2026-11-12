<?php
/**
 * Tests for the WooCommerce order screen keys.
 *
 * @package Presence_Woo_Orders
 *
 * @group presence
 */

class WP_Test_Presence_Woo_Orders_Screen_Key extends WP_Presence_UnitTestCase {

	/**
	 * An administrator.
	 *
	 * @var int
	 */
	private static $admin_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::$admin_id );
	}

	/**
	 * @covers ::wp_presence_woo_orders_current_screen_key
	 */
	public function test_keys_the_hpos_order_edit_screen_by_its_order() {
		set_current_screen( 'woocommerce_page_wc-orders' );
		$_GET['action'] = 'edit';
		$_GET['id']     = '72';

		$this->assertSame( 'wc/order/72', wp_presence_current_screen_key() );
	}

	/**
	 * @covers ::wp_presence_woo_orders_current_screen_key
	 */
	public function test_leaves_the_orders_list_without_a_key() {
		set_current_screen( 'woocommerce_page_wc-orders' );

		$this->assertSame( '', wp_presence_current_screen_key() );
	}

	/**
	 * @covers ::wp_presence_woo_orders_current_screen_key
	 */
	public function test_leaves_an_edit_without_an_order_id_without_a_key() {
		set_current_screen( 'woocommerce_page_wc-orders' );
		$_GET['action'] = 'edit';
		$_GET['id']     = 'abc';

		$this->assertSame( '', wp_presence_current_screen_key() );
	}

	/**
	 * With HPOS off the order is a post, which Presence API already keys.
	 *
	 * @covers ::wp_presence_woo_orders_current_screen_key
	 */
	public function test_leaves_other_screens_to_presence_api() {
		set_current_screen( 'post' );
		$post_id         = self::factory()->post->create();
		$GLOBALS['post'] = get_post( $post_id );
		$_GET['action']  = 'edit';
		$_GET['id']      = '72';

		$this->assertSame( 'post/' . $post_id, wp_presence_current_screen_key() );
	}

	/**
	 * The stale-screen route only accepts lowercase letters, digits, slashes, underscores and dashes.
	 *
	 * @covers ::wp_presence_woo_orders_screen_key
	 */
	public function test_key_fits_the_stale_screen_route() {
		$this->assertMatchesRegularExpression( '#^[a-z0-9/_-]+$#', wp_presence_woo_orders_screen_key( 72 ) );
	}
}
