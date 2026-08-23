<?php
/**
 * Site footer.
 *
 * @package BHC_Theme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;
?>
<footer class="site-footer" role="contentinfo">
	<div class="site-footer__inner">
		<div class="footer-brand">
			<p class="footer-brand__name"><?php bloginfo( 'name' ); ?></p>
			<p class="footer-brand__body">
				<?php esc_html_e( 'Bone, horn and wood handle stock cut, graded and finished in our own workshop, then shipped to makers worldwide.', 'bhc-theme' ); ?>
			</p>

			<?php if ( is_active_sidebar( 'footer-workshop' ) ) : ?>
				<?php dynamic_sidebar( 'footer-workshop' ); ?>
			<?php endif; ?>
		</div>

		<div>
			<h2 class="footer-column__title"><?php esc_html_e( 'Shop', 'bhc-theme' ); ?></h2>
			<ul class="footer-menu">
				<?php
				$footer_categories = get_terms(
					[
						'taxonomy'   => 'product_cat',
						'hide_empty' => true,
						'parent'     => 0,
						'number'     => 6,
					]
				);

				if ( is_array( $footer_categories ) ) :
					foreach ( $footer_categories as $footer_category ) :
						?>
						<li>
							<a href="<?php echo esc_url( (string) get_term_link( $footer_category ) ); ?>">
								<?php echo esc_html( $footer_category->name ); ?>
							</a>
						</li>
						<?php
					endforeach;
				endif;
				?>
			</ul>
		</div>

		<div>
			<h2 class="footer-column__title"><?php esc_html_e( 'Help', 'bhc-theme' ); ?></h2>
			<?php
			wp_nav_menu(
				[
					'theme_location' => 'footer',
					'container'      => false,
					'menu_class'     => 'footer-menu',
					'depth'          => 1,
					'fallback_cb'    => false,
				]
			);
			?>
		</div>

		<div>
			<h2 class="footer-column__title"><?php esc_html_e( 'New material first', 'bhc-theme' ); ?></h2>
			<p class="footer-brand__body">
				<?php esc_html_e( 'One email when a batch is cut. Bark-edge horn and rams horn usually sell out before the second email.', 'bhc-theme' ); ?>
			</p>

			<?php
			// Posts to the newsletter plugin's REST endpoint, which stores the
			// address and sends a confirmation. Without that plugin active the
			// form is not rendered at all, rather than collecting addresses
			// nothing will ever read.
			if ( class_exists( \BoneHornCrafts\Newsletter\Plugin::class ) ) :
				?>
				<form class="bhc-newsletter__form" data-bhc-newsletter data-bhc-newsletter-source="footer" method="post" action="<?php echo esc_url( rest_url( 'bhc-newsletter/v1/subscribe' ) ); ?>">
					<label class="screen-reader-text" for="footer-email"><?php esc_html_e( 'Email address', 'bhc-theme' ); ?></label>
					<input type="email" id="footer-email" name="email" autocomplete="email" placeholder="<?php esc_attr_e( 'you@workshop.com', 'bhc-theme' ); ?>" required />
					<button type="submit" class="bhc-button"><?php esc_html_e( 'Join', 'bhc-theme' ); ?></button>
				</form>
				<p class="footer-note"><?php esc_html_e( 'Confirm by email. One click to leave, any time.', 'bhc-theme' ); ?></p>
			<?php endif; ?>
		</div>

		<div>
			<h2 class="footer-column__title"><?php esc_html_e( 'Workshop', 'bhc-theme' ); ?></h2>

			<address class="footer-contact">
				<span class="footer-contact__line"><?php echo esc_html( bhc_contact( 'street' ) ); ?></span>
				<span class="footer-contact__line"><?php echo esc_html( bhc_contact( 'locality' ) ); ?></span>
				<span class="footer-contact__line"><?php echo esc_html( bhc_contact( 'region' ) ); ?></span>

				<a class="footer-contact__link" href="tel:<?php echo esc_attr( bhc_contact( 'phone_href' ) ); ?>">
					<?php echo esc_html( bhc_contact( 'phone' ) ); ?>
				</a>

				<a class="footer-contact__link" href="mailto:<?php echo esc_attr( bhc_contact( 'email' ) ); ?>">
					<?php echo esc_html( bhc_contact( 'email' ) ); ?>
				</a>
			</address>
		</div>
	</div>

	<div class="site-footer__legal">
		<?php
		wp_nav_menu(
			[
				'theme_location' => 'legal',
				'container'      => false,
				'menu_class'     => 'footer-legal-menu',
				'depth'          => 1,
				'fallback_cb'    => 'bhc_legal_menu_fallback',
			]
		);
		?>
	</div>

	<div class="site-footer__bottom">
		<p>
			<?php
			printf(
				/* translators: 1: year, 2: site name. */
				esc_html__( '© %1$s %2$s. All rights reserved.', 'bhc-theme' ),
				esc_html( (string) gmdate( 'Y' ) ),
				esc_html( get_bloginfo( 'name' ) )
			);
			?>
		</p>

		<p class="site-footer__credit">
			<?php
			$legal_entity = 'AS International';

			$options = bhc_service( \BoneHornCrafts\Core\Support\Options::class );

			if ( null !== $options ) {
				$legal_entity = $options->string( 'legal_entity' );
			}

			printf(
				/* translators: %s: manufacturing company name. */
				esc_html__( 'A brand by %s', 'bhc-theme' ),
				esc_html( $legal_entity )
			);
			?>
		</p>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
