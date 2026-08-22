<?php
/**
 * REST API wiring.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\API;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\AbstractServiceProvider;
use BoneHornCrafts\Core\Admin\HealthReport;
use BoneHornCrafts\Core\Checkout\DeliveryEstimator;
use BoneHornCrafts\Core\Checkout\PostcodeValidator;
use BoneHornCrafts\Core\Container;
use BoneHornCrafts\Core\Contracts\ContainerInterface;
use BoneHornCrafts\Core\Pricing\PriceFormatter;
use BoneHornCrafts\Core\Product\Badges\BadgeResolver;
use BoneHornCrafts\Core\Recommendations\RecommendationService;
use BoneHornCrafts\Core\Search\SearchService;
use BoneHornCrafts\Core\Security\RestGuard;
use BoneHornCrafts\Core\Wishlist\WishlistService;

/**
 * Registers the `bhc/v1` REST controllers.
 */
final class ApiServiceProvider extends AbstractServiceProvider {

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerInterface $container Container instance.
	 */
	public function register( ContainerInterface $container ): void {
		/** @var Container $container */
		$container->singleton(
			ProductPresenter::class,
			static fn ( Container $c ): ProductPresenter => new ProductPresenter(
				$c->get( BadgeResolver::class ),
				$c->get( PriceFormatter::class )
			)
		);

		$container->singleton(
			WishlistController::class,
			static fn ( Container $c ): WishlistController => new WishlistController(
				$c->get( WishlistService::class ),
				$c->get( ProductPresenter::class ),
				$c->get( RestGuard::class )
			)
		);

		$container->singleton(
			RecommendationsController::class,
			static fn ( Container $c ): RecommendationsController => new RecommendationsController(
				$c->get( RecommendationService::class ),
				$c->get( ProductPresenter::class ),
				$c->get( RestGuard::class )
			)
		);

		$container->singleton(
			CatalogController::class,
			static fn ( Container $c ): CatalogController => new CatalogController(
				$c->get( SearchService::class ),
				$c->get( ProductPresenter::class ),
				$c->get( RestGuard::class )
			)
		);

		$container->singleton(
			ShippingController::class,
			static fn ( Container $c ): ShippingController => new ShippingController(
				$c->get( DeliveryEstimator::class ),
				$c->get( PostcodeValidator::class ),
				$c->get( RestGuard::class )
			)
		);

		$container->singleton(
			HealthController::class,
			static fn ( Container $c ): HealthController => new HealthController(
				$c->get( HealthReport::class ),
				$c->get( RestGuard::class )
			)
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerInterface $container Container instance.
	 */
	public function boot( ContainerInterface $container ): void {
		$this->hook(
			$container,
			WishlistController::class,
			RecommendationsController::class,
			CatalogController::class,
			ShippingController::class,
			HealthController::class
		);
	}
}
