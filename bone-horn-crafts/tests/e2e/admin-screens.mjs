/**
 * Admin screen smoke test.
 *
 * Loads every admin surface the plugin adds, as a logged-in administrator, and
 * asserts that each one actually rendered its own content. The point is to
 * catch a fatal, a missing capability or a panel that silently stopped
 * registering.
 *
 * Credentials default to the ones `bin/setup-demo.sh` installs. Override with
 * BHC_ADMIN_USER / BHC_ADMIN_PASS against any other store.
 */

import { launchBrowser, BASE_URL } from './browser.mjs';

const base = BASE_URL;
const user = process.env.BHC_ADMIN_USER || 'admin';
const pass = process.env.BHC_ADMIN_PASS || 'admin';

const failures = [];

/**
 * Records a pass or a failure.
 *
 * @param {string}  description What is being asserted.
 * @param {boolean} condition   Result.
 * @param {string}  detail      Extra context printed on failure.
 */
function assert( description, condition, detail = '' ) {
	if ( condition ) {
		console.log( `  ok   ${ description }` );
		return;
	}

	failures.push( description + ( detail ? ` (${ detail })` : '' ) );
	console.log( `  FAIL ${ description }${ detail ? ` — ${ detail }` : '' }` );
}

const browser = await launchBrowser();
const context = await browser.newContext( { viewport: { width: 1440, height: 1000 } } );
const page = await context.newPage();
const errors = [];
page.on( 'pageerror', ( e ) => errors.push( e.message ) );

console.log( '\n# Authentication' );

await page.goto( `${ base }/wp-login.php`, { waitUntil: 'domcontentloaded' } );
await page.fill( '#user_login', user );
await page.fill( '#user_pass', pass );
await page.click( '#wp-submit' );
await page.waitForLoadState( 'domcontentloaded' );

const loggedIn = page.url().includes( '/wp-admin' );

assert( `signed in as ${ user }`, loggedIn, page.url() );

// Every later assertion reads an authenticated screen. Without a session they
// would all read the login form and pass for the wrong reason, which is how
// this suite used to report four green admin screens while signed out.
if ( ! loggedIn ) {
	console.log( '\nCannot continue without an admin session.' );
	await browser.close();
	process.exit( 1 );
}

console.log( '\n# Plugin admin screens' );

const screens = [
	[ 'admin-dashboard', '/wp-admin/admin.php?page=bhc-commerce', 'Bone Horn Crafts — operations' ],
	[ 'admin-health', '/wp-admin/admin.php?page=bhc-commerce-health', 'System health' ],
	[ 'admin-settings', '/wp-admin/admin.php?page=bhc-commerce-settings', 'Commerce settings' ],
	[ 'admin-scheduler', '/wp-admin/admin.php?page=wc-status&tab=action-scheduler', 'Scheduled Actions' ],
];

for ( const [ name, path, marker ] of screens ) {
	const response = await page.goto( base + path, { waitUntil: 'domcontentloaded' } );
	await page.waitForTimeout( 400 );
	await page.screenshot( { path: `/tmp/shots/${ name }.png`, fullPage: false } );

	const text = await page.locator( 'body' ).innerText();

	assert( `${ name } responds 200`, 200 === response.status(), String( response.status() ) );
	assert( `${ name } renders its own heading`, text.includes( marker ), `expected "${ marker }"` );
	assert(
		`${ name } emits no PHP notices`,
		! /Fatal error|Warning:|Notice:|Deprecated:/.test( text ),
		'PHP output present',
	);
	assert( `${ name } is not the login form`, ! text.includes( 'Remember Me' ) );
}

console.log( '\n# WooCommerce management screens' );

