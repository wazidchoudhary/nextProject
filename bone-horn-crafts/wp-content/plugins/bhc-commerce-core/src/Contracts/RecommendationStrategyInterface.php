<?php
/**
 * Recommendation strategy contract.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Contracts;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Recommendations\RecommendationContext;

/**
 * A single scoring strategy used by the recommendation service.
 *
 * Strategies are pure with respect to WordPress state: they receive a context
 * object and return `product_id => score` maps. New strategies can be added
 * through the `bhc_recommendation_strategies` filter without touching the
 * service (open/closed principle).
 */
interface RecommendationStrategyInterface {

	/**
	 * Unique strategy identifier, used for weighting and debugging.
	 */
	public function id(): string;

	/**
	 * Relative weight applied to this strategy's scores.
	 */
	public function weight(): float;

	/**
	 * Returns candidate product IDs mapped to a 0..1 score.
	 *
	 * @param RecommendationContext $context Seed product and constraints.
	 *
	 * @return array<int, float>
	 */
	public function candidates( RecommendationContext $context ): array;
}
