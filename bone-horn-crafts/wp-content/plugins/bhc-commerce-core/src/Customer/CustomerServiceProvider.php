<?php
/**
 * Customer module wiring.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Customer;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\AbstractServiceProvider;
use BoneHornCrafts\Core\Container;
use BoneHornCrafts\Core\Contracts\ContainerInterface;
use BoneHornCrafts\Core\Support\Context;
use BoneHornCrafts\Core\Wishlist\WishlistRenderer;

/**
 * Registers customer facing account features.
 */
final class CustomerServiceProvider extends AbstractServiceProvider {

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
		$container->singleton( WholesaleService::class, static fn (): WholesaleService => new WholesaleService() );

		$container->singleton(
			AccountEndpoints::class,
			static fn ( Container $c ): AccountEndpoints => new AccountEndpoints( $c->get( WishlistRenderer::class ) )
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerInterface $container Container instance.
	 */
	public function boot( ContainerInterface $container ): void {
		$this->hook( $container, WholesaleService::class );

		$context = $this->context ?? new Context();

		if ( ! $context->is_admin() ) {
			$this->hook( $container, AccountEndpoints::class );
		}
	}
}
