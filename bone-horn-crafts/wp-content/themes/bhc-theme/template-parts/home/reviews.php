<?php
/**
 * Customer reviews wall.
 *
 * Reads the newest approved product reviews rather than hard-coded quotes, so
 * the section always reflects what the catalogue actually says. On this demo
 * build those reviews are part of the clearly labelled fictional dataset.
 *
 * @package BHC_Theme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$reviews = get_comments(
	[
		'status'     => 'approve',
		'type'       => 'review',
		'number'     => 3,
		'post_type'  => 'product',
		'orderby'    => 'comment_date_gmt',
		'order'      => 'DESC',
		'meta_query' => [
			[
				'key'     => 'rating',
				'value'   => 4,
				'compare' => '>=',
				'type'    => 'NUMERIC',
			],
		],
	]
);

if ( ! is_array( $reviews ) || [] === $reviews ) {
	return;
}
?>
<section class="section" aria-labelledby="home-reviews">
	<div class="container">
		<?php
		bhc_section_header(
			__( 'What makers say', 'bhc-theme' ),
			__( 'Reviews left on the listings themselves, newest first.', 'bhc-theme' )
		);
		?>

		<div class="review-wall">
			<?php foreach ( $reviews as $review ) : ?>
				<?php
				$rating  = (int) get_comment_meta( $review->comment_ID, 'rating', true );
				$product = wc_get_product( (int) $review->comment_post_ID );
				?>
				<article class="review-card">
					<p class="bhc-card__rating">
						<span class="bhc-stars" aria-hidden="true" style="--bhc-rating: <?php echo esc_attr( (string) $rating ); ?>"></span>
						<span class="screen-reader-text">
							<?php
							printf(
								/* translators: %d: star rating. */
								esc_html__( 'Rated %d out of 5', 'bhc-theme' ),
								absint( $rating )
							);
							?>
						</span>
					</p>

					<blockquote><?php echo esc_html( wp_trim_words( $review->comment_content, 34 ) ); ?></blockquote>

					<cite>
						<?php echo esc_html( $review->comment_author ); ?>
						<?php if ( $product instanceof WC_Product ) : ?>
							—
							<a href="<?php echo esc_url( (string) $product->get_permalink() ); ?>"><?php echo esc_html( $product->get_name() ); ?></a>
						<?php endif; ?>
					</cite>
				</article>
			<?php endforeach; ?>
		</div>
	</div>
</section>
