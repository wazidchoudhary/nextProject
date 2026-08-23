<?php
/**
 * Performance module wiring.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Performance;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\AbstractServiceProvider;
use BoneHornCrafts\Core\Container;
use BoneHornCrafts\Core\Contracts\ContainerInterface;

/**
 * Registers the query-reduction passes.
 */
final class PerformanceServiceProvider extends AbstractServiceProvider {

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerInterface $container Container instance.
	 */
	public function register( ContainerInterface $container ): void {
		/** @var Container $container */
		$container->singleton( OptionAutoloader::class, static fn (): OptionAutoloader => new OptionAutoloader() );
		$container->singleton( PagePrimer::class, static fn (): PagePrimer => new PagePrimer() );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerInterface $container Container instance.
	 */
	public function boot( ContainerInterface $container ): void {
		$this->hook( $container, PagePrimer::class );

		add_action(
			'bhc_schema_installed',
			static function () use ( $container ): void {
				$container->get( OptionAutoloader::class )->apply_once();
			}
		);
	}
}
