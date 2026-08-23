<?php
/**
 * Primes the store's own pages in a single query.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Performance;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Contracts\HookableInterface;

/**
 * Fetches every page the storefront links to in one query instead of fourteen.
 *
 * A store resolves a lot of pages on an ordinary request: WooCommerce asks for
 * the shop, cart, checkout, account and terms pages; the header and footer link
 * to the wishlist and the policy pages; the legal menu checks each of its own.
 * Every one of those goes through `get_post()`, and a cold cache turns each
 * into its own `SELECT * FROM wp_posts WHERE ID = N`.
 *
 * Measured on a cold home-page render: twenty such queries for fourteen
 * distinct pages, several fetched more than once before anything cached them.
 *
 * `_prime_post_caches()` takes the whole list at once, so the same information
 * arrives in one round trip and every later `get_post()` is a cache hit.
 */
final class PagePrimer implements HookableInterface {

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		// Front end only. wp-admin resolves a different and much wider set of
		// posts, and priming this handful there would be noise.
		if ( is_admin() ) {
			return;
		}

		// Priority 0, and that is load-bearing. WooCommerce resolves the shop,
		// cart and checkout pages from WC_Post_Types::register_post_types on
		// `init` at priority 5; priming after it simply adds a query to work
		// already done. Measured both ways: at 99 the page count went up, not
		// down.
		add_action( 'init', [ $this, 'prime' ], 0 );
	}

	/**
	 * Warms the post cache for the store's pages.
	 */
	public function prime(): void {
		$ids = $this->page_ids();

		if ( count( $ids ) < 2 ) {
			return;
		}

		// update_term_cache is false on purpose: these are pages, and nothing
		// reads taxonomy terms off them. update_meta_cache stays on, because
		// page templates and our own markers are read from meta.
		_prime_post_caches( $ids, false, true );
	}

	/**
	 * Every page id the storefront is likely to resolve.
	 *
	 * @return int[]
	 */
	public function page_ids(): array {
		$ids = [];

		if ( function_exists( 'wc_get_page_id' ) ) {
			foreach ( [ 'shop', 'cart', 'checkout', 'myaccount', 'terms' ] as $page ) {
				$ids[] = (int) wc_get_page_id( $page );
			}
		}

		foreach ( [ 'woocommerce_refund_returns_page_id', 'wp_page_for_privacy_policy', 'page_on_front', 'page_for_posts' ] as $option ) {
			$ids[] = (int) get_option( $option, 0 );
		}

		/**
		 * Filters the pages primed in one query on every front-end request.
		 *
		 * Modules add their own here — the wishlist page, the policy pages —
		 * rather than each resolving its page separately.
		 *
		 * @since 1.0.0
		 *
		 * @param int[] $ids Page ids.
		 */
		$ids = (array) apply_filters( 'bhc_primed_page_ids', $ids );

		$ids = array_map( 'intval', $ids );

		return array_values( array_unique( array_filter( $ids, static fn ( int $id ): bool => $id > 0 ) ) );
	}
}
