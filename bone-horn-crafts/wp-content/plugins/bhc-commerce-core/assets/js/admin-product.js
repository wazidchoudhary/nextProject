/**
 * Product editor helpers: the wholesale price-break repeater.
 *
 * Loaded only on the product edit screen.
 */
( function () {
	const container = document.querySelector( '[data-bhc-tiers]' );
	const addButton = document.querySelector( '[data-bhc-add-tier]' );

	if ( ! container || ! addButton ) {
		return;
	}

	addButton.addEventListener( 'click', function () {
		const rows = container.querySelectorAll( '.bhc-tier-row' );

		if ( rows.length >= 8 ) {
			return;
		}

		const index = rows.length;
		const row = document.createElement( 'p' );

		row.className = 'form-field bhc-tier-row';
		row.innerHTML =
			'<label for="bhc_tier_qty_' + index + '">Minimum quantity / unit price</label>' +
			'<input type="number" min="2" step="1" id="bhc_tier_qty_' + index + '" name="bhc_price_tiers[' + index + '][min_qty]" class="short" placeholder="10" />' +
			'<input type="text" inputmode="decimal" name="bhc_price_tiers[' + index + '][price]" class="short wc_input_price" placeholder="0.00" aria-label="Tier unit price" />';

		container.appendChild( row );

		const input = row.querySelector( 'input' );

		if ( input ) {
			input.focus();
		}
	} );
}() );
