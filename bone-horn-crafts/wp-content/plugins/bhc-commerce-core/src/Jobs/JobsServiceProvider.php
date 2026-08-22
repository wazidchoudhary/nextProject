<?php
/**
 * Background job wiring.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Jobs;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\AbstractServiceProvider;
use BoneHornCrafts\Core\Analytics\MerchandisingIndexer;
use BoneHornCrafts\Core\Analytics\ProductStatsRepository;
use BoneHornCrafts\Core\Analytics\ProductViewTracker;
use BoneHornCrafts\Core\Cache\CacheManager;
use BoneHornCrafts\Core\Container;
use BoneHornCrafts\Core\Contracts\ContainerInterface;
use BoneHornCrafts\Core\Contracts\LoggerInterface;
use BoneHornCrafts\Core\Product\ProductRepository;
use BoneHornCrafts\Core\Search\FacetRepository;
use BoneHornCrafts\Core\Support\Options;
use BoneHornCrafts\Core\Wishlist\WishlistRepository;

/**
 * Registers the batch jobs and the scheduler.
 */
final class JobsServiceProvider extends AbstractServiceProvider {

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerInterface $container Container instance.
	 */
	public function register( ContainerInterface $container ): void {
		/** @var Container $container */
		$container->singleton(
			MerchandisingIndexJob::class,
			static fn ( Container $c ): MerchandisingIndexJob => new MerchandisingIndexJob(
				$c->get( MerchandisingIndexer::class ),
				$c->get( ProductRepository::class ),
				$c->get( CacheManager::class ),
				$c->get( Options::class ),
				$c->get( LoggerInterface::class )
			)
		);

		$container->singleton(
			ViewBufferFlushJob::class,
			static fn ( Container $c ): ViewBufferFlushJob => new ViewBufferFlushJob(
				$c->get( ProductViewTracker::class ),
				$c->get( ProductStatsRepository::class ),
				$c->get( LoggerInterface::class )
			)
		);

		$container->singleton(
			WishlistPruneJob::class,
			static fn ( Container $c ): WishlistPruneJob => new WishlistPruneJob(
				$c->get( WishlistRepository::class ),
				$c->get( LoggerInterface::class )
			)
		);

		$container->singleton(
			CacheWarmJob::class,
			static fn ( Container $c ): CacheWarmJob => new CacheWarmJob(
				$c->get( ProductRepository::class ),
				$c->get( FacetRepository::class ),
				$c->get( LoggerInterface::class )
			)
		);

		$container->singleton(
			Scheduler::class,
			static fn ( Container $c ): Scheduler => new Scheduler(
				[
					MerchandisingIndexJob::HOOK => $c->get( MerchandisingIndexJob::class ),
					ViewBufferFlushJob::HOOK    => $c->get( ViewBufferFlushJob::class ),
					WishlistPruneJob::HOOK      => $c->get( WishlistPruneJob::class ),
					CacheWarmJob::HOOK          => $c->get( CacheWarmJob::class ),
				],
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
		$this->hook( $container, Scheduler::class );
	}
}
