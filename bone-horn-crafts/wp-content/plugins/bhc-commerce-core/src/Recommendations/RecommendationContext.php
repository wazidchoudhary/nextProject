<?php
/**
 * Recommendation input context.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Recommendations;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Product\Attributes\AttributeCatalog;
use WC_Product;

/**
 * Everything a strategy needs about the seed product, resolved once.
 *
 * Resolving the seed's categories, tags and attribute terms up front is what
 * keeps the strategies free of N+1 queries: five strategies share one set of
 * term lookups instead of each doing their own.
 */
final class RecommendationContext {

	/**
	 * Seed product category term ids.
	 *
	 * @var int[]
	 */
	public readonly array $category_ids;

	/**
	 * Seed product tag term ids.
	 *
	 * @var int[]
	 */
	public readonly array $tag_ids;

	/**
	 * Attribute term ids keyed by taxonomy.
	 *
	 * @var array<string, int[]>
	 */
	public readonly array $attribute_terms;

	/**
	 * Seed product price.
	 */
	public readonly float $price;

	/**
	 * Constructor.
	 *
	 * @param WC_Product $product   Seed product.
	 * @param int        $limit     Number of recommendations wanted.
	 * @param int[]      $exclude   Product ids to exclude.
	 * @param string     $placement Placement identifier, used for cache keys and filters.
	 */
	public function __construct(
		public readonly WC_Product $product,
		public readonly int $limit = 8,
		public readonly array $exclude = [],
		public readonly string $placement = 'complete_your_build'
	) {
		$product_id = $product->get_id();

		$this->category_ids = $this->term_ids( $product_id, 'product_cat' );
		$this->tag_ids      = $this->term_ids( $product_id, 'product_tag' );
		$this->price        = (float) $product->get_price();

		$attributes = [];

		foreach ( AttributeCatalog::facet_slugs() as $slug ) {
			$taxonomy = AttributeCatalog::taxonomy( $slug );
			$term_ids = $this->term_ids( $product_id, $taxonomy );

			if ( [] !== $term_ids ) {
				$attributes[ $taxonomy ] = $term_ids;
			}
		}

		$this->attribute_terms = $attributes;
	}

	/**
	 * Ids that must never appear in the result (the seed plus caller exclusions).
	 *
	 * @return int[]
	 */
	public function excluded_ids(): array {
		return array_values( array_unique( array_merge( [ $this->product->get_id() ], array_map( 'absint', $this->exclude ) ) ) );
	}

	/**
	 * How many candidates each strategy should fetch.
	 *
	 * Over-fetching a little lets the aggregator drop duplicates and
	 * out-of-stock items without a second query, but the multiplier is capped
	 * so a large `limit` cannot turn into an unbounded query.
	 */
	public function candidate_limit(): int {
		return max( 4, min( 40, $this->limit * 3 ) );
	}

	/**
	 * Price band used by the price-proximity strategy.
	 *
	 * @return array{0:float,1:float}
	 */
	public function price_band(): array {
		if ( $this->price <= 0 ) {
			return [ 0.0, 0.0 ];
		}

		return [ round( $this->price * 0.6, 2 ), round( $this->price * 1.6, 2 ) ];
	}

	/**
	 * Reads term ids from the object term cache.
	 *
	 * @param int    $product_id Product id.
	 * @param string $taxonomy   Taxonomy.
	 *
	 * @return int[]
	 */
	private function term_ids( int $product_id, string $taxonomy ): array {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return [];
		}

		$terms = get_the_terms( $product_id, $taxonomy );

		if ( ! is_array( $terms ) ) {
			return [];
		}

		return array_values( array_map( static fn ( $term ): int => (int) $term->term_id, $terms ) );
	}
}
