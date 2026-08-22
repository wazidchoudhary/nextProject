<?php
/**
 * Order module wiring.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Order;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\AbstractServiceProvider;
use BoneHornCrafts\Core\Cache\CacheManager;
use BoneHornCrafts\Core\Container;
use BoneHornCrafts\Core\Contracts\ContainerInterface;
use BoneHornCrafts\Core\Contracts\LoggerInterface;
use BoneHornCrafts\Core\Order\Admin\OrderOperationsMetaBox;
use BoneHornCrafts\Core\Support\Context;

/**
 * Registers order services.
 */
final class OrderServiceProvider extends AbstractServiceProvider {

	/**
	 * Request context.
	 *
	 * @var Context|null
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
			OrderRepository::class,
			static fn ( Container $c ): OrderRepository => new OrderRepository( $c->get( CacheManager::class ) )
		);

		$container->singleton(
			OrderOperationsService::class,
			static fn ( Container $c ): OrderOperationsService => new OrderOperationsService( $c->get( LoggerInterface::class ) )
		);

		$container->singleton(
			OrderOperationsMetaBox::class,
			static fn (): OrderOperationsMetaBox => new OrderOperationsMetaBox()
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerInterface $container Container instance.
	 */
	public function boot( ContainerInterface $container ): void {
		$this->hook( $container, OrderOperationsService::class );

		$context = $this->context ?? new Context();

		if ( $context->is_admin() ) {
			$this->hook( $container, OrderOperationsMetaBox::class );
		}
	}
}
