<?php
/**
 * Demo dataset bookkeeping.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Demo;

defined( 'ABSPATH' ) || exit;

/**
 * Records exactly which objects the seeder created.
 *
 * This is what makes `wp bhc demo reset` safe: the reset only removes ids that
 * are listed here, so a product a real merchandiser added by hand is never
 * touched, even if it sits in a demo category. Nothing is matched by title,
 * slug or "looks like demo data" heuristics.
 */
final class DemoState {

	public const OPTION = 'bhc_demo_state';

	/**
	 * Loaded state.
	 *
	 * @var array<string, int[]>|null
	 */
	private ?array $state = null;

	/**
	 * Object buckets tracked by the seeder.
	 *
	 * @var string[]
	 */
	public const BUCKETS = [ 'products', 'attachments', 'terms', 'orders', 'customers', 'pages', 'posts', 'menus', 'comments', 'zones' ];

	/**
	 * Returns the full state.
	 *
	 * @return array<string, int[]>
	 */
	public function all(): array {
		if ( null !== $this->state ) {
			return $this->state;
		}

		$stored = get_option( self::OPTION, [] );
		$state  = [];

		foreach ( self::BUCKETS as $bucket ) {
			$ids = is_array( $stored ) && isset( $stored[ $bucket ] ) ? (array) $stored[ $bucket ] : [];

			$state[ $bucket ] = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
		}

		return $this->state = $state;
	}

	/**
	 * Returns the ids in one bucket.
	 *
	 * @param string $bucket Bucket name.
	 *
	 * @return int[]
	 */
	public function get( string $bucket ): array {
		return $this->all()[ $bucket ] ?? [];
	}

	/**
	 * Records one or more ids.
	 *
	 * @param string    $bucket Bucket name.
	 * @param int|int[] $ids    Ids to record.
	 */
	public function track( string $bucket, $ids ): void {
		if ( ! in_array( $bucket, self::BUCKETS, true ) ) {
			return;
		}

		$state = $this->all();

		$state[ $bucket ] = array_values(
			array_unique(
				array_filter(
					array_merge( $state[ $bucket ], array_map( 'absint', (array) $ids ) )
				)
			)
		);

		$this->state = $state;

		update_option( self::OPTION, $state, false );
	}

	/**
	 * Whether anything has been seeded.
	 */
	public function is_seeded(): bool {
		foreach ( $this->all() as $ids ) {
			if ( [] !== $ids ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Summary counts for `wp bhc demo status`.
	 *
	 * @return array<string, int>
	 */
	public function summary(): array {
		$summary = [];

		foreach ( $this->all() as $bucket => $ids ) {
			$summary[ $bucket ] = count( $ids );
		}

		return $summary;
	}

	/**
	 * Clears the recorded state.
	 */
	public function forget(): void {
		$this->state = null;

		delete_option( self::OPTION );
	}
}
