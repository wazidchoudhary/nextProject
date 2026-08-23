<?php
/**
 * Store content module wiring.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Content;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\AbstractServiceProvider;
use BoneHornCrafts\Core\Container;
use BoneHornCrafts\Core\Contracts\ContainerInterface;
use BoneHornCrafts\Core\Store\BusinessDetails;
use BoneHornCrafts\Core\Store\PlaceholderContactRepair;
use BoneHornCrafts\Core\Support\Options;

/**
 * Registers the business details and the policy pages built from them.
 */
final class ContentServiceProvider extends AbstractServiceProvider {

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerInterface $container Container instance.
	 */
	public function register( ContainerInterface $container ): void {
		/** @var Container $container */
		$container->singleton(
			BusinessDetails::class,
			static fn ( Container $c ): BusinessDetails => new BusinessDetails( $c->get( Options::class ) )
		);

		$container->singleton(
			PlaceholderContactRepair::class,
			static fn ( Container $c ): PlaceholderContactRepair => new PlaceholderContactRepair( $c->get( Options::class ) )
		);

		$container->singleton(
			PolicyPageContent::class,
			static fn ( Container $c ): PolicyPageContent => new PolicyPageContent( $c->get( BusinessDetails::class ) )
		);

		$container->singleton(
			PolicyPageInstaller::class,
			static fn ( Container $c ): PolicyPageInstaller => new PolicyPageInstaller( $c->get( PolicyPageContent::class ) )
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerInterface $container Container instance.
	 */
	public function boot( ContainerInterface $container ): void {
		// Policy pages are store content, not sample data — the same reasoning
		// that moved customer registration out of the demo seeder. A store that
		// imported a real catalogue and never seeded had no privacy policy and
		// an empty footer legal menu.
		add_action(
			'bhc_schema_installed',
			static function () use ( $container ): void {
				// Order matters: the policy pages embed the phone and email, so
				// the placeholders have to be corrected before the pages that
				// quote them are written.
				$container->get( PlaceholderContactRepair::class )->apply();
				$container->get( PolicyPageInstaller::class )->install_once();
			}
		);
	}
}
