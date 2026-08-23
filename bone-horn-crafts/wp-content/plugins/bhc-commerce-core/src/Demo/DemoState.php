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

		$this->state = $state;

		return $this->state;
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
	 * Counts objects that still exist, not ids that were ever created. The
	 * distinction matters: the state is an append-only record, so something
	 * deleted since — a product removed by hand, a variation the seeder
	 * withdrew — would otherwise still be counted and the command would report
	 * a store that is not there. `product` and `product_variation` are also
	 * reported separately, because "252 products" for a 60-product catalogue is
	 * exactly the kind of number nobody checks.
	 *
	 * @return array<string, int>
	 */
	public function summary(): array {
		$summary = [];

		foreach ( $this->all() as $bucket => $ids ) {
			if ( 'products' === $bucket ) {
				$summary['products']   = 0;
				$summary['variations'] = 0;

				foreach ( $ids as $id ) {
					$type = get_post_type( $id );

					if ( 'product' === $type ) {
						++$summary['products'];
					} elseif ( 'product_variation' === $type ) {
						++$summary['variations'];
					}
				}

				continue;
			}

			$summary[ $bucket ] = count( array_filter( $ids, fn ( int $id ): bool => $this->exists( $bucket, $id ) ) );
		}

		return $summary;
	}

	/**
	 * Whether a tracked object still exists.
	 *
	 * @param string $bucket Bucket name.
	 * @param int    $id     Object id.
	 */
	private function exists( string $bucket, int $id ): bool {
		return match ( $bucket ) {
			'orders'    => function_exists( 'wc_get_order' ) && false !== wc_get_order( $id ),
			'customers' => false !== get_userdata( $id ),
			'comments'  => null !== get_comment( $id ),
			'terms', 'menus' => ! is_wp_error( get_term( $id ) ) && null !== get_term( $id ),
			'zones'     => class_exists( \WC_Shipping_Zones::class )
				&& null !== \WC_Shipping_Zones::get_zone( $id ),
			default     => null !== get_post( $id ),
		};
	}

	/**
	 * Clears the recorded state.
	 */
	public function forget(): void {
		$this->state = null;

		delete_option( self::OPTION );
	}

	/**
	 * Clears only the named buckets, leaving the rest recorded.
	 *
	 * A partial reset removes some of what was seeded and spares the rest, so
	 * the state has to survive it: dropping the whole option would strand the
	 * pages, menus and shipping zones that were deliberately kept, leaving a
	 * later full reset with no record of them.
	 *
	 * @param string[] $buckets Buckets to clear.
	 */
	public function forget_buckets( array $buckets ): void {
		$state = $this->all();

		foreach ( $buckets as $bucket ) {
			if ( isset( $state[ $bucket ] ) ) {
				$state[ $bucket ] = [];
			}
		}

		$this->state = $state;

		update_option( self::OPTION, $state, false );
	}
}
