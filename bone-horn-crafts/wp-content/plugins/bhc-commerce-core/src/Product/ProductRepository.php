<?php
/**
 * Product read model.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Product;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Analytics\ProductStatsRepository;
use BoneHornCrafts\Core\Cache\CacheManager;
use WC_Product;

/**
 * Bounded, cache-aware product queries used by every merchandising surface.
 *
 * Rules this class exists to enforce:
 *
 * * Every query has a limit. No code path can load the whole catalogue.
 * * Id lists are cached; product objects are not. Caching hydrated
 *   `WC_Product` objects duplicates WooCommerce's own caching and goes stale in
 *   ways that are painful to debug. Caching the id list removes the expensive
 *   part (the query) and keeps invalidation to a single group bump.
 * * Hydration primes post, meta and term caches in one batch, so rendering a
 *   twelve-card rail costs a handful of queries instead of dozens — the
 *   classic WooCommerce N+1.
 */
final class ProductRepository {

	/**
	 * Constructor.
	 *
	 * @param ProductQuery           $query Query builder.
	 * @param CacheManager           $cache Cache manager.
	 * @param ProductStatsRepository $stats Merchandising stats.
	 */
	public function __construct(
		private ProductQuery $query,
		private CacheManager $cache,
		private ProductStatsRepository $stats
	) {}

	/**
	 * Runs a bounded product query and returns ids.
	 *
	 * @param array<string, mixed> $args Query arguments.
	 *
	 * @return int[]
	 */
	public function query( array $args ): array {
		return $this->query->ids( $args );
	}

	/**
	 * Total rows found by the most recent counted query.
	 */
	public function last_total(): int {
		return $this->query->last_total();
	}

	/**
	 * Recently published products.
	 *
	 * @param int $limit Maximum products.
	 *
	 * @return int[]
	 */
	public function new_arrival_ids( int $limit = 8 ): array {
		$limit = $this->clamp( $limit );

		return (array) $this->cache->for_group( 'products' )->remember(
			'new_arrivals_' . $limit,
			fn (): array => $this->query(
				[
					'limit'   => $limit,
					'orderby' => 'date',
					'order'   => 'DESC',
				]
			),
			2 * HOUR_IN_SECONDS
		);
	}

	/**
	 * Bestsellers from the merchandising index, topped up from `total_sales`.
	 *
	 * @param int $limit Maximum products.
	 *
	 * @return int[]
	 */
	public function bestseller_ids( int $limit = 8 ): array {
		$limit = $this->clamp( $limit );

		return (array) $this->cache->for_group( 'stats' )->remember(
			'bestsellers_' . $limit,
			function () use ( $limit ): array {
				$ids = $this->stats->bestseller_ids( $limit );

				if ( count( $ids ) >= $limit ) {
					return $ids;
				}

				$fallback = $this->query(
					[
						'limit'   => $limit,
						'orderby' => 'popularity',
						'order'   => 'DESC',
						'exclude' => $ids,
					]
				);

				return array_slice( array_values( array_unique( array_merge( $ids, $fallback ) ) ), 0, $limit );
			},
			3 * HOUR_IN_SECONDS
		);
	}

	/**
	 * Products currently on sale (uses the lookup `onsale` flag).
	 *
	 * @param int $limit Maximum products.
	 *
	 * @return int[]
	 */
	public function on_sale_ids( int $limit = 8 ): array {
		$limit = $this->clamp( $limit );

		return (array) $this->cache->for_group( 'products' )->remember(
			'on_sale_' . $limit,
			fn (): array => $this->query(
				[
					'limit'   => $limit,
					'on_sale' => true,
					'orderby' => 'popularity',
				]
			),
			2 * HOUR_IN_SECONDS
		);
	}

	/**
	 * Products in a category.
	 *
	 * @param string $category_slug Category slug.
	 * @param int    $limit         Maximum products.
	 * @param string $orderby       Ordering.
	 *
	 * @return int[]
	 */
	public function category_ids( string $category_slug, int $limit = 8, string $orderby = 'date' ): array {
		$category_slug = sanitize_title( $category_slug );
		$limit         = $this->clamp( $limit );

		if ( '' === $category_slug ) {
			return [];
		}

		return (array) $this->cache->for_group( 'products' )->remember(
			'category_' . $category_slug . '_' . $limit . '_' . sanitize_key( $orderby ),
			fn (): array => $this->query(
				[
					'limit'    => $limit,
					'orderby'  => $orderby,
					'category' => [ $category_slug ],
				]
			),
			2 * HOUR_IN_SECONDS
		);
	}

	/**
	 * Products carrying a merchandising tag.
	 *
	 * @param string $tag_slug Tag slug.
	 * @param int    $limit    Maximum products.
	 *
	 * @return int[]
	 */
	public function tag_ids( string $tag_slug, int $limit = 8 ): array {
		$tag_slug = sanitize_title( $tag_slug );
		$limit    = $this->clamp( $limit );

		if ( '' === $tag_slug ) {
			return [];
		}

		return (array) $this->cache->for_group( 'products' )->remember(
			'tag_' . $tag_slug . '_' . $limit,
			fn (): array => $this->query(
				[
					'limit'   => $limit,
					'orderby' => 'popularity',
					'tag'     => [ $tag_slug ],
				]
			),
			2 * HOUR_IN_SECONDS
		);
	}

