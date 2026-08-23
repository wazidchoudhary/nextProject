<?php
/**
 * Shipping and export information surfaces.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Checkout;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Contracts\HookableInterface;
use BoneHornCrafts\Core\Support\Options;
use BoneHornCrafts\Core\Support\Template;
use BoneHornCrafts\Core\Product\ProductMeta;
use WC_Product;

/**
 * Renders the delivery estimator on product pages and the export notice at
 * checkout.
 */
final class ShippingInfoRenderer implements HookableInterface {

	/**
	 * Constructor.
	 *
	 * @param DeliveryEstimator $estimator Delivery estimator.
	 * @param Template          $template  Template renderer.
	 * @param Options           $options   Settings.
	 */
	public function __construct(
		private DeliveryEstimator $estimator,
		private Template $template,
		private Options $options
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		if ( $this->options->bool( 'delivery_estimator_enabled' ) ) {
			add_action( 'woocommerce_single_product_summary', [ $this, 'render_estimator' ], 35 );
		}

		add_action( 'woocommerce_review_order_before_payment', [ $this, 'render_export_notice' ] );
		add_shortcode( 'bhc_delivery_estimator', [ $this, 'shortcode' ] );
	}

	/**
	 * Renders the product page delivery estimator.
	 */
	public function render_estimator(): void {
		global $product;

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template escapes its own output.
		echo $this->estimator_markup( $product );
	}

	/**
	 * `[bhc_delivery_estimator]` shortcode.
	 */
	public function shortcode(): string {
		$product = wc_get_product( get_the_ID() );

		return $product instanceof WC_Product ? $this->estimator_markup( $product ) : '';
	}

	/**
	 * Builds the estimator markup for a product.
	 *
	 * @param WC_Product $product Product.
	 */
	public function estimator_markup( WC_Product $product ): string {
		$country   = $this->default_country();
		$countries = function_exists( 'WC' ) && null !== WC()->countries
			? WC()->countries->get_shipping_countries()
			: [];

		return $this->template->render(
			'checkout/delivery-estimator.php',
			[
				'product'   => $product,
				'countries' => $countries,
				'selected'  => $country,
				'estimate'  => $this->estimator->estimate( $product, $country ),
			]
		);
	}

	/**
	 * Renders the customs/export notice at checkout.
	 */
	public function render_export_notice(): void {
		$country = function_exists( 'WC' ) && null !== WC()->customer
			? (string) WC()->customer->get_shipping_country()
			: '';

		$this->template->output(
			'checkout/export-notice.php',
			[
				'country'  => $country,
				'domestic' => 'IN' === strtoupper( $country ),
				// The brief asks for delivery *and* export information here, and
				// only the export half was rendering. A shopper at the payment
				// step wants to know when it arrives, not only what customs will
				// do with it.
				'estimate' => $this->cart_estimate( $country ),
			]
		);
	}

	/**
	 * Delivery estimate for the cart as a whole.
	 *
	 * A cart ships as one parcel, so its window is set by the slowest item in
	 * it: quoting the fastest would be a promise the order cannot keep.
	 *
	 * @param string $country Destination country code.
	 *
	 * @return array<string, mixed> Estimate payload, or an empty array.
	 */
	private function cart_estimate( string $country ): array {
		if ( '' === $country || ! function_exists( 'WC' ) || null === WC()->cart ) {
			return [];
		}

		$slowest   = null;
		$lead_time = -1;

		foreach ( WC()->cart->get_cart() as $item ) {
			$product = $item['data'] ?? null;

			if ( ! $product instanceof WC_Product ) {
				continue;
			}

			$days = ProductMeta::lead_time_days( $product );

			if ( $days > $lead_time ) {
				$lead_time = $days;
				$slowest   = $product;
			}
		}

		return $this->estimator->estimate( $slowest, $country );
	}

	/**
	 * Country used before the shopper picks one.
	 */
	private function default_country(): string {
		if ( function_exists( 'WC' ) && null !== WC()->customer ) {
			$country = (string) WC()->customer->get_shipping_country();

			if ( '' !== $country ) {
				return $country;
			}
		}

		return 'US';
	}
}
