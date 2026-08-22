<?php
/**
 * WooCommerce integration.
 *
 * The theme replaces WooCommerce's default page furniture with its own layout
 * hooks, and adds the storefront affordances a materials shop needs: a Buy Now
 * button, a sticky mobile purchase bar, and product tabs for care and shipping
 * information. Business rules stay in the plugin; this file is presentation.
 *
 * @package BHC_Theme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Removes the default WooCommerce page furniture.
 */
function bhc_woocommerce_unhook_defaults(): void {
	remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
	remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
	remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
	remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );

	// The theme renders its own loop card, so the default loop hooks are not
	// used at all — see woocommerce/content-product.php.
	remove_action( 'woocommerce_before_shop_loop_item', 'woocommerce_template_loop_product_link_open', 10 );
	remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail', 10 );
	remove_action( 'woocommerce_shop_loop_item_title', 'woocommerce_template_loop_product_title', 10 );
	remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_rating', 5 );
	remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );
	remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_product_link_close', 5 );
	remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );

	// Single product: keep WooCommerce's summary hooks but drop the default
	// meta block, which prints raw taxonomy lists.
	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );
	remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_upsell_display', 15 );
}

add_action( 'init', 'bhc_woocommerce_unhook_defaults' );

/**
 * Opens the theme's content wrapper on WooCommerce pages.
 */
function bhc_woocommerce_wrapper_start(): void {
	echo '<main id="main" class="site-main woocommerce-main"><div class="container">';

	bhc_breadcrumbs();
}

/**
 * Closes the theme's content wrapper.
 */
function bhc_woocommerce_wrapper_end(): void {
	echo '</div></main>';
}

add_action( 'woocommerce_before_main_content', 'bhc_woocommerce_wrapper_start', 10 );
add_action( 'woocommerce_after_main_content', 'bhc_woocommerce_wrapper_end', 10 );

/**
 * Catalogue grid shape.
 *
 * @return int
 */
function bhc_loop_columns(): int {
	return 3;
}

add_filter( 'loop_shop_columns', 'bhc_loop_columns', 20 );

/**
 * Products per archive page.
 *
 * Twelve fills four rows of three without a partial row, and keeps the query
 * (and the hydration batch behind it) at a size that stays fast.
 *
 * @return int
 */
function bhc_products_per_page(): int {
	return 12;
}

add_filter( 'loop_shop_per_page', 'bhc_products_per_page', 20 );

/**
 * Adds a "Buy now" button beside Add to cart on the product page.
 *
 * Materials buyers frequently order a single pair; a direct route to checkout
 * removes a step without hiding the cart.
 */
function bhc_buy_now_button(): void {
	global $product;

	if ( ! $product instanceof WC_Product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
		return;
	}

	printf(
		'<button type="submit" name="bhc_buy_now" value="1" class="bhc-button bhc-button--ghost bhc-buy-now">%s</button>',
		esc_html__( 'Buy now', 'bhc-theme' )
	);
}

add_action( 'woocommerce_after_add_to_cart_button', 'bhc_buy_now_button', 5 );

/**
 * Sends a Buy Now submission straight to checkout.
 *
 * @param string $url Redirect URL.
 *
 * @return string
 */
function bhc_buy_now_redirect( string $url ): string {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce validates the add-to-cart request itself; this only chooses the redirect target.
	if ( isset( $_POST['bhc_buy_now'] ) ) {
		return bhc_wc_page_url( 'checkout' );
	}

	return $url;
}

add_filter( 'woocommerce_add_to_cart_redirect', 'bhc_buy_now_redirect' );

/**
 * Replaces the default product tabs with ones a maker actually reads.
 *
 * @param array<string, array<string, mixed>> $tabs Default tabs.
 *
 * @return array<string, array<string, mixed>>
 */
function bhc_product_tabs( array $tabs ): array {
	global $product;

	if ( isset( $tabs['description'] ) ) {
		$tabs['description']['title'] = __( 'Material detail', 'bhc-theme' );
	}

	unset( $tabs['additional_information'] );

	$tabs['bhc_specification'] = [
		'title'    => __( 'Specification', 'bhc-theme' ),
		'priority' => 15,
		'callback' => 'bhc_render_specification_tab',
	];

	if ( $product instanceof WC_Product && '' !== \BoneHornCrafts\Core\Product\ProductMeta::care_instructions( $product ) ) {
		$tabs['bhc_care'] = [
			'title'    => __( 'Care &amp; finishing', 'bhc-theme' ),
			'priority' => 20,
			'callback' => 'bhc_render_care_tab',
		];
	}

	$tabs['bhc_shipping'] = [
		'title'    => __( 'Shipping &amp; returns', 'bhc-theme' ),
		'priority' => 25,
		'callback' => 'bhc_render_shipping_tab',
	];

	return $tabs;
}

add_filter( 'woocommerce_product_tabs', 'bhc_product_tabs', 20 );

/**
 * Renders the specification tab.
 */
