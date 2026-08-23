<?php
/**
 * Product gallery.
 *
 * Overrides `woocommerce/templates/single-product/product-image.php`.
 *
 * WooCommerce's default gallery needs flexslider, photoswipe and zoom — roughly
 * 90KB of JavaScript. This replacement is a main image plus thumbnail buttons,
 * enhanced by ~30 lines in the theme module and fully functional without any
 * JavaScript at all (the thumbnails are links to the full-size files).
 *
 * @package BHC_Theme
 * @version 9.7.0
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! $product instanceof WC_Product ) {
	return;
}

$image_ids = array_values(
	array_filter(
		array_merge( [ (int) $product->get_image_id() ], array_map( 'absint', $product->get_gallery_image_ids() ) )
	)
);

if ( [] === $image_ids ) {
	printf(
		'<div class="product-gallery"><div class="product-gallery__main"><img src="%s" alt="%s" width="600" height="600" /></div></div>',
		esc_url( (string) wc_placeholder_img_src( 'woocommerce_single' ) ),
		esc_attr( $product->get_name() )
	);

	return;
}

$main_id = (int) $image_ids[0];
?>
<div class="product-gallery" data-bhc-gallery>
	<div class="product-gallery__main" data-bhc-gallery-main>
		<?php
		// The product image is the LCP element on this page: eager, high
		// priority, explicit dimensions, and preloaded from inc/performance.php.
		echo wp_get_attachment_image(
			$main_id,
			'woocommerce_single',
			false,
			[
				'id'            => 'bhc-gallery-image',
				'fetchpriority' => 'high',
				'loading'       => 'eager',
				'decoding'      => 'async',
				'sizes'         => '(min-width: 64em) 52vw, 100vw',
				'alt'           => esc_attr( $product->get_name() ),
			]
		);
		?>
	</div>

	<?php if ( count( $image_ids ) > 1 ) : ?>
		<ul class="product-gallery__thumbs">
			<?php foreach ( $image_ids as $index => $image_id ) : ?>
				<li>
					<button
						type="button"
						class="product-gallery__thumb"
						data-bhc-gallery-thumb
						data-full="<?php echo esc_url( (string) wp_get_attachment_image_url( $image_id, 'woocommerce_single' ) ); ?>"
						data-srcset="<?php echo esc_attr( (string) wp_get_attachment_image_srcset( $image_id, 'woocommerce_single' ) ); ?>"
						<?php
						// Carried so the main image's alt text changes with it.
						// Swapping src while leaving alt behind means a screen
						// reader describes the first photograph for all three.
						?>
						data-alt="<?php echo esc_attr( (string) get_post_meta( $image_id, '_wp_attachment_image_alt', true ) ); ?>"
						aria-current="<?php echo 0 === $index ? 'true' : 'false'; ?>"
					>
						<?php
						echo wp_get_attachment_image(
							$image_id,
							'woocommerce_gallery_thumbnail',
							false,
							[
								'loading'  => 'lazy',
								'decoding' => 'async',
								'alt'      => sprintf(
									/* translators: 1: product name, 2: image number. */
									esc_attr__( '%1$s — view %2$d', 'bhc-theme' ),
									esc_attr( $product->get_name() ),
									(int) $index + 1
								),
							]
						);
						?>
					</button>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>
