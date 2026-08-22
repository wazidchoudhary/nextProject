<?php
/**
 * Recommendation aggregation.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Recommendations;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Cache\CacheManager;
use BoneHornCrafts\Core\Contracts\LoggerInterface;
use BoneHornCrafts\Core\Contracts\RecommendationStrategyInterface;
use BoneHornCrafts\Core\Product\ProductRepository;
use BoneHornCrafts\Core\Support\Arr;
use BoneHornCrafts\Core\Support\Options;
use WC_Product;

/**
 * Blends several weighted signals into one ordered recommendation list.
 *
 * Scoring: each strategy returns `product_id => 0..1`, the service multiplies
 * by the strategy weight and sums. A product that several signals agree on
 * therefore outranks a product that only one signal likes, which is what makes
 * "Complete your build" feel deliberate rather than random.
 *
 * Cost control:
 * * The whole blended result is cached per (product, placement, limit) in the
 *   `recommendations` group, which the product invalidator bumps on any
 *   catalogue change.
 * * Strategies are only executed on a cache miss, and each is individually
 *   bounded by `candidate_limit()`.
 * * Hydration happens once, after the blend, on at most `limit` ids.
 */
final class RecommendationService {

	/**
	 * Constructor.
	 *
	 * @param RecommendationStrategyInterface[] $strategies Weighted strategies.
	 * @param ProductRepository                 $products   Product read model.
	 * @param CacheManager                      $cache      Cache manager.
	 * @param Options                           $options    Settings.
	 * @param LoggerInterface                   $logger     Logger.
	 */
	public function __construct(
		private array $strategies,
		private ProductRepository $products,
		private CacheManager $cache,
		private Options $options,
		private LoggerInterface $logger
	) {}

	/**
	 * Returns recommended product ids for a seed product.
	 *
	 * @param WC_Product $product   Seed product.
	 * @param int        $limit     Maximum ids.
	 * @param string     $placement Placement key.
	 * @param int[]      $exclude   Extra ids to exclude.
	 *
	 * @return int[]
	 */
	public function ids_for( WC_Product $product, int $limit = 0, string $placement = 'complete_your_build', array $exclude = [] ): array {
		$limit = $limit > 0 ? $limit : $this->options->int( 'recommendations_limit' );
		$limit = max( 1, min( 24, $limit ) );

		$cache_key = sprintf(
			'rec_%d_%s_%d_%s',
			$product->get_id(),
			sanitize_key( $placement ),
			$limit,
			[] === $exclude ? 'x' : substr( md5( implode( ',', array_map( 'absint', $exclude ) ) ), 0, 8 )
		);

		return (array) $this->cache->for_group( 'recommendations' )->remember(
			$cache_key,
			function () use ( $product, $limit, $placement, $exclude ): array {
				$context = new RecommendationContext( $product, $limit, $exclude, $placement );

				return $this->blend( $context );
			},
			max( MINUTE_IN_SECONDS, $this->options->int( 'recommendations_ttl' ) )
		);
	}

	/**
	 * Returns hydrated recommended products.
	 *
	 * @param WC_Product $product   Seed product.
	 * @param int        $limit     Maximum products.
	 * @param string     $placement Placement key.
	 * @param int[]      $exclude   Extra ids to exclude.
	 *
	 * @return WC_Product[]
	 */
	public function products_for( WC_Product $product, int $limit = 0, string $placement = 'complete_your_build', array $exclude = [] ): array {
		return $this->products->hydrate( $this->ids_for( $product, $limit, $placement, $exclude ) );
	}

	/**
	 * Runs every strategy and merges the weighted scores.
	 *
	 * @param RecommendationContext $context Context.
	 *
	 * @return int[]
	 */
	private function blend( RecommendationContext $context ): array {
		$scores    = [];
		$diagnostic = [];

		foreach ( $this->strategies as $strategy ) {
			if ( ! $strategy instanceof RecommendationStrategyInterface ) {
				continue;
			}

			$weight     = max( 0.0, $strategy->weight() );
			$candidates = $strategy->candidates( $context );

			$diagnostic[ $strategy->id() ] = count( $candidates );

			foreach ( $candidates as $product_id => $score ) {
				$product_id = absint( $product_id );

				if ( $product_id <= 0 ) {
					continue;
				}

				$scores[ $product_id ] = ( $scores[ $product_id ] ?? 0.0 ) + ( $weight * (float) $score );
			}
		}

		$excluded = array_fill_keys( $context->excluded_ids(), true );

		foreach ( array_keys( $scores ) as $product_id ) {
			if ( isset( $excluded[ $product_id ] ) ) {
				unset( $scores[ $product_id ] );
			}
		}

		$ids = Arr::top_scores( $scores, $context->limit );

		// Fall back to the same category when the seed is too new to have any
		// signal at all — an empty rail looks broken on a product page.
		if ( count( $ids ) < $context->limit && [] !== $context->category_ids ) {
			$fallback = $this->products->query(
				[
					'limit'     => $context->limit - count( $ids ),
					'exclude'   => array_merge( $context->excluded_ids(), $ids ),
					'orderby'   => 'popularity',
					'tax_query' => [
						[
							'taxonomy' => 'product_cat',
							'field'    => 'term_id',
							'terms'    => $context->category_ids,
						],
					],
				]
			);

			$ids = array_values( array_unique( array_merge( $ids, $fallback ) ) );
		}

		$this->logger->debug(
			'recommendations.blended',
			[
				'seed'       => $context->product->get_id(),
				'placement'  => $context->placement,
				'strategies' => $diagnostic,
				'returned'   => count( $ids ),
			]
		);

		/**
		 * Filters the final recommendation id list.
		 *
		 * @since 1.0.0
		 *
		 * @param int[]                 $ids     Recommended product ids.
		 * @param RecommendationContext $context Context.
		 */
		return (array) apply_filters( 'bhc_recommendation_ids', array_slice( $ids, 0, $context->limit ), $context );
	}
}
