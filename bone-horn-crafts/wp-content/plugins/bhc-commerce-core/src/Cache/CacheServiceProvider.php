<?php
/**
 * Cache service provider.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Cache;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\AbstractServiceProvider;
use BoneHornCrafts\Core\Container;
use BoneHornCrafts\Core\Contracts\CacheInterface;
use BoneHornCrafts\Core\Contracts\ContainerInterface;
use BoneHornCrafts\Core\Contracts\LoggerInterface;

/**
 * Chooses a cache backend and exposes it to the rest of the plugin.
 */
final class CacheServiceProvider extends AbstractServiceProvider {

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerInterface $container Container instance.
	 */
	public function register( ContainerInterface $container ): void {
		/** @var Container $container */
		$container->singleton( RedisStatus::class, static fn (): RedisStatus => new RedisStatus() );

		$container->singleton(
			StoreInterface::class,
			static function ( Container $c ): StoreInterface {
				/**
				 * Filters the cache store implementation.
				 *
				 * Hosting environments with an exotic cache can inject their
				 * own store without touching the plugin.
				 *
				 * @since 1.0.0
				 *
				 * @param StoreInterface|null $store Custom store, or null to auto-detect.
				 */
				$custom = apply_filters( 'bhc_cache_store', null );

				if ( $custom instanceof StoreInterface ) {
					return $custom;
				}

				$redis = $c->get( RedisStatus::class );

				// A persistent object cache (Redis/Memcached) is always preferred.
				// Without one, `wp_cache_*` is request scoped, so transients give
				// us real cross-request caching instead.
				return $redis->has_persistent_object_cache()
					? new ObjectCacheStore( 'bhc_core' )
					: new TransientStore();
			}
		);

		$container->singleton(
			CacheManager::class,
			static fn ( Container $c ): CacheManager => new CacheManager(
				$c->get( StoreInterface::class ),
				BHC_CORE_VERSION,
				'general',
				HOUR_IN_SECONDS
			)
		);

		$container->alias( CacheInterface::class, CacheManager::class );

		$container->singleton(
			Invalidator::class,
			static fn ( Container $c ): Invalidator => new Invalidator(
				$c->get( CacheManager::class ),
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
		$this->hook( $container, Invalidator::class );
	}
}
