/**
 * Bone Horn Crafts storefront module.
 *
 * Vanilla ES module, no framework and no jQuery. Everything here progressively
 * enhances markup that already works without JavaScript:
 *
 * - the wishlist button is a real form that posts to admin-post.php;
 * - the filter panel is a real GET form that reloads the shop page;
 * - the delivery estimator renders a server-side estimate before it loads.
 *
 * If this file fails to load, the store still functions. That is the point.
 */

const config = window.bhcCommerce || {};
const api = ( config.restUrl || '' ).replace( /\/$/, '' );
const strings = config.strings || {};

/**
 * Small fetch wrapper that always sends the REST nonce and never throws
 * an unhandled rejection into the console.
 *
 * @param {string} path   Endpoint path, relative to the plugin namespace.
 * @param {Object} [init] Fetch init options.
 * @return {Promise<Object|null>} Parsed JSON, or null on failure.
 */
async function request( path, init = {} ) {
	if ( ! api ) {
		return null;
	}

	try {
		const response = await fetch( `${ api }/${ path.replace( /^\//, '' ) }`, {
			credentials: 'same-origin',
			...init,
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce || '',
				...( init.headers || {} ),
			},
		} );

		if ( ! response.ok ) {
			return null;
		}

		return await response.json();
	} catch {
		// A failed enrichment must never break the page: every caller
		// treats null as "leave the server-rendered markup alone".
		return null;
	}
}

/**
 * Announces a message to assistive technology and to the visible status area.
 *
 * @param {string} message Message to announce.
 */
function announce( message ) {
	let region = document.getElementById( 'bhc-live-region' );

	if ( ! region ) {
		region = document.createElement( 'div' );
		region.id = 'bhc-live-region';
		region.className = 'screen-reader-text';
		region.setAttribute( 'role', 'status' );
		region.setAttribute( 'aria-live', 'polite' );
		document.body.appendChild( region );
	}

	region.textContent = message;
}

/* -------------------------------------------------------------------------
 * Wishlist
 * ---------------------------------------------------------------------- */

function updateWishlistCount( count ) {
	document.querySelectorAll( '[data-bhc-wishlist-count]' ).forEach( ( node ) => {
		node.textContent = String( count );
		node.classList.toggle( 'is-empty', count === 0 );
	} );
}

function initWishlist() {
	const forms = document.querySelectorAll( '.bhc-wishlist-form' );

	if ( ! forms.length || ! config.wishlist || ! config.wishlist.enabled ) {
		return;
	}

	forms.forEach( ( form ) => {
		form.addEventListener( 'submit', async ( event ) => {
			const button = form.querySelector( '[data-bhc-wishlist-toggle]' );

			if ( ! button ) {
				return;
			}

			// Only intercept once we know the request can be made; otherwise
			// the plain form submission is left to do its job.
			event.preventDefault();

			button.disabled = true;

			const productId = parseInt( button.dataset.productId || '0', 10 );
			const result = await request( 'wishlist/toggle', {
				method: 'POST',
				body: JSON.stringify( { product_id: productId } ),
			} );

			button.disabled = false;

			if ( ! result ) {
				announce( strings.error || 'Request failed' );
				form.submit();

				return;
			}

			const label = button.querySelector( '[data-bhc-wishlist-label]' );

			button.classList.toggle( 'is-saved', Boolean( result.in_list ) );
			button.setAttribute( 'aria-pressed', result.in_list ? 'true' : 'false' );

			if ( label ) {
				label.textContent = result.in_list ? strings.saved : strings.save;
			}

			updateWishlistCount( result.count || 0 );
			announce( result.message || '' );
		} );
	} );
}

/* -------------------------------------------------------------------------
 * Catalogue filters
 * ---------------------------------------------------------------------- */

function serialiseFilters( form ) {
	const data = new FormData( form );
	const params = new URLSearchParams();

	for ( const [ key, value ] of data.entries() ) {
		if ( value === '' ) {
			continue;
		}

		const name = key.replace( /\[\]$/, '' );
		const existing = params.get( name );

		params.set( name, existing ? `${ existing },${ value }` : String( value ) );
	}

	return params;
}

