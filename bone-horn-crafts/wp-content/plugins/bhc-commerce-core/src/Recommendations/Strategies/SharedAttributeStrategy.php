<?php
/**
 * Shared attribute recommendations.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Recommendations\Strategies;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Recommendations\RecommendationContext;

/**
 * Suggests products that share the seed's material, finish or application.
 *
 * Application is the strongest craft signal: someone buying a bone nut blank is
 * building a guitar, so bridge pins and saddle blanks are the useful
 * suggestion — not "another bone product".
 */
final class SharedAttributeStrategy extends AbstractQueryStrategy {

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'shared_attributes';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param RecommendationContext $context Context.
	 *
	 * @return array<int, float>
	 */
	public function candidates( RecommendationContext $context ): array {
		if ( [] === $context->attribute_terms ) {
			return [];
		}

		$tax_query = [ 'relation' => 'OR' ];

		foreach ( $context->attribute_terms as $taxonomy => $term_ids ) {
			$tax_query[] = [
				'taxonomy' => $taxonomy,
				'field'    => 'term_id',
				'terms'    => array_slice( $term_ids, 0, 5 ),
			];
		}

		$ids = $this->products->query(
			[
				'limit'     => $context->candidate_limit(),
				'exclude'   => $context->excluded_ids(),
				'orderby'   => 'popularity',
				'order'     => 'DESC',
				'tax_query' => $tax_query,
			]
		);

		return $this->score_by_rank( $ids );
	}
}
