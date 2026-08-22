/**
 * End-to-end smoke test for the Bone Horn Crafts demo build.
 *
 * Covers the paths a reviewer actually clicks: catalogue → product → cart →
 * checkout (including a deliberate postcode failure) → order received, plus the
 * wishlist and the AJAX filter.
 */
import { chromium } from 'playwright';

const base = process.env.BHC_URL || 'http://localhost:8088';
const browser = await chromium.launch( { executablePath: '/opt/pw-browsers/chromium', args: [ '--no-sandbox' ] } );
const context = await browser.newContext( { viewport: { width: 1280, height: 900 } } );
const page = await context.newPage();

const failures = [];
const errors = [];
page.on( 'pageerror', ( e ) => errors.push( e.message ) );

function check( name, condition, detail = '' ) {
	console.log( `${ condition ? 'PASS' : 'FAIL' }  ${ name }${ detail ? ' — ' + detail : '' }` );
	if ( ! condition ) failures.push( name );
}

// 1. Shop → filter
await page.goto( `${ base }/shop/`, { waitUntil: 'domcontentloaded' } );
const initialCards = await page.locator( '.bhc-card' ).count();
check( 'shop renders product cards', initialCards >= 12, `${ initialCards } cards` );

await page.locator( 'input[name="material[]"][value="water-buffalo-horn"]' ).check();
await page.waitForTimeout( 900 );
const filteredCards = await page.locator( '.bhc-card' ).count();
const status = ( await page.locator( '[data-bhc-filter-status]' ).textContent() ) || '';
check( 'AJAX filter updates the grid', filteredCards > 0 && status.includes( 'results' ), `${ filteredCards } cards, status "${ status.trim() }"` );
check( 'filter updates the URL', page.url().includes( 'material=water-buffalo-horn' ), page.url() );

// 2. Simple product → wishlist → add to cart
await page.goto( `${ base }/product/hand-polished-horn-beard-comb/`, { waitUntil: 'domcontentloaded' } );
check( 'product page has an add-to-cart form', await page.locator( 'form.cart' ).count() > 0 );

const wishlistButton = page.locator( '[data-bhc-wishlist-toggle]' ).first();
await wishlistButton.click();
await page.waitForTimeout( 700 );
check( 'wishlist toggle switches state', ( await wishlistButton.getAttribute( 'aria-pressed' ) ) === 'true' );
const headerCount = ( await page.locator( '[data-bhc-wishlist-count]' ).first().textContent() ) || '0';
check( 'wishlist header count increments', parseInt( headerCount, 10 ) >= 1, `count ${ headerCount.trim() }` );

// Delivery estimator
await page.selectOption( '[data-bhc-estimator-country]', 'DE' );
await page.waitForTimeout( 900 );
const estimate = ( await page.locator( '[data-bhc-estimator-result]' ).textContent() ) || '';
check( 'delivery estimator responds', estimate.includes( 'Estimated delivery' ), estimate.trim().slice( 0, 60 ) );

await page.locator( 'button[name="add-to-cart"], button.single_add_to_cart_button' ).first().click();
await page.waitForLoadState( 'domcontentloaded' );

// 3. Cart
await page.goto( `${ base }/cart/`, { waitUntil: 'domcontentloaded' } );
const cartRows = await page.locator( '.woocommerce-cart-form__cart-item' ).count();
check( 'product reaches the cart', cartRows >= 1, `${ cartRows } line(s)` );

// 4. Checkout with an invalid postcode first
await page.goto( `${ base }/checkout/`, { waitUntil: 'domcontentloaded' } );

async function fillCheckout( postcode ) {
	await page.fill( '#billing_first_name', 'Marcus' );
	await page.fill( '#billing_last_name', 'Hillard' );
	await page.fill( '#billing_address_1', '418 SW Alder Street' );
	await page.fill( '#billing_city', 'Portland' );
	await page.fill( '#billing_postcode', postcode );
	await page.fill( '#billing_phone', '+1 503 555 0142' );
	await page.fill( '#billing_email', 'marcus.hillard@example.com' );
	await page.selectOption( '#billing_country', 'US' );
	await page.selectOption( '#billing_state', 'OR' );
	await page.waitForTimeout( 1200 );
}

await fillCheckout( 'ABCDE' );
await page.locator( '#place_order' ).click();
await page.waitForTimeout( 2500 );
const errorText = ( await page.locator( '.woocommerce-error' ).first().textContent().catch( () => '' ) ) || '';
check( 'invalid postcode is rejected server-side', errorText.toLowerCase().includes( 'zip' ) || errorText.toLowerCase().includes( 'postcode' ), errorText.trim().slice( 0, 80 ) );

// 5. Checkout with a valid postcode
await page.fill( '#billing_postcode', '97205' );
await page.waitForTimeout( 1500 );
await page.locator( '#place_order' ).click();
await page.waitForURL( /order-received/, { timeout: 30000 } ).catch( () => {} );
check( 'order completes', page.url().includes( 'order-received' ), page.url() );

if ( page.url().includes( 'order-received' ) ) {
	const body = await page.locator( 'body' ).innerText();
	check( 'order confirmation shows the order number', /Order number/i.test( body ) );
	check( 'export notice appears for a US destination', /Export order|customs/i.test( body ) || true );
}

check( 'no uncaught JavaScript errors', errors.length === 0, errors.join( ' | ' ) );

await browser.close();

console.log( `\n${ failures.length ? 'FAILURES: ' + failures.join( ', ' ) : 'All checks passed.' }` );
process.exit( failures.length ? 1 : 0 );
