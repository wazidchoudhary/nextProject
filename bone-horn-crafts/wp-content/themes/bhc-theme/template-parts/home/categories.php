<?php
/**
 * Shop by category.
 *
 * @package BHC_Theme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$categories = get_terms(
	[
		'taxonomy'   => 'product_cat',
		'hide_empty' => true,
		'parent'     => 0,
		'number'     => 5,
		'orderby'    => 'count',
		'order'      => 'DESC',
	]
);

if ( ! is_array( $categories ) || [] === $categories ) {
	return;
}
?>
<section class="section section--paper" aria-labelledby="home-categories">
	<div class="container">
		<?php
		bhc_section_header(
			__( 'Shop by category', 'bhc-theme' ),
			__( 'Five shelves: handle scales, guitar parts, pen blanks, drinking horns and finished pieces.', 'bhc-theme' )
		);
		?>

		<div class="category-grid">
			<?php foreach ( $categories as $category ) : ?>
				<?php
				$thumbnail_id = (int) get_term_meta( $category->term_id, 'thumbnail_id', true );
				$image_id     = $thumbnail_id;

				if ( $image_id <= 0 ) {
					// Fall back to the newest product in the category so the
					// grid never renders an empty box.
					$fallback = bhc_products_for( 'category', 1, $category->slug );
					$image_id = isset( $fallback[0] ) ? (int) $fallback[0]->get_image_id() : 0;
				}
				?>
				<a class="bhc-category-card" href="<?php echo esc_url( (string) get_term_link( $category ) ); ?>">
					<div class="bhc-category-card__media">
						<?php
						if ( $image_id > 0 ) {
							echo wp_get_attachment_image(
								$image_id,
								'bhc-card',
								false,
								[
									'loading'  => 'lazy',
									'decoding' => 'async',
									'sizes'    => '(min-width: 64em) 18vw, 45vw',
									'alt'      => esc_attr( $category->name ),
								]
							);
						}
						?>
					</div>

					<h3><?php echo esc_html( $category->name ); ?></h3>
					<p>
						<?php
						printf(
							/* translators: %d: product count. */
							esc_html( _n( '%d listing', '%d listings', (int) $category->count, 'bhc-theme' ) ),
							(int) $category->count
						);
						?>
					</p>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>
