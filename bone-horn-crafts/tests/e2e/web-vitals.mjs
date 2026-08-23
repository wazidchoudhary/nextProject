/**
 * Core Web Vitals checks.
 *
 * Measures CLS and LCP for the pages that carry the most layout risk, at a
 * desktop and a mobile viewport, and fails the run when either crosses its
 * budget. Field data is what ultimately counts, but a lab regression — a card
 * that stops reserving its box, an image that loses its dimensions — shows up
 * here immediately and is cheap to catch before it ships.
 */

import { launchBrowser, BASE_URL } from './browser.mjs';

const BASE = BASE_URL;

const BUDGETS = {
	cls: 0.1,
	// Generous against PHP's built-in single-process server, which serialises
	// every subresource. The point is to catch a regression, not to benchmark
	// the host.
	lcp: 2500,
};

const PAGES = [
	[ 'home', '/' ],
	[ 'shop', '/shop/' ],
	[ 'product', '/product/cattle-bone-scales-sanded-400-grit/' ],
];

const VIEWPORTS = [
	[ 'desktop', 1440, 900 ],
	[ 'mobile', 390, 844 ],
];

const browser = await launchBrowser();

let failures = 0;

for ( const [ label, width, height ] of VIEWPORTS ) {
	const context = await browser.newContext( { viewport: { width, height }, deviceScaleFactor: 1 } );

	for ( const [ name, path ] of PAGES ) {
		const page = await context.newPage();
		const errors = [];

		page.on( 'pageerror', ( e ) => errors.push( e.message ) );
		page.on( 'console', ( m ) => m.type() === 'error' && errors.push( m.text() ) );

		await page.goto( BASE + path, { waitUntil: 'networkidle', timeout: 30000 } );

		// Scroll the document so lazily loaded imagery settles, then return to
		// the top. A shift caused by a late image is still a shift.
		await page.evaluate( async () => {
			const step = window.innerHeight;
			for ( let y = 0; y < document.body.scrollHeight; y += step ) {
				window.scrollTo( 0, y );
				await new Promise( ( r ) => setTimeout( r, 100 ) );
			}
			window.scrollTo( 0, 0 );
		} );

		await page.waitForTimeout( 600 );

		const vitals = await page.evaluate( () => new Promise( ( resolve ) => {
			let cls = 0;
			let lcp = 0;

			new PerformanceObserver( ( list ) => {
				for ( const entry of list.getEntries() ) {
					if ( ! entry.hadRecentInput ) {
						cls += entry.value;
					}
				}
			} ).observe( { type: 'layout-shift', buffered: true } );

			new PerformanceObserver( ( list ) => {
				const entries = list.getEntries();
				lcp = entries[ entries.length - 1 ]?.startTime || 0;
			} ).observe( { type: 'largest-contentful-paint', buffered: true } );

			setTimeout( () => resolve( { cls, lcp } ), 700 );
		} ) );

		const ok = vitals.cls <= BUDGETS.cls && vitals.lcp <= BUDGETS.lcp && errors.length === 0;

		if ( ! ok ) {
			failures++;
		}

		console.log(
			`${ ok ? 'PASS' : 'FAIL' }  ${ label }/${ name } — CLS ${ vitals.cls.toFixed( 4 ) } ` +
			`(budget ${ BUDGETS.cls }), LCP ${ Math.round( vitals.lcp ) }ms (budget ${ BUDGETS.lcp }ms)` +
			( errors.length ? `, JS errors: ${ errors.join( ' ; ' ) }` : '' ),
		);

		await page.close();
	}

	await context.close();
}

await browser.close();

console.log( failures === 0 ? '\nAll web vitals within budget.' : `\n${ failures } page(s) over budget.` );
process.exit( failures === 0 ? 0 : 1 );
