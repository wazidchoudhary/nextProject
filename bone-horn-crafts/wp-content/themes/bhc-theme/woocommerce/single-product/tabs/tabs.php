<?php
/**
 * Product tabs.
 *
 * Overrides `woocommerce/templates/single-product/tabs/tabs.php`.
 *
 * WooCommerce's markup declares `role="tab"` and `role="tabpanel"` but never
 * emits `aria-selected`, which the ARIA tabs pattern requires — its own jQuery
 * sets the state after load. This theme drives the tabs itself (see
 * assets/js/theme.js), so the state has to be correct in the markup too, and
 * correct before JavaScript runs: a screen-reader user otherwise hears five
 * tabs with no indication of which one is showing.
 *
 * The additions over the core template:
 *
 * * `aria-selected` on every tab, true on the first.
 * * Roving `tabindex`, so Tab reaches the tab strip once and the arrow keys
 *   move within it, rather than tabbing through all five.
 * * `hidden` on the inactive panels, so they are out of the accessibility tree
 *   rather than merely out of sight.
 *
 * @package BHC_Theme
 * @version 9.6.0
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Filter tabs and allow third parties to add their own.
 *
 * @see woocommerce_default_product_tabs()
 */
$bhc_product_tabs = apply_filters( 'woocommerce_product_tabs', [] );

if ( empty( $bhc_product_tabs ) ) {
	return;
}

$bhc_tab_index = 0;
?>

<div class="woocommerce-tabs wc-tabs-wrapper product-tabs">
	<ul class="tabs wc-tabs product-tabs__nav" role="tablist">
		<?php foreach ( $bhc_product_tabs as $bhc_key => $bhc_tab ) : ?>
			<?php $bhc_active = 0 === $bhc_tab_index; ?>
			<li role="presentation" class="<?php echo esc_attr( $bhc_key ); ?>_tab">
				<a
					href="#tab-<?php echo esc_attr( $bhc_key ); ?>"
					id="tab-title-<?php echo esc_attr( $bhc_key ); ?>"
					role="tab"
					aria-controls="tab-<?php echo esc_attr( $bhc_key ); ?>"
					aria-selected="<?php echo $bhc_active ? 'true' : 'false'; ?>"
					tabindex="<?php echo $bhc_active ? '0' : '-1'; ?>"
				>
					<?php echo wp_kses_post( apply_filters( 'woocommerce_product_' . $bhc_key . '_tab_title', $bhc_tab['title'], $bhc_key ) ); ?>
				</a>
			</li>
			<?php ++$bhc_tab_index; ?>
		<?php endforeach; ?>
	</ul>

	<?php
	$bhc_tab_index = 0;

	foreach ( $bhc_product_tabs as $bhc_key => $bhc_tab ) :
		$bhc_active = 0 === $bhc_tab_index;
		?>
		<div
			class="woocommerce-Tabs-panel woocommerce-Tabs-panel--<?php echo esc_attr( $bhc_key ); ?> panel entry-content wc-tab"
			id="tab-<?php echo esc_attr( $bhc_key ); ?>"
			role="tabpanel"
			aria-labelledby="tab-title-<?php echo esc_attr( $bhc_key ); ?>"
			<?php echo $bhc_active ? '' : 'hidden'; ?>
		>
			<?php
			if ( isset( $bhc_tab['callback'] ) ) {
				call_user_func( $bhc_tab['callback'], $bhc_key, $bhc_tab );
			}
			?>
		</div>
		<?php
		++$bhc_tab_index;
	endforeach;
	?>

	<?php do_action( 'woocommerce_product_after_tabs' ); ?>
</div>
