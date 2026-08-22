<?php
/**
 * Newsletter block.
 *
 * @package BHC_Theme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$copy = isset( $args['copy'] ) && is_array( $args['copy'] ) ? $args['copy'] : [];
?>
<section class="section" aria-labelledby="home-newsletter">
	<div class="container">
		<div class="newsletter">
			<div>
				<h2 id="home-newsletter"><?php echo esc_html( (string) ( $copy['title'] ?? __( 'New material, first', 'bhc-theme' ) ) ); ?></h2>
				<p><?php echo esc_html( (string) ( $copy['body'] ?? __( 'One email when a batch is cut. No campaigns, no resends.', 'bhc-theme' ) ) ); ?></p>
			</div>

			<form class="bhc-newsletter__form" action="<?php echo esc_url( home_url( '/contact/' ) ); ?>" method="get">
				<label class="screen-reader-text" for="newsletter-email"><?php esc_html_e( 'Email address', 'bhc-theme' ); ?></label>
				<input type="email" id="newsletter-email" name="email" placeholder="<?php esc_attr_e( 'you@workshop.com', 'bhc-theme' ); ?>" required />
				<button type="submit" class="bhc-button"><?php esc_html_e( 'Notify me', 'bhc-theme' ); ?></button>
			</form>
		</div>
	</div>
</section>
