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
 * 3. A discount chip that states the saving in both percent and currency.
 */
final class PriceFormatter implements HookableInterface {

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
	 * Renders sale prices with an accessible "was / now" structure.
	 *
	 * @param string $price   Existing markup.
	 * @param string $regular Regular price string.
	 * @param string $sale    Sale price string.
	 */
	public function format_sale_price( string $price, string $regular, string $sale ): string {
		return sprintf(
			'<span class="bhc-price"><del aria-hidden="true">%1$s</del><span class="screen-reader-text">%2$s</span> <ins>%3$s</ins></span>',
			wp_kses_post( (string) $regular ),
			esc_html__( 'Original price:', 'bhc-commerce-core' ),
			wp_kses_post( (string) $sale )
		);
	}

	/**
	 * Percentage saved for a product.
	 *
	 * @param WC_Product $product Product.
	 */
	public function discount_percentage( WC_Product $product ): int {
		[ $regular, $active ] = $this->prices( $product );

		return $this->calculator->percentage_off( $regular, $active );
	}

	/**
	 * Amount saved for a product, formatted for display.
	 *
	 * @param WC_Product $product Product.
	 */
	public function savings_html( WC_Product $product ): string {
		[ $regular, $active ] = $this->prices( $product );

		$savings = $this->calculator->savings( $regular, $active );

		if ( $savings <= 0.0 ) {
			return '';
		}

		return sprintf(
			'<span class="bhc-price__savings">%s</span>',
			sprintf(
				/* translators: 1: percentage saved, 2: amount saved. */
				esc_html__( 'Save %1$d%% (%2$s)', 'bhc-commerce-core' ),
				$this->calculator->percentage_off( $regular, $active ),
				wp_kses_post( wc_price( $savings ) )
			)
		);
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

	/**
	 * Resolves the regular/active price pair for a product.
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
}
