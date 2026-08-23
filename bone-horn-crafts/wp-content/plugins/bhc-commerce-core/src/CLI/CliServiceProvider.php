<?php
/**
 * WP-CLI wiring.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\CLI;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\AbstractServiceProvider;
use BoneHornCrafts\Core\Admin\HealthReport;
use BoneHornCrafts\Core\Analytics\MerchandisingIndexer;
use BoneHornCrafts\Core\Cache\CacheManager;
use BoneHornCrafts\Core\Import\FirebaseImporter;
use BoneHornCrafts\Core\Container;
use BoneHornCrafts\Core\Contracts\ContainerInterface;
use BoneHornCrafts\Core\Demo\DemoSeeder;
use BoneHornCrafts\Core\Demo\DemoState;
use BoneHornCrafts\Core\Jobs\CacheWarmJob;
use BoneHornCrafts\Core\Jobs\MerchandisingIndexJob;
use BoneHornCrafts\Core\Product\Attributes\AttributeRegistrar;
use BoneHornCrafts\Core\Product\ProductRepository;
use BoneHornCrafts\Core\Support\Context;

/**
 * Registers the WP-CLI commands, CLI context only.
 */
final class CliServiceProvider extends AbstractServiceProvider {

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
		$container->singleton(
			ProductsCommand::class,
			static fn ( Container $c ): ProductsCommand => new ProductsCommand(
				$c->get( MerchandisingIndexJob::class ),
				$c->get( MerchandisingIndexer::class ),
				$c->get( AttributeRegistrar::class ),
				$c->get( ProductRepository::class ),
				$c->get( CacheManager::class )
			)
		);

		$container->singleton(
			CacheCommand::class,
			static fn ( Container $c ): CacheCommand => new CacheCommand(
				$c->get( CacheWarmJob::class ),
				$c->get( CacheManager::class )
			)
		);

		$container->singleton(
			FirebaseImporter::class,
			static fn (): FirebaseImporter => new FirebaseImporter()
		);

		$container->singleton(
			ImportCommand::class,
			static fn ( Container $c ): ImportCommand => new ImportCommand(
				$c->get( FirebaseImporter::class ),
				$c->get( CacheManager::class )
			)
		);

		$container->singleton(
			HealthCommand::class,
			static fn ( Container $c ): HealthCommand => new HealthCommand( $c->get( HealthReport::class ) )
		);

		$container->singleton(
			DemoCommand::class,
			static fn ( Container $c ): DemoCommand => new DemoCommand(
				$c->get( DemoSeeder::class ),
				$c->get( DemoState::class ),
				$c->get( MerchandisingIndexJob::class ),
				$c->get( CacheManager::class )
			)
		);

		$container->singleton(
			CommandRegistrar::class,
			static fn ( Container $c ): CommandRegistrar => new CommandRegistrar( $c )
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerInterface $container Container instance.
	 */
	public function boot( ContainerInterface $container ): void {
		$container->get( CommandRegistrar::class )->register();
	}
}
