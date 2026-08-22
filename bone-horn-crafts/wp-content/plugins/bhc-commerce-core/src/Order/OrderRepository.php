<?php
/**
 * Order read model.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Order;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Cache\CacheManager;
use WC_Order;

/**
 * Bounded order queries used by the admin dashboard and the indexing jobs.
 *
 * Uses `wc_get_orders()` throughout, which routes to the HPOS tables when they
 * are enabled and to posts when they are not — the calling code never needs to
 * know which storage is active.
 */
final class OrderRepository {

	/**
	 * Constructor.
	 *
	 * @param CacheManager $cache Cache manager.
	 */
	public function __construct( private CacheManager $cache ) {}

	/**
	 * Recent orders for the admin dashboard.
	 *
	 * @param int $limit Maximum orders.
	 *
	 * @return WC_Order[]
	 */
	public function recent( int $limit = 5 ): array {
		$orders = wc_get_orders(
			[
				'limit'   => max( 1, min( 25, $limit ) ),
				'orderby' => 'date',
				'order'   => 'DESC',
				'type'    => 'shop_order',
			]
		);

		return array_values( array_filter( (array) $orders, static fn ( $order ): bool => $order instanceof WC_Order ) );
	}

	/**
	 * Order ids in a date window, paged for batch jobs.
	 *
	 * @param int    $days   Look-back window in days.
	 * @param int    $limit  Page size.
	 * @param int    $page   Page number (1 based).
	 * @param string[] $statuses Order statuses.
	 *
	 * @return int[]
	 */
	public function ids_since( int $days = 90, int $limit = 100, int $page = 1, array $statuses = [ 'wc-completed', 'wc-processing' ] ): array {
		$ids = wc_get_orders(
			[
				'limit'        => max( 1, min( 500, $limit ) ),
				'paged'        => max( 1, $page ),
				'orderby'      => 'date',
				'order'        => 'ASC',
				'return'       => 'ids',
				'status'       => $statuses,
				'date_created' => '>' . ( time() - ( max( 1, $days ) * DAY_IN_SECONDS ) ),
			]
		);

		return array_values( array_map( 'absint', (array) $ids ) );
	}

	/**
	 * Counts orders in a status. Cached briefly for the dashboard.
	 *
	 * @param string $status Status slug without the `wc-` prefix.
	 */
	public function count_by_status( string $status ): int {
		$status = sanitize_key( $status );

		return (int) $this->cache->for_group( 'stats' )->remember(
			'order_count_' . $status,
			static function () use ( $status ): int {
				$counts = function_exists( 'wc_get_order_count' ) ? wc_get_order_count( 'wc-' . $status ) : 0;

				return (int) $counts;
			},
			5 * MINUTE_IN_SECONDS
		);
	}

	/**
	 * Revenue in the last N days, for the dashboard tile.
	 *
	 * @param int $days Look-back window.
	 */
	public function revenue_last_days( int $days = 30 ): float {
		return (float) $this->cache->for_group( 'stats' )->remember(
			'revenue_' . $days,
			static function () use ( $days ): float {
				$orders = wc_get_orders(
					[
						'limit'        => 200,
						'status'       => [ 'wc-completed', 'wc-processing' ],
						'date_created' => '>' . ( time() - ( max( 1, $days ) * DAY_IN_SECONDS ) ),
					]
				);

				$total = 0.0;

				foreach ( (array) $orders as $order ) {
					if ( $order instanceof WC_Order ) {
						$total += (float) $order->get_total();
					}
				}

				return round( $total, 2 );
			},
			15 * MINUTE_IN_SECONDS
		);
	}
}