/**
 * Appends the next page of results in place.
 *
 * Deliberately a button rather than infinite scroll. A crawler does not
 * scroll, so replacing the numbered pages with a scroll listener takes the
 * catalogue out of the index; and a shopper who opens a product and comes back
 * loses their position. This leaves the paged URLs exactly as they were — they
 * still work with JavaScript off — and adds a way to keep reading without a
 * full page load.
 *
 * The button is hidden in the markup and revealed here, so a visitor without
 * JavaScript never sees a control that cannot work.
 */
function initLoadMore() {
	const holder = document.querySelector( '[data-bhc-load-more]' );
	const grid = document.querySelector( '[data-bhc-product-grid], ul.products' );

	if ( ! holder || ! grid || ! api ) {
		return;
	}

	const button = holder.querySelector( '[data-bhc-load-more-button]' );
	const status = holder.querySelector( '[data-bhc-load-more-status]' );
	const totalPages = parseInt( holder.dataset.totalPages, 10 ) || 1;

	if ( ! button ) {
		return;
	}

	// Whatever page the visitor actually landed on, not always 1: /shop/page/3/
	// must continue from four.
	const current = new URLSearchParams( window.location.search );
	const pathPage = window.location.pathname.match( /\/page\/(\d+)/ );
	let page = pathPage ? parseInt( pathPage[ 1 ], 10 ) : parseInt( current.get( 'paged' ) || '1', 10 );
	let busy = false;

	holder.hidden = false;

	button.addEventListener( 'click', async () => {
		if ( busy || page >= totalPages ) {
			return;
		}

		busy = true;
		button.disabled = true;

		const label = button.textContent;

		button.textContent = strings.loading || label;

		try {
			const params = new URLSearchParams( current );

			params.set( 'page', String( page + 1 ) );

			const response = await fetch( `${ api }/catalog?${ params.toString() }`, {
				headers: { Accept: 'application/json' },
			} );

			if ( ! response.ok ) {
				throw new Error( String( response.status ) );
			}

			const payload = await response.json();

			if ( ! payload.html ) {
				page = totalPages;
			} else {
				grid.insertAdjacentHTML( 'beforeend', payload.html );
				page += 1;
			}

			if ( status ) {
				status.textContent = formatResultCount( grid.children.length );
			}

			if ( page >= totalPages ) {
				holder.hidden = true;
			}
		} catch {
			if ( status ) {
				status.textContent = strings.loadMoreFailed || '';
			}
		} finally {
			busy = false;
			button.disabled = false;
			button.textContent = label;
		}
	} );
}

/**
 * Renders a result count in the visitor's language, with correct pluralisation.
 *
 * @param {number} total Number of results.
 * @return {string} Localised count.
 */
function formatResultCount( total ) {
	const count = Number( total ) || 0;

	if ( 1 === count ) {
		return strings.resultOne || '1 result';
	}

	return ( strings.resultMany || '%s results' ).replace( '%s', String( count ) );
}