function bhc_render_specification_tab(): void {
	global $product;

	if ( ! $product instanceof WC_Product ) {
		return;
	}

	get_template_part( 'template-parts/product/specification', null, [ 'product' => $product ] );
}

/**
 * Renders the care tab.
 */
function bhc_render_care_tab(): void {
	global $product;

	if ( ! $product instanceof WC_Product ) {
		return;
	}

	printf(
		'<h2>%s</h2><p>%s</p>',
		esc_html__( 'Care and finishing', 'bhc-theme' ),
		esc_html( \BoneHornCrafts\Core\Product\ProductMeta::care_instructions( $product ) )
	);

	printf(
		'<p><a href="%s">%s</a></p>',
		esc_url( home_url( '/care-finishing-guide/' ) ),
		esc_html__( 'Read the full care and finishing guide', 'bhc-theme' )
	);
}

/**
 * Renders the shipping and returns tab.
 */
function bhc_render_shipping_tab(): void {
	get_template_part( 'template-parts/product/shipping' );
}

/**
 * Adds the sticky mobile purchase bar.
 */
function bhc_sticky_add_to_cart(): void {
	global $product;

	if ( ! is_product() || ! $product instanceof WC_Product ) {
		return;
	}

	get_template_part( 'template-parts/product/sticky-cart', null, [ 'product' => $product ] );
}

add_action( 'wp_footer', 'bhc_sticky_add_to_cart' );

/**
 * Styles the loop add-to-cart button as a quiet secondary action.
 *
 * @param array<string, mixed> $args    Button arguments.
 * @param WC_Product           $product Product.
 *
 * @return array<string, mixed>
 */
function bhc_loop_add_to_cart_args( array $args, $product ): array {
	$args['class'] = 'bhc-button bhc-button--quiet';

	return $args;
}

add_filter( 'woocommerce_loop_add_to_cart_args', 'bhc_loop_add_to_cart_args', 10, 2 );

/**
 * Replaces WooCommerce's ordering dropdown labels with plainer wording.
 *
 * @param array<string, string> $options Sort options.
 *
 * @return array<string, string>
 */
function bhc_catalog_ordering_options( array $options ): array {
	return [
		'menu_order' => __( 'Featured', 'bhc-theme' ),
		'date'       => __( 'Newest first', 'bhc-theme' ),
		'popularity' => __( 'Best selling', 'bhc-theme' ),
		'rating'     => __( 'Highest rated', 'bhc-theme' ),
		'price'      => __( 'Price: low to high', 'bhc-theme' ),
		'price-desc' => __( 'Price: high to low', 'bhc-theme' ),
	];
}

add_filter( 'woocommerce_catalog_orderby', 'bhc_catalog_ordering_options', 20 );

/**
 * Formats the archive result count.
 *
 * @param string $markup Existing markup.
 *
 * @return string
 */
function bhc_result_count_markup( string $markup ): string {
	return str_replace( '<p class="woocommerce-result-count"', '<p class="woocommerce-result-count filter-bar__count"', $markup );
}

add_filter( 'woocommerce_result_count', 'bhc_result_count_markup' );

/**
 * Keeps the mini cart count accurate for the header without cart fragments.
 *
 * @param array<string, mixed> $fragments Cart fragments.
 *
 * @return array<string, mixed>
 */
function bhc_cart_count_fragment( array $fragments ): array {
	ob_start();

	printf(
		'<span class="bhc-icon-button__count%s" data-bhc-cart-count>%d</span>',
		bhc_cart_count() > 0 ? '' : ' is-empty',
		bhc_cart_count()
	);

	$fragments['[data-bhc-cart-count]'] = (string) ob_get_clean();

	return $fragments;
}

add_filter( 'woocommerce_add_to_cart_fragments', 'bhc_cart_count_fragment' );

/**
 * Replaces the stock message with wording that suits lot-based stock.
 *
 * WooCommerce wraps the returned string in its own `<p class="stock">`, so this
 * returns plain text rather than markup.
 *
 * @param string     $text    Availability text.
 * @param WC_Product $product Product.
 *
 * @return string
 */
function bhc_availability_text( string $text, $product ): string {
	if ( ! $product instanceof WC_Product ) {
		return $text;
	}

	if ( ! $product->is_in_stock() ) {
		return __( 'Out of stock — next lot in preparation', 'bhc-theme' );
	}

	$quantity = $product->get_stock_quantity();

	if ( $product->managing_stock() && null !== $quantity && $quantity <= 6 ) {
		/* translators: %d: remaining stock. */
		return sprintf( __( 'Only %d left from this lot', 'bhc-theme' ), (int) $quantity );
	}

	return __( 'In stock, ready to dispatch', 'bhc-theme' );
}

add_filter( 'woocommerce_get_availability_text', 'bhc_availability_text', 10, 2 );

/**
 * Removes the "Product" prefix from archive titles.
 *
 * @param string $title Archive title.
 *
 * @return string
 */
function bhc_archive_title( string $title ): string {
	return wp_strip_all_tags( $title );
}

add_filter( 'get_the_archive_title', 'bhc_archive_title', 20 );
