<?php
/**
 * Wishlist persistence.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Wishlist;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Database\AbstractRepository;
use BoneHornCrafts\Core\Database\Schema;

/**
 * SQL layer for the `bhc_wishlist` table.
 *
 * Every statement is prepared, every id is cast to an integer first, and every
 * read is bounded by an explicit limit.
 */
final class WishlistRepository extends AbstractRepository {

	/**
	 * {@inheritDoc}
	 */
	protected function table(): string {
		return Schema::table( Schema::TABLE_WISHLIST );
	}

	/**
	 * Adds a product to a customer's list.
	 *
	 * The unique key on (user_id, product_id) makes a duplicate insert fail
	 * cheaply instead of requiring a read-then-write race.
	 *
	 * @param int $user_id    Customer id.
	 * @param int $product_id Product id.
	 */
	public function add( int $user_id, int $product_id ): bool {
		$user_id    = absint( $user_id );
		$product_id = absint( $product_id );

		if ( $user_id <= 0 || $product_id <= 0 ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write.
		$inserted = $this->db->query(
			$this->db->prepare(
				sprintf(
					'INSERT IGNORE INTO %s (user_id, list_token, product_id, date_added) VALUES (%%d, %%s, %%d, %%s)',
					$this->table()
				),
				$user_id,
				'',
				$product_id,
				current_time( 'mysql', true )
			)
		);

		return (int) $inserted > 0;
	}

	/**
	 * Removes a product from a customer's list.
	 *
	 * @param int $user_id    Customer id.
	 * @param int $product_id Product id.
	 */
	public function remove( int $user_id, int $product_id ): bool {
		$user_id    = absint( $user_id );
		$product_id = absint( $product_id );

		if ( $user_id <= 0 || $product_id <= 0 ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write.
		return (bool) $this->db->delete(
			$this->table(),
			[
				'user_id'    => $user_id,
				'product_id' => $product_id,
			],
			[ '%d', '%d' ]
		);
	}

	/**
	 * Returns a customer's product ids, newest first.
	 *
	 * @param int $user_id Customer id.
	 * @param int $limit   Maximum ids.
	 *
	 * @return int[]
	 */
	public function ids_for_user( int $user_id, int $limit = 60 ): array {
		$user_id = absint( $user_id );

		if ( $user_id <= 0 ) {
			return [];
		}

		$limit = max( 1, min( 200, $limit ) );
		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal table; values prepared.
		$ids = $this->db->get_col(
			$this->db->prepare(
				"SELECT product_id FROM {$table} WHERE user_id = %d ORDER BY date_added DESC, id DESC LIMIT %d",
				$user_id,
				$limit
			)
		);

		return array_map( 'absint', (array) $ids );
	}

	/**
	 * Whether a product is on a customer's list.
	 *
	 * @param int $user_id    Customer id.
	 * @param int $product_id Product id.
	 */
	public function has( int $user_id, int $product_id ): bool {
		$user_id    = absint( $user_id );
		$product_id = absint( $product_id );

		if ( $user_id <= 0 || $product_id <= 0 ) {
			return false;
		}

		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal table; values prepared.
		return (bool) $this->db->get_var(
			$this->db->prepare(
				"SELECT 1 FROM {$table} WHERE user_id = %d AND product_id = %d LIMIT 1",
				$user_id,
				$product_id
			)
		);
	}

	/**
	 * Counts a customer's saved products.
	 *
	 * @param int $user_id Customer id.
	 */
	public function count_for_user( int $user_id ): int {
		$user_id = absint( $user_id );

		if ( $user_id <= 0 ) {
			return 0;
		}

		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal table; values prepared.
		return (int) $this->db->get_var(
			$this->db->prepare( "SELECT COUNT(1) FROM {$table} WHERE user_id = %d", $user_id )
		);
	}

	/**
	 * Empties a customer's list.
	 *
	 * @param int $user_id Customer id.
	 */
	public function clear( int $user_id ): int {
		$user_id = absint( $user_id );

		if ( $user_id <= 0 ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write.
		return (int) $this->db->delete( $this->table(), [ 'user_id' => $user_id ], [ '%d' ] );
	}

	/**
	 * Adds several products in one statement (guest list merge on login).
	 *
	 * @param int   $user_id     Customer id.
	 * @param int[] $product_ids Product ids.
	 */
	public function bulk_add( int $user_id, array $product_ids ): int {
		$user_id     = absint( $user_id );
		$product_ids = array_slice( array_values( array_unique( array_filter( array_map( 'absint', $product_ids ) ) ) ), 0, 100 );

		if ( $user_id <= 0 || [] === $product_ids ) {
			return 0;
		}

		$now          = current_time( 'mysql', true );
		$placeholders = [];
		$values       = [];

		foreach ( $product_ids as $product_id ) {
			$placeholders[] = '(%d, %s, %d, %s)';
			$values[]       = $user_id;
			$values[]       = '';
			$values[]       = $product_id;
			$values[]       = $now;
		}

		$sql = sprintf(
			'INSERT IGNORE INTO %s (user_id, list_token, product_id, date_added) VALUES %s',
			$this->table(),
			implode( ', ', $placeholders )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Placeholders generated above; values prepared.
		$result = $this->db->query( $this->db->prepare( $sql, $values ) );

		return false === $result ? 0 : (int) $result;
	}

	/**
	 * Most saved products, used as a merchandising signal.
	 *
	 * @param int $limit Maximum products.
	 *
	 * @return array<int, int> product_id => save count.
	 */
	public function most_saved( int $limit = 12 ): array {
		$limit = max( 1, min( 100, $limit ) );
		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal table; values prepared.
		$rows = $this->db->get_results(
			$this->db->prepare(
				"SELECT product_id, COUNT(1) AS saves FROM {$table} GROUP BY product_id ORDER BY saves DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		$counts = [];

		foreach ( (array) $rows as $row ) {
			$counts[ (int) $row['product_id'] ] = (int) $row['saves'];
		}

		return $counts;
	}

	/**
	 * Deletes rows for products that no longer exist. Housekeeping job helper.
	 *
	 * @param int $batch_size Maximum rows removed per call.
	 */
	public function prune_orphans( int $batch_size = 200 ): int {
		$table = $this->table();
		$posts = $this->db->posts;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal tables; value prepared.
		$ids = $this->db->get_col(
			$this->db->prepare(
				"SELECT w.id FROM {$table} w LEFT JOIN {$posts} p ON p.ID = w.product_id
				 WHERE p.ID IS NULL OR p.post_status = 'trash' LIMIT %d",
				max( 1, min( 1000, $batch_size ) )
			)
		);

		$ids = array_map( 'absint', (array) $ids );

		if ( [] === $ids ) {
			return 0;
		}

		$placeholders = $this->placeholders( $ids );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Placeholders built from integer ids.
		$deleted = $this->db->query(
			$this->db->prepare( "DELETE FROM {$table} WHERE id IN ({$placeholders})", $ids )
		);

		return false === $deleted ? 0 : (int) $deleted;
	}

	/**
	 * Total rows. Used by the admin dashboard and demo reset.
	 */
	public function total(): int {
		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Internal table, no user input.
		return (int) $this->db->get_var( "SELECT COUNT(1) FROM {$table}" );
	}
}
