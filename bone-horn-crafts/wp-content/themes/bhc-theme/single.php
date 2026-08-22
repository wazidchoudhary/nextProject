<?php
/**
 * Single journal article.
 *
 * @package BHC_Theme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="main" class="site-main" role="main">
	<div class="container container--narrow">
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<article <?php post_class( 'entry' ); ?>>
				<header class="page-header">
					<?php bhc_breadcrumbs(); ?>

					<h1 class="page-header__title"><?php the_title(); ?></h1>

					<p class="page-header__description">
						<?php bhc_posted_on(); ?> · <?php echo esc_html( bhc_reading_time() ); ?>
					</p>
				</header>

				<?php if ( has_post_thumbnail() ) : ?>
					<figure class="entry-media">
						<?php
						the_post_thumbnail(
							'bhc-wide',
							[
								'fetchpriority' => 'high',
								'decoding'      => 'async',
								'sizes'         => '(min-width: 48em) 44rem, 100vw',
							]
						);
						?>
					</figure>
				<?php endif; ?>

				<div class="entry-content entry-content--narrow">
					<?php
					the_content();

					wp_link_pages(
						[
							'before' => '<nav class="page-links">',
							'after'  => '</nav>',
						]
					);
					?>
				</div>
			</article>

			<?php
			$related = bhc_products_for( 'new', 4 );

			if ( [] !== $related ) :
				?>
				<section class="bhc-rail">
					<header class="bhc-rail__header">
						<h2 class="section__title"><?php esc_html_e( 'Material from this batch', 'bhc-theme' ); ?></h2>
					</header>

					<div class="bhc-rail__track">
						<?php foreach ( $related as $related_product ) : ?>
							<?php bhc_product_card( $related_product ); ?>
						<?php endforeach; ?>
					</div>
				</section>
				<?php
			endif;

			if ( comments_open() || get_comments_number() ) {
				comments_template();
			}

		endwhile;
		?>
	</div>
</main>

<?php
get_footer();
