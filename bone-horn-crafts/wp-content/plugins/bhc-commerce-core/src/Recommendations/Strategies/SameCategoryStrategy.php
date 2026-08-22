<?php
/**
 * Same-category recommendations.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Recommendations\Strategies;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Recommendations\RecommendationContext;

/**
 * Suggests other products from the same category.
 *
 * This is the baseline signal: a maker looking at buffalo horn scales is
 * almost always interested in the rest of that shelf.
 */
final class SameCategoryStrategy extends AbstractQueryStrategy {

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'same_category';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param RecommendationContext $context Context.
	 *
	 * @return array<int, float>
	 */
	public function candidates( RecommendationContext $context ): array {
		if ( [] === $context->category_ids ) {
			return [];
		}

		$ids = $this->products->query(
			[
				'limit'     => $context->candidate_limit(),
				'exclude'   => $context->excluded_ids(),
				'orderby'   => 'popularity',
				'order'     => 'DESC',
				'tax_query' => [
					[
						'taxonomy'         => 'product_cat',
						'field'            => 'term_id',
						'terms'            => $context->category_ids,
						'include_children' => false,
					],
				],
			]
		);

		return $this->score_by_rank( $ids );
	}
}
