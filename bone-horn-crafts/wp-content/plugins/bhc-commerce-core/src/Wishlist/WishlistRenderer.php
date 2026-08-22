<?php
/**
 * Wishlist presentation.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Wishlist;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Contracts\HookableInterface;
use BoneHornCrafts\Core\Support\Template;
use WC_Product;

/**
 * Renders wishlist buttons and the wishlist page.
 *
 * The button is a real `<button>` with `aria-pressed`, so it works without
 * JavaScript through a normal form submission and upgrades to a fetch call when
 * the module loads.
 */
final class WishlistRenderer implements HookableInterface {

	/**
	 * Constructor.
	 *
	 * @param WishlistService $wishlist Wishlist service.
	 * @param Template        $template Template renderer.
	 */
	public function __construct( private WishlistService $wishlist, private Template $template ) {}

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'woocommerce_after_shop_loop_item', [ $this, 'render_loop_button' ], 12 );

		// `woocommerce_after_add_to_cart_form` fires *outside* the cart form.
		// Hooking inside it (after_add_to_cart_button) nests a form in a form,
		// which browsers silently drop — the button then submits the cart form
		// instead of toggling the wishlist.
		add_action( 'woocommerce_after_add_to_cart_form', [ $this, 'render_single_button' ], 8 );

		add_shortcode( 'bhc_wishlist', [ $this, 'render_page' ] );
		add_shortcode( 'bhc_wishlist_count', [ $this, 'render_count' ] );

		add_action( 'admin_post_bhc_wishlist_toggle', [ $this, 'handle_form_submission' ] );
		add_action( 'admin_post_nopriv_bhc_wishlist_toggle', [ $this, 'handle_form_submission' ] );
	}

	/**
	 * Renders the button inside a product card.
	 */
	public function render_loop_button(): void {
		global $product;

		if ( $product instanceof WC_Product ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in button().
			echo $this->button( $product, 'compact' );
		}
	}

	/**
	 * Renders the button next to the add-to-cart button.
	 */
	public function render_single_button(): void {
		global $product;

		if ( $product instanceof WC_Product ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in button().
			echo $this->button( $product, 'full' );
		}
	}

	/**
	 * Builds the wishlist toggle markup.
	 *
	 * @param WC_Product $product Product.
	 * @param string     $variant `compact` or `full`.
	 */
	public function button( WC_Product $product, string $variant = 'compact' ): string {
		if ( ! $this->wishlist->is_available() ) {
			return '';
		}

		$in_list = $this->wishlist->contains( $product->get_id() );

		return $this->template->render(
			'wishlist/button.php',
			[
				'product'     => $product,
				'in_list'     => $in_list,
				'variant'     => $variant,
				'nonce'       => wp_create_nonce( 'bhc_wishlist_toggle_' . $product->get_id() ),
				'action_url'  => admin_url( 'admin-post.php' ),
				'label_add'   => __( 'Save to wishlist', 'bhc-commerce-core' ),
				'label_saved' => __( 'Saved to wishlist', 'bhc-commerce-core' ),
			]
		);
	}

	/**
	 * `[bhc_wishlist]` shortcode: renders the saved products grid.
	 */
	public function render_page(): string {
		return $this->template->render(
			'wishlist/page.php',
			[
				'products'  => $this->wishlist->products(),
				'available' => $this->wishlist->is_available(),
				'shop_url'  => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' ),
			]
		);
	}

	/**
	 * `[bhc_wishlist_count]` shortcode: renders the header counter.
	 */
	public function render_count(): string {
		return sprintf(
			'<span class="bhc-wishlist-count" data-bhc-wishlist-count>%d</span>',
			$this->wishlist->count()
		);
	}

	/**
	 * No-JavaScript fallback handler for the toggle form.
	 */
	public function handle_form_submission(): void {
		$product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
		$nonce      = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_wpnonce'] ) ) : '';

		if ( $product_id <= 0 || ! wp_verify_nonce( $nonce, 'bhc_wishlist_toggle_' . $product_id ) ) {
			wp_safe_redirect( wp_get_referer() ?: home_url( '/' ) );

			exit;
		}

		$this->wishlist->toggle( $product_id );

		$redirect = wp_get_referer() ?: get_permalink( $product_id );

		wp_safe_redirect( $redirect ?: home_url( '/' ) );

		exit;
	}
}
