<?php
/**
 * Product affinity index repository.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Recommendations;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Database\AbstractRepository;
use BoneHornCrafts\Core\Database\Schema;

/**
 * Reads and writes the pre-computed `bhc_product_affinity` index.
 *
 * The index is built by a nightly Action Scheduler job. At request time a
 * "frequently bought together" block is one indexed `SELECT ... ORDER BY score
 * LIMIT n` instead of a scan across order items.
 */
final class AffinityRepository extends AbstractRepository {

	public const STRATEGY_BOUGHT_TOGETHER = 'bought_together';
	public const STRATEGY_VIEWED_TOGETHER = 'viewed_together';

	/**
	 * {@inheritDoc}
	 */
	protected function table(): string {
		return Schema::table( Schema::TABLE_AFFINITY );
	}

	/**
	 * Returns related product ids with their scores.
	 *
	 * @param int    $product_id Seed product id.
	 * @param int    $limit      Maximum rows.
	 * @param string $strategy   Strategy key.
	 *
	 * @return array<int, float> related_id => score.
	 */
	public function related( int $product_id, int $limit = 8, string $strategy = self::STRATEGY_BOUGHT_TOGETHER ): array {
		$product_id = absint( $product_id );

		if ( $product_id <= 0 ) {
			return [];
		}

		$limit = max( 1, min( 50, $limit ) );
		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal table; values prepared.
		$rows = $this->db->get_results(
			$this->db->prepare(
				"SELECT related_id, score FROM {$table}
				 WHERE product_id = %d AND strategy = %s
				 ORDER BY score DESC, related_id ASC
				 LIMIT %d",
				$product_id,
				sanitize_key( $strategy ),
				$limit
			),
			ARRAY_A
		);

		$related = [];

		foreach ( (array) $rows as $row ) {
			$related[ (int) $row['related_id'] ] = (float) $row['score'];
		}

		return $related;
	}

	/**
	 * Replaces the stored affinities for one seed product.
	 *
	 * Written as delete-then-insert inside one call so a partially rebuilt
	 * index never mixes yesterday's and today's scores for the same product.
	 *
	 * @param int               $product_id Seed product id.
	 * @param array<int, float> $scores     related_id => score.
	 * @param string            $strategy   Strategy key.
	 */
	public function replace_for_product( int $product_id, array $scores, string $strategy = self::STRATEGY_BOUGHT_TOGETHER ): int {
		$product_id = absint( $product_id );
		$strategy   = sanitize_key( $strategy );

		if ( $product_id <= 0 ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write.
		$this->db->delete(
			$this->table(),
			[
				'product_id' => $product_id,
				'strategy'   => $strategy,
			],
			[ '%d', '%s' ]
		);

		$rows = [];

		foreach ( $scores as $related_id => $score ) {
			$related_id = absint( $related_id );
			$score      = round( (float) $score, 5 );

			if ( $related_id <= 0 || $related_id === $product_id || $score <= 0 ) {
				continue;
			}

			$rows[ $related_id ] = $score;
		}

		if ( [] === $rows ) {
			return 0;
		}

		arsort( $rows );

		$rows         = array_slice( $rows, 0, 20, true );
		$now          = current_time( 'mysql', true );
		$placeholders = [];
		$values       = [];

		foreach ( $rows as $related_id => $score ) {
			$placeholders[] = '(%d, %d, %s, %f, %s)';
			$values[]       = $product_id;
			$values[]       = $related_id;
			$values[]       = $strategy;
			$values[]       = $score;
			$values[]       = $now;
		}

		$sql = sprintf(
			'INSERT INTO %s (product_id, related_id, strategy, score, updated_at) VALUES %s',
			$this->table(),
			implode( ', ', $placeholders )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Placeholders generated above; values prepared.
		$result = $this->db->query( $this->db->prepare( $sql, $values ) );

		return false === $result ? 0 : count( $rows );
	}

	/**
	 * Removes index rows that reference a product (after it is deleted).
	 *
	 * @param int $product_id Product id.
	 */
	public function forget_product( int $product_id ): void {
		$product_id = absint( $product_id );

		if ( $product_id <= 0 ) {
			return;
		}

		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal table; values prepared.
		$this->db->query(
			$this->db->prepare( "DELETE FROM {$table} WHERE product_id = %d OR related_id = %d", $product_id, $product_id )
		);
	}

	/**
	 * Number of indexed rows, for the admin dashboard.
	 */
	public function total(): int {
		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Internal table, no user input.
		return (int) $this->db->get_var( "SELECT COUNT(1) FROM {$table}" );
	}

	/**
	 * Timestamp of the most recent index write.
	 */
	public function last_updated(): string {
		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Internal table, no user input.
		return (string) $this->db->get_var( "SELECT MAX(updated_at) FROM {$table}" );
	}

	/**
	 * Deletes index rows that reference products which no longer exist.
	 *
	 * @return int Rows removed.
	 */
	public function prune_orphans(): int {
		$table = $this->table();
		$posts = $this->db->posts;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Internal tables, no user input.
		$ids = $this->db->get_col(
			"SELECT DISTINCT a.product_id FROM {$table} a
			 LEFT JOIN {$posts} p ON p.ID = a.product_id AND p.post_status <> 'trash'
			 WHERE p.ID IS NULL LIMIT 500"
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Internal tables, no user input.
		$related = $this->db->get_col(
			"SELECT DISTINCT a.related_id FROM {$table} a
			 LEFT JOIN {$posts} p ON p.ID = a.related_id AND p.post_status <> 'trash'
			 WHERE p.ID IS NULL LIMIT 500"
		);

		$removed = 0;

		foreach ( array_map( 'absint', array_merge( (array) $ids, (array) $related ) ) as $product_id ) {
			if ( $product_id <= 0 ) {
				continue;
			}

			$this->forget_product( $product_id );

			++$removed;
		}

		return $removed;
	}

	/**
	 * Empties the index. Used by `wp bhc demo reset` and full rebuilds.
	 */
	public function truncate(): void {
		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Internal table, no user input.
		$this->db->query( "DELETE FROM {$table}" );
	}
}
