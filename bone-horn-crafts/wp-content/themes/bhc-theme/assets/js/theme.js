/**
 * Bone Horn Crafts theme module.
 *
 * Vanilla ES module, ~6KB, deferred. Every behaviour here enhances markup that
 * already works: the drawer is a real navigation list, the gallery thumbnails
 * are real buttons pointing at full-size files, the tabs are WooCommerce's own
 * anchors, and the sticky bar re-submits the real add-to-cart form.
 *
 * No jQuery, no polyfills, no dependency on WooCommerce's own scripts.
 */

/* -------------------------------------------------------------------------
 * Mobile navigation drawer
 * ---------------------------------------------------------------------- */

function initDrawer() {
	const toggle = document.querySelector( '[data-bhc-nav-toggle]' );
	const drawer = document.querySelector( '[data-bhc-drawer]' );

	if ( ! toggle || ! drawer ) {
		return;
	}

	const closers = drawer.querySelectorAll( '[data-bhc-drawer-close]' );
	let lastFocused = null;

	function focusables() {
		return Array.from(
			drawer.querySelectorAll( 'a[href], button:not([disabled]), input, select, textarea' ),
		).filter( ( node ) => node.offsetParent !== null );
	}

	function open() {
		lastFocused = document.activeElement;

		drawer.dataset.open = 'true';
		drawer.setAttribute( 'aria-hidden', 'false' );
		toggle.setAttribute( 'aria-expanded', 'true' );
		document.body.classList.add( 'bhc-no-scroll' );

		const first = focusables()[ 0 ];

		if ( first ) {
			first.focus();
		}
	}

	function close() {
		drawer.dataset.open = 'false';
		drawer.setAttribute( 'aria-hidden', 'true' );
		toggle.setAttribute( 'aria-expanded', 'false' );
		document.body.classList.remove( 'bhc-no-scroll' );

		if ( lastFocused instanceof HTMLElement ) {
			lastFocused.focus();
		}
	}

	toggle.addEventListener( 'click', () => {
		drawer.dataset.open === 'true' ? close() : open();
	} );

	closers.forEach( ( node ) => node.addEventListener( 'click', close ) );

	// Focus trap + escape, so the drawer behaves like a real dialog.
	document.addEventListener( 'keydown', ( event ) => {
		if ( drawer.dataset.open !== 'true' ) {
			return;
		}

		if ( event.key === 'Escape' ) {
			close();

			return;
		}

		if ( event.key !== 'Tab' ) {
			return;
		}

		const items = focusables();

		if ( ! items.length ) {
			return;
		}

		const first = items[ 0 ];
		const last = items[ items.length - 1 ];

		if ( event.shiftKey && document.activeElement === first ) {
			event.preventDefault();
			last.focus();
		} else if ( ! event.shiftKey && document.activeElement === last ) {
			event.preventDefault();
			first.focus();
		}
	} );
}

/* -------------------------------------------------------------------------
 * Mobile search
 * ---------------------------------------------------------------------- */

function initMobileSearch() {
	const toggle = document.querySelector( '[data-bhc-search-toggle]' );
	const panel = document.querySelector( '[data-bhc-search-panel]' );

	if ( ! toggle || ! panel ) {
		return;
	}

	const field = panel.querySelector( 'input[type="search"]' );

	function setOpen( open ) {
		panel.hidden = ! open;
		toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );

		if ( open && field ) {
			field.focus();
		}
	}

	toggle.addEventListener( 'click', () => {
		setOpen( panel.hidden );
	} );

	// Escape closes and returns focus to the control that opened it, which is
	// what a keyboard user expects and what screen readers announce correctly.
	panel.addEventListener( 'keydown', ( event ) => {
		if ( event.key === 'Escape' ) {
			setOpen( false );
			toggle.focus();
		}
	} );
}

/* -------------------------------------------------------------------------
 * Product gallery
 * ---------------------------------------------------------------------- */