function initFilters() {
	const form = document.querySelector( '[data-bhc-filters]' );
	const grid = document.querySelector( '[data-bhc-product-grid]' );

	if ( ! form || ! grid ) {
		return;
	}

	const status = form.querySelector( '[data-bhc-filter-status]' );
	let controller = null;
	let inFlight = 0;

	/**
	 * Marks the grid and panel as busy.
	 *
	 * The grid keeps the cards it already has while a request is in flight, so
	 * the overlay covers content of the same height and nothing reflows — the
	 * alternative, emptying the grid first, collapses the page to the header
	 * and bounces it back a moment later.
	 *
	 * Counted rather than set to a boolean: a filter change while another
	 * request is still running would otherwise clear the busy state as soon as
	 * the first one settles, leaving the second running invisibly.
	 *
	 * @param {boolean} busy Whether a request is in flight.
	 */
	function setBusy( busy ) {
		inFlight = Math.max( 0, inFlight + ( busy ? 1 : -1 ) );

		const active = inFlight > 0;

		grid.setAttribute( 'aria-busy', active ? 'true' : 'false' );
		grid.classList.toggle( 'is-loading', active );
		form.classList.toggle( 'is-loading', active );
	}

	async function apply( pushState = true ) {
		const params = serialiseFilters( form );

		if ( status ) {
			status.textContent = strings.loading || '';
		}

		if ( controller ) {
			controller.abort();
		}

		controller = new AbortController();

		setBusy( true );

		let payload = null;

		try {
			payload = await request( `catalog?${ params.toString() }`, {
				signal: controller.signal,
			} );
		} finally {
			setBusy( false );
		}

		// `request()` returns null for an abort as well as a genuine failure.
		// Leaving the status on "Loading…" in either case is how a filter panel
		// ends up permanently claiming to be working; say what happened
		// instead.
		if ( ! payload ) {
			if ( status && 0 === inFlight ) {
				status.textContent = strings.error || '';
			}

			return;
		}

		// Markup comes from the server, rendered by the same template the
		// archive uses. Building it here meant maintaining the card twice, and
		// the copies had already drifted apart.
		grid.innerHTML = payload.html || `<p class="bhc-empty__body">${ strings.noResults || '' }</p>`;

		const summary = formatResultCount( payload.total );

		if ( status ) {
			status.textContent = summary;
		}

		announce( summary );

		if ( pushState ) {
			const query = new URLSearchParams( payload.query || {} ).toString();

			window.history.pushState( {}, '', query ? `${ form.action }?${ query }` : form.action );
		}
	}

	// Debounce so dragging a price field does not fire a request per keystroke.
	let timer = null;

	form.addEventListener( 'change', () => {
		window.clearTimeout( timer );
		timer = window.setTimeout( apply, 180 );
	} );

	form.addEventListener( 'submit', ( event ) => {
		event.preventDefault();
		apply();
	} );

	window.addEventListener( 'popstate', () => {
		window.location.reload();
	} );
}

/* -------------------------------------------------------------------------
 * Delivery estimator
 * ---------------------------------------------------------------------- */

function initEstimator() {
	const root = document.querySelector( '[data-bhc-estimator]' );

	if ( ! root || config.estimator === false ) {
		return;
	}

	const select = root.querySelector( '[data-bhc-estimator-country]' );
	const result = root.querySelector( '[data-bhc-estimator-result]' );

	if ( ! select || ! result ) {
		return;
	}

	select.addEventListener( 'change', async () => {
		result.textContent = strings.loading || '';

		const payload = await request(
			`delivery-estimate?country=${ encodeURIComponent( select.value ) }&product_id=${ encodeURIComponent( root.dataset.productId || '0' ) }`,
		);

		if ( ! payload || ! payload.estimate ) {
			result.textContent = strings.error || '';

			return;
		}

		result.textContent = payload.estimate.label || '';
	} );
}

/* -------------------------------------------------------------------------
 * Mobile filter drawer
 * ---------------------------------------------------------------------- */

function initFilterDrawer() {
	const toggle = document.querySelector( '[data-bhc-filter-toggle]' );
	const panel = document.querySelector( '[data-bhc-filters]' );

	if ( ! toggle || ! panel ) {
		return;
	}

	toggle.addEventListener( 'click', () => {
		const open = panel.classList.toggle( 'is-open' );

		toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		document.body.classList.toggle( 'bhc-no-scroll', open );

		if ( open ) {
			const first = panel.querySelector( 'input, button, select' );

			if ( first ) {
				first.focus();
			}
		}
	} );

	document.addEventListener( 'keydown', ( event ) => {
		if ( event.key === 'Escape' && panel.classList.contains( 'is-open' ) ) {
			toggle.click();
			toggle.focus();
		}
	} );
}

function boot() {
	initWishlist();
	initFilters();
	initLoadMore();
	initEstimator();
	initFilterDrawer();
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', boot );
} else {
	boot();
}
