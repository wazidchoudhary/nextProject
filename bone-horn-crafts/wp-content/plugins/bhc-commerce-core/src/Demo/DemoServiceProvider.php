<?php
/**
 * Demo module wiring.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Demo;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\AbstractServiceProvider;
use BoneHornCrafts\Core\Container;
use BoneHornCrafts\Core\Contracts\ContainerInterface;
use BoneHornCrafts\Core\Contracts\LoggerInterface;
use BoneHornCrafts\Core\Product\Attributes\AttributeRegistrar;
use BoneHornCrafts\Core\Support\Context;

/**
 * Registers the demo seeder.
 *
 * CLI only: the seeder is never reachable from a web request, so there is no
 * route by which a visitor — or a compromised admin session — can trigger a
 * catalogue rebuild.
 */
final class DemoServiceProvider extends AbstractServiceProvider {

	/**
	 * {@inheritDoc}
	 *
	 * @param Context $context Request context.
	 */
	public function should_load( Context $context ): bool {
		return $context->is_cli();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerInterface $container Container instance.
	 */
	public function register( ContainerInterface $container ): void {
		/** @var Container $container */
		$container->singleton( DemoState::class, static fn (): DemoState => new DemoState() );

		$container->singleton(
			ImageFactory::class,
			static fn ( Container $c ): ImageFactory => new ImageFactory( $c->get( LoggerInterface::class ) )
		);

		$container->singleton(
			DemoSeeder::class,
			static fn ( Container $c ): DemoSeeder => new DemoSeeder(
				$c->get( DemoState::class ),
				$c->get( ImageFactory::class ),
				$c->get( AttributeRegistrar::class ),
				$c->get( LoggerInterface::class )
			)
		);
	}
}
