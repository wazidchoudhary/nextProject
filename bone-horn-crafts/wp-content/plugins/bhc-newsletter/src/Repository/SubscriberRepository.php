<?php
/**
 * Subscriber storage.
 *
 * @package BoneHornCrafts\Newsletter
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Newsletter\Repository;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Newsletter\Database\SchemaInstaller;
use BoneHornCrafts\Newsletter\Domain\Subscriber;
use BoneHornCrafts\Newsletter\Domain\SubscriberStatus;

/**
 * Every read and write against the subscriber table.
 *
 * The only class in the plugin that knows SQL exists. Services above it work in
 * `Subscriber` objects and `SubscriberStatus` cases, which is what keeps the
 * confirmation flow testable without a database.
 */
final class SubscriberRepository {

	/**
	 * Finds a subscriber by email.
	 *
	 * @param string $email Email address.
	 */
	public function find_by_email( string $email ): ?Subscriber {
		global $wpdb;

		$table = SchemaInstaller::table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- A table identifier cannot be bound as a parameter; it is built from $wpdb->prefix and never from input.
		$sql = "SELECT * FROM {$table} WHERE email = %s LIMIT 1";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Plugin-owned table; the SQL above is a constant with placeholders and the value is bound below.
		$row = $wpdb->get_row( $wpdb->prepare( $sql, $this->normalise( $email ) ), ARRAY_A );

		return is_array( $row ) ? Subscriber::from_row( $row ) : null;
	}

	/**
	 * Finds a subscriber by their confirmation token.
	 *
	 * @param string $token Token from a confirmation or unsubscribe link.
	 */
	public function find_by_token( string $token ): ?Subscriber {
		if ( '' === $token ) {
			return null;
		}

		global $wpdb;

		$table = SchemaInstaller::table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- As above.
		$sql = "SELECT * FROM {$table} WHERE token = %s LIMIT 1";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Plugin-owned table; the SQL above is a constant with placeholders and the value is bound below.
		$row = $wpdb->get_row( $wpdb->prepare( $sql, $token ), ARRAY_A );

		return is_array( $row ) ? Subscriber::from_row( $row ) : null;
	}

