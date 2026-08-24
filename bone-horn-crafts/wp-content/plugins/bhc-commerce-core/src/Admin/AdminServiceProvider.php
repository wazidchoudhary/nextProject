<?php
/**
 * Admin module wiring.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Admin;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\AbstractServiceProvider;
use BoneHornCrafts\Core\Analytics\ProductStatsRepository;
use BoneHornCrafts\Core\Cache\CacheManager;
use BoneHornCrafts\Core\Cache\RedisStatus;
use BoneHornCrafts\Core\Container;
use BoneHornCrafts\Core\Contracts\ContainerInterface;
use BoneHornCrafts\Core\Database\Installer;
use BoneHornCrafts\Core\Jobs\Scheduler;
use BoneHornCrafts\Core\Product\Admin\QuickImageColumn;
use BoneHornCrafts\Core\Store\BusinessDetails;
use BoneHornCrafts\Core\Store\PlaceholderContactRepair;
use BoneHornCrafts\Core\Store\StoreVisibility;
use BoneHornCrafts\Core\Order\OrderRepository;
use BoneHornCrafts\Core\Plugin;
use BoneHornCrafts\Core\Product\ProductRepository;
use BoneHornCrafts\Core\Recommendations\AffinityRepository;
use BoneHornCrafts\Core\Support\Context;
use BoneHornCrafts\Core\Support\Options;
use BoneHornCrafts\Core\Wishlist\WishlistRepository;

/**
 * Registers the admin screens.
 *
 * `HealthReport` is bound unconditionally because the REST health endpoint and
 * the WP-CLI health command both use it outside wp-admin; the screens
 * themselves only load in an admin request.
 */
final class AdminServiceProvider extends AbstractServiceProvider {

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
			HealthReport::class,
			static fn ( Container $c ): HealthReport => new HealthReport(
				$c->get( CacheManager::class ),
				$c->get( RedisStatus::class ),
				$c->get( Scheduler::class ),
				$c->get( Installer::class ),
				$c->get( ProductRepository::class ),
				$c->get( WishlistRepository::class ),
				$c->get( AffinityRepository::class ),
				$c->get( ProductStatsRepository::class ),
				$c->get( BusinessDetails::class ),
				$c->get( PlaceholderContactRepair::class ),
				$c->get( StoreVisibility::class )
			)
		);

		$container->singleton(
			DashboardPage::class,
			static fn ( Container $c ): DashboardPage => new DashboardPage(
				$c->get( ProductRepository::class ),
				$c->get( OrderRepository::class ),
				$c->get( HealthReport::class ),
				$c->get( Scheduler::class )
			)
		);

		$container->singleton(
			HealthPage::class,
			static fn ( Container $c ): HealthPage => new HealthPage( $c->get( HealthReport::class ) )
		);

		$container->singleton(
			SettingsPage::class,
			static fn ( Container $c ): SettingsPage => new SettingsPage(
				$c->get( Options::class ),
				$c->get( CacheManager::class )
			)
		);

		$container->singleton(
			AdminMenu::class,
			static fn ( Container $c ): AdminMenu => new AdminMenu(
				$c->get( DashboardPage::class ),
				$c->get( HealthPage::class ),
				$c->get( SettingsPage::class )
			)
		);

		$container->singleton(
			AdminAssets::class,
			static fn ( Container $c ): AdminAssets => new AdminAssets(
				$c->get( Plugin::class ),
				$c->get( QuickImageColumn::class )
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

		if ( $context->is_admin() ) {
			$this->hook( $container, AdminMenu::class, AdminAssets::class );
		}
	}
}
