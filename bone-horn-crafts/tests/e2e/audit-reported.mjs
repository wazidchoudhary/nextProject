/**
 * Reproduction harness for the reported production defects.
 *
 * This is not a regression suite — it is the evidence-gathering pass that runs
 * before fixing anything, so each report is confirmed, reclassified or refuted
 * against a real browser rather than by reading the source. It prints a verdict
 * per item and always exits 0: its job is to describe the store, not to gate.
 */

import { launchBrowser, BASE_URL } from './browser.mjs';

const base = BASE_URL;
const browser = await launchBrowser();
const context = await browser.newContext( { viewport: { width: 1440, height: 1000 } } );
const page = await context.newPage();

const errors = [];
page.on( 'pageerror', ( e ) => errors.push( e.message ) );

const report = [];

/**
 * Records one finding.
 *
 * @param {string} area    Which report this belongs to.
 * @param {string} verdict CONFIRMED, WORKS, or PARTIAL.
 * @param {string} detail  What was observed.
 */
function finding( area, verdict, detail ) {
	report.push( { area, verdict, detail } );
	console.log( `[${ verdict.padEnd( 9 ) }] ${ area }\n            ${ detail }` );
}

// ---------------------------------------------------------------------------
console.log( '\n## 1. Registration / signup\n' );

await page.goto( `${ base }/my-account/`, { waitUntil: 'domcontentloaded' } );
const accountBody = await page.locator( 'body' ).innerText();
const hasRegisterForm = await page.locator( 'form.register, #reg_email, input[name="register"]' ).count();

finding(
	'signup: my-account',
	hasRegisterForm > 0 ? 'WORKS' : 'CONFIRMED',
	hasRegisterForm > 0
		? 'A registration form is present.'
		: `No registration form. Page offers only: ${ accountBody.slice( 0, 90 ).replace( /\s+/g, ' ' ) }`,
);

// ---------------------------------------------------------------------------
console.log( '\n## 2. Header search\n' );

await page.goto( base, { waitUntil: 'domcontentloaded' } );

const searchInput = page.locator( 'header input[type="search"]' ).first();
const searchCount = await searchInput.count();

if ( searchCount ) {
	await searchInput.fill( 'horn' );
	await searchInput.press( 'Enter' );
	await page.waitForLoadState( 'domcontentloaded' );
	await page.waitForTimeout( 800 );

	const url = page.url();
	const results = await page.locator( '.bhc-card, .product' ).count();
	const bodyText = await page.locator( 'body' ).innerText();
	const noResults = /no products|nothing found|no results/i.test( bodyText );

	finding(
		'search: header submit',
		results > 0 ? 'WORKS' : 'CONFIRMED',
		`URL ${ url } — ${ results } result card(s)${ noResults ? ', page says no results' : '' }`,
	);
} else {
	finding( 'search: header submit', 'CONFIRMED', 'No search input found in the header.' );
}

// ---------------------------------------------------------------------------
console.log( '\n## 3. Wishlist as a signed-out visitor\n' );

await page.goto( `${ base }/shop/`, { waitUntil: 'domcontentloaded' } );
await page.waitForTimeout( 500 );

const wishBtn = page.locator( '[data-bhc-wishlist-toggle], .bhc-card__wishlist' ).first();

if ( await wishBtn.count() ) {
	const before = page.url();
	const responses = [];

	page.on( 'response', ( r ) => {
		if ( r.url().includes( '/wishlist' ) ) {
			responses.push( `${ r.status() } ${ r.url().split( '/wp-json' )[ 1 ] || r.url() }` );
		}
	} );

	await wishBtn.click();
	await page.waitForTimeout( 1800 );

	const after = page.url();
	const redirected = after !== before;
	const pressed = await wishBtn.getAttribute( 'aria-pressed' ).catch( () => null );

	finding(
		'wishlist: guest toggle',
		redirected ? 'CONFIRMED' : ( 'true' === pressed ? 'WORKS' : 'PARTIAL' ),
		redirected
			? `Navigated away to ${ after }`
			: `Stayed put. aria-pressed=${ pressed }. API: ${ responses.join( ' | ' ) || 'no wishlist request seen' }`,
	);
} else {
	finding( 'wishlist: guest toggle', 'CONFIRMED', 'No wishlist control found on a product card.' );
}

// Wishlist page itself
await page.goto( `${ base }/wishlist/`, { waitUntil: 'domcontentloaded' } );
const wishPageUrl = page.url();

