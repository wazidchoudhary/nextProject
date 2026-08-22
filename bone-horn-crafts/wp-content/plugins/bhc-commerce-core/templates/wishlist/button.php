<?php
/**
 * Wishlist toggle button.
 *
 * @package BoneHornCrafts\Core
 *
 * @var \WC_Product $product     Product.
 * @var bool        $in_list     Whether the product is saved.
 * @var string      $variant     `compact` or `full`.
 * @var string      $nonce       Toggle nonce.
 * @var string      $action_url  Fallback form action.
 * @var string      $label_add   Label when not saved.
 * @var string      $label_saved Label when saved.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

if ( ! isset( $product ) || ! $product instanceof WC_Product ) {
	return;
}

$variant = 'full' === ( $variant ?? 'compact' ) ? 'full' : 'compact';
$label   = $in_list ? $label_saved : $label_add;
?>
<form class="bhc-wishlist-form bhc-wishlist-form--<?php echo esc_attr( $variant ); ?>" method="post" action="<?php echo esc_url( (string) $action_url ); ?>">
	<input type="hidden" name="action" value="bhc_wishlist_toggle" />
	<input type="hidden" name="product_id" value="<?php echo esc_attr( (string) $product->get_id() ); ?>" />
	<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( (string) $nonce ); ?>" />

	<button
		type="submit"
		class="bhc-wishlist-toggle<?php echo $in_list ? ' is-saved' : ''; ?>"
		data-bhc-wishlist-toggle
		data-product-id="<?php echo esc_attr( (string) $product->get_id() ); ?>"
		aria-pressed="<?php echo $in_list ? 'true' : 'false'; ?>"
	>
		<span class="bhc-wishlist-toggle__icon" aria-hidden="true">
			<svg viewBox="0 0 24 24" width="20" height="20" focusable="false" role="presentation">
				<path d="M12 20.7 4.6 13.3a4.6 4.6 0 0 1 6.5-6.5l.9.9.9-.9a4.6 4.6 0 1 1 6.5 6.5Z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
			</svg>
		</span>
		<span class="bhc-wishlist-toggle__label<?php echo 'compact' === $variant ? ' screen-reader-text' : ''; ?>" data-bhc-wishlist-label>
			<?php echo esc_html( (string) $label ); ?>
		</span>
	</button>
</form>
