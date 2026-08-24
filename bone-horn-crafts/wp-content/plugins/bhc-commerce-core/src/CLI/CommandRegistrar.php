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
		$this->register_import_commands();
		$this->register_payment_commands();
		$this->register_setup_commands();
	}

	/**
	 * `wp bhc setup accounts` and `wp bhc setup pages`.
	 */
	private function register_setup_commands(): void {
		$container = $this->container;

		$this->register_pages_command();
		$this->register_contact_command();
		$this->register_hero_command();
		$this->register_autoload_command();
		$this->register_live_command();

		WP_CLI::add_command(
			'bhc setup accounts',
			static function ( array $args, array $assoc_args ) use ( $container ): void {
				unset( $args, $assoc_args );

				$setup = $container->get( \BoneHornCrafts\Core\Customer\AccountSetup::class );
				$drift = $setup->drift();

				if ( [] === $drift ) {
					WP_CLI::success( 'Customer accounts are already configured. Nothing to change.' );

					return;
				}

				foreach ( $drift as $option => $values ) {
					WP_CLI::log(
						sprintf(
							'  %-52s %s -> %s',
							$option,
							wp_json_encode( $values['actual'] ),
							wp_json_encode( $values['expected'] )
						)
					);
				}

				$setup->apply();

				WP_CLI::success(
					sprintf( '%d setting(s) applied. Registration is now open at the My Account page and at checkout.', count( $drift ) )
				);
			},
			[
				'shortdesc' => 'Turn on customer registration on My Account and at checkout.',
				'longdesc'  => "Three independent switches control whether people can create an account: WordPress's own users_can_register, WooCommerce's My Account registration setting, and its checkout signup setting. Setting one and testing that page makes the other two look fine, which is why a store can appear to offer accounts and not actually do so.\n\nApplied automatically when the plugin's schema installs. Run this to apply it to a store that predates that, or after deliberately changing one of the settings back.\n\n## EXAMPLES\n\n    wp bhc setup accounts",
			]
		);
	}

	/**
	 * `wp bhc setup live`.
	 */
	private function register_live_command(): void {
		$container = $this->container;

		WP_CLI::add_command(
			'bhc setup live',
			static function ( array $args, array $assoc_args ) use ( $container ): void {
				unset( $args, $assoc_args );

				$visibility = $container->get( \BoneHornCrafts\Core\Store\StoreVisibility::class );

				WP_CLI::log( '  ' . $visibility->describe() );

				if ( ! $visibility->go_live() ) {
					WP_CLI::success( 'The store is already live. Nothing to change.' );

					return;
				}

				WP_CLI::success( 'Coming Soon cleared. Flush caches so the public pages rebuild: wp bhc cache flush' );
			},
			[
				'shortdesc' => "Take the store out of WooCommerce's Coming Soon mode.",
				'longdesc'  => "WooCommerce's onboarding leaves Coming Soon enabled, which hides the store from every logged-out visitor and from search engines while an administrator — being signed in — sees a normal storefront. Nothing in the admin reveals it, so a live store can sit unreachable indefinitely.\n\nThis only ever clears the flags. Turning Coming Soon back on is deliberate and belongs in WooCommerce > Settings > Site visibility, not in a deploy step.\n\n## EXAMPLES\n\n    wp bhc setup live",
			]
		);
	}

	/**
	 * `wp bhc setup autoload`.
	 */
	private function register_autoload_command(): void {
		$container = $this->container;

		WP_CLI::add_command(
			'bhc setup autoload',
			static function ( array $args, array $assoc_args ) use ( $container ): void {
				unset( $args, $assoc_args );

				$autoloader = $container->get( \BoneHornCrafts\Core\Performance\OptionAutoloader::class );

				foreach ( $autoloader->seed_defaults() as $created ) {
					WP_CLI::log( sprintf( '  created  %s', $created ) );
				}

				$pending = $autoloader->pending();

				if ( [] === $pending ) {
					WP_CLI::success( 'Every hot option is already autoloaded. Nothing to change.' );

					return;
				}

				foreach ( $pending as $option ) {
					WP_CLI::log( sprintf( '  %s', $option ) );
				}

				$autoloader->apply();

				WP_CLI::success(
					sprintf(
						'%d option(s) moved into the autoload set — %d fewer queries on every front-end request.',
						count( $pending ),
						count( $pending )
					)
				);
			},
			[
				'shortdesc' => 'Autoload the options every front-end request reads.',
				'longdesc'  => "WordPress fetches all autoloaded options in one query at bootstrap. An option outside that set costs its own SELECT the first time it is read, and a cold home-page render was measured issuing 21 of them — HPOS sync flags, WooCommerce feature switches, the cart and checkout page ids, the site logo.\n\nOnly small, frequently read options are moved: every autoloaded option is loaded on every request including admin and cron, so the point is to move the hot ones in, not to grow the payload.\n\nRuns automatically when the schema upgrades. Safe to re-run.\n\n## EXAMPLES\n\n    wp bhc setup autoload",
			]
		);
	}

	/**
	 * `wp bhc setup hero`.
	 */
	private function register_hero_command(): void {
		$container = $this->container;

		WP_CLI::add_command(
			'bhc setup hero',
			static function ( array $args, array $assoc_args ) use ( $container ): void {
				unset( $assoc_args );

				$banner = $container->get( \BoneHornCrafts\Core\Store\HeroBanner::class );
				$source = (string) ( $args[0] ?? '' );

				if ( '' === $source ) {
					$current = $banner->current();

					if ( 0 === $current ) {
						WP_CLI::warning( 'No banner set. Pass an image file or an attachment id.' );

						return;
					}

					WP_CLI::log( sprintf( '  id  %d', $current ) );
					WP_CLI::log( sprintf( '  url %s', (string) wp_get_attachment_url( $current ) ) );
					WP_CLI::success( 'A home page banner is set.' );

					return;
				}

				// A bare number is an attachment already in the library;
				// anything else is a file to import.
				$result = ctype_digit( $source )
					? $banner->set( (int) $source )
					: $banner->import( $source );

				if ( is_wp_error( $result ) ) {
					WP_CLI::error( $result->get_error_message() );
				}

				$attachment = $banner->current();

				WP_CLI::log( sprintf( '  id  %d', $attachment ) );
				WP_CLI::log( sprintf( '  url %s', (string) wp_get_attachment_url( $attachment ) ) );
				WP_CLI::success( 'Home page banner set. Flush caches to see it: wp bhc cache flush' );
			},
			[
				'shortdesc' => 'Set the home page hero banner.',
				'longdesc'  => "The banner is stored as a media-library attachment rather than a file bundled with the theme, so it can be changed without a deploy — from here, or under Bone Horn Crafts \u2192 Settings.\n\nA landscape image works best: the copy is set over the left of it, and the crop holds the right edge as the viewport narrows, so keep the subject to the right and leave the left comparatively empty.\n\nRun with no argument to report the banner currently set.\n\n## OPTIONS\n\n[<file>]\n: Path to an image to import, or the id of an attachment already in the library.\n\n## EXAMPLES\n\n    wp bhc setup hero\n    wp bhc setup hero ~/banner.jpg\n    wp bhc setup hero 1234",
			]
		);
	}

	/**
	 * `wp bhc setup contact`.
	 */
	private function register_contact_command(): void {
		$container = $this->container;

		WP_CLI::add_command(
			'bhc setup contact',
			static function ( array $args, array $assoc_args ) use ( $container ): void {
				unset( $args, $assoc_args );

				$repair = $container->get( \BoneHornCrafts\Core\Store\PlaceholderContactRepair::class );
				$drift  = $repair->drift();

				if ( [] === $drift ) {
					$business = $container->get( \BoneHornCrafts\Core\Store\BusinessDetails::class );

					WP_CLI::log( sprintf( '  phone   %s', $business->phone() ) );
					WP_CLI::log( sprintf( '  email   %s', $business->email() ) );
					WP_CLI::log( sprintf( '  address %s', $business->address_inline() ) );
					WP_CLI::success( 'Contact details are already set. Nothing to change.' );

					return;
				}

				foreach ( $drift as $key => $values ) {
					WP_CLI::log( sprintf( '  %-20s %s -> %s', $key, $values['current'], $values['replacement'] ) );
				}

				$repair->apply();

				WP_CLI::success(
					sprintf(
						'%d placeholder(s) replaced. Re-run `wp bhc setup pages --refresh` if the policy pages already quoted the old value.',
						count( $drift )
					)
				);
			},
			[
				'shortdesc' => 'Replace sample contact details left in the settings row.',
				'longdesc'  => "Options::all() merges the stored settings row over the defaults, so once a value has been written, correcting the default does nothing to an existing site. A store seeded while the defaults were still sample data kept a placeholder telephone number and email — which are now published in the Organization JSON-LD and printed on the contact and policy pages.\n\nOnly a value that still matches a known placeholder exactly is replaced; anything you have typed yourself is left alone. Runs automatically when the schema upgrades.\n\n## EXAMPLES\n\n    wp bhc setup contact",
			]
		);
	}

	/**
	 * `wp bhc setup pages`.
	 */
	private function register_pages_command(): void {
		$container = $this->container;

		WP_CLI::add_command(
			'bhc setup pages',
			static function ( array $args, array $assoc_args ) use ( $container ): void {
				unset( $args );

				$installer = $container->get( \BoneHornCrafts\Core\Content\PolicyPageInstaller::class );
				$refresh   = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'refresh', false );

				$created = $installer->install();

				foreach ( $created as $slug ) {
					WP_CLI::log( sprintf( '  created  %s', $slug ) );
				}

				if ( $refresh ) {
					WP_CLI::warning( 'Rewriting the body of every policy page. Local edits to these pages are lost.' );

					foreach ( $installer->refresh() as $slug ) {
						WP_CLI::log( sprintf( '  rewrote  %s', $slug ) );
					}
				}

				WP_CLI\Utils\format_items(
					'table',
					array_map(
						static fn ( string $slug, int $id ): array => [
							'page'    => $slug,
							'id'      => $id,
							'url'     => $id > 0 ? (string) get_permalink( $id ) : '',
							'present' => $id > 0 ? 'yes' : 'no',
						],
						array_keys( $installer->status() ),
						array_values( $installer->status() )
					),
					[ 'page', 'id', 'present', 'url' ]
				);

				WP_CLI::success(
					[] === $created && ! $refresh
						? 'All policy pages already exist. Nothing was changed.'
						: sprintf( '%d page(s) created.', count( $created ) )
				);
			},
			[
				'shortdesc' => 'Publish the contact and legal pages, and point WooCommerce at them.',
				'longdesc'  => "Creates the contact, privacy policy, terms, shipping and returns pages if they are missing, then sets WordPress's privacy page and WooCommerce's terms and refunds page options to match.\n\nThis runs automatically when the plugin's schema installs. Run it by hand on a store that predates that, or after a page was deleted.\n\nExisting pages are never modified: an owner who rewrote the returns policy keeps their copy. Pass --refresh to overwrite every page body with the shipped copy, which discards those edits.\n\n## OPTIONS\n\n[--refresh]\n: Overwrite the body of every page with the shipped copy. Destructive.\n\n## EXAMPLES\n\n    wp bhc setup pages\n    wp bhc setup pages --refresh",
			]
		);
	}

	/**
	 * `wp bhc payments verify`.
	 */
	private function register_payment_commands(): void {
		$container = $this->container;

		WP_CLI::add_command(
			'bhc payments verify',
			static function ( array $args, array $assoc_args ) use ( $container ): void {
				unset( $args, $assoc_args );

				$result = $container->get( \BoneHornCrafts\Core\Payments\PayPalVerifier::class )->verify();

				WP_CLI::log( sprintf( 'Mode:   %s', $result['mode'] ) );
				WP_CLI::log( sprintf( 'Status: %d', $result['status'] ) );

				if ( '' !== $result['scopes'] ) {
					WP_CLI::log( sprintf( 'Scopes: %s', substr( $result['scopes'], 0, 200 ) ) );
				}

				if ( $result['ok'] ) {
					WP_CLI::success( $result['message'] );

					return;
				}

				WP_CLI::error( $result['message'] );
			},
			[
				'shortdesc' => 'Ask PayPal whether the configured credentials work.',
				'longdesc'  => "Performs a client-credentials OAuth request against PayPal. Nothing is charged and nothing is created — it only exchanges the configured client id and secret for a bearer token, which is the same thing the gateway does before taking a payment.\n\nCredentials come from BHC_PAYPAL_CLIENT_ID and BHC_PAYPAL_CLIENT_SECRET in wp-config.php, so they never reach the database.\n\n## EXAMPLES\n\n    wp bhc payments verify",
			]
		);
	}

	/**
	 * `wp bhc import firebase`.
	 */
	private function register_import_commands(): void {
		$container = $this->container;

		WP_CLI::add_command(
			'bhc import images',
			static function ( array $args, array $assoc_args ) use ( $container ): void {
				$container->get( ImportCommand::class )->images( $args, $assoc_args );
			},
			[
				'shortdesc' => 'Attach product imagery from a local folder.',
				'longdesc'  => "Runs in two passes. Files matched exactly — through the export manifest, a SKU folder or filename, or an identical product name — are attached directly. Anything less certain is proposed in a review CSV and never applied on its own, because similarity matching measured against a real catalogue was confidently wrong.\n\n## EXAMPLES\n\n    wp bhc import images ./images --plan=mapping.csv\n    wp bhc import images ./images --apply=mapping.csv",
				'synopsis'  => [
					[
						'type'        => 'positional',
						'name'        => 'directory',
						'description' => 'Folder holding the images.',
						'optional'    => false,
					],
					[
						'type'        => 'assoc',
						'name'        => 'manifest',
						'description' => 'Export JSON used for exact filename matching.',
						'optional'    => true,
					],
					[
						'type'        => 'assoc',
						'name'        => 'plan',
						'description' => 'Write a review CSV here and change nothing.',
						'optional'    => true,
					],
					[
						'type'        => 'assoc',
						'name'        => 'apply',
						'description' => 'Attach images according to a reviewed CSV.',
						'optional'    => true,
					],
					[
						'type'        => 'flag',
						'name'        => 'replace',
						'description' => 'Replace existing imagery.',
						'optional'    => true,
					],
					[
						'type'        => 'flag',
						'name'        => 'dry-run',
						'description' => 'Report without writing.',
						'optional'    => true,
					],
				],
			]
		);

		WP_CLI::add_command(
			'bhc import firebase',
			static function ( array $args, array $assoc_args ) use ( $container ): void {
				$container->get( ImportCommand::class )->firebase( $args, $assoc_args );
			},
			[
				'shortdesc' => 'Import a real product catalogue from a Firebase Realtime Database export.',
				'longdesc'  => "Matches on SKU (the source productId), so re-running updates rather than duplicates. A record whose productPrice is a list of {price, type} rows becomes a variable product with one variation per row.\n\nOnly the `product` branch is read. Any `orders` or `messages` branch is ignored: it holds real customer data, which does not belong in a catalogue import.\n\n## EXAMPLES\n\n    wp bhc import firebase export.json --dry-run\n    wp bhc import firebase export.json --limit=5\n    wp bhc import firebase export.json",
				'synopsis'  => [
					[
						'type'        => 'positional',
						'name'        => 'file',
						'description' => 'Path to the exported JSON.',
						'optional'    => false,
					],
					[
						'type'        => 'flag',
						'name'        => 'dry-run',
						'description' => 'Report what would be imported without writing anything.',
						'optional'    => true,
					],
					[
						'type'        => 'flag',
						'name'        => 'skip-images',
						'description' => 'Do not download imagery.',
						'optional'    => true,
					],
					[
						'type'        => 'assoc',
						'name'        => 'limit',
						'description' => 'Import at most this many products.',
						'optional'    => true,
					],
				],
			]
		);
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
						'type'        => 'assoc',
						'name'        => 'only',
						'description' => 'Comma-separated buckets to remove (products,attachments,orders,customers,comments,pages,posts,menus,zones,terms). Default: all of them.',
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
