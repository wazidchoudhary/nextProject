<?php
/**
 * Workshop gallery strip.
 *
 * @package BHC_Theme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$copy = isset( $args['copy'] ) && is_array( $args['copy'] ) ? $args['copy'] : [];

// Deliberately the *secondary* gallery shots, not the catalogue image. This
// strip used to reuse the bestsellers' primary images — the same six pictures
// already on screen two sections higher — which made it read as padding rather
// than a look at the bench. Every product carries two further photographs that
// appear nowhere else on the home page.
$gallery_items = [];

foreach ( bhc_products_for( 'new', 12 ) as $gallery_product ) {
	foreach ( $gallery_product->get_gallery_image_ids() as $gallery_image_id ) {
		$gallery_items[ (int) $gallery_image_id ] = $gallery_product;

		// One frame per product, so the strip spans the catalogue rather than
		// showing the same piece from three angles.
		break;
	}

	if ( count( $gallery_items ) >= 6 ) {
		break;
	}
}

if ( count( $gallery_items ) < 6 ) {
	// A catalogue without gallery images still gets a strip.
	foreach ( bhc_products_for( 'bestsellers', 6 ) as $gallery_product ) {
		$image_id = (int) $gallery_product->get_image_id();

		if ( $image_id > 0 && ! isset( $gallery_items[ $image_id ] ) ) {
			$gallery_items[ $image_id ] = $gallery_product;
		}
	}
}

if ( [] === $gallery_items ) {
	return;
}

// Gallery shots are secondary attachments, so ProductRepository::prime() has
// not seen them — it primes the featured image only. Left unprimed, each of
// the six costs a post row and a meta row when wp_get_attachment_image()
// resolves it.
if ( [] !== $gallery_items ) {
	_prime_post_caches( array_map( 'intval', array_keys( $gallery_items ) ), false, true );
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
			<?php foreach ( array_slice( $gallery_items, 0, 6, true ) as $gallery_image_id => $gallery_product ) : ?>
				<figure>
					<a href="<?php echo esc_url( (string) $gallery_product->get_permalink() ); ?>">
						<?php
						echo wp_get_attachment_image(
							(int) $gallery_image_id,
							'bhc-card',
							false,
							[
								'loading'  => 'lazy',
								'decoding' => 'async',
								'sizes'    => '(min-width: 48em) 16vw, 45vw',
								/* translators: %s: product name. */
								'alt'      => esc_attr( sprintf( __( '%s on the workshop bench', 'bhc-theme' ), $gallery_product->get_name() ) ),
							]
						);
						?>
					</a>
				</figure>
			<?php endforeach; ?>
		</div>
	</div>
</section>
