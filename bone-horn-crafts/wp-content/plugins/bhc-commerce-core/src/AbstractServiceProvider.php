<?php
/**
 * Base service provider.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Contracts\ContainerInterface;
use BoneHornCrafts\Core\Contracts\HookableInterface;
use BoneHornCrafts\Core\Contracts\ServiceProviderInterface;
use BoneHornCrafts\Core\Support\Context;

/**
 * Convenience base class for providers.
 */
abstract class AbstractServiceProvider implements ServiceProviderInterface {

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerInterface $container Container instance.
	 */
	public function register( ContainerInterface $container ): void {}

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerInterface $container Container instance.
	 */
	public function boot( ContainerInterface $container ): void {}

	/**
	 * {@inheritDoc}
	 *
	 * @param Context $context Request context.
	 */
	public function should_load( Context $context ): bool {
		return true;
	}

	/**
	 * Resolves a service and registers its hooks.
	 *
	 * @param ContainerInterface $container Container instance.
	 * @param class-string       ...$ids    Hookable service ids.
	 */
	final protected function hook( ContainerInterface $container, string ...$ids ): void {
		foreach ( $ids as $id ) {
			$service = $container->get( $id );

			if ( $service instanceof HookableInterface ) {
				$service->register_hooks();
			}
		}
	}
}
