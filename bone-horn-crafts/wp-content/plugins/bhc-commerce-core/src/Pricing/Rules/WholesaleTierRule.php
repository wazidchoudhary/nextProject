<?php
/**
 * Quantity break pricing rule.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Pricing\Rules;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Contracts\PricingRuleInterface;
use BoneHornCrafts\Core\Pricing\DiscountCalculator;
use BoneHornCrafts\Core\Pricing\PriceContext;
use BoneHornCrafts\Core\Product\ProductMeta;

/**
 * Applies the product's quantity price breaks.
 */
final class WholesaleTierRule implements PricingRuleInterface {

	/**
	 * Constructor.
	 *
	 * @param DiscountCalculator $calculator Discount maths.
	 */
	public function __construct( private DiscountCalculator $calculator ) {}

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'wholesale_tier';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param PriceContext $context Pricing context.
	 */
	public function applies( PriceContext $context ): bool {
		if ( ! ProductMeta::wholesale_enabled( $context->product ) ) {
			return false;
		}

		return [] !== ProductMeta::price_tiers( $context->product );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param PriceContext $context Pricing context.
	 */
	public function apply( PriceContext $context ): float {
		return $this->calculator->tier_price(
			ProductMeta::price_tiers( $context->product ),
			$context->quantity,
			$context->base_price
		);
	}
}
