<?php
/**
 * Analytics module wiring.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Analytics;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\AbstractServiceProvider;
use BoneHornCrafts\Core\Cache\CacheManager;
use BoneHornCrafts\Core\Container;
use BoneHornCrafts\Core\Contracts\ContainerInterface;
use BoneHornCrafts\Core\Contracts\LoggerInterface;
use BoneHornCrafts\Core\Recommendations\AffinityRepository;
use BoneHornCrafts\Core\Support\Context;

/**
 * Registers view tracking and the merchandising indexer.
 */
final class AnalyticsServiceProvider extends AbstractServiceProvider {

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
		$container->singleton(
			ProductViewTracker::class,
			static fn ( Container $c ): ProductViewTracker => new ProductViewTracker( $c->get( CacheManager::class ) )
		);

		$container->singleton(
			MerchandisingIndexer::class,
			static fn ( Container $c ): MerchandisingIndexer => new MerchandisingIndexer(
				$c->get( ProductStatsRepository::class ),
				$c->get( AffinityRepository::class ),
				$c->get( LoggerInterface::class )
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
			$this->hook( $container, ProductViewTracker::class );
		}
	}
}
