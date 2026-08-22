<?php
/**
 * Pricing context value object.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Pricing;

defined( 'ABSPATH' ) || exit;

use WC_Product;

/**
 * Immutable inputs for a price calculation.
 *
 * Passing a context object instead of five positional arguments keeps the
 * pricing rules substitutable (Liskov) and makes the unit tests read like the
 * business rule they encode.
 */
final class PriceContext {

	/**
	 * Constructor.
	 *
	 * @param WC_Product $product      Product being priced.
	 * @param float      $base_price   Price before this rule runs.
	 * @param int        $quantity     Quantity in the cart line.
	 * @param int        $customer_id  Customer id (0 for guests).
	 * @param bool       $is_wholesale Whether the customer is a wholesale account.
	 */
	public function __construct(
		public readonly WC_Product $product,
		public readonly float $base_price,
		public readonly int $quantity = 1,
		public readonly int $customer_id = 0,
		public readonly bool $is_wholesale = false
	) {}

	/**
	 * Returns a copy with a different base price.
	 *
	 * @param float $price New base price.
	 */
	public function with_price( float $price ): self {
		return new self( $this->product, $price, $this->quantity, $this->customer_id, $this->is_wholesale );
	}
}
