<?php
/**
 * Product card.
 *
 * Overridable at `yourtheme/bone-horn-crafts/product/card.php`.
 *
 * @package BoneHornCrafts\Core
 *
 * @var \WC_Product $product Product being rendered.
 * @var bool        $eager   Whether the image should load eagerly (LCP candidate).
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Plugin;
use BoneHornCrafts\Core\Product\Badges\BadgeRenderer;
use BoneHornCrafts\Core\Wishlist\WishlistRenderer;

if ( ! isset( $product ) || ! $product instanceof WC_Product ) {
	return;
}

$eager    = isset( $eager ) ? (bool) $eager : false;
$badges   = Plugin::resolve( BadgeRenderer::class )->markup( $product, 2 );
$wishlist = Plugin::resolve( WishlistRenderer::class )->button( $product, 'compact' );
?>
<article class="bhc-card" data-product-id="<?php echo esc_attr( (string) $product->get_id() ); ?>">
	<div class="bhc-card__media">
		<a class="bhc-card__link" href="<?php echo esc_url( (string) $product->get_permalink() ); ?>" tabindex="-1" aria-hidden="true">
			<?php
			echo wp_kses_post(
				$product->get_image(
					'woocommerce_thumbnail',
					[
						'loading'       => $eager ? 'eager' : 'lazy',
						'decoding'      => 'async',
						'class'         => 'bhc-card__image',
						// fetchpriority on the first card helps the LCP candidate
						// start downloading before the rest of the grid.
						'fetchpriority' => $eager ? 'high' : 'auto',
					]
				)
			);
			?>
		</a>
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by the renderers.
		echo $badges;
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by the renderers.
		echo $wishlist;
		?>
	</div>

	<div class="bhc-card__body">
		<h3 class="bhc-card__title">
			<a href="<?php echo esc_url( (string) $product->get_permalink() ); ?>"><?php echo esc_html( $product->get_name() ); ?></a>
		</h3>

		<?php if ( $product->get_average_rating() > 0 ) : ?>
			<p class="bhc-card__rating">
				<span class="bhc-stars" aria-hidden="true" style="--bhc-rating: <?php echo esc_attr( (string) round( (float) $product->get_average_rating(), 2 ) ); ?>"></span>
				<span class="screen-reader-text">
					<?php
					printf(
						/* translators: 1: average rating, 2: review count. */
						esc_html__( 'Rated %1$s out of 5 from %2$d reviews', 'bhc-commerce-core' ),
						esc_html( (string) round( (float) $product->get_average_rating(), 1 ) ),
						(int) $product->get_review_count()
					);
					?>
				</span>
				<span class="bhc-card__reviews">(<?php echo (int) $product->get_review_count(); ?>)</span>
			</p>
		<?php endif; ?>

		<?php // The price HTML already carries the unit of sale (see PriceFormatter), so it is not repeated here. ?>
		<p class="bhc-card__price"><?php echo wp_kses_post( (string) $product->get_price_html() ); ?></p>

		<p class="bhc-card__actions">
			<a class="bhc-button bhc-button--ghost" href="<?php echo esc_url( (string) $product->get_permalink() ); ?>">
				<?php esc_html_e( 'View material', 'bhc-commerce-core' ); ?>
				<span class="screen-reader-text"><?php echo esc_html( $product->get_name() ); ?></span>
			</a>
		</p>
	</div>
</article>
