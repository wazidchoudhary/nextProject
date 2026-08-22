<?php
/**
 * Contact details block.
 *
 * @package BHC_Theme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$options = bhc_service( \BoneHornCrafts\Core\Support\Options::class );
$email   = null !== $options ? $options->string( 'organization_email' ) : 'hello@bonehorncrafts.com';
$phone   = null !== $options ? $options->string( 'organization_phone' ) : '';
?>
<section class="section section--tight" aria-labelledby="contact-details">
	<h2 id="contact-details"><?php esc_html_e( 'Reach the workshop', 'bhc-theme' ); ?></h2>

	<ul class="product-assurances">
		<li>
			<strong><?php esc_html_e( 'Email', 'bhc-theme' ); ?></strong>
			<a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a>
		</li>

		<?php if ( '' !== $phone ) : ?>
			<li>
				<strong><?php esc_html_e( 'Phone', 'bhc-theme' ); ?></strong>
				<span><?php echo esc_html( $phone ); ?></span>
			</li>
		<?php endif; ?>

		<li>
			<strong><?php esc_html_e( 'Hours', 'bhc-theme' ); ?></strong>
			<span><?php esc_html_e( 'Monday to Saturday, 09:00–18:00 IST', 'bhc-theme' ); ?></span>
		</li>

		<li>
			<strong><?php esc_html_e( 'Wholesale', 'bhc-theme' ); ?></strong>
			<span><?php esc_html_e( 'Send the item, annual quantity and destination country for a landed price.', 'bhc-theme' ); ?></span>
		</li>
	</ul>

	<form class="bhc-newsletter__form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" style="margin-top:2rem">
		<input type="hidden" name="action" value="bhc_contact_placeholder" />
		<?php wp_nonce_field( 'bhc_contact_form' ); ?>

		<label class="screen-reader-text" for="contact-email"><?php esc_html_e( 'Your email', 'bhc-theme' ); ?></label>
		<input type="email" id="contact-email" name="email" placeholder="<?php esc_attr_e( 'you@workshop.com', 'bhc-theme' ); ?>" required />

		<button type="submit" class="bhc-button"><?php esc_html_e( 'Ask the workshop', 'bhc-theme' ); ?></button>
	</form>

	<p class="bhc-field__hint">
		<?php esc_html_e( 'This demo build does not send mail. On a live site this form posts to a validated, nonce-protected handler.', 'bhc-theme' ); ?>
	</p>
</section>
