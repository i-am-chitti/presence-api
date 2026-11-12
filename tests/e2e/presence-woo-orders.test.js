/**
 * Presence for WooCommerce Orders — E2E Tests
 *
 * Asserts an HPOS order open in one browser shows the stale-screen notice when
 * a shop manager changes its status from the Orders list, which WooCommerce
 * alone doesn't warn about, so clicking Update would put the old status back.
 *
 * WooCommerce isn't part of the shared environment, so this spec installs a
 * pinned version, switches HPOS on, and uninstalls it with its data after.
 *
 * Run from plugin root:
 *   npx playwright test --config tests/e2e/playwright.config.js tests/e2e/presence-woo-orders.test.js
 *
 * @package WordPress
 */
import { execSync } from 'node:child_process';
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

const WOOCOMMERCE_VERSION = '11.3.0';

const MANAGER = {
	username: 'presencetestshopmanager',
	password: 'password',
	displayName: 'Sam Manager',
};

/**
 * Runs a WP-CLI command on the development instance and returns its last line.
 *
 * The `--` keeps wp-env from reading WP-CLI's own flags, such as `--version`.
 *
 * @param {string} command The WP-CLI command, without `wp`.
 * @return {string} The command's last line of output.
 */
function wpCli( command ) {
	return execSync( `npx wp-env run cli -- wp ${ command }`, {
		encoding: 'utf8',
		stdio: [ 'ignore', 'pipe', 'pipe' ],
		timeout: 120_000,
	} )
		.trim()
		.split( '\n' )
		.pop()
		.trim();
}

/**
 * Creates an order and returns its ID.
 *
 * @return {string} The order ID.
 */
function createOrder() {
	return wpCli(
		`eval '$order = wc_create_order(); $order->save(); echo $order->get_id();'`
	);
}

/**
 * Opens an order's edit screen and waits for Heartbeat.
 *
 * @param {import('@playwright/test').Page} page    The page.
 * @param {string}                          orderId The order ID.
 */
async function openOrder( page, orderId ) {
	await page.goto(
		`/wp-admin/admin.php?page=wc-orders&action=edit&id=${ orderId }`
	);
	await page.waitForFunction(
		() =>
			typeof wp !== 'undefined' && wp.heartbeat && wp.heartbeat.connectNow
	);
}

/**
 * Logs the shop manager in, in a browser context of their own.
 *
 * @param {import('@playwright/test').Browser} browser The browser.
 * @return {Promise<import('@playwright/test').BrowserContext>} The shop manager's context.
 */
async function logInManager( browser ) {
	const context = await browser.newContext( {
		storageState: { cookies: [], origins: [] },
	} );
	const page = await context.newPage();
	await page.goto( '/wp-login.php' );
	await page.locator( '#user_login' ).fill( MANAGER.username );
	await page.locator( '#user_pass' ).fill( MANAGER.password );
	await page.locator( '#wp-submit' ).click();
	await page.waitForURL( /wp-admin/ );
	return context;
}

test.describe( 'Presence for WooCommerce Orders', () => {
	test.beforeAll( () => {
		wpCli(
			`plugin install woocommerce --version=${ WOOCOMMERCE_VERSION } --activate`
		);
		wpCli( 'plugin activate presence-woo-orders' );
		// Otherwise the next admin screen redirects to WooCommerce's setup wizard.
		wpCli( 'transient delete _wc_activation_redirect' );
		wpCli( 'wc hpos enable' );
		wpCli(
			`user create ${ MANAGER.username } ${ MANAGER.username }@example.com --role=shop_manager --user_pass=${ MANAGER.password } --display_name="${ MANAGER.displayName }"`
		);
	} );

	test.afterAll( () => {
		wpCli( `user delete ${ MANAGER.username } --yes` );
		wpCli( 'plugin deactivate presence-woo-orders woocommerce' );
		wpCli(
			`--exec='define( "WC_REMOVE_ALL_DATA", true );' plugin uninstall woocommerce`
		);
		// HPOS leaves a placeholder post per order, which the uninstall keeps.
		wpCli(
			`db query "DELETE FROM wp_posts WHERE post_type LIKE 'shop_order%'"`
		);
		wpCli( 'option delete wp_presence_screen_revisions' );
		wpCli(
			`db query "DELETE FROM wp_presence WHERE room LIKE 'wc/order:%'"`
		);
	} );

	test( 'warns an open HPOS order when a shop manager changes its status from the Orders list', async ( {
		page,
		browser,
	} ) => {
		const orderId = createOrder();
		await openOrder( page, orderId );

		const managerContext = await logInManager( browser );
		const manager = managerContext.pages()[ 0 ];

		// The row's Processing button, which marks the order through admin-ajax.
		await manager.goto( '/wp-admin/admin.php?page=wc-orders' );
		const markProcessing = await manager
			.locator( `#order-${ orderId } .wc-action-button-processing` )
			.getAttribute( 'href' );
		await manager.goto( markProcessing );
		await managerContext.close();

		await Promise.all( [
			expect( page.locator( '.wp-presence-stale-notice' ) ).toContainText(
				`${ MANAGER.displayName } updated this screen`,
				{ timeout: 8000 }
			),
			page.evaluate( () => wp.heartbeat.connectNow() ),
		] );
	} );

	test( 'shows each person on an order who else has it open', async ( {
		page,
		browser,
	} ) => {
		const orderId = createOrder();
		await openOrder( page, orderId );
		await page.evaluate( () => wp.heartbeat.connectNow() );

		const managerContext = await logInManager( browser );
		const manager = managerContext.pages()[ 0 ];
		await openOrder( manager, orderId );

		const viewers = '.presence-woo-orders-viewers';
		await Promise.all( [
			expect( manager.locator( viewers ) ).toHaveText(
				'Also on this order: admin',
				{ timeout: 8000 }
			),
			manager.evaluate( () => wp.heartbeat.connectNow() ),
		] );
		await Promise.all( [
			expect( page.locator( viewers ) ).toHaveText(
				`Also on this order: ${ MANAGER.displayName }`,
				{ timeout: 8000 }
			),
			page.evaluate( () => wp.heartbeat.connectNow() ),
		] );

		await managerContext.close();
	} );
} );
