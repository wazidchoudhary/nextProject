<?php
/**
 * Search results.
 *
 * @package BHC_Theme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

get_header();

$search_term = get_search_query();
?>

<main id="main" class="site-main" role="main">
	<div class="container">
		<header class="page-header">
			<?php bhc_breadcrumbs(); ?>

			<h1 class="page-header__title">
				<?php
				printf(
					/* translators: %s: search term. */
					esc_html__( 'Search: %s', 'bhc-theme' ),
					esc_html( $search_term )
				);
				?>
			</h1>

			<p class="page-header__description">
				<?php
				printf(
					/* translators: %d: result count. */
					esc_html( _n( '%d result', '%d results', (int) $GLOBALS['wp_query']->found_posts, 'bhc-theme' ) ),
					(int) $GLOBALS['wp_query']->found_posts
				);
				?>
			</p>

			<?php get_search_form(); ?>
		</header>

		<?php if ( have_posts() ) : ?>
			<div class="bhc-grid bhc-grid--3" data-bhc-product-grid>
				<?php
				while ( have_posts() ) :
					the_post();

					$result_product = function_exists( 'wc_get_product' ) ? wc_get_product( get_the_ID() ) : null;

					if ( $result_product instanceof WC_Product ) {
						bhc_product_card( $result_product );

						continue;
					}

					get_template_part( 'template-parts/content/card', 'post' );
				endwhile;
				?>
			</div>

			<?php the_posts_pagination( [ 'mid_size' => 2 ] ); ?>
		<?php else : ?>
			<div class="bhc-empty">
				<h2 class="bhc-empty__title"><?php esc_html_e( 'No matches', 'bhc-theme' ); ?></h2>
				<p class="bhc-empty__body">
					<?php esc_html_e( 'Try a material name (buffalo horn, camel bone, stabilized burl), a SKU, or the build you are working on.', 'bhc-theme' ); ?>
				</p>
				<p><a class="bhc-button" href="<?php echo esc_url( bhc_wc_page_url( 'shop' ) ); ?>"><?php esc_html_e( 'Browse the catalogue', 'bhc-theme' ); ?></a></p>
			</div>
		<?php endif; ?>
	</div>
</main>

<?php
get_footer();
