<?php
/**
 * Single product layout.
 *
 * Overrides `woocommerce/templates/content-single-product.php`.
 *
 * The two-column composition is the theme's; everything inside the columns is
 * still driven by WooCommerce hooks, so plugins that add to the summary keep
 * working.
 *
 * @package BHC_Theme
 * @version 3.6.0
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

global $product;

if ( post_password_required() ) {
	echo get_the_password_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core markup.

	return;
}
?>
<div id="product-<?php the_ID(); ?>" <?php wc_product_class( 'single-product-main', $product ); ?>>
	<?php do_action( 'woocommerce_before_single_product_summary_wrapper' ); ?>

	<div class="product-layout">
		<div class="product-layout__media">
			<?php
			/**
			 * Gallery. The theme's own template renders it — see
			 * woocommerce/single-product/product-image.php.
			 */
			do_action( 'woocommerce_before_single_product_summary' );
			?>
		</div>

		<div class="product-layout__summary">
			<div class="summary entry-summary product-summary">
				<?php
				/**
				 * Title, price, excerpt, add to cart, badges, tier table,
				 * wishlist and delivery estimator all attach here.
				 */
				do_action( 'woocommerce_single_product_summary' );
				?>
			</div>
		</div>
	</div>

	<?php
	/**
	 * Tabs, recommendation rails and reviews.
	 */
	do_action( 'woocommerce_after_single_product_summary' );
	?>
</div>
