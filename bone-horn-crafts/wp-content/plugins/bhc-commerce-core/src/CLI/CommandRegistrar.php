<?php
/**
 * WP-CLI command registration.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\CLI;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Container;
use WP_CLI;

/**
 * Registers the `wp bhc` command family.
 *
 * Each subcommand is registered explicitly as a closure that resolves its
 * command object from the container. WP-CLI builds composite commands by
 * reflecting a *class name*, which would force the command classes to have
 * no-argument constructors and pull their own dependencies — the exact service
 * locator pattern the rest of the plugin avoids. Registering leaves explicitly
 * costs a few lines here and keeps constructor injection everywhere else.
 */
final class CommandRegistrar {

	/**
	 * Constructor.
	 *
	 * @param Container $container Service container.
	 */
	public function __construct( private Container $container ) {}

	/**
	 * Registers every command.
	 */
	public function register(): void {
		if ( ! class_exists( WP_CLI::class ) ) {
			return;
		}

		$this->register_product_commands();
		$this->register_cache_commands();
		$this->register_health_command();
		$this->register_demo_commands();
	}

	/**
	 * `wp bhc products sync`.
	 */
	private function register_product_commands(): void {
		$container = $this->container;

		WP_CLI::add_command(
			'bhc products sync',
			static function ( array $args, array $assoc_args ) use ( $container ): void {
				$container->get( ProductsCommand::class )->sync( $args, $assoc_args );
			},
			[
				'shortdesc' => 'Rebuild the merchandising indexes and craft attributes.',
				'longdesc'  => "## EXAMPLES\n\n    wp bhc products sync\n    wp bhc products sync --job=affinity --batch=100\n    wp bhc products sync --async",
				'synopsis'  => [
					[
						'type'        => 'assoc',
						'name'        => 'job',
						'description' => 'Which index to rebuild.',
						'optional'    => true,
						'options'     => [ 'all', 'stats', 'affinity', 'ranks' ],
						'default'     => 'all',
					],
					[
						'type'        => 'assoc',
						'name'        => 'batch',
						'description' => 'Products handled per batch.',
						'optional'    => true,
						'default'     => '40',
					],
					[
						'type'        => 'flag',
						'name'        => 'attributes',
						'description' => 'Also create any missing craft attributes and terms.',
						'optional'    => true,
					],
					[
						'type'        => 'flag',
						'name'        => 'async',
						'description' => 'Queue the rebuild through Action Scheduler instead of running it now.',
						'optional'    => true,
					],
				],
			]
		);
	}

	/**
	 * `wp bhc cache warm|flush|status`.
	 */
	private function register_cache_commands(): void {
		$container = $this->container;

		WP_CLI::add_command(
			'bhc cache warm',
			static function ( array $args, array $assoc_args ) use ( $container ): void {
				$container->get( CacheCommand::class )->warm( $args, $assoc_args );
			},
			[
				'shortdesc' => 'Warm the caches the storefront reads first.',
				'longdesc'  => "## EXAMPLES\n\n    wp bhc cache warm",
			]
		);

		WP_CLI::add_command(
			'bhc cache flush',
			static function ( array $args, array $assoc_args ) use ( $container ): void {
				$container->get( CacheCommand::class )->flush( $args, $assoc_args );
			},
			[
				'shortdesc' => 'Flush one or all plugin cache groups.',
				'longdesc'  => "## EXAMPLES\n\n    wp bhc cache flush\n    wp bhc cache flush --group=recommendations",
				'synopsis'  => [
					[
						'type'        => 'assoc',
						'name'        => 'group',
						'description' => 'Cache group to flush.',
						'optional'    => true,
						'options'     => [ 'all', 'products', 'recommendations', 'search', 'facets', 'stats', 'seo' ],
						'default'     => 'all',
					],
				],
			]
		);

		WP_CLI::add_command(
			'bhc cache status',
			static function ( array $args, array $assoc_args ) use ( $container ): void {
				$container->get( CacheCommand::class )->status( $args, $assoc_args );
			},
			[ 'shortdesc' => 'Show which cache backend is active.' ]
		);
	}