finding(
	'wishlist: page access',
	wishPageUrl.includes( 'wishlist' ) ? 'WORKS' : 'CONFIRMED',
	`/wishlist/ resolved to ${ wishPageUrl }`,
);

// ---------------------------------------------------------------------------
console.log( '\n## 4. Cart\n' );

await page.goto( `${ base }/shop/`, { waitUntil: 'domcontentloaded' } );
const firstCard = page.locator( '.bhc-card__title a, .woocommerce-loop-product__link' ).first();
await firstCard.click();
await page.waitForLoadState( 'domcontentloaded' );

const addBtn = page.locator( 'button[name="add-to-cart"], .single_add_to_cart_button' ).first();

if ( await addBtn.count() ) {
	await addBtn.click();
	await page.waitForTimeout( 2200 );

	await page.goto( `${ base }/cart/`, { waitUntil: 'domcontentloaded' } );
	await page.waitForTimeout( 700 );

	const rows = await page.locator( '.cart_item, .wc-block-cart-items__row' ).count();
	const cartText = await page.locator( 'body' ).innerText();
	const empty = /cart is currently empty/i.test( cartText );

	finding(
		'cart: add and view',
		rows > 0 ? 'WORKS' : 'CONFIRMED',
		`${ rows } line(s) in cart${ empty ? ' — cart reports empty' : '' }`,
	);
} else {
	finding( 'cart: add and view', 'CONFIRMED', 'No add-to-cart control on the product page.' );
}

// ---------------------------------------------------------------------------
console.log( '\n## 5. Checkout layout shift when validation fails\n' );

await page.goto( `${ base }/checkout/`, { waitUntil: 'domcontentloaded' } );
await page.waitForTimeout( 900 );

const measure = async () => page.evaluate( () => {
	const el = document.querySelector( '#customer_details, .woocommerce-checkout' );
	const order = document.querySelector( '#order_review, .woocommerce-checkout-review-order' );
	return {
		customerTop: el ? Math.round( el.getBoundingClientRect().top + window.scrollY ) : null,
		orderTop: order ? Math.round( order.getBoundingClientRect().top + window.scrollY ) : null,
		orderLeft: order ? Math.round( order.getBoundingClientRect().left ) : null,
		docHeight: Math.round( document.body.scrollHeight ),
	};
} );

const beforeLayout = await measure();

const placeOrder = page.locator( '#place_order' );

if ( await placeOrder.count() ) {
	await placeOrder.click();
	await page.waitForTimeout( 2600 );

	const afterLayout = await measure();
	const noticeCount = await page.locator( '.woocommerce-error, .woocommerce-NoticeGroup' ).count();

	const shifted =
		beforeLayout.orderTop !== afterLayout.orderTop ||
		beforeLayout.orderLeft !== afterLayout.orderLeft;

	finding(
		'checkout: layout on error',
		shifted ? 'CONFIRMED' : 'WORKS',
		`notices=${ noticeCount } · order review top ${ beforeLayout.orderTop }→${ afterLayout.orderTop }, ` +
		`left ${ beforeLayout.orderLeft }→${ afterLayout.orderLeft }, doc height ${ beforeLayout.docHeight }→${ afterLayout.docHeight }`,
	);
} else {
	finding( 'checkout: layout on error', 'PARTIAL', 'No place-order button — cart may be empty.' );
}

// ---------------------------------------------------------------------------
console.log( '\n## 6. Filter loading feedback\n' );

await page.goto( `${ base }/shop/`, { waitUntil: 'domcontentloaded' } );
await page.waitForTimeout( 600 );

const filterInput = page.locator( '.bhc-filters input[type="checkbox"]' ).first();

if ( await filterInput.count() ) {
	const busyStates = await page.evaluate( () => {
		const grid = document.querySelector( '[data-bhc-results], .products, .bhc-grid' );
		return {
			hasAriaBusy: grid ? grid.hasAttribute( 'aria-busy' ) : false,
			hasLoadingClass: grid ? /load|busy|pending/i.test( grid.className ) : false,
			liveRegions: document.querySelectorAll( '[aria-live]' ).length,
		};
	} );

	await filterInput.check();
	await page.waitForTimeout( 220 );

	const during = await page.evaluate( () => {
		const grid = document.querySelector( '[data-bhc-results], .products, .bhc-grid' );
		return {
			ariaBusy: grid ? grid.getAttribute( 'aria-busy' ) : null,
			className: grid ? grid.className : null,
			spinner: document.querySelectorAll( '.bhc-spinner, .spinner, [data-bhc-loading]' ).length,
		};
	} );

	await page.waitForTimeout( 1600 );

	finding(
		'filters: loading indicator',
		'true' === during.ariaBusy || during.spinner > 0 ? 'WORKS' : 'CONFIRMED',
		`during fetch: aria-busy=${ during.ariaBusy }, spinner elements=${ during.spinner }, ` +
		`grid class="${ ( during.className || '' ).slice( 0, 60 ) }", live regions=${ busyStates.liveRegions }`,
	);
} else {
	finding( 'filters: loading indicator', 'PARTIAL', 'No filter checkbox found on the shop page.' );
}