function initGallery() {
	const gallery = document.querySelector( '[data-bhc-gallery]' );

	if ( ! gallery ) {
		return;
	}

	const image = gallery.querySelector( '#bhc-gallery-image' );
	const thumbs = gallery.querySelectorAll( '[data-bhc-gallery-thumb]' );

	if ( ! image || thumbs.length < 2 ) {
		return;
	}

	function select( button ) {
		if ( ! button.dataset.full ) {
			return;
		}

		// Swapping src/srcset keeps one <img> in the DOM: no layout shift, and
		// the browser reuses the decoded bitmap when returning to a thumbnail.
		image.src = button.dataset.full;
		image.srcset = button.dataset.srcset || '';

		// The alt text has to travel with the picture. Without this a screen
		// reader announces the first photograph's description for all three,
		// which is worse than no description at all because it is confidently
		// wrong.
		if ( button.dataset.alt !== undefined ) {
			image.alt = button.dataset.alt;
		}

		thumbs.forEach( ( node ) => node.setAttribute( 'aria-current', node === button ? 'true' : 'false' ) );
	}

	thumbs.forEach( ( button, index ) => {
		button.addEventListener( 'click', () => select( button ) );

		button.addEventListener( 'keydown', ( event ) => {
			if ( event.key !== 'ArrowRight' && event.key !== 'ArrowLeft' ) {
				return;
			}

			event.preventDefault();

			const next = event.key === 'ArrowRight'
				? thumbs[ ( index + 1 ) % thumbs.length ]
				: thumbs[ ( index - 1 + thumbs.length ) % thumbs.length ];

			next.focus();
			select( next );
		} );
	} );
}

/* -------------------------------------------------------------------------
 * Sticky purchase bar
 * ---------------------------------------------------------------------- */

function initStickyCart() {
	const bar = document.querySelector( '[data-bhc-sticky-cart]' );
	const form = document.querySelector( 'form.cart' );

	if ( ! bar || ! form ) {
		return;
	}

	const trigger = bar.querySelector( '[data-bhc-sticky-add]' );

	if ( trigger ) {
		trigger.addEventListener( 'click', () => {
			// Re-use the real form rather than duplicating purchase logic, so
			// variations, quantity and validation all still apply.
			if ( typeof form.requestSubmit === 'function' ) {
				form.requestSubmit();
			} else {
				form.submit();
			}
		} );
	}

	if ( ! ( 'IntersectionObserver' in window ) ) {
		return;
	}

	const observer = new IntersectionObserver(
		( entries ) => {
			entries.forEach( ( entry ) => {
				const visible = ! entry.isIntersecting && entry.boundingClientRect.top < 0;

				bar.dataset.visible = visible ? 'true' : 'false';
				bar.setAttribute( 'aria-hidden', visible ? 'false' : 'true' );

				// Keep the tab order in step with the accessibility tree.
				if ( visible ) {
					bar.removeAttribute( 'inert' );
				} else {
					bar.setAttribute( 'inert', '' );
				}
			} );
		},
		{ rootMargin: '0px 0px -100% 0px' },
	);

	observer.observe( form );
}

/* -------------------------------------------------------------------------
 * Product tabs
 * ---------------------------------------------------------------------- */

function initTabs() {
	const tabList = document.querySelector( '.wc-tabs, [role="tablist"].product-tabs__nav' );

	if ( ! tabList ) {
		return;
	}

	const tabs = Array.from( tabList.querySelectorAll( 'a[href^="#"]' ) );

	if ( tabs.length < 2 ) {
		return;
	}

	const panelFor = ( tab ) => document.getElementById( tab.getAttribute( 'aria-controls' ) );

	// The ARIA tabs pattern is a state machine, not a set of attributes: exactly
	// one tab is aria-selected and focusable, and the other panels are `hidden`
	// so they leave the accessibility tree rather than just going out of sight.
	function activate( tab ) {
		tabs.forEach( ( candidate ) => {
			const selected = candidate === tab;
			const panel = panelFor( candidate );

			candidate.setAttribute( 'aria-selected', selected ? 'true' : 'false' );
			candidate.setAttribute( 'tabindex', selected ? '0' : '-1' );

			if ( panel ) {
				panel.hidden = ! selected;
			}
		} );
	}

	tabs.forEach( ( tab, index ) => {
		tab.addEventListener( 'click', ( event ) => {
			event.preventDefault();
			activate( tab );
		} );

		tab.addEventListener( 'keydown', ( event ) => {
			const keys = {
				ArrowRight: tabs[ ( index + 1 ) % tabs.length ],
				ArrowLeft: tabs[ ( index - 1 + tabs.length ) % tabs.length ],
				Home: tabs[ 0 ],
				End: tabs[ tabs.length - 1 ],
			};

			const next = keys[ event.key ];

			if ( ! next ) {
				return;
			}

			event.preventDefault();
			activate( next );
			next.focus();
		} );
	} );

	// Deep links like /product/x/#tab-reviews should open that tab.
	const requested = tabs.find( ( tab ) => tab.getAttribute( 'href' ) === window.location.hash );

	activate( requested || tabs[ 0 ] );
}

