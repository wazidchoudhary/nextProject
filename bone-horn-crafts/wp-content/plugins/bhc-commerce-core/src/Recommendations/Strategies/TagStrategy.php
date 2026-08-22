<?php
/**
 * Tag based recommendations.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Recommendations\Strategies;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Recommendations\RecommendationContext;

/**
 * Suggests products sharing merchandising tags such as `viking`,
 * `workshop-essential` or `gift`.
 */
final class TagStrategy extends AbstractQueryStrategy {

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'shared_tags';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param RecommendationContext $context Context.
	 *
	 * @return array<int, float>
	 */
	public function candidates( RecommendationContext $context ): array {
		if ( [] === $context->tag_ids ) {
			return [];
		}

		$ids = $this->products->query(
			[
				'limit'     => $context->candidate_limit(),
				'exclude'   => $context->excluded_ids(),
				'orderby'   => 'date',
				'order'     => 'DESC',
				'tax_query' => [
					[
						'taxonomy' => 'product_tag',
						'field'    => 'term_id',
						'terms'    => array_slice( $context->tag_ids, 0, 8 ),
					],
				],
			]
		);

		return $this->score_by_rank( $ids );
	}
}
