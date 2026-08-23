/**
 * Media-library picker for the plugin settings screen.
 *
 * Vanilla; the one dependency is `wp.media`, which WordPress ships to any
 * screen that has called wp_enqueue_media().
 */

( function () {
	const strings = window.bhcImageField || {};

	/**
	 * Wires one field.
	 *
	 * @param {HTMLElement} field Field wrapper.
	 */
	function initField( field ) {
		const input = field.querySelector( '[data-bhc-image-value]' );
		const preview = field.querySelector( '[data-bhc-image-preview]' );
		const choose = field.querySelector( '[data-bhc-image-choose]' );
		const clear = field.querySelector( '[data-bhc-image-clear]' );

		if ( ! input || ! preview || ! choose ) {
			return;
		}

		let frame = null;

		choose.addEventListener( 'click', () => {
			if ( ! frame ) {
				frame = window.wp.media( {
					title: strings.frameTitle || 'Select image',
					button: { text: strings.frameButton || 'Use this image' },
					library: { type: 'image' },
					multiple: false,
				} );

				frame.on( 'select', () => {
					const attachment = frame.state().get( 'selection' ).first();

					if ( ! attachment ) {
						return;
					}

					const data = attachment.toJSON();
					const src = data.sizes?.medium?.url || data.url;

					input.value = data.id;
					preview.innerHTML = '';

					const img = document.createElement( 'img' );

					img.className = 'bhc-image-field__preview';
					img.src = src;
					img.alt = '';
					preview.appendChild( img );

					if ( clear ) {
						clear.hidden = false;
					}
				} );
			}

			frame.open();
		} );

		if ( clear ) {
			clear.addEventListener( 'click', () => {
				input.value = '0';
				preview.innerHTML = '';
				clear.hidden = true;
			} );
		}
	}

	document.querySelectorAll( '[data-bhc-image-field]' ).forEach( initField );
} )();
