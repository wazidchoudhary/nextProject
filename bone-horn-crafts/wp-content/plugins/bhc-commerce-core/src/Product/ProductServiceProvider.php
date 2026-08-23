<?php
/**
 * Product module wiring.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Product;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\AbstractServiceProvider;
use BoneHornCrafts\Core\Analytics\ProductStatsRepository;
use BoneHornCrafts\Core\Cache\CacheManager;
use BoneHornCrafts\Core\Container;
use BoneHornCrafts\Core\Contracts\ContainerInterface;
use BoneHornCrafts\Core\Contracts\LoggerInterface;
use BoneHornCrafts\Core\Product\Admin\ProductDataPanel;
use BoneHornCrafts\Core\Product\Admin\QuickImageColumn;
use BoneHornCrafts\Core\Product\Attributes\AttributeRegistrar;
use BoneHornCrafts\Core\Product\Badges\BadgeRegistry;
use BoneHornCrafts\Core\Product\Badges\BadgeRenderer;
use BoneHornCrafts\Core\Product\Badges\BadgeResolver;
use BoneHornCrafts\Core\Product\RecentlyViewed\RecentlyViewedService;
use BoneHornCrafts\Core\Security\SignedCookie;
use BoneHornCrafts\Core\Support\Context;
use BoneHornCrafts\Core\Support\Options;

/**
 * Registers catalogue services: attributes, badges, meta and read models.
 */
final class ProductServiceProvider extends AbstractServiceProvider {

	/**
	 * Request context, captured during `should_load()`.
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
		$container->singleton( BadgeRegistry::class, static fn (): BadgeRegistry => new BadgeRegistry() );

		$container->singleton(
			ProductStatsRepository::class,
			static fn (): ProductStatsRepository => new ProductStatsRepository()
		);

		$container->singleton(
			BadgeResolver::class,
			static fn ( Container $c ): BadgeResolver => new BadgeResolver(
				$c->get( BadgeRegistry::class ),
				$c->get( Options::class ),
				$c->get( ProductStatsRepository::class ),
				$c->get( CacheManager::class )
			)
		);

		$container->singleton(
			BadgeRenderer::class,
			static fn ( Container $c ): BadgeRenderer => new BadgeRenderer( $c->get( BadgeResolver::class ) )
		);

		$container->singleton( ProductQuery::class, static fn (): ProductQuery => new ProductQuery() );

		$container->singleton(
			ProductRepository::class,
			static fn ( Container $c ): ProductRepository => new ProductRepository(
				$c->get( ProductQuery::class ),
				$c->get( CacheManager::class ),
				$c->get( ProductStatsRepository::class )
			)
		);

		$container->singleton(
			AttributeRegistrar::class,
			static fn ( Container $c ): AttributeRegistrar => new AttributeRegistrar( $c->get( LoggerInterface::class ) )
		);

		$container->singleton(
			RecentlyViewedService::class,
			static fn ( Container $c ): RecentlyViewedService => new RecentlyViewedService(
				$c->get( SignedCookie::class ),
				$c->get( Options::class )
			)
		);

		$container->singleton(
			ProductDataPanel::class,
			static fn ( Container $c ): ProductDataPanel => new ProductDataPanel( $c->get( BadgeRegistry::class ) )
		);

		$container->singleton(
			QuickImageColumn::class,
			static fn ( Container $c ): QuickImageColumn => new QuickImageColumn(
				$c->get( CacheManager::class ),
				$c->get( LoggerInterface::class )
			)
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerInterface $container Container instance.
	 */
	public function boot( ContainerInterface $container ): void {
		$context = $this->context ?? new Context();

		// Attribute creation is an install-time concern, never a request-time one.
		add_action(
			'bhc_schema_installed',
			static function () use ( $container ): void {
				$container->get( AttributeRegistrar::class )->install();
			}
		);

		// The list-table image editor answers an admin-ajax request, and
		// Context::is_admin() is deliberately false there — it excludes ajax so
		// that screen-only services do not load on every add-to-cart. So this
		// has to be registered on both paths: hooking it behind is_admin()
		// alone meant the wp_ajax_ action never existed on the request that
		// needed it, and admin-ajax answered with a bare "0".
		if ( $context->is_admin() || $context->is_ajax() ) {
			$this->hook( $container, QuickImageColumn::class );
		}

		if ( $context->is_admin() ) {
			$this->hook( $container, ProductDataPanel::class );

			return;
		}

		if ( $context->is_frontend() ) {
			$this->hook( $container, BadgeRenderer::class, RecentlyViewedService::class );
		}
	}
}
