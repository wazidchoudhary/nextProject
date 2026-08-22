<?php
/**
 * Merchandising index builder.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Analytics;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Contracts\LoggerInterface;
use BoneHornCrafts\Core\Recommendations\AffinityRepository;
use wpdb;

/**
 * Turns raw order history into the two indexes the storefront reads.
 *
 * Source of truth is `wc_order_product_lookup`, WooCommerce's own analytics
 * lookup table: it already holds one row per order line with the product id,
 * quantity, net total and order date, all indexed. Reading it avoids joining
 * `order_items` to `order_itemmeta` to `posts` on every rebuild, which is the
 * difference between a job that finishes in seconds and one that times out on a
 * catalogue of any size.
 *
 * Both index builders are idempotent — running them twice produces the same
 * result — which is what makes them safe to retry from Action Scheduler.
 */
final class MerchandisingIndexer {

	/**
	 * Database handle.
	 *
	 * @var wpdb
	 */
	private wpdb $db;

	/**
	 * Constructor.
	 *
	 * @param ProductStatsRepository $stats    Stats repository.
	 * @param AffinityRepository     $affinity Affinity repository.
	 * @param LoggerInterface        $logger   Logger.
	 * @param wpdb|null              $db       Database handle.
	 */
	public function __construct(
		private ProductStatsRepository $stats,
		private AffinityRepository $affinity,
		private LoggerInterface $logger,
		?wpdb $db = null
	) {
		global $wpdb;

		$this->db = $db ?? $wpdb;
	}

