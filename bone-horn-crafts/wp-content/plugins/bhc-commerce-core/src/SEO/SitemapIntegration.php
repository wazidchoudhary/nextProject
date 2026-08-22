<?php
/**
 * Core XML sitemap integration.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\SEO;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Contracts\HookableInterface;

/**
 * Tunes WordPress core's built-in sitemaps rather than shipping another one.
 *
 * Core already generates `/wp-sitemap.xml` with correct paging and caching.
 * What it does not know is which WooCommerce pages are transactional, so those
 * are excluded here — a sitemap that lists the cart is a crawl-budget leak and
 * a soft-404 generator.
 */
final class SitemapIntegration implements HookableInterface {

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_filter( 'wp_sitemaps_posts_query_args', [ $this, 'exclude_transactional_pages' ], 10, 2 );
		add_filter( 'wp_sitemaps_taxonomies', [ $this, 'filter_taxonomies' ], 10, 1 );
		add_filter( 'wp_sitemaps_post_types', [ $this, 'filter_post_types' ], 10, 1 );
	}

	/**
	 * Removes cart/checkout/account pages from the page sitemap.
	 *
	 * @param array<string, mixed> $args      Query args.
	 * @param string               $post_type Post type.
	 *
	 * @return array<string, mixed>
	 */
	public function exclude_transactional_pages( array $args, string $post_type ): array {
		if ( 'page' !== $post_type || ! function_exists( 'wc_get_page_id' ) ) {
			return $args;
		}

		$excluded = array_values(
			array_filter(
				array_map(
					static fn ( string $page ): int => (int) wc_get_page_id( $page ),
					[ 'cart', 'checkout', 'myaccount' ]
				),
				static fn ( int $id ): bool => $id > 0
			)
		);

		if ( [] === $excluded ) {
			return $args;
		}

		$args['post__not_in'] = array_merge( (array) ( $args['post__not_in'] ?? [] ), $excluded );

		return $args;
	}

	/**
	 * Keeps attribute taxonomies out of the sitemap.
	 *
	 * Attribute archives are facet views, not landing pages; the canonical
	 * catalogue entry points are the product categories.
	 *
	 * @param array<string, mixed> $taxonomies Taxonomy objects keyed by name.
	 *
	 * @return array<string, mixed>
	 */
	public function filter_taxonomies( array $taxonomies ): array {
		foreach ( array_keys( $taxonomies ) as $taxonomy ) {
			if ( str_starts_with( (string) $taxonomy, 'pa_' ) || 'product_visibility' === $taxonomy ) {
				unset( $taxonomies[ $taxonomy ] );
			}
		}

		return $taxonomies;
	}

	/**
	 * Ensures products are included in the sitemap.
	 *
	 * @param array<string, mixed> $post_types Post type objects keyed by name.
	 *
	 * @return array<string, mixed>
	 */
	public function filter_post_types( array $post_types ): array {
		if ( ! isset( $post_types['product'] ) && post_type_exists( 'product' ) ) {
			$post_type = get_post_type_object( 'product' );

			if ( null !== $post_type ) {
				$post_types['product'] = $post_type;
			}
		}

		return $post_types;
	}
}
