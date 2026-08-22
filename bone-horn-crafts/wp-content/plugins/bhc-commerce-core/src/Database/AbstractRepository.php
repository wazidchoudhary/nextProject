<?php
/**
 * Base repository.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Database;

defined( 'ABSPATH' ) || exit;

use wpdb;

/**
 * Shared `$wpdb` plumbing for the custom-table repositories.
 *
 * `$wpdb` is injected rather than pulled from the global inside each method,
 * which is what makes the repositories testable and keeps the SQL in one layer.
 */
abstract class AbstractRepository {

	/**
	 * Database handle.
	 */
	protected wpdb $db;

	/**
	 * Constructor.
	 *
	 * @param wpdb|null $db Database handle. Defaults to the global $wpdb.
	 */
	public function __construct( ?wpdb $db = null ) {
		global $wpdb;

		$this->db = $db ?? $wpdb;
	}

	/**
	 * Fully qualified table name for this repository.
	 */
	abstract protected function table(): string;

	/**
	 * Builds a `%d` placeholder list for an id array.
	 *
	 * Ids are cast with `absint()` before they reach `prepare()`, so the
	 * generated placeholder list can never carry user input.
	 *
	 * @param int[] $ids Ids.
	 */
	protected function placeholders( array $ids ): string {
		return implode( ',', array_fill( 0, count( $ids ), '%d' ) );
	}

	/**
	 * Returns the last database error, if any.
	 */
	protected function last_error(): string {
		return (string) $this->db->last_error;
	}
}
