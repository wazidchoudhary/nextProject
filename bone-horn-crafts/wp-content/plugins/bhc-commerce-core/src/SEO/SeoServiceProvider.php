<?php
/**
 * SEO module wiring.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\SEO;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\AbstractServiceProvider;
use BoneHornCrafts\Core\Container;
use BoneHornCrafts\Core\Contracts\ContainerInterface;
use BoneHornCrafts\Core\Pricing\PriceFormatter;
use BoneHornCrafts\Core\SEO\Schema\ArticleSchema;
use BoneHornCrafts\Core\SEO\Schema\BreadcrumbListSchema;
use BoneHornCrafts\Core\SEO\Schema\OrganizationSchema;
use BoneHornCrafts\Core\SEO\Schema\ProductSchema;
use BoneHornCrafts\Core\SEO\Schema\SchemaGraph;
use BoneHornCrafts\Core\SEO\Schema\WebSiteSchema;
use BoneHornCrafts\Core\Support\Context;
use BoneHornCrafts\Core\Support\Options;

/**
 * Registers metadata, schema, robots policy and sitemap integration.
 */
final class SeoServiceProvider extends AbstractServiceProvider {

	/**
	 * Request context.
	 */
	private ?Context $context = null;

	/**
	 * {@inheritDoc}
	 *
	 * @param Context $context Request context.
	 */
	public function should_load( Context $context ): bool {
		$this->context = $context;

		// Sitemap requests are served through the REST-ish rewrite, not a
		// normal template, so REST context must stay enabled here.
		return ! $context->is_admin() && ! $context->is_cli();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerInterface $container Container instance.
	 */
	public function register( ContainerInterface $container ): void {
		/** @var Container $container */
		$container->singleton(
			BrandProfile::class,
			static fn ( Container $c ): BrandProfile => new BrandProfile( $c->get( Options::class ) )
		);

		$container->singleton(
			BreadcrumbService::class,
			static fn ( Container $c ): BreadcrumbService => new BreadcrumbService( $c->get( BrandProfile::class ) )
		);

		$container->singleton(
			MetaTagService::class,
			static fn ( Container $c ): MetaTagService => new MetaTagService( $c->get( BrandProfile::class ) )
		);

		$container->singleton( RobotsPolicy::class, static fn (): RobotsPolicy => new RobotsPolicy() );
		$container->singleton( SitemapIntegration::class, static fn (): SitemapIntegration => new SitemapIntegration() );

		$container->singleton(
			OrganizationSchema::class,
			static fn ( Container $c ): OrganizationSchema => new OrganizationSchema( $c->get( BrandProfile::class ) )
		);

		$container->singleton(
			SchemaGraph::class,
			static function ( Container $c ): SchemaGraph {
				$brand        = $c->get( BrandProfile::class );
				$organization = $c->get( OrganizationSchema::class );

				/**
				 * Filters the JSON-LD graph pieces.
				 *
				 * @since 1.0.0
				 *
				 * @param \BoneHornCrafts\Core\SEO\Schema\SchemaPieceInterface[] $pieces Graph pieces.
				 */
				$pieces = (array) apply_filters(
					'bhc_schema_pieces',
					[
						$organization,
						new WebSiteSchema( $brand, $organization ),
						new BreadcrumbListSchema( $c->get( BreadcrumbService::class ), $brand ),
						new ProductSchema( $brand, $c->get( PriceFormatter::class ), $organization ),
						new ArticleSchema( $brand, $organization ),
					]
				);

				return new SchemaGraph( $pieces, $c->get( Options::class ) );
			}
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerInterface $container Container instance.
	 */
	public function boot( ContainerInterface $container ): void {
		$this->hook( $container, SitemapIntegration::class, RobotsPolicy::class );

		$context = $this->context ?? new Context();

		if ( $context->is_frontend() ) {
			$this->hook( $container, MetaTagService::class, SchemaGraph::class );
		}
	}
}
