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

// The first card in a catalogue grid is the LCP candidate on the shop page.
$loop_index = (int) wc_get_loop_prop( 'loop' );

bhc_product_card( $product, $loop_index <= 1 );
