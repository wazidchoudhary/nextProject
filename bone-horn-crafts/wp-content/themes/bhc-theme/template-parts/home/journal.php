<?php
/**
 * Latest journal articles.
 *
 * @package BHC_Theme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$journal = new WP_Query(
	[
		'post_type'              => 'post',
		'posts_per_page'         => 3,
		'ignore_sticky_posts'    => true,
		'no_found_rows'          => true,
		'update_post_meta_cache' => false,
	]
);

if ( ! $journal->have_posts() ) {
	wp_reset_postdata();

	return;
}
?>
<section class="section section--surface" aria-labelledby="home-journal">
	<div class="container">
		<?php
		bhc_section_header(
			__( 'From the workshop journal', 'bhc-theme' ),
			__( 'How the material behaves, and what we have learned cutting it.', 'bhc-theme' ),
			get_permalink( (int) get_option( 'page_for_posts' ) ) ?: home_url( '/blog/' ),
			__( 'Read the journal', 'bhc-theme' )
		);
		?>

		<div class="bhc-grid bhc-grid--3">
			<?php
			while ( $journal->have_posts() ) :
				$journal->the_post();

				get_template_part( 'template-parts/content/card', 'post' );
			endwhile;

			wp_reset_postdata();
			?>
		</div>
	</div>
</section>
