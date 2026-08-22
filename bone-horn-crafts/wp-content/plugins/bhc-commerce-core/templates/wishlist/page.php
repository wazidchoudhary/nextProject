<?php
/**
 * Wishlist page.
 *
 * @package BoneHornCrafts\Core
 *
 * @var \WC_Product[] $products  Saved products.
 * @var bool          $available Whether the wishlist is enabled.
 * @var string        $shop_url  Shop permalink.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Plugin;
use BoneHornCrafts\Core\Support\Template;

if ( empty( $available ) ) : ?>
	<p class="bhc-notice"><?php esc_html_e( 'The wishlist is currently unavailable.', 'bhc-commerce-core' ); ?></p>
	<?php
	return;
endif;

if ( empty( $products ) ) : ?>
	<div class="bhc-empty">
		<h2 class="bhc-empty__title"><?php esc_html_e( 'Nothing saved yet', 'bhc-commerce-core' ); ?></h2>
		<p class="bhc-empty__body"><?php esc_html_e( 'Save material while you plan a build and it will wait for you here. One-off pairs move quickly, so a saved list is the fastest way back to them.', 'bhc-commerce-core' ); ?></p>
		<p><a class="bhc-button" href="<?php echo esc_url( (string) $shop_url ); ?>"><?php esc_html_e( 'Browse the catalogue', 'bhc-commerce-core' ); ?></a></p>
	</div>
	<?php
	return;
endif;

$template = Plugin::resolve( Template::class );
?>
<div class="bhc-wishlist" data-bhc-wishlist-grid>
	<div class="bhc-grid bhc-grid--3">
		<?php
		foreach ( array_values( $products ) as $index => $saved_product ) {
			$template->output(
				'product/card.php',
				[
					'product' => $saved_product,
					'eager'   => 0 === $index,
				]
			);
		}
		?>
	</div>
</div>
