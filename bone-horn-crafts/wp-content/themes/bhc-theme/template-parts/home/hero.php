<?php
/**
 * Home hero.
 *
 * The hero image is the LCP element on this page. It is rendered with
 * `fetchpriority="high"`, is never lazy-loaded, and carries explicit width and
 * height so the layout is stable before it decodes.
 *
 * @package BHC_Theme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$copy = isset( $args['copy'] ) && is_array( $args['copy'] ) ? $args['copy'] : [];

$hero_products = bhc_products_for( 'new', 1 );
$hero_product  = $hero_products[0] ?? null;
$hero_image_id = $hero_product instanceof WC_Product ? (int) $hero_product->get_image_id() : 0;

if ( $hero_image_id > 0 ) {
	add_filter(
		'bhc_lcp_image_id',
		static function () use ( $hero_image_id ): int {
			return $hero_image_id;
		}
	);
}
?>
<section class="hero" aria-labelledby="hero-title">
	<div class="hero__inner">
		<div class="hero__content">
			<span class="eyebrow"><?php echo esc_html( $copy['eyebrow'] ?? __( 'Cut and finished in our own workshop', 'bhc-theme' ) ); ?></span>

			<h1 class="hero__title" id="hero-title">
				<?php echo esc_html( $copy['title'] ?? __( 'Materials Made for Makers', 'bhc-theme' ) ); ?>
			</h1>

			<p class="hero__body">
				<?php echo esc_html( $copy['body'] ?? __( 'Bone, horn and wood handle stock, matched in pairs and finished so your build starts right.', 'bhc-theme' ) ); ?>
			</p>

			<div class="hero__actions">
				<a class="bhc-button" href="<?php echo esc_url( home_url( '/new-arrivals/' ) ); ?>">
					<?php echo esc_html( $copy['cta'] ?? __( 'Shop New Arrivals', 'bhc-theme' ) ); ?>
				</a>

				<a class="bhc-button bhc-button--ghost" href="<?php echo esc_url( bhc_wc_page_url( 'shop' ) ); ?>">
					<?php echo esc_html( $copy['cta_alt'] ?? __( 'Browse the full catalogue', 'bhc-theme' ) ); ?>
				</a>
			</div>

			<ul class="hero__stats">
				<li>
					<strong><?php esc_html_e( '60+', 'bhc-theme' ); ?></strong>
					<span><?php esc_html_e( 'Materials in stock', 'bhc-theme' ); ?></span>
				</li>
				<li>
					<strong><?php esc_html_e( '8 weeks', 'bhc-theme' ); ?></strong>
					<span><?php esc_html_e( 'Degreasing before cutting', 'bhc-theme' ); ?></span>
				</li>
				<li>
					<strong><?php esc_html_e( '24 countries', 'bhc-theme' ); ?></strong>
					<span><?php esc_html_e( 'Shipped from the bench', 'bhc-theme' ); ?></span>
				</li>
			</ul>
		</div>

		<?php if ( $hero_image_id > 0 ) : ?>
			<div class="hero__media">
				<?php
				echo wp_get_attachment_image(
					$hero_image_id,
					'bhc-hero',
					false,
					[
						'fetchpriority' => 'high',
						'loading'       => 'eager',
						'decoding'      => 'async',
						'sizes'         => '(min-width: 64em) 46vw, 100vw',
						'alt'           => esc_attr__( 'Hand-finished bone handle material on a workshop bench', 'bhc-theme' ),
					]
				);
				?>
			</div>
		<?php endif; ?>
	</div>
</section>
