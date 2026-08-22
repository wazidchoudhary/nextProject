<?php
/**
 * Pricing rule contract.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Contracts;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Pricing\PriceContext;

/**
 * A composable rule that may adjust a unit price.
 */
interface PricingRuleInterface {

	/**
	 * Rule identifier, surfaced in cart item metadata for traceability.
	 */
	public function id(): string;

	/**
	 * Whether the rule applies to the given context.
	 *
	 * @param PriceContext $context Pricing context.
	 */
	public function applies( PriceContext $context ): bool;

	/**
	 * Returns the adjusted unit price.
	 *
	 * @param PriceContext $context Pricing context.
	 */
	public function apply( PriceContext $context ): float;
}
