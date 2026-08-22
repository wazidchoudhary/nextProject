import { chromium } from 'playwright';

const base = 'http://localhost:8088';
const browser = await chromium.launch( { executablePath: '/opt/pw-browsers/chromium', args: [ '--no-sandbox' ] } );
const context = await browser.newContext( { viewport: { width: 1440, height: 1000 } } );
const page = await context.newPage();
const errors = [];
page.on( 'pageerror', ( e ) => errors.push( e.message ) );

await page.goto( `${ base }/wp-login.php`, { waitUntil: 'domcontentloaded' } );
await page.fill( '#user_login', 'bhcadmin' );
await page.fill( '#user_pass', 'Demo!Pass#2026' );
await page.click( '#wp-submit' );
await page.waitForLoadState( 'domcontentloaded' );
console.log( 'logged in:', page.url().includes( 'wp-admin' ) );

const screens = [
	[ 'admin-dashboard', '/wp-admin/admin.php?page=bhc-commerce' ],
	[ 'admin-health', '/wp-admin/admin.php?page=bhc-commerce-health' ],
	[ 'admin-settings', '/wp-admin/admin.php?page=bhc-commerce-settings' ],
	[ 'admin-scheduler', '/wp-admin/admin.php?page=wc-status&tab=action-scheduler&s=bhc&status=pending' ],
];

for ( const [ name, path ] of screens ) {
	const response = await page.goto( base + path, { waitUntil: 'domcontentloaded' } );
	await page.waitForTimeout( 400 );
	await page.screenshot( { path: `/tmp/shots/${ name }.png`, fullPage: false } );
	const text = await page.locator( 'body' ).innerText();
	console.log( `${ name }: ${ response.status() } | notices: ${ /Fatal error|Warning:|Notice:/.test( text ) ? 'PHP OUTPUT PRESENT' : 'clean' }` );
}

// Product editor panel
const productEdit = await page.evaluate( async () => {
	const res = await fetch( '/wp-json/wp/v2/product?per_page=1', { credentials: 'same-origin' } );
	const data = await res.json().catch( () => [] );
	return Array.isArray( data ) && data[ 0 ] ? data[ 0 ].id : 0;
} );

if ( productEdit ) {
	await page.goto( `${ base }/wp-admin/post.php?post=${ productEdit }&action=edit`, { waitUntil: 'domcontentloaded' } );
	await page.waitForTimeout( 1200 );
	const tab = await page.locator( 'li.bhc_craft_options, a[href="#bhc_craft_product_data"]' ).count();
	console.log( 'product editor: Bone Horn Crafts tab present:', tab > 0 );

	if ( tab > 0 ) {
		await page.locator( 'a[href="#bhc_craft_product_data"]' ).first().click();
		await page.waitForTimeout( 400 );
		await page.screenshot( { path: '/tmp/shots/admin-product-panel.png' } );
	}
}

console.log( 'JS errors:', errors.length ? errors.join( ' | ' ) : 'none' );
await browser.close();
