<?php
/**
 * Product archive (shop, category, tag).
 *
 * Overrides `woocommerce/templates/archive-product.php`.
 *
 * @package BHC_Theme
 * @version 8.6.0
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

get_header( 'shop' );

/**
 * Opens the theme wrapper (see inc/woocommerce.php).
 */
do_action( 'woocommerce_before_main_content' );

$filter_panel = bhc_service( \BoneHornCrafts\Core\Search\FilterPanelRenderer::class );
$queried_term = is_tax() ? get_queried_object() : null;
?>

<header class="page-header">
	<h1 class="page-header__title">
		<?php
		if ( $queried_term instanceof WP_Term ) {
			echo esc_html( $queried_term->name );
		} elseif ( is_search() ) {
			printf(
				/* translators: %s: search term. */
				esc_html__( 'Search: %s', 'bhc-theme' ),
				esc_html( get_search_query() )
			);
		} else {
			echo esc_html( woocommerce_page_title( false ) );
		}
		?>
	</h1>

	<?php
	// Prefer curated copy: the term description on a category, the shop page
	// excerpt on the shop. `get_the_archive_description()` falls back to the
	// product post type's registered description, which is WooCommerce's stock
	// "This is where you can browse products in this store" placeholder.
	if ( $queried_term instanceof WP_Term ) {
		$description = $queried_term->description;
	} elseif ( function_exists( 'is_shop' ) && is_shop() ) {
		$description = (string) get_post_field( 'post_excerpt', (int) wc_get_page_id( 'shop' ) );
	} else {
		$description = (string) get_the_archive_description();
	}

	if ( '' !== trim( wp_strip_all_tags( (string) $description ) ) ) :
		?>
		<p class="page-header__description"><?php echo esc_html( wp_strip_all_tags( (string) $description ) ); ?></p>
	<?php endif; ?>
</header>

<div class="layout-sidebar">
	<div class="layout-sidebar__aside">
		<button type="button" class="bhc-button bhc-button--quiet filter-toggle" data-bhc-filter-toggle aria-expanded="false">
			<?php bhc_icon( 'filter', 18 ); ?>
			<?php esc_html_e( 'Filter', 'bhc-theme' ); ?>
		</button>

		<?php
		if ( null !== $filter_panel ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by the plugin template.
			echo $filter_panel->render();
		}
		?>
	</div>

	<div class="layout-sidebar__main">
		<?php if ( woocommerce_product_loop() ) : ?>
			<div class="filter-bar">
				<?php
				woocommerce_result_count();
				woocommerce_catalog_ordering();
				?>
			</div>

			<?php
			woocommerce_product_loop_start();

			if ( wc_get_loop_prop( 'total' ) ) {
				while ( have_posts() ) {
					the_post();

					do_action( 'woocommerce_shop_loop' );

					wc_get_template_part( 'content', 'product' );
				}
			}

			woocommerce_product_loop_end();

			// Pagination stays exactly as it was. Infinite scroll would replace
			// it, and a crawler does not scroll — the numbered pages are how
			// the catalogue gets indexed, and how a shopper gets back to where
			// they were. The button below is an addition to them, not a
			// replacement: it appends the next page in place while the real
			// paged URLs keep working with JavaScript off.
			do_action( 'woocommerce_after_shop_loop' );

			$bhc_total_pages = (int) wc_get_loop_prop( 'total_pages' );

			if ( $bhc_total_pages > 1 ) :
				?>
				<div class="bhc-load-more" data-bhc-load-more data-total-pages="<?php echo esc_attr( (string) $bhc_total_pages ); ?>" hidden>
					<button type="button" class="bhc-button bhc-button--ghost" data-bhc-load-more-button>
						<?php esc_html_e( 'Load more', 'bhc-theme' ); ?>
					</button>
					<p class="bhc-load-more__status" role="status" aria-live="polite" data-bhc-load-more-status></p>
				</div>
				<?php
			endif;
			?>
		<?php else : ?>
			<div class="bhc-empty">
				<h2 class="bhc-empty__title"><?php esc_html_e( 'Nothing matches those filters', 'bhc-theme' ); ?></h2>
				<p class="bhc-empty__body"><?php esc_html_e( 'Natural material comes in batches, so a combination that existed last month may be sold out. Try widening the material or finish filter.', 'bhc-theme' ); ?></p>
				<p><a class="bhc-button" href="<?php echo esc_url( bhc_wc_page_url( 'shop' ) ); ?>"><?php esc_html_e( 'Clear all filters', 'bhc-theme' ); ?></a></p>
			</div>
		<?php endif; ?>
	</div>
</div>

<?php
do_action( 'woocommerce_after_main_content' );

get_footer( 'shop' );