	/**
	 * Products inside a price band, ordered by popularity.
	 *
	 * @param float $min     Minimum price.
	 * @param float $max     Maximum price.
	 * @param int   $limit   Maximum products.
	 * @param int[] $exclude Ids to exclude.
	 *
	 * @return int[]
	 */
	public function price_band_ids( float $min, float $max, int $limit = 8, array $exclude = [] ): array {
		if ( $max <= 0.0 ) {
			return [];
		}

		return $this->query(
			[
				'limit'     => $this->clamp( $limit ),
				'min_price' => max( 0.0, $min ),
				'max_price' => $max,
				'orderby'   => 'popularity',
				'exclude'   => $exclude,
			]
		);
	}

	/**
	 * Products flagged with a manual badge slug.
	 *
	 * @param string $badge_slug Badge slug.
	 * @param int    $limit      Maximum products.
	 *
	 * @return int[]
	 */
	public function badge_ids( string $badge_slug, int $limit = 8 ): array {
		$badge_slug = sanitize_key( $badge_slug );
		$limit      = $this->clamp( $limit );

		if ( '' === $badge_slug ) {
			return [];
		}

		return (array) $this->cache->for_group( 'products' )->remember(
			'badge_' . $badge_slug . '_' . $limit,
			fn (): array => $this->query(
				[
					'limit'      => $limit,
					'orderby'    => 'popularity',
					'meta_query' => [
						[
							'key'     => ProductMeta::BADGES,
							'value'   => '"' . $badge_slug . '"',
							'compare' => 'LIKE',
						],
					],
				]
			),
			2 * HOUR_IN_SECONDS
		);
	}

	/**
	 * Products at or below the low stock threshold.
	 *
	 * @param int $limit Maximum products.
	 *
	 * @return int[]
	 */
	public function low_stock_ids( int $limit = 10 ): array {
		$limit     = $this->clamp( $limit );
		$threshold = max( 1, (int) get_option( 'woocommerce_notify_low_stock_amount', 2 ) );

		return (array) $this->cache->for_group( 'products' )->remember(
			'low_stock_' . $limit . '_' . $threshold,
			fn (): array => $this->query(
				[
					'limit'         => $limit,
					'max_stock'     => $threshold,
					'in_stock_only' => true,
					'orderby'       => 'title',
					'order'         => 'ASC',
					'visibility'    => 'any',
				]
			),
			15 * MINUTE_IN_SECONDS
		);
	}

	/**
	 * Hydrates product objects for a list of ids, preserving order.
	 *
	 * @param int[] $ids Product ids.
	 *
	 * @return WC_Product[]
	 */
	public function hydrate( array $ids ): array {
		$ids = array_slice( array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) ), 0, 100 );

		if ( [] === $ids ) {
			return [];
		}

		$this->prime( $ids );

		$products = [];

		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );

			if ( $product instanceof WC_Product && 'publish' === $product->get_status() ) {
				$products[] = $product;
			}
		}

		return $products;
	}

	/**
	 * Warms the post, meta and term caches for a list of product ids.
	 *
	 * This is the single highest-value optimisation in the plugin: without it
	 * every `get_meta()`, `get_the_terms()` and permalink call inside a product
	 * card fires its own query.
	 *
	 * @param int[] $ids Product ids.
	 */
	public function prime( array $ids ): void {
		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );

		if ( [] === $ids ) {
			return;
		}

		_prime_post_caches( $ids, false, false );
		update_meta_cache( 'post', $ids );
		update_object_term_cache( $ids, 'product' );

		// Card imagery is part of the same render, and an unprimed attachment
		// costs a post row plus a meta row each. Thumbnail ids are already in
		// the meta cache primed above, so collecting them is free.
		$attachment_ids = [];

		foreach ( $ids as $id ) {
			$thumbnail_id = (int) get_post_thumbnail_id( $id );

			if ( $thumbnail_id > 0 ) {
				$attachment_ids[] = $thumbnail_id;
			}
		}

		$attachment_ids = array_values( array_unique( $attachment_ids ) );

		if ( [] !== $attachment_ids ) {
			_prime_post_caches( $attachment_ids, false, true );
		}
	}

	/**
	 * Published product count, cached for the dashboard and sitemap.
	 */
	public function published_count(): int {
		return (int) $this->cache->for_group( 'products' )->remember(
			'published_count',
			static function (): int {
				$counts = (array) wp_count_posts( 'product' );

				return (int) ( $counts['publish'] ?? 0 );
			},
			HOUR_IN_SECONDS
		);
	}

	/**
	 * Clamps a caller supplied limit into a sane range.
	 *
	 * @param int $limit Requested limit.
	 */
	private function clamp( int $limit ): int {
		return max( 1, min( 48, $limit ) );
	}
}
