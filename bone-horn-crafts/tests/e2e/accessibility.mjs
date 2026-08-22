/**
 * Accessibility audit.
 *
 * Runs axe-core over the pages a customer actually moves through, at a desktop
 * and a mobile viewport, and fails on any violation at serious or critical
 * impact. Automated checks cover roughly a third of WCAG; this catches the
 * mechanical third — missing labels, contrast, landmark and heading structure,
 * ARIA misuse — and does not replace a manual keyboard and screen-reader pass.
 */

import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { launchBrowser, BASE_URL } from './browser.mjs';

const require = createRequire( import.meta.url );
const AXE_SOURCE = readFileSync( require.resolve( 'axe-core/axe.min.js' ), 'utf8' );

const FAIL_ON = [ 'serious', 'critical' ];

const PAGES = [
	[ 'home', '/' ],
	[ 'shop', '/shop/' ],
	[ 'product', '/product/cattle-bone-scales-sanded-400-grit/' ],
	[ 'category', '/product-category/horn-scales/' ],
	[ 'cart', '/cart/' ],
	[ 'my-account', '/my-account/' ],
	[ 'wishlist', '/wishlist/' ],
	[ 'blog', '/blog/' ],
	[ 'about', '/about-us/' ],
	[ 'faq', '/faq/' ],
	[ 'search', '/?s=horn' ],
	[ '404', '/this-page-does-not-exist/' ],
];

const VIEWPORTS = [
	[ 'desktop', 1440, 900 ],
	[ 'mobile', 390, 844 ],
];

const browser = await launchBrowser();

let failed = 0;
let total = 0;
const seen = new Map();

for ( const [ label, width, height ] of VIEWPORTS ) {
	const context = await browser.newContext( { viewport: { width, height } } );

	for ( const [ name, path ] of PAGES ) {
		const page = await context.newPage();

		await page.goto( BASE_URL + path, { waitUntil: 'domcontentloaded', timeout: 30000 } );
		await page.addScriptTag( { content: AXE_SOURCE } );

		const results = await page.evaluate( async () => {
			// eslint-disable-next-line no-undef
			return await axe.run( document, {
				runOnly: { type: 'tag', values: [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'best-practice' ] },
			} );
		} );

		const blocking = results.violations.filter( ( v ) => FAIL_ON.includes( v.impact ) );
		const minor = results.violations.filter( ( v ) => ! FAIL_ON.includes( v.impact ) );

		total++;

		for ( const violation of results.violations ) {
			const key = `${ violation.impact }:${ violation.id }`;
			seen.set( key, ( seen.get( key ) || 0 ) + violation.nodes.length );
		}

		if ( blocking.length ) {
			failed++;
			console.log( `FAIL  ${ label }/${ name }` );
			for ( const v of blocking ) {
				console.log( `        ${ v.impact }  ${ v.id }: ${ v.help }` );
				for ( const node of v.nodes.slice( 0, 3 ) ) {
					console.log( `          ${ node.target.join( ' ' ) }` );
				}
			}
		} else {
			console.log(
				`PASS  ${ label }/${ name }` +
				( minor.length ? `  (${ minor.length } minor: ${ minor.map( ( v ) => v.id ).join( ', ' ) })` : '' )
			);
		}

		await page.close();
	}

	await context.close();
}

await browser.close();

if ( seen.size ) {
	console.log( '\nAll findings by rule:' );
	for ( const [ key, nodes ] of [ ...seen.entries() ].sort() ) {
		console.log( `  ${ key } — ${ nodes } node(s)` );
	}
}

console.log(
	failed === 0
		? `\nNo serious or critical violations across ${ total } page renders.`
		: `\n${ failed } of ${ total } page renders have serious or critical violations.`
);

process.exit( failed === 0 ? 0 : 1 );
