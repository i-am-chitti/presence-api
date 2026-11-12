<?php
/**
 * Stands in for WooCommerce's OrderUtil, which the suite doesn't load, so tests can switch HPOS on and off.
 *
 * @package Presence_Woo_Orders
 */

namespace Automattic\WooCommerce\Utilities;

class OrderUtil {

	/**
	 * Whether HPOS is on.
	 *
	 * @var bool
	 */
	public static $enabled = false;

	public static function custom_orders_table_usage_is_enabled() {
		return self::$enabled;
	}
}
