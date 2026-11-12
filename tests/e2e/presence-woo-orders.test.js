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
	} );

	test( 'warns an open HPOS order when a shop manager changes its status from the Orders list', async ( {
		page,
		browser,
	} ) => {
		const orderId = wpCli(
			`eval '$order = wc_create_order(); $order->save(); echo $order->get_id();'`
		);

		await page.goto(
			`/wp-admin/admin.php?page=wc-orders&action=edit&id=${ orderId }`
		);
		await page.waitForFunction(
			() =>
				typeof wp !== 'undefined' &&
				wp.heartbeat &&
				wp.heartbeat.connectNow
		);

		const managerContext = await browser.newContext( {
			storageState: { cookies: [], origins: [] },
		} );
		const manager = await managerContext.newPage();
		await manager.goto( '/wp-login.php' );
		await manager.fill( '#user_login', MANAGER.username );
		await manager.fill( '#user_pass', MANAGER.password );
		await manager.click( '#wp-submit' );
		await manager.waitForURL( /wp-admin/ );

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
} );
