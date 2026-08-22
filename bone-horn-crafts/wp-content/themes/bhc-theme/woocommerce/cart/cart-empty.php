<?php
/**
 * Empty cart.
 *
 * Overrides `woocommerce/templates/cart/cart-empty.php`.
 *
 * @package BHC_Theme
 * @version 7.0.1
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_cart_is_empty' );
?>
<div class="bhc-empty">
	<h2 class="bhc-empty__title"><?php esc_html_e( 'Your cart is empty', 'bhc-theme' ); ?></h2>
	<p class="bhc-empty__body">
		<?php esc_html_e( 'Material is listed as it is cut, and the one-off pairs move fastest. Start with what is new this week.', 'bhc-theme' ); ?>
	</p>

	<p>
		<a class="bhc-button" href="<?php echo esc_url( home_url( '/new-arrivals/' ) ); ?>"><?php esc_html_e( 'See new arrivals', 'bhc-theme' ); ?></a>
		<a class="bhc-button bhc-button--ghost" href="<?php echo esc_url( bhc_wc_page_url( 'shop' ) ); ?>"><?php esc_html_e( 'Browse everything', 'bhc-theme' ); ?></a>
	</p>
</div>

<?php
$suggestions = bhc_products_for( 'bestsellers', 4 );

if ( [] !== $suggestions ) :
	?>
	<section class="bhc-rail">
		<header class="bhc-rail__header">
			<h2 class="section__title"><?php esc_html_e( 'Makers are buying', 'bhc-theme' ); ?></h2>
		</header>

		<div class="bhc-rail__track">
			<?php foreach ( $suggestions as $suggested ) : ?>
				<?php bhc_product_card( $suggested ); ?>
			<?php endforeach; ?>
		</div>
	</section>
	<?php
endif;
