<?php
/**
 * Frequently bought together strip.
 *
 * @package BoneHornCrafts\Core
 *
 * @var \WC_Product   $seed     Seed product.
 * @var \WC_Product[] $products Suggested products.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

if ( empty( $products ) || ! isset( $seed ) || ! $seed instanceof WC_Product ) {
	return;
}

$total = (float) wc_get_price_to_display( $seed );

foreach ( $products as $suggested ) {
	$total += (float) wc_get_price_to_display( $suggested );
}
?>
<section class="bhc-fbt" aria-labelledby="bhc-fbt-title">
	<h2 class="bhc-fbt__title" id="bhc-fbt-title"><?php esc_html_e( 'Makers usually add', 'bhc-commerce-core' ); ?></h2>

	<ul class="bhc-fbt__list">
		<?php foreach ( $products as $suggested ) : ?>
			<li class="bhc-fbt__item">
				<a class="bhc-fbt__link" href="<?php echo esc_url( (string) $suggested->get_permalink() ); ?>">
					<?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core-generated attachment markup; wp_kses_post() would strip srcset/sizes/loading. See templates/product/card.php.
					echo $suggested->get_image(
						'woocommerce_gallery_thumbnail',
						[
							'loading'  => 'lazy',
							'decoding' => 'async',
							'sizes'    => '100px',
						]
					);
					?>
					<span class="bhc-fbt__name"><?php echo esc_html( $suggested->get_name() ); ?></span>
					<span class="bhc-fbt__price"><?php echo wp_kses_post( (string) $suggested->get_price_html() ); ?></span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>

	<p class="bhc-fbt__total">
		<?php
		printf(
			/* translators: %s: formatted total price. */
			esc_html__( 'Together with this listing: %s', 'bhc-commerce-core' ),
			wp_kses_post( wc_price( $total ) )
		);
		?>
	</p>
</section>
