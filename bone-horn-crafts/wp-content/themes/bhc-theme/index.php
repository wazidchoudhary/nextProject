<?php
/**
 * Fallback archive template (also the blog index).
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
		$blog_page_id = (int) get_option( 'page_for_posts' );

		get_template_part(
			'template-parts/content/page-header',
			null,
			[
				'title'       => is_home() && $blog_page_id > 0 ? get_the_title( $blog_page_id ) : __( 'Workshop journal', 'bhc-theme' ),
				'description' => is_home() && $blog_page_id > 0 ? (string) get_post_field( 'post_excerpt', $blog_page_id ) : '',
			]
		);

		if ( have_posts() ) :
			// Card titles are h3. Without a heading between them and the page
			// h1, the document jumps a level.
			printf(
				'<h2 class="screen-reader-text">%s</h2>',
				esc_html__( 'Journal articles', 'bhc-theme' )
			);

			echo '<div class="bhc-grid bhc-grid--3">';

			while ( have_posts() ) :
				the_post();

				get_template_part( 'template-parts/content/card', 'post' );
			endwhile;

			echo '</div>';

			the_posts_pagination(
				[
					'mid_size'  => 2,
					'prev_text' => __( 'Newer', 'bhc-theme' ),
					'next_text' => __( 'Older', 'bhc-theme' ),
				]
			);
		else :
			?>
			<div class="bhc-empty">
				<h2 class="bhc-empty__title"><?php esc_html_e( 'Nothing here yet', 'bhc-theme' ); ?></h2>
				<p class="bhc-empty__body"><?php esc_html_e( 'The journal is written between batches, so it fills up slowly.', 'bhc-theme' ); ?></p>
			</div>
			<?php
		endif;
		?>
	</div>
</main>

<?php
get_footer();
