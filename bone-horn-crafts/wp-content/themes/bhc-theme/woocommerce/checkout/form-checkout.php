<?php
/**
 * Checkout form.
 *
 * Overrides `woocommerce/templates/checkout/form-checkout.php`.
 *
 * The only change is structural: the order-review heading and the review table
 * are wrapped in one element so the form has a predictable number of direct
 * children.
 *
 * WooCommerce emits three siblings — customer details, an `<h3>`, and the
 * review — and this theme lays the form out as a two-column grid. Grid
 * auto-placement then puts the heading in the right-hand column and the review
 * back in the left, one row down; and because WooCommerce injects its validation
 * notices as a *fourth* sibling at the top of the same form, every item shifts
 * one cell when a submit fails. The billing fields jump from the left column to
 * the right, measured here as `left 136 -> 806`. Wrapping the two right-hand
 * pieces makes the grid two items wide with an optional full-width notice row,
 * which cannot scramble.
 *
 * Every hook, id and class WooCommerce documents is preserved, so payment
 * gateways and extensions that target them keep working.
 *
 * @package BHC_Theme
 * @version 9.4.0
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_checkout_form', $checkout );

// If checkout registration is disabled and not logged in, the user cannot checkout.
if ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) {
	echo esc_html( apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __( 'You must be logged in to checkout.', 'woocommerce' ) ) );

	return;
}
?>

<form name="checkout" method="post" class="checkout woocommerce-checkout" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data" aria-label="<?php echo esc_attr__( 'Checkout', 'woocommerce' ); ?>">

	<?php if ( $checkout->get_checkout_fields() ) : ?>

		<?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>

		<div class="col2-set" id="customer_details">
			<div class="col-1">
				<?php do_action( 'woocommerce_checkout_billing' ); ?>
			</div>

			<div class="col-2">
				<?php do_action( 'woocommerce_checkout_shipping' ); ?>
			</div>
		</div>

		<?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>

	<?php endif; ?>

	<div class="bhc-checkout__aside">
		<?php do_action( 'woocommerce_checkout_before_order_review_heading' ); ?>

		<h3 id="order_review_heading"><?php esc_html_e( 'Your order', 'woocommerce' ); ?></h3>

		<?php do_action( 'woocommerce_checkout_before_order_review' ); ?>

		<div id="order_review" class="woocommerce-checkout-review-order">
			<?php do_action( 'woocommerce_checkout_order_review' ); ?>
		</div>

		<?php do_action( 'woocommerce_checkout_after_order_review' ); ?>
	</div>

</form>

<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>
