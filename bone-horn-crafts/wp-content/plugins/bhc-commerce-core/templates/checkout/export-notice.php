<?php
/**
 * Checkout export notice.
 *
 * @package BoneHornCrafts\Core
 *
 * @var string               $country  Destination country code.
 * @var bool                 $domestic Whether the order is domestic.
 * @var array<string, mixed> $estimate Delivery estimate for the cart, if known.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;
?>
<section class="bhc-export-notice" aria-label="<?php esc_attr_e( 'Delivery and customs information', 'bhc-commerce-core' ); ?>">
	<?php if ( ! empty( $estimate['label'] ) ) : ?>
		<p class="bhc-export-notice__delivery"><strong><?php echo esc_html( (string) $estimate['label'] ); ?></strong></p>
		<?php if ( empty( $estimate['supported'] ) ) : ?>
			<p><?php esc_html_e( 'We have not shipped to this destination before, so treat the window above as indicative.', 'bhc-commerce-core' ); ?></p>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( ! empty( $domestic ) ) : ?>
		<p><?php esc_html_e( 'Domestic order: GST is shown on your invoice at the rate applicable to each item.', 'bhc-commerce-core' ); ?></p>
	<?php else : ?>
		<p><?php esc_html_e( 'Export order: your parcel ships with an accurate commercial invoice and HS codes for customs.', 'bhc-commerce-core' ); ?></p>
		<p><?php esc_html_e( 'Import duties and taxes charged on arrival are payable by the recipient and are not collected here.', 'bhc-commerce-core' ); ?></p>
	<?php endif; ?>
</section>