// These are WooCommerce's own screens. They are asserted because the store is
// managed through them: if a plugin hook fatals here, the merchant cannot add a
// product or process an order at all.
const wooScreens = [
	[ 'woo-products', '/wp-admin/edit.php?post_type=product', 'All Products' ],
	[ 'woo-orders', '/wp-admin/admin.php?page=wc-orders', 'Orders' ],
	[ 'woo-new-product', '/wp-admin/post-new.php?post_type=product', 'Product data' ],
	[ 'woo-settings', '/wp-admin/admin.php?page=wc-settings', 'General' ],
	[ 'woo-reports', '/wp-admin/admin.php?page=wc-admin&path=%2Fanalytics%2Foverview', 'Analytics' ],
	[ 'woo-coupons', '/wp-admin/admin.php?page=wc-admin&path=%2Fmarketing%2Fcoupons', 'Coupons' ],
];

for ( const [ name, path, marker ] of wooScreens ) {
	const response = await page.goto( base + path, { waitUntil: 'domcontentloaded' } );
	await page.waitForTimeout( 900 );
	await page.screenshot( { path: `/tmp/shots/${ name }.png`, fullPage: false } );

	const text = await page.locator( 'body' ).innerText();

	assert( `${ name } responds 200`, 200 === response.status(), String( response.status() ) );
	assert( `${ name } renders`, text.includes( marker ), `expected "${ marker }"` );
	assert(
		`${ name } emits no PHP notices`,
		! /Fatal error|Warning:|Notice:/.test( text ),
		'PHP output present',
	);
}

console.log( '\n# Product editor panel' );

const productId = await page.evaluate( async () => {
	const res = await fetch( '/wp-json/wp/v2/product?per_page=1', { credentials: 'same-origin' } );
	const data = await res.json().catch( () => [] );
	return Array.isArray( data ) && data[ 0 ] ? data[ 0 ].id : 0;
} );

assert( 'a product exists to edit', productId > 0 );

if ( productId ) {
	await page.goto( `${ base }/wp-admin/post.php?post=${ productId }&action=edit`, {
		waitUntil: 'domcontentloaded',
	} );
	await page.waitForTimeout( 1200 );

	const tab = await page.locator( 'li.bhc_craft_options, a[href="#bhc_craft_product_data"]' ).count();

	assert( 'the Bone Horn Crafts product tab is registered', tab > 0 );

	if ( tab > 0 ) {
		await page.locator( 'a[href="#bhc_craft_product_data"]' ).first().click();
		await page.waitForTimeout( 400 );
		await page.screenshot( { path: '/tmp/shots/admin-product-panel.png' } );

		const panel = await page.locator( '#bhc_craft_product_data' ).innerText();

		for ( const field of [
			'Unit of sale',
			'Workshop lead time',
			'Wholesale price breaks',
			'HSN code',
			'Domestic GST rate',
			'Country of manufacture',
		] ) {
			assert( `panel exposes "${ field }"`, panel.includes( field ) );
		}
	}
}

console.log( '\n# Order editor panel' );

const orderRow = await page.goto( `${ base }/wp-admin/admin.php?page=wc-orders`, {
	waitUntil: 'domcontentloaded',
} );

assert( 'order list responds 200', 200 === orderRow.status() );

const orderLink = await page.locator( 'a[href*="page=wc-orders&action=edit"]' ).first();
const hasOrder = await orderLink.count();

assert( 'at least one order exists', hasOrder > 0 );

if ( hasOrder ) {
	await orderLink.click();
	await page.waitForLoadState( 'domcontentloaded' );
	await page.waitForTimeout( 800 );
	await page.screenshot( { path: '/tmp/shots/admin-order-panel.png' } );

	const body = await page.locator( 'body' ).innerText();

	assert( 'the workshop & export meta box renders', /workshop\s*&\s*export/i.test( body ) );
	assert( 'order editor emits no PHP notices', ! /Fatal error|Warning:|Notice:/.test( body ) );
}

console.log( '\n' + '-'.repeat( 60 ) );

assert( 'no uncaught JavaScript errors', 0 === errors.length, errors.join( ' | ' ) );

await browser.close();

if ( failures.length ) {
	console.log( `FAIL — ${ failures.length } failed` );
	failures.forEach( ( f ) => console.log( `  - ${ f }` ) );
	process.exit( 1 );
}

console.log( 'PASS — all admin screens rendered' );
