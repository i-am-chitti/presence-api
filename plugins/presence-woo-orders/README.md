# Presence for WooCommerce Orders

A proof of concept for [#917](https://github.com/WordPress/presence-api/issues/917): a separate plugin that needs Presence API and WooCommerce, and builds on the Presence API's public functions the way any other plugin would.

WooCommerce already locks an order while someone edits it. With HPOS on, though, nothing tells you when an order changes while you have it open, such as a status change from the Orders list, and clicking Update puts the old status back. With HPOS off, Presence API's stale-screen notice already warns, since orders are still posts.

## Setup

`npm run env:start` makes it available without activating it, since WooCommerce isn't part of the shared environment. Install and activate WooCommerce, then activate it after Presence API.
