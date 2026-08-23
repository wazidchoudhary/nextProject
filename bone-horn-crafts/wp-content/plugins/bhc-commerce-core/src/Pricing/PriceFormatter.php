<?php
/**
 * Storefront price presentation.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Pricing;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Contracts\HookableInterface;
use BoneHornCrafts\Core\Product\ProductMeta;
use WC_Product;

/**
 * Formats prices the way a materials supplier needs them.
 *
 * Three storefront behaviours live here:
 *
 * 1. Size-driven price ranges. A scale sold in three thicknesses is a variable
 *    product; the archive shows "$19.99 – $28.99" and the detail page switches
 *    to the exact price once a size is chosen.
 * 2. A unit suffix ("per matched pair", "set of 6") so a price is never
 *    ambiguous.
 * 3. A discount chip on sale prices, shown only where the saving is large
 *    enough to be worth a customer's attention.
 */
final class PriceFormatter implements HookableInterface {

	/**
	 * Smallest saving worth putting a chip on, as a percentage.
	 */
	private const MIN_DISCOUNT_SHOWN = 5;

	/**
	 * Constructor.
	 *
	 * @param DiscountCalculator $calculator Discount maths.
	 */
	public function __construct( private DiscountCalculator $calculator ) {}

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_filter( 'woocommerce_get_price_html', [ $this, 'append_unit_suffix' ], 10, 2 );
		add_filter( 'woocommerce_format_sale_price', [ $this, 'format_sale_price' ], 10, 3 );
	}

	/**
	 * Appends the unit of sale to a price string.
	 *
	 * @param string     $price_html Price markup.
	 * @param WC_Product $product    Product.
	 */
	public function append_unit_suffix( string $price_html, WC_Product $product ): string {
		if ( '' === $price_html || is_admin() ) {
			return $price_html;
		}

		$unit = ProductMeta::unit_of_sale( $product );

		if ( '' === $unit ) {
			return $price_html;
		}

		return $price_html . sprintf(
			' <span class="bhc-price__unit">%s</span>',
			esc_html( $unit )
		);
	}

	/**
	 * Renders sale prices with an accessible "was / now" structure and the saving.
	 *
	 * `woocommerce_format_sale_price` passes the regular and sale values as
	 * WooCommerce received them, which for a product price is a bare number
	 * like `28.99` — WooCommerce runs each through `wc_price()` itself while
	 * building the markup this filter then replaces. Re-rendering the raw
	 * values, as this method used to, silently dropped the currency symbol,
	 * the thousands separator and the decimal precision from every sale price
	 * in the store. Numeric values are formatted here the same way; anything
	 * already formatted (a variable product's price range, for instance)
	 * passes through untouched.
	 *
	 * @param string $price   Existing markup, discarded in favour of ours.
	 * @param mixed  $regular Regular price: a number, or pre-formatted markup.
	 * @param mixed  $sale    Sale price: a number, or pre-formatted markup.
	 */
	public function format_sale_price( string $price, mixed $regular, mixed $sale ): string {
		$regular_html = $this->as_price_html( $regular );
		$sale_html    = $this->as_price_html( $sale );

		$markup = sprintf(
			'<span class="bhc-price"><del aria-hidden="true">%1$s</del><span class="screen-reader-text">%2$s</span> <ins>%3$s</ins></span>',
			wp_kses_post( $regular_html ),
			esc_html__( 'Original price:', 'bhc-commerce-core' ),
			wp_kses_post( $sale_html )
		);

		// The brief asks for the discount to be shown "where appropriate".
		// Appropriate is: a genuine saving, on the storefront, big enough to be
		// worth a customer's attention. A 1% chip is noise.
		if ( is_admin() || ! is_numeric( $regular ) || ! is_numeric( $sale ) ) {
			return $markup;
		}

		$percentage = $this->calculator->percentage_off( (float) $regular, (float) $sale );

		if ( $percentage < self::MIN_DISCOUNT_SHOWN ) {
			return $markup;
		}

		return $markup . sprintf(
			' <span class="bhc-price__savings">%s</span>',
			sprintf(
				/* translators: %d: percentage saved. */
				esc_html__( 'Save %d%%', 'bhc-commerce-core' ),
				$percentage
			)
		);
	}

	/**
	 * Formats a price value for display, leaving existing markup alone.
	 *
	 * @param mixed $value Number, or already-formatted price markup.
	 */
	private function as_price_html( mixed $value ): string {
		if ( is_numeric( $value ) ) {
			return (string) wc_price( (float) $value );
		}

		return (string) $value;
	}

	/**
	 * Percentage saved on a product, for callers that need the number rather
	 * than the storefront chip — the REST presenter, chiefly.
	 *
	 * @param WC_Product $product Product.
	 */
	public function discount_percentage( WC_Product $product ): int {
		[ $regular, $active ] = $this->prices( $product );

		return $this->calculator->percentage_off( $regular, $active );
	}

	/**
	 * Resolves the regular/active price pair for a product.
	 *
	 * A variable product's headline price is its cheapest variation, so that is
	 * the pair a discount should be computed against.
	 *
	 * @param WC_Product $product Product.
	 *
	 * @return array{0:float,1:float}
	 */
	private function prices( WC_Product $product ): array {
		if ( $product->is_type( 'variable' ) ) {
			return [
				(float) $product->get_variation_regular_price( 'min' ),
				(float) $product->get_variation_price( 'min' ),
			];
		}

		return [ (float) $product->get_regular_price(), (float) $product->get_price() ];
	}

	/**
	 * Whether the product's price is a range (multiple sizes).
	 *
	 * @param WC_Product $product Product.
	 */
	public function has_price_range( WC_Product $product ): bool {
		if ( ! $product->is_type( 'variable' ) ) {
			return false;
		}

		return (float) $product->get_variation_price( 'min' ) < (float) $product->get_variation_price( 'max' );
	}

	/**
	 * Returns the lowest price of a product, used for schema and sorting.
	 *
	 * @param WC_Product $product Product.
	 */
	public function lowest_price( WC_Product $product ): float {
		return $product->is_type( 'variable' )
			? (float) $product->get_variation_price( 'min' )
			: (float) $product->get_price();
	}

	/**
	 * Returns the highest price of a product.
	 *
	 * @param WC_Product $product Product.
	 */
	public function highest_price( WC_Product $product ): float {
		return $product->is_type( 'variable' )
			? (float) $product->get_variation_price( 'max' )
			: (float) $product->get_price();
	}
}
