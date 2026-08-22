<?php
/**
 * Order history based recommendations.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Recommendations\Strategies;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Contracts\RecommendationStrategyInterface;
use BoneHornCrafts\Core\Recommendations\AffinityRepository;
use BoneHornCrafts\Core\Recommendations\RecommendationContext;

/**
 * Reads the pre-computed "bought together" index.
 *
 * The heavy lifting (scanning order items, counting co-occurrence) happens in
 * the nightly indexer; this strategy is one indexed SELECT. It is weighted
 * highest because real purchase behaviour beats every heuristic.
 */
final class BoughtTogetherStrategy implements RecommendationStrategyInterface {

	/**
	 * Constructor.
	 *
	 * @param AffinityRepository $affinity Affinity index.
	 * @param float              $weight   Relative weight.
	 */
	public function __construct( private AffinityRepository $affinity, private float $weight = 2.0 ) {}

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'bought_together';
	}

	/**
	 * {@inheritDoc}
	 */
	public function weight(): float {
		return $this->weight;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param RecommendationContext $context Context.
	 *
	 * @return array<int, float>
	 */
	public function candidates( RecommendationContext $context ): array {
		$related = $this->affinity->related(
			$context->product->get_id(),
			$context->candidate_limit(),
			AffinityRepository::STRATEGY_BOUGHT_TOGETHER
		);

		if ( [] === $related ) {
			return [];
		}

		$excluded = array_fill_keys( $context->excluded_ids(), true );
		$max      = max( $related );

		$scores = [];

		foreach ( $related as $product_id => $score ) {
			if ( isset( $excluded[ $product_id ] ) || $max <= 0 ) {
				continue;
			}

			$scores[ $product_id ] = round( $score / $max, 5 );
		}

		return $scores;
	}
}
