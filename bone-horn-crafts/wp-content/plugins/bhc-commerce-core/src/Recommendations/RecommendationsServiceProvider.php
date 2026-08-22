<?php
/**
 * Recommendations module wiring.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Recommendations;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\AbstractServiceProvider;
use BoneHornCrafts\Core\Cache\CacheManager;
use BoneHornCrafts\Core\Container;
use BoneHornCrafts\Core\Contracts\ContainerInterface;
use BoneHornCrafts\Core\Contracts\LoggerInterface;
use BoneHornCrafts\Core\Product\ProductRepository;
use BoneHornCrafts\Core\Product\RecentlyViewed\RecentlyViewedService;
use BoneHornCrafts\Core\Recommendations\Strategies\BoughtTogetherStrategy;
use BoneHornCrafts\Core\Recommendations\Strategies\PriceBandStrategy;
use BoneHornCrafts\Core\Recommendations\Strategies\SameCategoryStrategy;
use BoneHornCrafts\Core\Recommendations\Strategies\SharedAttributeStrategy;
use BoneHornCrafts\Core\Recommendations\Strategies\TagStrategy;
use BoneHornCrafts\Core\Support\Context;
use BoneHornCrafts\Core\Support\Options;
use BoneHornCrafts\Core\Support\Template;

/**
 * Registers the recommendation engine and its strategies.
 */
final class RecommendationsServiceProvider extends AbstractServiceProvider {

	/**
	 * Request context.
	 */
	private ?Context $context = null;

	/**
	 * {@inheritDoc}
	 *
	 * @param Context $context Request context.
	 */
	public function should_load( Context $context ): bool {
		$this->context = $context;

		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerInterface $container Container instance.
	 */
	public function register( ContainerInterface $container ): void {
		/** @var Container $container */
		$container->singleton( AffinityRepository::class, static fn (): AffinityRepository => new AffinityRepository() );

		$container->singleton(
			RecommendationService::class,
			static function ( Container $c ): RecommendationService {
				$products = $c->get( ProductRepository::class );

				/**
				 * Filters the recommendation strategy chain.
				 *
				 * Weights are relative: doubling a weight doubles that
				 * signal's influence on the blended score.
				 *
				 * @since 1.0.0
				 *
				 * @param \BoneHornCrafts\Core\Contracts\RecommendationStrategyInterface[] $strategies Strategies.
				 */
				$strategies = (array) apply_filters(
					'bhc_recommendation_strategies',
					[
						new BoughtTogetherStrategy( $c->get( AffinityRepository::class ), 2.0 ),
						new SharedAttributeStrategy( $products, 1.4 ),
						new SameCategoryStrategy( $products, 1.0 ),
						new TagStrategy( $products, 0.8 ),
						new PriceBandStrategy( $products, 0.4 ),
					]
				);

				return new RecommendationService(
					$strategies,
					$products,
					$c->get( CacheManager::class ),
					$c->get( Options::class ),
					$c->get( LoggerInterface::class )
				);
			}
		);

		$container->singleton(
			RecommendationRenderer::class,
			static fn ( Container $c ): RecommendationRenderer => new RecommendationRenderer(
				$c->get( RecommendationService::class ),
				$c->get( RecentlyViewedService::class ),
				$c->get( ProductRepository::class ),
				$c->get( Template::class )
			)
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerInterface $container Container instance.
	 */
	public function boot( ContainerInterface $container ): void {
		$context = $this->context ?? new Context();

		if ( $context->is_frontend() ) {
			$this->hook( $container, RecommendationRenderer::class );
		}

		// Keep the index clean when products disappear.
		add_action(
			'woocommerce_delete_product',
			static function ( int $product_id ) use ( $container ): void {
				$container->get( AffinityRepository::class )->forget_product( $product_id );
			}
		);
	}
}