	/**
	 * `wp bhc health-check`.
	 */
	private function register_health_command(): void {
		$container = $this->container;

		WP_CLI::add_command(
			'bhc health-check',
			static function ( array $args, array $assoc_args ) use ( $container ): void {
				$container->get( HealthCommand::class )( $args, $assoc_args );
			},
			[
				'shortdesc' => 'Report environment and background job health.',
				'longdesc'  => "Exits non-zero when a check fails, so it can gate a deployment.\n\n## EXAMPLES\n\n    wp bhc health-check\n    wp bhc health-check --format=json",
				'synopsis'  => [
					[
						'type'        => 'assoc',
						'name'        => 'format',
						'description' => 'Output format.',
						'optional'    => true,
						'options'     => [ 'table', 'json' ],
						'default'     => 'table',
					],
					[
						'type'        => 'flag',
						'name'        => 'strict',
						'description' => 'Fail on warnings as well as failures.',
						'optional'    => true,
					],
				],
			]
		);
	}

	/**
	 * `wp bhc demo seed|reset|status`.
	 */
	private function register_demo_commands(): void {
		$container = $this->container;

		WP_CLI::add_command(
			'bhc demo seed',
			static function ( array $args, array $assoc_args ) use ( $container ): void {
				$container->get( DemoCommand::class )->seed( $args, $assoc_args );
			},
			[
				'shortdesc' => 'Generate the fictional demo catalogue, customers, orders and content.',
				'longdesc'  => "Safe to run repeatedly: products are matched by SKU, pages by slug and terms by slug.\n\n## EXAMPLES\n\n    wp bhc demo seed\n    wp bhc demo seed --products=12 --orders=6 --skip-images",
				'synopsis'  => [
					[
						'type'        => 'assoc',
						'name'        => 'products',
						'description' => 'Limit how many catalogue rows are created.',
						'optional'    => true,
					],
					[
						'type'        => 'assoc',
						'name'        => 'orders',
						'description' => 'How many demo orders to create.',
						'optional'    => true,
						'default'     => '24',
					],
					[
						'type'        => 'flag',
						'name'        => 'skip-images',
						'description' => 'Skip image rendering.',
						'optional'    => true,
					],
					[
						'type'        => 'flag',
						'name'        => 'skip-content',
						'description' => 'Skip pages, journal articles and menus.',
						'optional'    => true,
					],
					[
						'type'        => 'flag',
						'name'        => 'skip-index',
						'description' => 'Skip the merchandising index rebuild.',
						'optional'    => true,
					],
				],
			]
		);

		WP_CLI::add_command(
			'bhc demo reset',
			static function ( array $args, array $assoc_args ) use ( $container ): void {
				$container->get( DemoCommand::class )->reset( $args, $assoc_args );
			},
			[
				'shortdesc' => 'Remove everything the demo seeder created. Requires confirmation.',
				'longdesc'  => "Only objects recorded in the demo state option are deleted, and each one is re-checked for its demo marker first.\n\n## EXAMPLES\n\n    wp bhc demo reset\n    wp bhc demo reset --yes",
				'synopsis'  => [
					[
						'type'        => 'flag',
						'name'        => 'yes',
						'description' => 'Skip the confirmation prompt.',
						'optional'    => true,
					],
					[
						'type'        => 'flag',
						'name'        => 'orphans',
						'description' => 'Also remove demo-marked objects that are no longer tracked.',
						'optional'    => true,
					],
				],
			]
		);

		WP_CLI::add_command(
			'bhc demo status',
			static function ( array $args, array $assoc_args ) use ( $container ): void {
				$container->get( DemoCommand::class )->status( $args, $assoc_args );
			},
			[ 'shortdesc' => 'Show what the demo seeder currently owns.' ]
		);
	}
}