	/**
	 * Whether WooCommerce's analytics lookup table is available.
	 */
	public function lookup_available(): bool {
		$table = $this->db->prefix . 'wc_order_product_lookup';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema probe.
		return (string) $this->db->get_var( $this->db->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	}

	/**
	 * Rebuilds sales statistics for a batch of products.
	 *
	 * @param int[] $product_ids Product ids.
	 * @param int   $window_days Look-back window.
	 *
	 * @return int Number of rows written.
	 */
	public function rebuild_stats( array $product_ids, int $window_days = 30 ): int {
		$product_ids = array_values( array_unique( array_filter( array_map( 'absint', $product_ids ) ) ) );

		if ( [] === $product_ids ) {
			return 0;
		}

		$sales = $this->sales_for( $product_ids, $window_days );
		$views = $this->stats->for_products( $product_ids );

		$written = 0;

		foreach ( $product_ids as $product_id ) {
			$units   = (int) ( $sales[ $product_id ]['units'] ?? 0 );
			$revenue = (float) ( $sales[ $product_id ]['revenue'] ?? 0.0 );
			$seen    = (int) ( $views[ $product_id ]['views_30d'] ?? 0 );

			// Trending blends demand with attention so a product that sells
			// steadily is not buried by one viral listing.
			$trending = round( ( $units * 3 ) + ( $seen * 0.1 ), 5 );

			$ok = $this->stats->upsert(
				$product_id,
				[
					'views_30d'       => $seen,
					'units_30d'       => $units,
					'revenue_30d'     => $revenue,
					'bestseller_rank' => (int) ( $views[ $product_id ]['bestseller_rank'] ?? 0 ),
					'trending_score'  => $trending,
				]
			);

			$written += $ok ? 1 : 0;
		}

		return $written;
	}

	/**
	 * Removes derived rows for products that no longer exist.
	 *
	 * @return int Rows removed across both derived tables.
	 */
	public function prune_orphans(): int {
		return $this->stats->prune_orphans() + $this->affinity->prune_orphans();
	}

	/**
	 * Recomputes the bestseller ranking across the whole catalogue.
	 *
	 * @param int $limit How many products receive a rank.
	 *
	 * @return int Number of ranked products.
	 */
	public function rebuild_ranks( int $limit = 60 ): int {
		$limit = max( 1, min( 500, $limit ) );
		$table = $this->db->prefix . 'bhc_product_stats';

		$this->stats->reset_ranks();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal table; value prepared.
		$rows = $this->db->get_col(
			$this->db->prepare(
				"SELECT product_id FROM {$table} WHERE units_30d > 0 ORDER BY units_30d DESC, revenue_30d DESC LIMIT %d",
				$limit
			)
		);

		$rank = 0;

		foreach ( array_map( 'absint', (array) $rows ) as $product_id ) {
			++$rank;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write.
			$this->db->update(
				$table,
				[
					'bestseller_rank' => $rank,
					'updated_at'      => current_time( 'mysql', true ),
				],
				[ 'product_id' => $product_id ],
				[ '%d', '%s' ],
				[ '%d' ]
			);
		}

		$this->logger->info( 'index.ranks_rebuilt', [ 'ranked' => $rank ] );

		return $rank;
	}

	/**
	 * Rebuilds the "bought together" affinity rows for a batch of products.
	 *
	 * @param int[] $product_ids Seed product ids.
	 * @param int   $window_days Look-back window.
	 *
	 * @return int Number of seeds indexed.
	 */
	public function rebuild_affinity( array $product_ids, int $window_days = 180 ): int {
		$product_ids = array_values( array_unique( array_filter( array_map( 'absint', $product_ids ) ) ) );

		if ( [] === $product_ids || ! $this->lookup_available() ) {
			return 0;
		}

		$lookup = $this->db->prefix . 'wc_order_product_lookup';
		$since  = gmdate( 'Y-m-d H:i:s', time() - ( max( 1, $window_days ) * DAY_IN_SECONDS ) );
		$seeds  = 0;

		foreach ( $product_ids as $product_id ) {
			// One self-join over an indexed table: for every order containing
			// the seed, count the other products in that order.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal table; values prepared.
			$rows = $this->db->get_results(
				$this->db->prepare(
					"SELECT other.product_id AS related_id, COUNT(DISTINCT other.order_id) AS orders
					 FROM {$lookup} seed
					 INNER JOIN {$lookup} other
					     ON other.order_id = seed.order_id AND other.product_id <> seed.product_id
					 WHERE seed.product_id = %d AND seed.date_created >= %s
					 GROUP BY other.product_id
					 ORDER BY orders DESC
					 LIMIT 20",
					$product_id,
					$since
				),
				ARRAY_A
			);

			$scores = [];

			foreach ( (array) $rows as $row ) {
				$scores[ (int) $row['related_id'] ] = (float) $row['orders'];
			}

			if ( [] === $scores ) {
				continue;
			}

			$this->affinity->replace_for_product( $product_id, $scores, AffinityRepository::STRATEGY_BOUGHT_TOGETHER );

			++$seeds;
		}

		return $seeds;
	}

	/**
	 * Reads unit and revenue totals from the analytics lookup table.
	 *
	 * @param int[] $product_ids Product ids.
	 * @param int   $window_days Look-back window.
	 *
	 * @return array<int, array{units:int, revenue:float}>
	 */
	private function sales_for( array $product_ids, int $window_days ): array {
		if ( ! $this->lookup_available() ) {
			return [];
		}

		$lookup       = $this->db->prefix . 'wc_order_product_lookup';
		$since        = gmdate( 'Y-m-d H:i:s', time() - ( max( 1, $window_days ) * DAY_IN_SECONDS ) );
		$placeholders = implode( ',', array_fill( 0, count( $product_ids ), '%d' ) );

		$values   = $product_ids;
		$values[] = $since;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Placeholders built from integer ids.
		$rows = $this->db->get_results(
			$this->db->prepare(
				"SELECT product_id, SUM(product_qty) AS units, SUM(product_net_revenue) AS revenue
				 FROM {$lookup}
				 WHERE product_id IN ({$placeholders}) AND date_created >= %s
				 GROUP BY product_id",
				$values
			),
			ARRAY_A
		);

		$sales = [];

		foreach ( (array) $rows as $row ) {
			$sales[ (int) $row['product_id'] ] = [
				'units'   => (int) $row['units'],
				'revenue' => round( (float) $row['revenue'], 2 ),
			];
		}

		return $sales;
	}
}
