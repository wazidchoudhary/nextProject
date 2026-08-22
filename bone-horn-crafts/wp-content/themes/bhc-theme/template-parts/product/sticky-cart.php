<?php
/**
 * Sticky mobile purchase bar.
 *
 * Rendered once, hidden by default, and revealed by the theme module when the
 * real add-to-cart form scrolls out of view. It re-submits the same form rather
 * than duplicating the purchase logic.
 *
 * @package BHC_Theme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$product = $args['product'] ?? null;

if ( ! $product instanceof WC_Product || ! $product->is_purchasable() ) {
	return;
}
?>
<div class="sticky-cart" data-bhc-sticky-cart data-visible="false" aria-hidden="true">
	<div>
		<p class="sticky-cart__price"><?php echo wp_kses_post( (string) $product->get_price_html() ); ?></p>
	</div>

	<button type="button" class="bhc-button" data-bhc-sticky-add>
		<?php echo esc_html( $product->is_in_stock() ? __( 'Add to cart', 'bhc-theme' ) : __( 'Out of stock', 'bhc-theme' ) ); ?>
	</button>
</div>