/* -------------------------------------------------------------------------
 * Quantity steppers
 * ---------------------------------------------------------------------- */

function initQuantity() {
	document.querySelectorAll( '.quantity input[type="number"]' ).forEach( ( input ) => {
		if ( input.dataset.bhcStepper === 'ready' ) {
			return;
		}

		input.dataset.bhcStepper = 'ready';

		input.addEventListener( 'change', () => {
			const min = parseFloat( input.min || '1' );
			const max = parseFloat( input.max || 'Infinity' );
			const value = parseFloat( input.value || '1' );

			if ( Number.isNaN( value ) || value < min ) {
				input.value = String( min );
			} else if ( value > max ) {
				input.value = String( max );
			}
		} );
	} );
}

/* -------------------------------------------------------------------------
 * Header shadow on scroll
 * ---------------------------------------------------------------------- */

function initHeaderState() {
	const header = document.querySelector( '.site-header' );

	if ( ! header ) {
		return;
	}

	let ticking = false;

	function update() {
		header.classList.toggle( 'is-scrolled', window.scrollY > 8 );
		ticking = false;
	}

	window.addEventListener(
		'scroll',
		() => {
			if ( ! ticking ) {
				// One layout read per frame keeps this off the INP critical path.
				window.requestAnimationFrame( update );
				ticking = true;
			}
		},
		{ passive: true },
	);
}

/* -------------------------------------------------------------------------
 * Catalogue ordering
 * ---------------------------------------------------------------------- */

/**
 * Submits the sort form when the dropdown changes.
 *
 * WooCommerce's own orderby template ships a bare <select> with no submit
 * control: the only thing that sends the form is one jQuery handler inside
 * WooCommerce's `woocommerce` frontend script. This theme dequeues that script
 * everywhere except cart, checkout and account, which is a deliberate and
 * worthwhile saving — and it silently broke sorting on the shop, on every
 * category archive and on search results. The dropdown changed, and nothing
 * happened.
 *
 * Rebinding it here keeps the saving and restores the behaviour. The template
 * override also renders a real submit button, hidden unless scripting is off,
 * so sorting still works when this never runs.
 */
function initCatalogOrdering() {
	const forms = document.querySelectorAll( 'form.woocommerce-ordering' );

	forms.forEach( ( form ) => {
		const select = form.querySelector( 'select.orderby' );

		if ( ! select ) {
			return;
		}

		select.addEventListener( 'change', () => {
			// requestSubmit() runs validation and fires the submit event, which
			// form.submit() skips. The fallback covers older Safari.
			if ( typeof form.requestSubmit === 'function' ) {
				form.requestSubmit();

				return;
			}

			form.submit();
		} );
	} );
}

function boot() {
	initDrawer();
	initMobileSearch();
	initGallery();
	initStickyCart();
	initTabs();
	initQuantity();
	initHeaderState();
	initCatalogOrdering();
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', boot );
} else {
	boot();
}

// WooCommerce re-renders fragments after AJAX add-to-cart; re-bind the parts
// that live inside replaced markup.
document.body.addEventListener( 'wc_fragments_refreshed', initQuantity );
