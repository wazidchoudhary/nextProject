<?php
/**
 * Core service provider.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Contracts\ContainerInterface;
use BoneHornCrafts\Core\Contracts\LoggerInterface;
use BoneHornCrafts\Core\Logging\Logger;
use BoneHornCrafts\Core\Support\Options;
use BoneHornCrafts\Core\Support\Template;

/**
 * Binds the primitives every other module depends on.
 */
final class CoreServiceProvider extends AbstractServiceProvider {

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerInterface $container Container instance.
	 */
	public function register( ContainerInterface $container ): void {
		/** @var Container $container */
		$container->singleton(
			Logger::class,
			static fn (): Logger => new Logger( 'bhc-core' )
		);

		$container->alias( LoggerInterface::class, Logger::class );

		$container->singleton(
			Options::class,
			static fn (): Options => new Options()
		);

		$container->singleton(
			Template::class,
			static fn ( Container $c ): Template => new Template(
				$c->get( Plugin::class )->dir() . 'templates/',
				'bone-horn-crafts'
			)
		);
	}
}
