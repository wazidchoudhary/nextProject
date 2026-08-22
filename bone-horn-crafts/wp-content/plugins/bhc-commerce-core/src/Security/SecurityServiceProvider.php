<?php
/**
 * Security service provider.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Security;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\AbstractServiceProvider;
use BoneHornCrafts\Core\Cache\CacheManager;
use BoneHornCrafts\Core\Container;
use BoneHornCrafts\Core\Contracts\ContainerInterface;

/**
 * Registers security primitives and installs plugin capabilities.
 */
final class SecurityServiceProvider extends AbstractServiceProvider {

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerInterface $container Container instance.
	 */
	public function register( ContainerInterface $container ): void {
		/** @var Container $container */
		$container->singleton(
			RateLimiter::class,
			static fn ( Container $c ): RateLimiter => new RateLimiter( $c->get( CacheManager::class )->for_group( 'ratelimit' ) )
		);

		$container->singleton(
			RestGuard::class,
			static fn ( Container $c ): RestGuard => new RestGuard( $c->get( RateLimiter::class ) )
		);

		$container->singleton( SignedCookie::class, static fn (): SignedCookie => new SignedCookie() );
		$container->singleton( Headers::class, static fn (): Headers => new Headers() );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerInterface $container Container instance.
	 */
	public function boot( ContainerInterface $container ): void {
		$this->hook( $container, Headers::class );

		add_action( 'bhc_schema_installed', [ Capabilities::class, 'install' ] );
	}
}
