<?php
/**
 * Merchandising statistics repository.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Analytics;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Database\AbstractRepository;
use BoneHornCrafts\Core\Database\Schema;

/**
 * Reads and writes the `bhc_product_stats` table.
 *
 * Every method is bounded: no query here can return more rows than the caller
 * asked for, and id lists are cast to integers before they are interpolated
 * into the `IN ()` placeholder list.
 */
final class ProductStatsRepository extends AbstractRepository {

	/**
	 * {@inheritDoc}
	 */
	protected function table(): string {
		return Schema::table( Schema::TABLE_STATS );
	}

	/**
	 * Returns ranked bestseller product ids.
	 *
	 * @param int $limit Maximum ids.
	 *
	 * @return int[]
	 */
	public function bestseller_ids( int $limit = 12 ): array {
		$limit = max( 1, min( 100, $limit ) );
		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internal; values are prepared.
		$ids = $this->db->get_col(
			$this->db->prepare(
				"SELECT product_id FROM {$table} WHERE bestseller_rank > 0 ORDER BY bestseller_rank ASC LIMIT %d",
				$limit
			)
		);

		return array_map( 'absint', (array) $ids );
	}

	/**
	 * Returns trending product ids (views weighted against recent sales).
	 *
	 * @param int $limit Maximum ids.
	 *
	 * @return int[]
	 */
	public function trending_ids( int $limit = 8 ): array {
		$limit = max( 1, min( 100, $limit ) );
		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internal; values are prepared.
		$ids = $this->db->get_col(
			$this->db->prepare(
				"SELECT product_id FROM {$table} WHERE trending_score > 0 ORDER BY trending_score DESC LIMIT %d",
				$limit
			)
		);

		return array_map( 'absint', (array) $ids );
	}

	/**
	 * Returns stat rows for a bounded list of products.
	 *
	 * @param int[] $product_ids Product ids.
	 *
	 * @return array<int, array{views_30d:int, units_30d:int, revenue_30d:float, bestseller_rank:int, trending_score:float}>
	 */
	public function for_products( array $product_ids ): array {
		$product_ids = array_values( array_unique( array_filter( array_map( 'absint', $product_ids ) ) ) );

		if ( [] === $product_ids ) {
			return [];
		}

		$product_ids  = array_slice( $product_ids, 0, 200 );
		$table        = $this->table();
		$placeholders = $this->placeholders( $product_ids );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Placeholders are generated from an integer-cast list.
		$rows = $this->db->get_results(
			$this->db->prepare(
				"SELECT product_id, views_30d, units_30d, revenue_30d, bestseller_rank, trending_score
				 FROM {$table} WHERE product_id IN ({$placeholders})",
				$product_ids
			),
			ARRAY_A
		);

		$stats = [];

		foreach ( (array) $rows as $row ) {
			$stats[ (int) $row['product_id'] ] = [
				'views_30d'       => (int) $row['views_30d'],
				'units_30d'       => (int) $row['units_30d'],
				'revenue_30d'     => (float) $row['revenue_30d'],
				'bestseller_rank' => (int) $row['bestseller_rank'],
				'trending_score'  => (float) $row['trending_score'],
			];
		}

		return $stats;
	}

