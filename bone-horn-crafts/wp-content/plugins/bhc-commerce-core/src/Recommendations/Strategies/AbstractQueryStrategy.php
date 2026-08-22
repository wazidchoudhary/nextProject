<?php
/**
 * Shared query helper for recommendation strategies.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Recommendations\Strategies;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Contracts\RecommendationStrategyInterface;
use BoneHornCrafts\Core\Product\ProductRepository;
use BoneHornCrafts\Core\Recommendations\RecommendationContext;

/**
 * Base class for strategies that find candidates with a product query.
 */
abstract class AbstractQueryStrategy implements RecommendationStrategyInterface {

	/**
	 * Constructor.
	 *
	 * @param ProductRepository $products Bounded product query helper.
	 * @param float             $weight   Relative weight.
	 */
	public function __construct( protected ProductRepository $products, protected float $weight = 1.0 ) {}

	/**
	 * {@inheritDoc}
	 */
	public function weight(): float {
		return $this->weight;
	}

	/**
	 * Converts an ordered id list into a decaying score map.
	 *
	 * The first result scores 1.0 and later results decay linearly, so a
	 * strategy's own ordering survives aggregation.
	 *
	 * @param int[] $ids Ordered ids.
	 *
	 * @return array<int, float>
	 */
	protected function score_by_rank( array $ids ): array {
		$ids   = array_values( array_filter( array_map( 'absint', $ids ) ) );
		$total = count( $ids );

		if ( 0 === $total ) {
			return [];
		}

		$scores = [];

		foreach ( $ids as $index => $id ) {
			$scores[ $id ] = round( 1.0 - ( $index / max( 1, $total ) ) * 0.5, 5 );
		}

		return $scores;
	}
}
