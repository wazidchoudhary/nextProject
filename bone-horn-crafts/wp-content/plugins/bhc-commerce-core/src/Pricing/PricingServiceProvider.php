<?php
/**
 * Pricing module wiring.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Pricing;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\AbstractServiceProvider;
use BoneHornCrafts\Core\Container;
use BoneHornCrafts\Core\Contracts\ContainerInterface;
use BoneHornCrafts\Core\Contracts\LoggerInterface;
use BoneHornCrafts\Core\Pricing\Rules\WholesaleTierRule;
use BoneHornCrafts\Core\Support\Options;
use BoneHornCrafts\Core\Support\Template;

/**
 * Registers pricing services and the rule chain.
 */
final class PricingServiceProvider extends AbstractServiceProvider {

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerInterface $container Container instance.
	 */
	public function register( ContainerInterface $container ): void {
		/** @var Container $container */
		$container->singleton( DiscountCalculator::class, static fn (): DiscountCalculator => new DiscountCalculator() );

		$container->singleton(
			WholesaleTierRule::class,
			static fn ( Container $c ): WholesaleTierRule => new WholesaleTierRule( $c->get( DiscountCalculator::class ) )
		);

		$container->singleton(
			PriceFormatter::class,
			static fn ( Container $c ): PriceFormatter => new PriceFormatter( $c->get( DiscountCalculator::class ) )
		);

		$container->singleton(
			TieredPricingService::class,
			static function ( Container $c ): TieredPricingService {
				/**
				 * Filters the ordered pricing rule chain.
				 *
				 * @since 1.0.0
				 *
				 * @param \BoneHornCrafts\Core\Contracts\PricingRuleInterface[] $rules Rules.
				 */
				$rules = (array) apply_filters(
					'bhc_pricing_rules',
					[ $c->get( WholesaleTierRule::class ) ]
				);

				return new TieredPricingService(
					$c->get( DiscountCalculator::class ),
					$rules,
					$c->get( Template::class ),
					$c->get( Options::class ),
					$c->get( LoggerInterface::class )
				);
			}
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerInterface $container Container instance.
	 */
	public function boot( ContainerInterface $container ): void {
		$this->hook( $container, TieredPricingService::class, PriceFormatter::class );
	}
}
