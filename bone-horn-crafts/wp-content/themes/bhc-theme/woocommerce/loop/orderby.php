<?php
/**
 * Catalogue ordering form.
 *
 * Overrides WooCommerce's `loop/orderby.php`.
 *
 * The stock template renders a <select> and no submit control, relying entirely
 * on a jQuery handler in WooCommerce's `woocommerce` frontend script to send the
 * form. This theme dequeues that script outside cart, checkout and account (see
 * inc/performance.php), so the only change here is a real submit button: sorting
 * then works with JavaScript disabled, and theme.js hides the button as soon as
 * it binds its own change handler.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package BHC_Theme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$bhc_id_suffix = wp_unique_id();

?>
<form class="woocommerce-ordering" method="get">
	<?php if ( $use_label ) : ?>
		<label for="woocommerce-orderby-<?php echo esc_attr( $bhc_id_suffix ); ?>"><?php esc_html_e( 'Sort by', 'bhc-theme' ); ?></label>
	<?php endif; ?>
	<select
		name="orderby"
		class="orderby"
		<?php if ( $use_label ) : ?>
			id="woocommerce-orderby-<?php echo esc_attr( $bhc_id_suffix ); ?>"
		<?php else : ?>
			aria-label="<?php esc_attr_e( 'Shop order', 'bhc-theme' ); ?>"
		<?php endif; ?>
	>
		<?php foreach ( $catalog_orderby_options as $bhc_id => $bhc_name ) : ?>
			<option value="<?php echo esc_attr( $bhc_id ); ?>" <?php selected( $orderby, $bhc_id ); ?>><?php echo esc_html( $bhc_name ); ?></option>
		<?php endforeach; ?>
	</select>

	<?php
	// Hidden by default and revealed only when scripting is off, so the button
	// never flashes into view before the deferred theme script can remove it.
	?>
	<button type="submit" class="bhc-button bhc-button--quiet woocommerce-ordering__submit" data-bhc-ordering-submit hidden>
		<?php esc_html_e( 'Sort', 'bhc-theme' ); ?>
	</button>
	<noscript>
		<style>.woocommerce-ordering__submit[hidden]{display:inline-flex}</style>
	</noscript>

	<input type="hidden" name="paged" value="1" />
	<?php wc_query_string_form_fields( null, [ 'orderby', 'submit', 'paged', 'product-page' ] ); ?>
</form>
