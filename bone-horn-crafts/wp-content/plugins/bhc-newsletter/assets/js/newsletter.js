/**
 * Newsletter signup.
 *
 * Progressive enhancement over a real form: without JavaScript the form still
 * submits to the server and the page reloads with the same outcome. This
 * upgrades that to an inline response so a visitor in the footer is not thrown
 * back to the top of a page they were reading.
 */

const config = window.bhcNewsletter || {};
const strings = config.strings || {};

/**
 * Replaces a form's status line.
 *
 * @param {HTMLElement} form    The form.
 * @param {string}      message Message to show.
 * @param {string}      tone    'working', 'success' or 'error'.
 */
function setStatus( form, message, tone ) {
	let status = form.querySelector( '[data-bhc-newsletter-status]' );

	if ( ! status ) {
		status = document.createElement( 'p' );
		status.className = 'bhc-newsletter__status';
		status.setAttribute( 'data-bhc-newsletter-status', '' );
		// Polite rather than assertive: this is a confirmation, not an
		// interruption, and assertive would cut off whatever is being read.
		status.setAttribute( 'role', 'status' );
		status.setAttribute( 'aria-live', 'polite' );
		form.appendChild( status );
	}

	status.textContent = message;
	status.dataset.tone = tone;
}

/**
 * Submits one signup form.
 *
 * @param {HTMLFormElement} form The form.
 */
async function submit( form ) {
	const field = form.querySelector( 'input[type="email"]' );
	const button = form.querySelector( 'button[type="submit"]' );

	if ( ! field ) {
		return;
	}

	const email = field.value.trim();

	// checkValidity() honours the browser's own email parsing and the field's
	// required attribute, so the client-side rule matches what the markup
	// already promises rather than a second, different regex.
	if ( ! email || ! field.checkValidity() ) {
		setStatus( form, strings.invalid || 'Please enter a valid email address.', 'error' );
		field.focus();

		return;
	}

	if ( button ) {
		button.disabled = true;
	}

	setStatus( form, strings.working || 'Sending…', 'working' );

	try {
		const response = await fetch( config.endpoint, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce || '',
			},
			body: JSON.stringify( {
				email,
				source: form.dataset.bhcNewsletterSource || 'footer',
			} ),
		} );

		const payload = await response.json().catch( () => null );

		if ( ! response.ok ) {
			// The server's message is the useful one — it distinguishes a
			// malformed address from a rate limit — so prefer it over a generic
			// fallback whenever there is one.
			setStatus( form, payload?.message || strings.error || 'Something went wrong.', 'error' );

			return;
		}

		setStatus( form, payload?.message || 'Check your inbox to confirm.', 'success' );
		form.reset();
	} catch {
		setStatus( form, strings.error || 'Something went wrong.', 'error' );
	} finally {
		if ( button ) {
			button.disabled = false;
		}
	}
}

function init() {
	if ( ! config.endpoint ) {
		return;
	}

	document.querySelectorAll( '[data-bhc-newsletter]' ).forEach( ( form ) => {
		form.addEventListener( 'submit', ( event ) => {
			event.preventDefault();
			submit( form );
		} );

		// The browser's own validation on a required type="email" field blocks
		// the submit event entirely, so this handler never runs and whatever
		// the status line said last stays on screen — a stale "check your
		// inbox" sitting above a field the browser is refusing to accept.
		// Clearing on edit means the visible state always matches the field.
		const field = form.querySelector( 'input[type="email"]' );

		if ( field ) {
			field.addEventListener( 'input', () => {
				const status = form.querySelector( '[data-bhc-newsletter-status]' );

				if ( status && 'working' !== status.dataset.tone ) {
					status.textContent = '';
					delete status.dataset.tone;
				}
			} );
		}
	} );
}

if ( 'loading' === document.readyState ) {
	document.addEventListener( 'DOMContentLoaded', init );
} else {
	init();
}
