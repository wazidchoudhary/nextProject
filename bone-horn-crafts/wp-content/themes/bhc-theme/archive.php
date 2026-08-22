<?php
/**
 * Archive template.
 *
 * @package BHC_Theme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="main" class="site-main" role="main">
	<div class="container">
		<?php
		get_template_part(
			'template-parts/content/page-header',
			null,
			[
				'title'       => wp_strip_all_tags( (string) get_the_archive_title() ),
				'description' => wp_strip_all_tags( (string) get_the_archive_description() ),
			]
		);

		if ( have_posts() ) :
			echo '<div class="bhc-grid bhc-grid--3">';

			while ( have_posts() ) :
				the_post();

				get_template_part( 'template-parts/content/card', 'post' );
			endwhile;

			echo '</div>';

			the_posts_pagination( [ 'mid_size' => 2 ] );
		endif;
		?>
	</div>
</main>

<?php
get_footer();
