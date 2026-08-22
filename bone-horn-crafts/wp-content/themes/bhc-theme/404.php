<?php
/**
 * 404 template.
 *
 * @package BHC_Theme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="main" class="site-main" role="main">
	<div class="container container--narrow">
		<div class="bhc-empty">
			<h1 class="bhc-empty__title"><?php esc_html_e( 'That page has moved on', 'bhc-theme' ); ?></h1>
			<p class="bhc-empty__body">
				<?php esc_html_e( 'One-off pairs sell out and their listings retire. The catalogue is the best place to pick up the trail.', 'bhc-theme' ); ?>
			</p>

			<p>
				<a class="bhc-button" href="<?php echo esc_url( bhc_wc_page_url( 'shop' ) ); ?>"><?php esc_html_e( 'Browse the catalogue', 'bhc-theme' ); ?></a>
			</p>

			<?php get_search_form(); ?>
		</div>

		<?php
		$suggestions = bhc_products_for( 'bestsellers', 4 );

		if ( [] !== $suggestions ) :
			?>
			<section class="bhc-rail">
				<header class="bhc-rail__header">
					<h2 class="section__title"><?php esc_html_e( 'Popular right now', 'bhc-theme' ); ?></h2>
				</header>

				<div class="bhc-rail__track">
					<?php foreach ( $suggestions as $suggested ) : ?>
						<?php bhc_product_card( $suggested ); ?>
					<?php endforeach; ?>
				</div>
			</section>
			<?php
		endif;
		?>
	</div>
</main>

<?php
get_footer();
