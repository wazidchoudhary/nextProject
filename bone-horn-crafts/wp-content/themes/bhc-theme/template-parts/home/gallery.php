<?php
/**
 * Workshop gallery strip.
 *
 * @package BHC_Theme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$copy     = isset( $args['copy'] ) && is_array( $args['copy'] ) ? $args['copy'] : [];
$products = bhc_products_for( 'bestsellers', 6 );

if ( count( $products ) < 6 ) {
	$products = array_merge( $products, bhc_products_for( 'new', 6 - count( $products ) ) );
}

if ( [] === $products ) {
	return;
}
?>
<section class="section section--paper" aria-labelledby="home-gallery">
	<div class="container">
		<?php
		bhc_section_header(
			(string) ( $copy['title'] ?? __( 'From the bench', 'bhc-theme' ) ),
			(string) ( $copy['body'] ?? __( 'Work in progress, batches drying and finished pieces before they are packed.', 'bhc-theme' ) )
		);
		?>

		<div class="gallery-strip">
			<?php foreach ( array_slice( $products, 0, 6 ) as $gallery_product ) : ?>
				<figure>
					<a href="<?php echo esc_url( (string) $gallery_product->get_permalink() ); ?>">
						<?php
						echo wp_get_attachment_image(
							(int) $gallery_product->get_image_id(),
							'bhc-card',
							false,
							[
								'loading'  => 'lazy',
								'decoding' => 'async',
								'sizes'    => '(min-width: 48em) 16vw, 45vw',
								'alt'      => esc_attr( $gallery_product->get_name() ),
							]
						);
						?>
					</a>
				</figure>
			<?php endforeach; ?>
		</div>
	</div>
</section>