	/**
	 * Inserts or updates a single stats row.
	 *
	 * @param int                  $product_id Product id.
	 * @param array<string, mixed> $data       Column values.
	 */
	public function upsert( int $product_id, array $data ): bool {
		$product_id = absint( $product_id );

		if ( $product_id <= 0 ) {
			return false;
		}

		$row = [
			'product_id'      => $product_id,
			'views_30d'       => absint( $data['views_30d'] ?? 0 ),
			'units_30d'       => absint( $data['units_30d'] ?? 0 ),
			'revenue_30d'     => round( (float) ( $data['revenue_30d'] ?? 0 ), 2 ),
			'bestseller_rank' => absint( $data['bestseller_rank'] ?? 0 ),
			'trending_score'  => round( (float) ( $data['trending_score'] ?? 0 ), 5 ),
			'updated_at'      => current_time( 'mysql', true ),
		];

		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal table; values prepared below.
		$exists = (int) $this->db->get_var(
			$this->db->prepare( "SELECT COUNT(1) FROM {$table} WHERE product_id = %d", $product_id )
		);

		if ( $exists > 0 ) {
			unset( $row['product_id'] );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write.
			return false !== $this->db->update(
				$table,
				$row,
				[ 'product_id' => $product_id ],
				[ '%d', '%d', '%f', '%d', '%f', '%s' ],
				[ '%d' ]
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write.
		return false !== $this->db->insert(
			$table,
			$row,
			[ '%d', '%d', '%d', '%f', '%d', '%f', '%s' ]
		);
	}

	/**
	 * Adds view counts for many products in a single statement.
	 *
	 * The view counter is flushed from a cache bucket by a scheduled job, so a
	 * product page view costs zero writes; this method turns a whole bucket
	 * into one `INSERT ... ON DUPLICATE KEY UPDATE`.
	 *
	 * @param array<int, int> $counts product_id => view increment.
	 */
	public function add_views( array $counts ): int {
		$clean = [];

		foreach ( $counts as $product_id => $views ) {
			$product_id = absint( $product_id );
			$views      = absint( $views );

			if ( $product_id > 0 && $views > 0 ) {
				$clean[ $product_id ] = $views;
			}
		}

		if ( [] === $clean ) {
			return 0;
		}

		$table   = $this->table();
		$updated = 0;
		$now     = current_time( 'mysql', true );

		foreach ( array_chunk( $clean, 100, true ) as $chunk ) {
			$values       = [];
			$placeholders = [];

			foreach ( $chunk as $product_id => $views ) {
				$placeholders[] = '(%d, %d, %s)';
				$values[]       = $product_id;
				$values[]       = $views;
				$values[]       = $now;
			}

			$sql = "INSERT INTO {$table} (product_id, views_30d, updated_at) VALUES " . implode( ', ', $placeholders )
				. ' ON DUPLICATE KEY UPDATE views_30d = views_30d + VALUES(views_30d), updated_at = VALUES(updated_at)';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Placeholders built above, values passed to prepare().
			$result = $this->db->query( $this->db->prepare( $sql, $values ) );

			if ( false !== $result ) {
				$updated += count( $chunk );
			}
		}

		return $updated;
	}

	/**
	 * Clears every ranking so a rebuild starts from a clean slate.
	 */
	public function reset_ranks(): void {
		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Internal table, no user input.
		$this->db->query( "UPDATE {$table} SET bestseller_rank = 0" );
	}

	/**
	 * Deletes stat rows for products that no longer exist.
	 *
	 * Derived tables do not cascade when a product is deleted, so without this
	 * a bestseller ranking can keep pointing at products that are gone — which
	 * silently empties the homepage rail rather than throwing an error.
	 *
	 * @return int Rows removed.
	 */
	public function prune_orphans(): int {
		$table = $this->table();
		$posts = $this->db->posts;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Internal tables, no user input.
		$removed = $this->db->query(
			"DELETE s FROM {$table} s
			 LEFT JOIN {$posts} p ON p.ID = s.product_id AND p.post_type IN ('product','product_variation') AND p.post_status <> 'trash'
			 WHERE p.ID IS NULL"
		);

		if ( false !== $removed ) {
			return (int) $removed;
		}

		// Some MySQL-compatible engines reject multi-table DELETE. Fall back to
		// a bounded two-step delete rather than leaving orphans behind.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Internal tables, no user input.
		$ids = $this->db->get_col(
			"SELECT s.product_id FROM {$table} s
			 LEFT JOIN {$posts} p ON p.ID = s.product_id AND p.post_type IN ('product','product_variation') AND p.post_status <> 'trash'
			 WHERE p.ID IS NULL LIMIT 500"
		);

		$ids = array_map( 'absint', (array) $ids );

		if ( [] === $ids ) {
			return 0;
		}

		$placeholders = $this->placeholders( $ids );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Placeholders built from integer ids.
		$deleted = $this->db->query(
			$this->db->prepare( "DELETE FROM {$table} WHERE product_id IN ({$placeholders})", $ids )
		);

		return false === $deleted ? 0 : (int) $deleted;
	}

	/**
	 * Removes every row. Used by `wp bhc demo reset`.
	 */
	public function truncate(): void {
		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Internal table, no user input.
		$this->db->query( "DELETE FROM {$table}" );
	}

	/**
	 * Number of stat rows currently stored.
	 */
	public function count(): int {
		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Internal table, no user input.
		return (int) $this->db->get_var( "SELECT COUNT(1) FROM {$table}" );
	}
}