// ---------------------------------------------------------------------------
console.log( '\n## 7. SEO output on a product page\n' );

await page.goto( `${ base }/shop/`, { waitUntil: 'domcontentloaded' } );
await page.locator( '.bhc-card__title a, .woocommerce-loop-product__link' ).first().click();
await page.waitForLoadState( 'domcontentloaded' );

const seo = await page.evaluate( () => {
	const blocks = [ ...document.querySelectorAll( 'script[type="application/ld+json"]' ) ];
	let types = [];

	for ( const b of blocks ) {
		try {
			const parsed = JSON.parse( b.textContent );
			const graph = parsed[ '@graph' ] || [ parsed ];
			types = types.concat( graph.map( ( n ) => n[ '@type' ] ) );
		} catch {
			types.push( 'UNPARSEABLE' );
		}
	}

	return {
		jsonLdBlocks: blocks.length,
		types,
		title: document.title,
		description: document.querySelector( 'meta[name="description"]' )?.content?.slice( 0, 70 ) || null,
		keywords: document.querySelector( 'meta[name="keywords"]' )?.content || null,
		canonical: document.querySelector( 'link[rel="canonical"]' )?.href || null,
		ogTags: document.querySelectorAll( 'meta[property^="og:"]' ).length,
		twitter: document.querySelectorAll( 'meta[name^="twitter:"]' ).length,
	};
} );

finding(
	'seo: product JSON-LD',
	seo.types.includes( 'Product' ) ? 'WORKS' : 'CONFIRMED',
	`${ seo.jsonLdBlocks } block(s), types: ${ seo.types.join( ', ' ) || 'none' }`,
);

finding(
	'seo: meta tags',
	seo.description && seo.canonical ? 'WORKS' : 'PARTIAL',
	`title="${ ( seo.title || '' ).slice( 0, 50 ) }" · description=${ seo.description ? 'yes' : 'MISSING' } · ` +
	`keywords=${ seo.keywords || 'absent' } · canonical=${ seo.canonical ? 'yes' : 'MISSING' } · ` +
	`og=${ seo.ogTags } · twitter=${ seo.twitter }`,
);

// ---------------------------------------------------------------------------
console.log( '\n## 8. Newsletter signup in the footer\n' );

const newsletter = await page.evaluate( () => {
	const form = document.querySelector( '.bhc-newsletter__form' );
	return form
		? { action: form.getAttribute( 'action' ), method: form.getAttribute( 'method' ) }
		: null;
} );

finding(
	'newsletter: footer form',
	newsletter && 'post' === ( newsletter.method || '' ).toLowerCase() ? 'WORKS' : 'CONFIRMED',
	newsletter
		? `Form ${ newsletter.method?.toUpperCase() } → ${ newsletter.action } (a GET to a page stores no subscriber)`
		: 'No newsletter form found.',
);

// ---------------------------------------------------------------------------
console.log( '\n## 9. Payment gateways\n' );

const gateways = await page.evaluate( async () => {
	const res = await fetch( '/?wc-ajax=get_refreshed_fragments' ).catch( () => null );
	return res ? res.status : null;
} );

finding( 'checkout: fragments endpoint', gateways === 200 ? 'WORKS' : 'PARTIAL', `wc-ajax status ${ gateways }` );

// ---------------------------------------------------------------------------
console.log( '\n' + '='.repeat( 74 ) );
console.log( 'SUMMARY' );
console.log( '='.repeat( 74 ) );

for ( const verdict of [ 'CONFIRMED', 'PARTIAL', 'WORKS' ] ) {
	const rows = report.filter( ( r ) => r.verdict === verdict );

	if ( rows.length ) {
		console.log( `\n${ verdict } (${ rows.length }):` );
		rows.forEach( ( r ) => console.log( `  · ${ r.area }` ) );
	}
}

console.log( `\nJS errors: ${ errors.length ? errors.join( ' | ' ) : 'none' }` );

await browser.close();
