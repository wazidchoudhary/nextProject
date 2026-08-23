<?php
/**
 * Home hero.
 *
 * A full-bleed banner with the copy set over it, rather than a two-column split.
 * The split put a product photograph shot on white next to cream page
 * background, and the image had no edge to read against — it looked like a
 * missing asset rather than the subject.
 *
 * The banner is the LCP element. It is rendered with `fetchpriority="high"`,
 * never lazy-loaded, and preloaded from inc/performance.php, which resolves it
 * the same way during `wp_head` — registering a filter from here would be too
 * late, because templates run after the head is already on the wire.
 *
 * @package BHC_Theme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$copy = isset( $args['copy'] ) && is_array( $args['copy'] ) ? $args['copy'] : [];

// Three tiers: the banner a shop owner selected, a file bundled with the theme,
// then the section's own gradient. The middle tier keeps a fresh install or a
// rebuilt staging site looking finished before anybody opens the settings.
$banner_id  = bhc_hero_banner_id();
$banner_url = $banner_id > 0 ? '' : bhc_hero_banner_fallback_url();
$has_banner = $banner_id > 0 || '' !== $banner_url;

$hero_product = bhc_hero_product();
?>
<section class="hero<?php echo $has_banner ? ' hero--banner' : ' hero--plain'; ?>" aria-labelledby="hero-title">
	<?php if ( $has_banner ) : ?>
		<div class="hero__backdrop" aria-hidden="true">
			<?php if ( $banner_id > 0 ) : ?>
				<?php
				echo wp_get_attachment_image(
					$banner_id,
					'full',
					false,
					[
						'class'         => 'hero__backdrop-image',
						'fetchpriority' => 'high',
						'loading'       => 'eager',
						'decoding'      => 'async',
						'sizes'         => '100vw',
						'alt'           => '',
					]
				);
				?>
			<?php else : ?>
				<?php
				// A bundled file has no attachment record, so there is no
				// srcset to build — it is emitted as a plain img with the same
				// loading hints.
				?>
				<img
					class="hero__backdrop-image"
					src="<?php echo esc_url( $banner_url ); ?>"
					alt=""
					fetchpriority="high"
					loading="eager"
					decoding="async"
				/>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<div class="hero__inner">
		<div class="hero__content">
			<span class="hero__eyebrow"><?php echo esc_html( $copy['eyebrow'] ?? __( 'Cut and finished in our own workshop', 'bhc-theme' ) ); ?></span>

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

				<a class="bhc-button bhc-button--onDark" href="<?php echo esc_url( bhc_wc_page_url( 'shop' ) ); ?>">
					<?php echo esc_html( $copy['cta_alt'] ?? __( 'Browse the catalogue', 'bhc-theme' ) ); ?>
				</a>
			</div>

			<ul class="hero__stats">
				<li>
					<strong><?php esc_html_e( '60+', 'bhc-theme' ); ?></strong>
					<span><?php esc_html_e( 'Materials in stock', 'bhc-theme' ); ?></span>
				</li>
				<li>
					<strong><?php esc_html_e( '8 weeks', 'bhc-theme' ); ?></strong>
					<span><?php esc_html_e( 'Degreasing time', 'bhc-theme' ); ?></span>
				</li>
				<li>
					<strong><?php esc_html_e( '24 countries', 'bhc-theme' ); ?></strong>
					<span><?php esc_html_e( 'Shipped worldwide', 'bhc-theme' ); ?></span>
				</li>
			</ul>
		</div>

		<?php if ( $hero_product instanceof WC_Product ) : ?>
			<a class="hero__feature" href="<?php echo esc_url( (string) $hero_product->get_permalink() ); ?>">
				<?php
				$feature_image = (int) $hero_product->get_image_id();

				if ( $feature_image > 0 ) {
					echo wp_get_attachment_image(
						$feature_image,
						'woocommerce_thumbnail',
						false,
						[
							'class'    => 'hero__feature-image',
							'loading'  => 'eager',
							'decoding' => 'async',
							'alt'      => '',
						]
					);
				}
				?>

				<span class="hero__feature-text">
					<span class="hero__feature-label"><?php esc_html_e( 'New this week', 'bhc-theme' ); ?></span>
					<span class="hero__feature-name"><?php echo esc_html( $hero_product->get_name() ); ?></span>
					<span class="hero__feature-price"><?php echo wp_kses_post( $hero_product->get_price_html() ); ?></span>
				</span>
			</a>
		<?php endif; ?>
	</div>
</section>
