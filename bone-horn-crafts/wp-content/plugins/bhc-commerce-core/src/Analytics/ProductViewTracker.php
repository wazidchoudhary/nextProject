<?php
/**
 * Buffered product view counter.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Analytics;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Cache\CacheManager;
use BoneHornCrafts\Core\Contracts\HookableInterface;

/**
 * Counts product views without writing to the database on a page view.
 *
 * A page view is the worst possible moment to run an `UPDATE`: it is the
 * highest-traffic request on the site, it is usually cacheable, and the write
 * lock lands on the exact rows the catalogue queries are reading. Instead each
 * view increments a small buffer in the object cache, and a scheduled job
 * flushes the whole buffer into `bhc_product_stats` in one statement.
 *
 * The trade-off is explicit: counts are approximate under heavy concurrency
 * and are lost if the cache is flushed before the job runs. For merchandising
 * ("what is trending this week") that is entirely acceptable; nothing
 * financial depends on it.
 */
final class ProductViewTracker implements HookableInterface {

	public const BUFFER_KEY = 'view_buffer';

	/**
	 * Constructor.
	 *
	 * @param CacheManager $cache Cache manager.
	 */
	public function __construct( private CacheManager $cache ) {}

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'template_redirect', [ $this, 'track' ], 20 );
	}

	/**
	 * Buffers a view for the product being displayed.
	 */
	public function track(): void {
		if ( is_admin() || ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		// Staff previewing the catalogue would otherwise skew the ranking.
		if ( current_user_can( 'edit_products' ) ) {
			return;
		}

		$product_id = (int) get_queried_object_id();

		if ( $product_id > 0 ) {
			$this->record( $product_id );
		}
	}

	/**
	 * Adds one view to the buffer.
	 *
	 * @param int $product_id Product id.
	 */
	public function record( int $product_id ): void {
		$product_id = absint( $product_id );

		if ( $product_id <= 0 ) {
			return;
		}

		$cache  = $this->cache->for_group( 'stats' );
		$buffer = (array) $cache->get( self::BUFFER_KEY, [] );

		$buffer[ $product_id ] = (int) ( $buffer[ $product_id ] ?? 0 ) + 1;

		// Cap the buffer so a bot crawl cannot grow it without bound.
		if ( count( $buffer ) > 500 ) {
			arsort( $buffer );

			$buffer = array_slice( $buffer, 0, 500, true );
		}

		$cache->set( self::BUFFER_KEY, $buffer, DAY_IN_SECONDS );
	}

	/**
	 * Returns and clears the buffer.
	 *
	 * @return array<int, int>
	 */
	public function drain(): array {
		$cache  = $this->cache->for_group( 'stats' );
		$buffer = (array) $cache->get( self::BUFFER_KEY, [] );

		$cache->delete( self::BUFFER_KEY );

		$clean = [];

		foreach ( $buffer as $product_id => $views ) {
			$product_id = absint( $product_id );
			$views      = absint( $views );

			if ( $product_id > 0 && $views > 0 ) {
				$clean[ $product_id ] = $views;
			}
		}

		return $clean;
	}

	/**
	 * Current buffer size, for the admin health screen.
	 */
	public function buffered_count(): int {
		return count( (array) $this->cache->for_group( 'stats' )->get( self::BUFFER_KEY, [] ) );
	}
}
