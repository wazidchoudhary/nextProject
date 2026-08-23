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

function renderProducts( products ) {
	if ( ! products.length ) {
		return `<p class="bhc-empty__body">${ strings.noResults || '' }</p>`;
	}

	return products
		.map( ( product ) => {
			const image = product.image || {};
			const badges = ( product.badges || [] )
				.map( ( badge ) => `<li class="bhc-badge bhc-badge--${ badge.tone }">${ badge.label }</li>` )
				.join( '' );

			return `
			<article class="bhc-card" data-product-id="${ product.id }">
				<div class="bhc-card__media">
					<a class="bhc-card__link" href="${ product.permalink }" tabindex="-1" aria-hidden="true">
						<img class="bhc-card__image" src="${ image.src || '' }" srcset="${ image.srcset || '' }" sizes="${ image.sizes || '' }"
							width="${ image.width || 400 }" height="${ image.height || 400 }" alt="${ image.alt || '' }" loading="lazy" decoding="async" />
					</a>
					${ badges ? `<ul class="bhc-badges">${ badges }</ul>` : '' }
				</div>
				<div class="bhc-card__body">
					<h3 class="bhc-card__title"><a href="${ product.permalink }">${ product.name }</a></h3>
					<p class="bhc-card__price">${ product.price_html }</p>
					${ product.unit_of_sale ? `<p class="bhc-card__unit">${ product.unit_of_sale }</p>` : '' }
				</div>
			</article>`;
		} )
		.join( '' );
}

function initFilters() {
	const form = document.querySelector( '[data-bhc-filters]' );
	const grid = document.querySelector( '[data-bhc-product-grid]' );

	if ( ! form || ! grid ) {
		return;
	}

	const status = form.querySelector( '[data-bhc-filter-status]' );
	let controller = null;

	async function apply( pushState = true ) {
		const params = serialiseFilters( form );

		if ( status ) {
			status.textContent = strings.loading || '';
		}

		if ( controller ) {
			controller.abort();
		}

		controller = new AbortController();

		const payload = await request( `catalog?${ params.toString() }`, {
			signal: controller.signal,
		} );

		if ( ! payload ) {
			return;
		}

		grid.innerHTML = renderProducts( payload.products || [] );

		if ( status ) {
			status.textContent = `${ payload.total } results`;
		}

		announce( `${ payload.total } results` );

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
	initEstimator();
	initFilterDrawer();
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', boot );
} else {
	boot();
}
