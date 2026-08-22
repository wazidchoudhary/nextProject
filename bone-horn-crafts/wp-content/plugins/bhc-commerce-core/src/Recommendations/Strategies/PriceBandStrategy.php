<?php
/**
 * Price proximity recommendations.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Recommendations\Strategies;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Recommendations\RecommendationContext;

/**
 * Suggests products in a comparable price band.
 *
 * Uses WooCommerce's `wc_product_meta_lookup` price columns through
 * `wc_get_products( 'price' => ... )`, so the filter is an indexed range scan
 * rather than a `postmeta` join on `_price`.
 */
final class PriceBandStrategy extends AbstractQueryStrategy {

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'price_band';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param RecommendationContext $context Context.
	 *
	 * @return array<int, float>
	 */
	public function candidates( RecommendationContext $context ): array {
		[ $min, $max ] = $context->price_band();

		if ( $max <= 0.0 ) {
			return [];
		}

		$ids = $this->products->price_band_ids( $min, $max, $context->candidate_limit(), $context->excluded_ids() );

		return $this->score_by_rank( $ids );
	}
}