	/**
	 * Inserts a new pending subscriber.
	 *
	 * @param string $email   Email address.
	 * @param string $token   Confirmation token.
	 * @param string $source  Capture point, e.g. "footer".
	 * @param string $ip_hash Hashed client address, or an empty string.
	 *
	 * @return int Row id, or 0 on failure.
	 */
	public function insert( string $email, string $token, string $source, string $ip_hash = '' ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Plugin-owned table whose name is built from $wpdb->prefix and cannot be bound; every value is a placeholder. Reads are per-request and small enough not to warrant a cache layer.
		$inserted = $wpdb->insert(
			SchemaInstaller::table(),
			[
				'email'      => $this->normalise( $email ),
				'status'     => SubscriberStatus::Pending->value,
				'token'      => $token,
				'source'     => substr( $source, 0, 60 ),
				'ip_hash'    => $ip_hash,
				'created_at' => current_time( 'mysql', true ),
			],
			[ '%s', '%s', '%s', '%s', '%s', '%s' ]
		);

		return $inserted ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Moves a subscriber to a new state.
	 *
	 * @param int              $id     Row id.
	 * @param SubscriberStatus $status New state.
	 */
	public function set_status( int $id, SubscriberStatus $status ): bool {
		global $wpdb;

		$data = [ 'status' => $status->value ];

		// The timestamps are part of the state change, not separate bookkeeping:
		// a confirmed row without a confirmation time cannot be audited later.
		if ( SubscriberStatus::Confirmed === $status ) {
			$data['confirmed_at'] = current_time( 'mysql', true );
			$data['ended_at']     = null;
		}

		if ( SubscriberStatus::Unsubscribed === $status ) {
			$data['ended_at'] = current_time( 'mysql', true );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Plugin-owned table whose name is built from $wpdb->prefix and cannot be bound; every value is a placeholder. Reads are per-request and small enough not to warrant a cache layer.
		return false !== $wpdb->update( SchemaInstaller::table(), $data, [ 'id' => $id ] );
	}

	/**
	 * Issues a fresh token for a subscriber.
	 *
	 * Used when someone re-subscribes after unsubscribing: the old token may
	 * have been in an email that has since been forwarded, and a link that
	 * still works is a link someone else can use.
	 *
	 * @param int    $id    Row id.
	 * @param string $token New token.
	 */
	public function set_token( int $id, string $token ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Plugin-owned table whose name is built from $wpdb->prefix and cannot be bound; every value is a placeholder. Reads are per-request and small enough not to warrant a cache layer.
		return false !== $wpdb->update(
			SchemaInstaller::table(),
			[ 'token' => $token ],
			[ 'id' => $id ]
		);
	}

	/**
	 * Deletes a subscriber outright.
	 *
	 * Offered for erasure requests. Ordinary opt-outs use
	 * {@see self::set_status()} so the opt-out itself survives.
	 *
	 * @param int $id Row id.
	 */
	public function delete( int $id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Plugin-owned table whose name is built from $wpdb->prefix and cannot be bound; every value is a placeholder. Reads are per-request and small enough not to warrant a cache layer.
		return false !== $wpdb->delete( SchemaInstaller::table(), [ 'id' => $id ], [ '%d' ] );
	}

	/**
	 * Counts subscribers per state.
	 *
	 * @return array<string, int>
	 */
	public function counts(): array {
		global $wpdb;

		$counts = [];

		foreach ( SubscriberStatus::cases() as $case ) {
			$counts[ $case->value ] = 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Plugin-owned table whose name is built from $wpdb->prefix and cannot be bound; every value is a placeholder. Reads are per-request and small enough not to warrant a cache layer.
		$rows = $wpdb->get_results( 'SELECT status, COUNT(*) AS total FROM ' . SchemaInstaller::table() . ' GROUP BY status', ARRAY_A );

		foreach ( (array) $rows as $row ) {
			$counts[ (string) $row['status'] ] = (int) $row['total'];
		}

		return $counts;
	}

	/**
	 * Queries subscribers.
	 *
	 * @param array<string, mixed> $args status, search, per_page, page, orderby, order.
	 *
	 * @return array{items: Subscriber[], total: int}
	 */
	public function query( array $args = [] ): array {
		global $wpdb;

		$status   = SubscriberStatus::tryFrom( (string) ( $args['status'] ?? '' ) );
		$search   = trim( (string) ( $args['search'] ?? '' ) );
		$per_page = max( 1, min( 500, (int) ( $args['per_page'] ?? 50 ) ) );
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );

		$where  = [ '1=1' ];
		$params = [];

		if ( $status instanceof SubscriberStatus ) {
			$where[]  = 'status = %s';
			$params[] = $status->value;
		}

		if ( '' !== $search ) {
			$where[]  = 'email LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $search ) . '%';
		}

		// Allow-listed rather than interpolated: an ORDER BY cannot be bound as
		// a parameter, so the only safe version is one the caller cannot shape.
		$orderby = in_array( (string) ( $args['orderby'] ?? '' ), [ 'email', 'status', 'created_at' ], true )
			? (string) $args['orderby']
			: 'created_at';

		$order = 'asc' === strtolower( (string) ( $args['order'] ?? 'desc' ) ) ? 'ASC' : 'DESC';

		$table  = SchemaInstaller::table();
		$clause = implode( ' AND ', $where );
		$offset = ( $page - 1 ) * $per_page;

		$sql = "SELECT * FROM {$table} WHERE {$clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Plugin-owned table. Every value is a bound placeholder; the table name, ORDER BY column and direction are allow-listed above and cannot come from input.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $params, [ $per_page, $offset ] ) ), ARRAY_A );

		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$clause}";

		$total = $params
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Plugin-owned table whose name is built from $wpdb->prefix and cannot be bound; every value is a placeholder. Reads are per-request and small enough not to warrant a cache layer.
			? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) )
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Plugin-owned table whose name is built from $wpdb->prefix and cannot be bound; every value is a placeholder. Reads are per-request and small enough not to warrant a cache layer.
			: (int) $wpdb->get_var( $count_sql );

		return [
			'items' => array_map(
				static fn ( array $row ): Subscriber => Subscriber::from_row( $row ),
				(array) $rows
			),
			'total' => $total,
		];
	}

	/**
	 * Streams every matching subscriber to a callback, in batches.
	 *
	 * An export must not hold the whole list in memory: a store with 200,000
	 * subscribers would exhaust the PHP limit and produce a blank download
	 * rather than an error anyone can act on.
	 *
	 * @param callable             $callback Receives one Subscriber per call.
	 * @param array<string, mixed> $args     Same filters as {@see self::query()}.
	 */
	public function each( callable $callback, array $args = [] ): int {
		$page  = 1;
		$total = 0;

		do {
			$batch = $this->query(
				array_merge(
					$args,
					[
						'page'     => $page,
						'per_page' => 500,
					]
				)
			);

			foreach ( $batch['items'] as $subscriber ) {
				$callback( $subscriber );

				++$total;
			}

			++$page;
		} while ( [] !== $batch['items'] );

		return $total;
	}

	/**
	 * Lower-cases and trims an address so uniqueness means what people expect.
	 *
	 * @param string $email Raw address.
	 */
	private function normalise( string $email ): string {
		return strtolower( trim( $email ) );
	}
}
