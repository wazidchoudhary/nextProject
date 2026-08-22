import { chromium } from 'playwright';

const pages = process.argv.slice(2);
const browser = await chromium.launch( { executablePath: '/opt/pw-browsers/chromium', args: [ '--no-sandbox' ] } );

for ( const spec of pages ) {
	const [ name, url, width, full ] = spec.split( '|' );
	const context = await browser.newContext( {
		viewport: { width: parseInt( width, 10 ), height: 900 },
		deviceScaleFactor: 1,
	} );
	const page = await context.newPage();
	const errors = [];
	page.on( 'console', ( m ) => m.type() === 'error' && errors.push( m.text() ) );
	page.on( 'pageerror', ( e ) => errors.push( 'pageerror: ' + e.message ) );

	await page.goto( url, { waitUntil: 'networkidle', timeout: 30000 } );

	if ( full === 'full' ) {
		// Scroll through the document so lazily loaded imagery is fetched
		// before the capture, then return to the top.
		await page.evaluate( async () => {
			const step = window.innerHeight;
			for ( let y = 0; y < document.body.scrollHeight; y += step ) {
				window.scrollTo( 0, y );
				await new Promise( ( r ) => setTimeout( r, 120 ) );
			}
			window.scrollTo( 0, 0 );
		} );
		await page.waitForLoadState( 'networkidle' );
	}

	await page.waitForTimeout( 400 );
	await page.screenshot( { path: `/tmp/shots/${ name }.png`, fullPage: full === 'full' } );

	const cls = await page.evaluate( () => new Promise( ( resolve ) => {
		let value = 0;
		new PerformanceObserver( ( list ) => {
			for ( const entry of list.getEntries() ) {
				if ( ! entry.hadRecentInput ) value += entry.value;
			}
		} ).observe( { type: 'layout-shift', buffered: true } );
		setTimeout( () => resolve( value ), 600 );
	} ) );

	console.log( `${ name }: ok | CLS=${ cls.toFixed( 4 ) } | console errors: ${ errors.length ? errors.join( ' ; ' ) : 'none' }` );
	await context.close();
}

await browser.close();
