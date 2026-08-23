<?php
/**
 * "Complete Your Build" — the store-window version of the recommendation engine.
 *
 * The product page asks "what goes with this one?". Here the same affinity index
 * is aggregated across the whole catalogue to answer "what do makers add to
 * finish a build?", which is the question a visitor who has not chosen a
 * material yet is actually asking.
 *
 * Falls back to the workshop-essential tag when the index is empty — a brand new
 * store has no order history to learn from, and an empty rail is worse than a
 * curated one.
 *
 * @package BHC_Theme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$affinity = bhc_service( \BoneHornCrafts\Core\Recommendations\AffinityRepository::class );
$products = [];

if ( null !== $affinity ) {
	$repository = bhc_service( \BoneHornCrafts\Core\Product\ProductRepository::class );

	if ( null !== $repository ) {
		$products = $repository->hydrate( $affinity->most_paired_ids( 4 ) );
	}
}

if ( count( $products ) < 4 ) {
	$products = bhc_products_for( 'tag', 4, 'workshop-essential' );
}

if ( [] === $products ) {
	return;
}
?>
<section class="section" aria-labelledby="home-complete-build">
	<div class="container">
		<?php
		bhc_section_header(
			__( 'Complete your build', 'bhc-theme' ),
			__( 'Pins, bolsters and spacer stock that makers add alongside their scales.', 'bhc-theme' ),
			home_url( '/shop/' ),
			__( 'Browse everything', 'bhc-theme' )
		);

		bhc_product_cards( $products, 4 );
		?>
	</div>
</section>
