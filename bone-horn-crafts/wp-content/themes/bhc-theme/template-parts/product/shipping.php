<?php
/**
 * Shipping and returns tab content.
 *
 * @package BHC_Theme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;
?>
<h2><?php esc_html_e( 'Dispatch and delivery', 'bhc-theme' ); ?></h2>
<p><?php esc_html_e( 'In-stock listings leave the workshop within one working day. Listings with a stated lead time are cut and finished first — that time is shown on this page, not discovered at checkout.', 'bhc-theme' ); ?></p>

<h2><?php esc_html_e( 'Customs', 'bhc-theme' ); ?></h2>
<p><?php esc_html_e( 'Every export parcel ships with an accurate commercial invoice and HS codes. Import duties and taxes charged on arrival are payable by the recipient.', 'bhc-theme' ); ?></p>

<h2><?php esc_html_e( 'Returns', 'bhc-theme' ); ?></h2>
<p><?php esc_html_e( 'Unworked material can be returned within thirty days of delivery. Once material has been cut, drilled or sanded it cannot come back — which is why every listing states its grading band.', 'bhc-theme' ); ?></p>

<p>
	<a href="<?php echo esc_url( home_url( '/shipping-delivery/' ) ); ?>"><?php esc_html_e( 'Full shipping information', 'bhc-theme' ); ?></a>
	·
	<a href="<?php echo esc_url( home_url( '/returns-refunds/' ) ); ?>"><?php esc_html_e( 'Returns and refunds', 'bhc-theme' ); ?></a>
</p>
