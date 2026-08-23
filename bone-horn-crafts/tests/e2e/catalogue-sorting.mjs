/**
 * Catalogue sorting regression suite.
 *
 * Sorting broke in a way no unit test could see: WooCommerce's orderby template
 * renders a bare <select> and relies on one jQuery handler inside WooCommerce's
 * `woocommerce` frontend script to submit the form. The theme dequeues that
 * script outside cart/checkout/account for good performance reasons, and the
 * dropdown went dead on the shop, on every category archive and on search.
 *
 * Nothing in PHP was wrong, so this can only be caught by driving a browser:
 * change the select, then assert the prices actually came back in order.
 *
 * Exits non-zero on failure so it can gate a deploy.
 */

import { launchBrowser, BASE_URL } from './browser.mjs';

const base = BASE_URL;
const browser = await launchBrowser();
const context = await browser.newContext( { viewport: { width: 1440, height: 1000 } } );
const page = await context.newPage();

const failures = [];
const pageErrors = [];

page.on( 'pageerror', ( e ) => pageErrors.push( e.message ) );

/**
 * Records a check result.
 *
 * @param {boolean} passed Whether the assertion held.
 * @param {string}  label  What was checked.
 * @param {string}  detail Observed value.
 */
function check( passed, label, detail ) {
	console.log( `[${ passed ? 'PASS' : 'FAIL' }] ${ label }\n       ${ detail }` );

	if ( ! passed ) {
		failures.push( label );
	}
}

/**
 * Reads the displayed prices from the product grid, in DOM order.
 *
 * Variable products render a range ("$12.00 – $30.00"); the first number is the
 * one WooCommerce sorts a low-to-high list on, so only the first is taken.
 *
 * @param {import('playwright').Page} target Page under test.
 *
 * @return {Promise<number[]>} Prices in grid order.
 */
async function gridPrices( target ) {
	return target.$$eval( 'ul.products li.product .price', ( nodes ) =>
		nodes
			.map( ( node ) => {
				const match = node.textContent.replace( /,/g, '' ).match( /\d+(\.\d+)?/ );

				return match ? parseFloat( match[ 0 ] ) : null;
			} )
			.filter( ( value ) => value !== null ),
	);
}

/**
 * Whether a list is sorted.
 *
 * @param {number[]} values    Values in display order.
 * @param {boolean}  ascending Expected direction.
 *
 * @return {boolean} True when every neighbouring pair is in order.
 */
function isSorted( values, ascending ) {
	return values.every( ( value, index ) => {
		if ( index === 0 ) {
			return true;
		}

		return ascending ? value >= values[ index - 1 ] : value <= values[ index - 1 ];
	} );
}

/**
 * Selects an ordering option and waits for the resulting navigation.
 *
 * @param {import('playwright').Page} target  Page under test.
 * @param {string}                    orderby Option value.
 *
 * @return {Promise<void>} Resolves once the sorted page has loaded.
 */
async function sortBy( target, orderby ) {
	await Promise.all( [
		target.waitForNavigation( { waitUntil: 'domcontentloaded' } ),
		target.selectOption( 'form.woocommerce-ordering select.orderby', orderby ),
	] );
}

for ( const path of [ '/shop/', '/?s=bone&post_type=product' ] ) {
	console.log( `\n## ${ path }\n` );

	await page.goto( `${ base }${ path }`, { waitUntil: 'domcontentloaded' } );

	const hasForm = await page.locator( 'form.woocommerce-ordering select.orderby' ).count();

	if ( ! hasForm ) {
		check( false, `${ path } renders an ordering control`, 'No form.woocommerce-ordering found.' );

		continue;
	}

	// The regression itself: changing the select must navigate. Without the
	// theme's own handler this resolves to a timeout, which is the bug.
	let navigated = true;

	try {
		await sortBy( page, 'price' );
	} catch {
		navigated = false;
	}

	check(
		navigated,
		`${ path } submits on change`,
		navigated
			? `Navigated to ${ page.url() }`
			: 'Changing the dropdown did not submit the form — the sort handler is missing.',
	);

	if ( ! navigated ) {
		continue;
	}

	check(
		page.url().includes( 'orderby=price' ),
		`${ path } carries orderby in the URL`,
		page.url(),
	);

	const ascending = await gridPrices( page );

	check(
		ascending.length > 1 && isSorted( ascending, true ),
		`${ path } sorts price low to high`,
		ascending.slice( 0, 8 ).join( ', ' ) || 'no prices found',
	);

	await sortBy( page, 'price-desc' );

	const descending = await gridPrices( page );

	check(
		descending.length > 1 && isSorted( descending, false ),
		`${ path } sorts price high to low`,
		descending.slice( 0, 8 ).join( ', ' ) || 'no prices found',
	);

	// A selection that survives the reload is what tells a shopper the sort
	// applied. It reads from the URL, so it breaks independently of the query.
	const selected = await page.$eval(
		'form.woocommerce-ordering select.orderby',
		( node ) => node.value,
	);

	check(
		selected === 'price-desc',
		`${ path } keeps the chosen option selected`,
		`select value is "${ selected }"`,
	);
}

check( pageErrors.length === 0, 'No uncaught JavaScript errors', pageErrors.join( ' | ' ) || 'none' );

await browser.close();

console.log(
	failures.length === 0
		? '\nAll sorting checks passed.'
		: `\n${ failures.length } check(s) failed:\n  ${ failures.join( '\n  ' ) }`,
);

process.exit( failures.length === 0 ? 0 : 1 );
