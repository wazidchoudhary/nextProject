<?php
/**
 * Product loop item.
 *
 * Overrides `woocommerce/templates/content-product.php`. The default loop hooks
 * are unhooked in inc/woocommerce.php: this delegates to the plugin's card
 * template so archives, rails, search results and AJAX filter results all render
 * exactly the same markup.
 *
 * @package BHC_Theme
 * @version 3.6.0
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

global $product;

$product = wc_get_product( get_the_ID() );

if ( ! $product instanceof WC_Product || ! $product->is_visible() ) {
	return;
}

// The first card in a catalogue grid is the LCP candidate; the rest are below
// the fold and must lazy-load.
//
// WooCommerce keeps this counter inside wc_get_loop_class(), which runs from
// wc_product_post_class(). This theme's card markup does not call post_class(),
// so nothing was ever incrementing it: the prop stayed at 0 and every card on
// the page claimed to be the LCP candidate — twelve eager full-size downloads
// competing with the one that actually matters. Incrementing it here uses the
// same prop WooCommerce resets to 0 at woocommerce_product_loop_start, so
// pagination and separate loops each start again from one.
$loop_index = (int) wc_get_loop_prop( 'loop', 0 ) + 1;

wc_set_loop_prop( 'loop', $loop_index );

bhc_product_card( $product, 1 === $loop_index );
