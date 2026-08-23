/**
 * Set a product's image from the products list table.
 *
 * Vanilla, no jQuery. The one dependency is `wp.media`, which WordPress
 * already ships to any screen that has called wp_enqueue_media().
 *
 * The table is paginated and re-rendered on sort and filter, so the click
 * handler is delegated from the table body rather than bound per row.
 */

( function () {
	const config = window.bhcQuickImage;

	if ( ! config || ! window.wp || ! window.wp.media ) {
		return;
	}

	// One frame, reused. Opening a new wp.media frame per click leaks a modal
	// and its event listeners every time.
	let frame = null;
	let activeButton = null;

	/**
	 * Replaces the thumbnail in the cell the button lives in.
	 *
	 * @param {HTMLElement} button Trigger.
	 * @param {string}      html   New thumbnail markup.
	 */
	function swapThumbnail( button, html ) {
		const cell = button.closest( 'td' );

		if ( ! cell ) {
			return;
		}

		const existing = cell.querySelector( 'img' );
		const holder = document.createElement( 'div' );

		holder.innerHTML = html;

		const replacement = holder.querySelector( 'img' );

		if ( existing && replacement ) {
			existing.replaceWith( replacement );
		}
	}

	/**
	 * Shows a message next to the trigger, replacing any previous one.
	 *
	 * @param {HTMLElement} button  Trigger.
	 * @param {string}      message Text.
	 */
	function showError( button, message ) {
		const cell = button.closest( 'td' );

		if ( ! cell ) {
			return;
		}

		cell.querySelectorAll( '.bhc-quick-image__error' ).forEach( ( node ) => node.remove() );

		const note = document.createElement( 'span' );

		note.className = 'bhc-quick-image__error';
		note.setAttribute( 'role', 'alert' );
		note.textContent = message;
		cell.appendChild( note );
	}

	/**
	 * Persists the chosen attachment.
	 *
	 * @param {HTMLElement} button       Trigger.
	 * @param {number}      attachmentId Attachment id, or 0 to clear.
	 *
	 * @return {Promise<void>} Resolves once the cell reflects the result.
	 */
	async function save( button, attachmentId ) {
		const label = button.textContent;

		button.disabled = true;
		button.textContent = config.i18n.saving;

		const body = new URLSearchParams( {
			action: config.action,
			nonce: config.nonce,
			product: button.dataset.bhcQuickImage,
			attachment: String( attachmentId ),
		} );

		try {
			const response = await fetch( config.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body,
			} );

			const payload = await response.json();

			if ( ! response.ok || ! payload.success ) {
				// The endpoint names each failure — a permissions problem reads
				// differently from a deleted attachment — so its message is
				// preferred over a generic one.
				showError( button, payload?.data?.message || config.i18n.failed );

				return;
			}

			button.closest( 'td' )?.querySelectorAll( '.bhc-quick-image__error' )
				.forEach( ( node ) => node.remove() );

			swapThumbnail( button, payload.data.thumbnail );
		} catch {
			showError( button, config.i18n.failed );
		} finally {
			button.disabled = false;
			button.textContent = label;
		}
	}

	/**
	 * Opens the media library for the clicked product.
	 *
	 * @param {HTMLElement} button Trigger.
	 */
	function openPicker( button ) {
		activeButton = button;

		if ( ! frame ) {
			frame = window.wp.media( {
				title: config.i18n.frameTitle,
				button: { text: config.i18n.frameButton },
				library: { type: 'image' },
				multiple: false,
			} );

			frame.on( 'select', () => {
				const attachment = frame.state().get( 'selection' ).first();

				if ( ! attachment || ! activeButton ) {
					return;
				}

				save( activeButton, attachment.get( 'id' ) );
			} );
		}

		// A reused frame remembers the last selection, which would show the
		// previous product's photograph as already chosen.
		frame.state()?.get( 'selection' )?.reset();
		frame.open();
	}

	document.addEventListener( 'click', ( event ) => {
		const button = event.target.closest( '[data-bhc-quick-image]' );

		if ( ! button ) {
			return;
		}

		event.preventDefault();
		openPicker( button );
	} );
} )();
