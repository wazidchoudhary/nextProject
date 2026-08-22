<?php
/**
 * System health report.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Admin;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Analytics\ProductStatsRepository;
use BoneHornCrafts\Core\Cache\CacheManager;
use BoneHornCrafts\Core\Cache\RedisStatus;
use BoneHornCrafts\Core\Database\Installer;
use BoneHornCrafts\Core\Database\Schema;
use BoneHornCrafts\Core\Jobs\Scheduler;
use BoneHornCrafts\Core\Product\ProductRepository;
use BoneHornCrafts\Core\Recommendations\AffinityRepository;
use BoneHornCrafts\Core\Wishlist\WishlistRepository;

/**
 * Collects everything an operator needs to answer "is the store healthy?".
 *
 * Nothing sensitive is included: no credentials, no connection strings, no
 * absolute paths, no customer data. The report is safe to screenshot into a
 * support ticket, which is exactly what people do with it.
 */
final class HealthReport {

	/**
	 * Constructor.
	 *
	 * @param CacheManager           $cache     Cache manager.
	 * @param RedisStatus            $redis     Object cache detection.
	 * @param Scheduler              $scheduler Job scheduler.
	 * @param Installer              $installer Schema installer.
	 * @param ProductRepository      $products  Product read model.
	 * @param WishlistRepository     $wishlist  Wishlist repository.
	 * @param AffinityRepository     $affinity  Affinity repository.
	 * @param ProductStatsRepository $stats     Stats repository.
	 */
	public function __construct(
		private CacheManager $cache,
		private RedisStatus $redis,
		private Scheduler $scheduler,
		private Installer $installer,
		private ProductRepository $products,
		private WishlistRepository $wishlist,
		private AffinityRepository $affinity,
		private ProductStatsRepository $stats
	) {}

	/**
	 * Builds the full report.
	 *
	 * @return array<string, mixed>
	 */
	public function build(): array {
		return [
			'plugin'      => [
				'version'             => BHC_CORE_VERSION,
				'db_version'          => $this->installer->installed_version(),
				'expected_db_version' => Installer::DB_VERSION,
				'tables_installed'    => Schema::is_installed(),
			],
			'environment' => [
				'php'              => PHP_VERSION,
				'wordpress'        => get_bloginfo( 'version' ),
				'woocommerce'      => defined( 'WC_VERSION' ) ? WC_VERSION : __( 'not active', 'bhc-commerce-core' ),
				'environment_type' => wp_get_environment_type(),
				'https'            => is_ssl(),
				'memory_limit'     => (string) ini_get( 'memory_limit' ),
				'max_execution'    => (string) ini_get( 'max_execution_time' ),
				'opcache'          => function_exists( 'opcache_get_status' ),
				'debug'            => defined( 'WP_DEBUG' ) && WP_DEBUG,
			],
			// Two different notions of "persistent" live here and conflating
			// them is a classic source of misleading health output: the store
			// may survive between requests (transients do) while there is still
			// no external object cache in front of the database.
			'cache'       => array_merge(
				[
					'store'                  => $this->cache->store_name(),
					'store_survives_request' => $this->cache->is_persistent(),
				],
				$this->redis->summary()
			),
			'jobs'        => [
				'action_scheduler' => function_exists( 'as_schedule_recurring_action' ),
				'wp_cron_disabled' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
				'schedule'         => $this->scheduler->status(),
			],
			'catalogue'   => [
				'published_products' => $this->products->published_count(),
				'low_stock'          => count( $this->products->low_stock_ids( 20 ) ),
				'stats_rows'         => $this->stats->count(),
				'affinity_rows'      => $this->affinity->total(),
				'affinity_updated'   => $this->affinity->last_updated(),
				'wishlist_rows'      => $this->wishlist->total(),
			],
		];
	}

	/**
	 * Reduces the report to pass/warn checks for the admin screen.
	 *
	 * @return array<int, array{label:string, status:string, detail:string}>
	 */
	public function checks(): array {
		$report = $this->build();
		$checks = [];

		$checks[] = [
			'label'  => __( 'Database schema', 'bhc-commerce-core' ),
			'status' => $report['plugin']['tables_installed'] && $report['plugin']['db_version'] === $report['plugin']['expected_db_version'] ? 'pass' : 'warn',
			'detail' => sprintf(
				/* translators: 1: installed schema version, 2: expected schema version. */
				__( 'Installed version %1$d, expected %2$d.', 'bhc-commerce-core' ),
				(int) $report['plugin']['db_version'],
				(int) $report['plugin']['expected_db_version']
			),
		];

		$checks[] = [
			'label'  => __( 'Persistent object cache', 'bhc-commerce-core' ),
			'status' => $report['cache']['persistent'] ? 'pass' : 'warn',
			'detail' => $report['cache']['persistent']
				? sprintf(
					/* translators: %s: cache implementation class. */
					__( 'Active (%s).', 'bhc-commerce-core' ),
					(string) $report['cache']['implementation']
				)
				: __( 'Not detected. The plugin is falling back to transients, which works but is slower.', 'bhc-commerce-core' ),
		];

		$checks[] = [
			'label'  => __( 'Action Scheduler', 'bhc-commerce-core' ),
			'status' => $report['jobs']['action_scheduler'] ? 'pass' : 'fail',
			'detail' => $report['jobs']['action_scheduler']
				? __( 'Available. Background indexing is scheduled.', 'bhc-commerce-core' )
				: __( 'Unavailable — WooCommerce provides it, so check that WooCommerce is active.', 'bhc-commerce-core' ),
		];

		$checks[] = [
			'label'  => __( 'Merchandising index', 'bhc-commerce-core' ),
			'status' => $report['catalogue']['stats_rows'] > 0 ? 'pass' : 'warn',
			'detail' => sprintf(
				/* translators: 1: stat rows, 2: affinity rows. */
				__( '%1$d product stat rows, %2$d affinity rows.', 'bhc-commerce-core' ),
				(int) $report['catalogue']['stats_rows'],
				(int) $report['catalogue']['affinity_rows']
			),
		];

		$checks[] = [
			'label'  => __( 'PHP version', 'bhc-commerce-core' ),
			'status' => version_compare( PHP_VERSION, '8.2', '>=' ) ? 'pass' : 'fail',
			'detail' => sprintf(
				/* translators: %s: PHP version. */
				__( 'Running PHP %s.', 'bhc-commerce-core' ),
				PHP_VERSION
			),
		];

		return $checks;
	}
}
