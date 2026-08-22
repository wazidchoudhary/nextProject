<?php
/**
 * Home page.
 *
 * Composed from template parts so each section is independently reviewable and
 * can be reordered without touching the others. Every product rail reads from
 * the plugin's cached, bounded repositories — the homepage runs a handful of
 * queries regardless of how large the catalogue grows.
 *
 * @package BHC_Theme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

get_header();

$bhc_home_copy = class_exists( \BoneHornCrafts\Core\Demo\ContentLibrary::class )
	? \BoneHornCrafts\Core\Demo\ContentLibrary::homepage()
	: [];

// Warm every rail on the page in one pass, before the first one renders.
bhc_prime_product_rails(
	[
		[ 'new', 8 ],
		[ 'bestsellers', 8 ],
		[ 'category', 4, 'workshop-essentials' ],
	]
);
?>

<main id="main" class="site-main" role="main">
	<?php get_template_part( 'template-parts/home/hero', null, [ 'copy' => $bhc_home_copy['hero'] ?? [] ] ); ?>

	<section class="section section--surface" aria-labelledby="home-new-arrivals">
		<div class="container">
			<?php
			bhc_section_header(
				__( 'New arrivals', 'bhc-theme' ),
				__( 'Listed the week it is cut. One-off pairs do not come back.', 'bhc-theme' ),
				home_url( '/new-arrivals/' ),
				__( 'See everything new', 'bhc-theme' )
			);

			bhc_product_cards( bhc_products_for( 'new', 8 ), 4 );
			?>
		</div>
	</section>

	<?php get_template_part( 'template-parts/home/categories' ); ?>

	<section class="section" aria-labelledby="home-bestsellers">
		<div class="container">
			<?php
			bhc_section_header(
				__( 'Bestsellers', 'bhc-theme' ),
				__( 'Ranked from real order volume over the last thirty days.', 'bhc-theme' ),
				home_url( '/bestsellers/' ),
				__( 'See the full ranking', 'bhc-theme' )
			);

			bhc_product_cards( bhc_products_for( 'bestsellers', 8 ), 4 );
			?>
		</div>
	</section>

	<?php
	get_template_part( 'template-parts/home/collection', null, [ 'copy' => $bhc_home_copy['collection'] ?? [] ] );
	get_template_part( 'template-parts/home/value-props', null, [ 'copy' => $bhc_home_copy['why'] ?? [] ] );
	?>

	<section class="section section--paper" aria-labelledby="home-essentials">
		<div class="container">
			<?php
			bhc_section_header(
				__( 'Workshop essentials', 'bhc-theme' ),
				__( 'Pin stock, spacer sheet and bolster blocks — the parts that finish a build.', 'bhc-theme' )
			);

			bhc_product_cards( bhc_products_for( 'category', 4, 'workshop-essentials' ), 4 );
			?>
		</div>
	</section>

	<?php
	get_template_part( 'template-parts/home/reviews' );
	get_template_part( 'template-parts/home/gallery', null, [ 'copy' => $bhc_home_copy['gallery'] ?? [] ] );
	get_template_part( 'template-parts/home/journal' );
	get_template_part( 'template-parts/home/newsletter', null, [ 'copy' => $bhc_home_copy['newsletter'] ?? [] ] );
	?>
</main>

<?php
get_footer();
