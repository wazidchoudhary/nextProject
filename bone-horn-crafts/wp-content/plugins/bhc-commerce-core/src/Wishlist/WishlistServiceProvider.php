<?php
/**
 * Wishlist module wiring.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Wishlist;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\AbstractServiceProvider;
use BoneHornCrafts\Core\Container;
use BoneHornCrafts\Core\Contracts\ContainerInterface;
use BoneHornCrafts\Core\Contracts\LoggerInterface;
use BoneHornCrafts\Core\Product\ProductRepository;
use BoneHornCrafts\Core\Security\SignedCookie;
use BoneHornCrafts\Core\Support\Context;
use BoneHornCrafts\Core\Support\Options;
use BoneHornCrafts\Core\Support\Template;

/**
 * Registers wishlist services.
 */
final class WishlistServiceProvider extends AbstractServiceProvider {

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
		$container->singleton( WishlistRepository::class, static fn (): WishlistRepository => new WishlistRepository() );

		$container->singleton(
			WishlistService::class,
			static fn ( Container $c ): WishlistService => new WishlistService(
				$c->get( WishlistRepository::class ),
				$c->get( SignedCookie::class ),
				$c->get( ProductRepository::class ),
				$c->get( Options::class ),
				$c->get( LoggerInterface::class )
			)
		);

		$container->singleton(
			WishlistRenderer::class,
			static fn ( Container $c ): WishlistRenderer => new WishlistRenderer(
				$c->get( WishlistService::class ),
				$c->get( Template::class )
			)
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerInterface $container Container instance.
	 */
	public function boot( ContainerInterface $container ): void {
		$this->hook( $container, WishlistService::class );

		$context = $this->context ?? new Context();

		if ( $context->is_frontend() || $context->is_ajax() ) {
			$this->hook( $container, WishlistRenderer::class );
		}
	}
}
