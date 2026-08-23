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
use BoneHornCrafts\Core\Store\BusinessDetails;
use BoneHornCrafts\Core\Store\PlaceholderContactRepair;
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
	 * @param CacheManager             $cache     Cache manager.
	 * @param RedisStatus              $redis     Object cache detection.
	 * @param Scheduler                $scheduler Job scheduler.
	 * @param Installer                $installer Schema installer.
	 * @param ProductRepository        $products  Product read model.
	 * @param WishlistRepository       $wishlist  Wishlist repository.
	 * @param AffinityRepository       $affinity  Affinity repository.
	 * @param ProductStatsRepository   $stats     Stats repository.
	 * @param BusinessDetails          $business  Business details.
	 * @param PlaceholderContactRepair $contact_repair Placeholder detection.
	 */
	public function __construct(
		private CacheManager $cache,
		private RedisStatus $redis,
		private Scheduler $scheduler,
		private Installer $installer,
		private ProductRepository $products,
		private WishlistRepository $wishlist,
		private AffinityRepository $affinity,
		private ProductStatsRepository $stats,
		private BusinessDetails $business,
		private PlaceholderContactRepair $contact_repair
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
			'store'       => [
				'placeholder_contact' => $this->contact_repair->drift(),
			],
			'jobs'        => [
				'action_scheduler' => function_exists( 'as_schedule_recurring_action' ),
				'wp_cron_disabled' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
				'schedule'         => $this->scheduler->status(),
				'failed'           => $this->scheduler->failed(),
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

		// Name Redis when it is what is actually running. "Active
		// (WP_Object_Cache)" is technically true of every drop-in and tells an
		// operator nothing about whether the thing they installed is the thing
		// serving them.
		if ( $report['cache']['persistent'] ) {
			$cache_detail = $report['cache']['redis']
				? sprintf(
					/* translators: %s: cache implementation class. */
					__( 'Active — Redis (%s).', 'bhc-commerce-core' ),
					(string) $report['cache']['implementation']
				)
				: sprintf(
					/* translators: %s: cache implementation class. */
					__( 'Active (%s). Not Redis.', 'bhc-commerce-core' ),
					(string) $report['cache']['implementation']
				);
		} elseif ( $report['cache']['redis_extension'] ) {
			$cache_detail = __( 'Not active. The PHP redis extension is loaded, so installing an object-cache.php drop-in would enable it.', 'bhc-commerce-core' );
		} else {
			$cache_detail = __( 'Not detected. The plugin is falling back to transients, which works but is slower.', 'bhc-commerce-core' );
		}

		$checks[] = [
			'label'  => __( 'Persistent object cache', 'bhc-commerce-core' ),
			'status' => $report['cache']['persistent'] ? 'pass' : 'warn',
			'detail' => $cache_detail,
		];

		$checks[] = [
			'label'  => __( 'Action Scheduler', 'bhc-commerce-core' ),
			'status' => $report['jobs']['action_scheduler'] ? 'pass' : 'fail',
			'detail' => $report['jobs']['action_scheduler']
				? __( 'Available. Background indexing is scheduled.', 'bhc-commerce-core' )
				: __( 'Unavailable — WooCommerce provides it, so check that WooCommerce is active.', 'bhc-commerce-core' ),
		];

		// A failed action is not fatal — `Scheduler::ensure_schedules()` recreates
		// a missing recurring action on the next request, so the site recovers by
		// itself. It is reported as a warning rather than a pass because the
		// usual cause is a window where the plugin's callbacks were absent
		// (a mid-upload deploy, WooCommerce deactivated), which is worth knowing
		// about even after it has healed.
		//
		// The remedy is `clean`, not `run`: `run` executes actions that are
		// pending and does nothing to a failed row, and Action Scheduler's own
		// cleaner only prunes complete and cancelled actions, so a failed row
		// otherwise sits there forever. `--before=now` is required because
		// `clean` defaults to deleting nothing newer than 31 days.
		$failed = (int) ( $report['jobs']['failed'] ?? 0 );

		$checks[] = [
			'label'  => __( 'Failed background jobs', 'bhc-commerce-core' ),
			'status' => 0 === $failed ? 'pass' : 'warn',
			'detail' => 0 === $failed
				? __( 'None recorded.', 'bhc-commerce-core' )
				: sprintf(
					/* translators: %d: number of failed actions. */
					_n(
						'%d failed action in the bhc-core group. The schedule repairs itself, so this is a record of a past failure rather than a job that is still broken. Delete the row with: wp action-scheduler clean --status=failed --before=now',
						'%d failed actions in the bhc-core group. The schedule repairs itself, so these are records of past failures rather than jobs that are still broken. Delete the rows with: wp action-scheduler clean --status=failed --before=now',
						$failed,
						'bhc-commerce-core'
					),
					$failed
				),
		];

		// Placeholder contact details are published as the business telephone
		// number in the Organization JSON-LD and printed on the contact page,
		// so a leftover sample value is a customer-facing error rather than an
		// untidy setting.
		$placeholders = (array) ( $report['store']['placeholder_contact'] ?? [] );

		$checks[] = [
			'label'  => __( 'Contact details', 'bhc-commerce-core' ),
			'status' => [] === $placeholders ? 'pass' : 'fail',
			'detail' => [] === $placeholders
				? sprintf(
					/* translators: 1: telephone number, 2: email address. */
					__( 'Publishing %1$s and %2$s.', 'bhc-commerce-core' ),
					$this->business->phone(),
					$this->business->email()
				)
				: sprintf(
					/* translators: %s: comma separated setting names. */
					__( 'Still set to sample values: %s. Fix with: wp bhc setup contact', 'bhc-commerce-core' ),
					implode( ', ', array_keys( $placeholders ) )
				),
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
			'label'  => __( 'WordPress', 'bhc-commerce-core' ),
			'status' => version_compare( (string) $report['environment']['wordpress'], '6.5', '>=' ) ? 'pass' : 'warn',
			'detail' => sprintf(
				/* translators: %s: WordPress version. */
				__( 'Running WordPress %s.', 'bhc-commerce-core' ),
				(string) $report['environment']['wordpress']
			),
		];

		$wc_active = defined( 'WC_VERSION' );

		$checks[] = [
			'label'  => __( 'WooCommerce', 'bhc-commerce-core' ),
			'status' => $wc_active && version_compare( (string) $report['environment']['woocommerce'], '8.0', '>=' ) ? 'pass' : 'fail',
			'detail' => $wc_active
				? sprintf(
					/* translators: %s: WooCommerce version. */
					__( 'Running WooCommerce %s.', 'bhc-commerce-core' ),
					(string) $report['environment']['woocommerce']
				)
				: __( 'Not active. This plugin requires WooCommerce.', 'bhc-commerce-core' ),
		];

		$checks[] = [
			'label'  => __( 'Plugin version', 'bhc-commerce-core' ),
			'status' => 'pass',
			'detail' => sprintf(
				/* translators: 1: plugin version, 2: environment type. */
				__( 'Bone Horn Crafts Commerce %1$s, environment "%2$s".', 'bhc-commerce-core' ),
				(string) $report['plugin']['version'],
				(string) $report['environment']['environment_type']
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

		$checks[] = $this->payment_check();

		return $checks;
	}

	/**
	 * Reports whether the store can actually take money.
	 *
	 * Deliberately does not call PayPal. A health screen that makes a network
	 * request on every load is a health screen that hangs when the network is
	 * the thing that is broken, and this one is loaded to diagnose exactly that
	 * kind of outage. It reports what is configured; `wp bhc payments verify`
	 * is the command that asks PayPal whether the configuration is any good.
	 *
	 * @return array{label:string, status:string, detail:string}
	 */
	private function payment_check(): array {
		$label = __( 'Payments', 'bhc-commerce-core' );

		if ( ! function_exists( 'WC' ) || null === WC()->payment_gateways() ) {
			return [
				'label'  => $label,
				'status' => 'warn',
				'detail' => __( 'WooCommerce is not available, so no gateway could be inspected.', 'bhc-commerce-core' ),
			];
		}

		$demo_titles = [ 'Pay on invoice (demo)', 'Bank transfer (demo)' ];
		$real        = [];
		$demo        = [];

		foreach ( WC()->payment_gateways()->get_available_payment_gateways() as $id => $gateway ) {
			if ( in_array( $gateway->get_title(), $demo_titles, true ) ) {
				$demo[] = (string) $id;

				continue;
			}

			$real[] = (string) $id;
		}

		if ( [] !== $demo ) {
			return [
				'label'  => $label,
				'status' => 'warn',
				'detail' => sprintf(
					/* translators: 1: comma-separated gateway ids, 2: comma-separated gateway ids or "none". */
					__( 'Demo gateways still live at checkout (%1$s) — they accept an order without taking payment. Real gateways: %2$s.', 'bhc-commerce-core' ),
					implode( ', ', $demo ),
					[] === $real ? __( 'none', 'bhc-commerce-core' ) : implode( ', ', $real )
				),
			];
		}

		if ( [] === $real ) {
			return [
				'label'  => $label,
				'status' => 'fail',
				'detail' => __( 'No payment gateway is available. Checkout will fail at the last step.', 'bhc-commerce-core' ),
			];
		}

		$paypal = defined( 'BHC_PAYPAL_CLIENT_ID' ) && '' !== (string) constant( 'BHC_PAYPAL_CLIENT_ID' )
			? sprintf(
				/* translators: %s: live or sandbox. */
				__( ' PayPal credentials come from wp-config (%s).', 'bhc-commerce-core' ),
				defined( 'BHC_PAYPAL_SANDBOX' ) && constant( 'BHC_PAYPAL_SANDBOX' )
					? __( 'sandbox', 'bhc-commerce-core' )
					: __( 'live', 'bhc-commerce-core' )
			)
			: '';

		return [
			'label'  => $label,
			'status' => 'pass',
			'detail' => sprintf(
				/* translators: 1: comma-separated gateway ids, 2: optional PayPal note. */
				__( 'Available at checkout: %1$s.%2$s', 'bhc-commerce-core' ),
				implode( ', ', $real ),
				$paypal
			),
		];
	}
}
