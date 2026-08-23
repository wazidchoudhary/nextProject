<?php
/**
 * Search module wiring.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Search;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\AbstractServiceProvider;
use BoneHornCrafts\Core\Cache\CacheManager;
use BoneHornCrafts\Core\Container;
use BoneHornCrafts\Core\Contracts\ContainerInterface;
use BoneHornCrafts\Core\Product\ProductRepository;
use BoneHornCrafts\Core\Support\Context;
use BoneHornCrafts\Core\Support\Template;

/**
 * Registers catalogue search and faceted filtering.
 */
final class SearchServiceProvider extends AbstractServiceProvider {

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
		$container->singleton( RequestParser::class, static fn (): RequestParser => new RequestParser() );

		$container->singleton(
			FacetRepository::class,
			static fn ( Container $c ): FacetRepository => new FacetRepository( $c->get( CacheManager::class ) )
		);

		$container->singleton(
			SearchService::class,
			static fn ( Container $c ): SearchService => new SearchService(
				$c->get( ProductRepository::class ),
				$c->get( FacetRepository::class ),
				$c->get( CacheManager::class ),
				$c->get( RequestParser::class )
			)
		);

		$container->singleton(
			FilterPanelRenderer::class,
			static fn ( Container $c ): FilterPanelRenderer => new FilterPanelRenderer(
				$c->get( SearchService::class ),
				$c->get( Template::class ),
				$c->get( RequestParser::class )
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

		if ( $context->is_frontend() ) {
			$this->hook( $container, SearchService::class, FilterPanelRenderer::class );
		}
	}
}
