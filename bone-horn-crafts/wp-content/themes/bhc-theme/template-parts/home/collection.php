<?php
/**
 * Viking & Medieval collection banner.
 *
 * @package BHC_Theme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$copy     = isset( $args['copy'] ) && is_array( $args['copy'] ) ? $args['copy'] : [];
$products = bhc_products_for( 'tag', 4, 'viking-medieval' );

if ( [] === $products ) {
	return;
}

$banner_image_id = (int) $products[0]->get_image_id();
?>
<section class="section" aria-labelledby="home-collection">
	<div class="container">
		<div class="collection-banner">
			<div class="collection-banner__content">
				<span class="eyebrow"><?php esc_html_e( 'Collection', 'bhc-theme' ); ?></span>

				<h2 id="home-collection">
					<?php echo esc_html( wp_specialchars_decode( (string) ( $copy['title'] ?? __( 'Viking & Medieval Collection', 'bhc-theme' ) ) ) ); ?>
				</h2>

				<p>
					<?php echo esc_html( $copy['body'] ?? __( 'Drinking horns, bark-edge scales and horn beads for reenactment kit and Norse-styled builds.', 'bhc-theme' ) ); ?>
				</p>

				<a class="bhc-button" href="<?php echo esc_url( get_term_link( 'viking-medieval', 'product_tag' ) instanceof WP_Error ? bhc_wc_page_url( 'shop' ) : (string) get_term_link( 'viking-medieval', 'product_tag' ) ); ?>">
					<?php esc_html_e( 'View the collection', 'bhc-theme' ); ?>
				</a>
			</div>

			<div class="collection-banner__media">
				<?php
				if ( $banner_image_id > 0 ) {
					echo wp_get_attachment_image(
						$banner_image_id,
						'bhc-wide',
						false,
						[
							'loading'  => 'lazy',
							'decoding' => 'async',
							'sizes'    => '(min-width: 64em) 46vw, 100vw',
							'alt'      => esc_attr__( 'Drinking horn and bark-edge horn material', 'bhc-theme' ),
						]
					);
				}
				?>
			</div>
		</div>

		<div class="bhc-rail__track" style="margin-top: 2rem;">
			<?php foreach ( $products as $collection_product ) : ?>
				<?php bhc_product_card( $collection_product ); ?>
			<?php endforeach; ?>
		</div>
	</div>
</section>
