# Presence for WooCommerce Orders

A proof of concept for [#917](https://github.com/WordPress/presence-api/issues/917): a separate plugin that needs Presence API and WooCommerce, and builds on the Presence API's public functions the way any other plugin would.

WooCommerce already locks an order while someone edits it. With HPOS on, though, nothing tells you when an order changes while you have it open, such as a status change from the Orders list, and clicking Update puts the old status back. With HPOS off, Presence API's stale-screen notice already warns, since orders are still posts.

The plugin adds two things to an order's edit screen, for anyone who can edit orders:

- **Who else is on the order.** "Also on this order: Sam Manager", with HPOS on or off.
- **A warning when the order changes.** With HPOS on, any save of the order, such as a status change from the Orders list, marks the open order's screen stale, and Presence API's notice names who saved it.

## Playground

[![Launch Presence for WooCommerce Orders](https://img.shields.io/badge/Launch-3858E9?style=for-the-badge&logo=wordpress&logoColor=white)](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/WordPress/presence-api/main/blueprint-woo-orders.json)

Opens an order with HPOS on. A Playground tab is one person, so a demo helper plays Sam Manager, who has the order open too. Its button changes the order's status as Sam, and the warning appears within seconds.

## Setup

`npm run env:start` makes it available without activating it, since WooCommerce isn't part of the shared environment. Install and activate WooCommerce, then activate it after Presence API.

`npm run test:e2e -- presence-woo-orders` installs WooCommerce, switches HPOS on, checks both features with two people, then uninstalls WooCommerce and its data.

## How it works

It calls only these Presence API functions and filters:

| Function or filter | Used for |
|---|---|
| `wp_presence_current_screen_key` | Giving an HPOS order's edit screen the key `wc/order/{id}`, since it isn't a post |
| `wp_presence_bump_screen_revision()` | Marking that key stale on `woocommerce_update_order` |
| `wp_presence_exchange()` | Joining the order's room, `wc/order:{id}`, and reading who else is in it |

The room follows [Custom rooms](../../README.md#custom-rooms): the order screen sends its own Heartbeat key, `presence-woo-orders`, and the handler checks `edit_shop_orders` before it joins. Names are set as text, and a closed tab drops off the list once its row expires.

With HPOS off, the order screen is `post.php`, so the screen key and the warning are Presence API's own, and only the room is added.
