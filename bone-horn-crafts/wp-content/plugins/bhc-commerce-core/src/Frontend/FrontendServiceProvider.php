<?php
/**
 * Frontend module wiring.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Frontend;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\AbstractServiceProvider;
use BoneHornCrafts\Core\Container;
use BoneHornCrafts\Core\Contracts\ContainerInterface;
use BoneHornCrafts\Core\Plugin;
use BoneHornCrafts\Core\Product\ProductRepository;
use BoneHornCrafts\Core\Support\Context;
use BoneHornCrafts\Core\Support\Options;
use BoneHornCrafts\Core\Support\Template;
use BoneHornCrafts\Core\Wishlist\WishlistService;

/**
 * Registers storefront presentation glue.
 */
final class FrontendServiceProvider extends AbstractServiceProvider {

	/**
	 * {@inheritDoc}
	 *
	 * @param Context $context Request context.
	 */
	public function should_load( Context $context ): bool {
		return $context->is_frontend() || $context->is_ajax();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerInterface $container Container instance.
	 */
	public function register( ContainerInterface $container ): void {
		/** @var Container $container */
		$container->singleton(
			Assets::class,
			static fn ( Container $c ): Assets => new Assets(
				$c->get( Plugin::class ),
				$c->get( WishlistService::class ),
				$c->get( Options::class )
			)
		);

		$container->singleton(
			Shortcodes::class,
			static fn ( Container $c ): Shortcodes => new Shortcodes(
				$c->get( ProductRepository::class ),
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
		$this->hook( $container, Assets::class, Shortcodes::class );
	}
}
