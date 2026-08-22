<?php
/**
 * Checkout export notice.
 *
 * @package BoneHornCrafts\Core
 *
 * @var string $country  Destination country code.
 * @var bool   $domestic Whether the order is domestic.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;
?>
<section class="bhc-export-notice" aria-label="<?php esc_attr_e( 'Shipping and customs information', 'bhc-commerce-core' ); ?>">
	<?php if ( ! empty( $domestic ) ) : ?>
		<p><?php esc_html_e( 'Domestic order: GST is shown on your invoice at the rate applicable to each item.', 'bhc-commerce-core' ); ?></p>
	<?php else : ?>
		<p><?php esc_html_e( 'Export order: your parcel ships with an accurate commercial invoice and HS codes for customs.', 'bhc-commerce-core' ); ?></p>
		<p><?php esc_html_e( 'Import duties and taxes charged on arrival are payable by the recipient and are not collected here.', 'bhc-commerce-core' ); ?></p>
	<?php endif; ?>
</section>
