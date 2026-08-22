<?php
/**
 * Cache invalidation listener.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Cache;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Contracts\HookableInterface;
use BoneHornCrafts\Core\Contracts\LoggerInterface;

/**
 * Maps store events to cache group invalidations.
 *
 * Invalidation lives in one class on purpose: when a cached value goes stale in
 * production you want a single file to read, not a `wp_cache_delete()` call
 * sprinkled through twenty services.
 */
final class Invalidator implements HookableInterface {

	public const GROUP_PRODUCTS        = 'products';
	public const GROUP_RECOMMENDATIONS = 'recommendations';
	public const GROUP_SEARCH          = 'search';
	public const GROUP_FACETS          = 'facets';
	public const GROUP_STATS           = 'stats';
	public const GROUP_SEO             = 'seo';

	/**
	 * Every group the plugin owns.
	 *
	 * @var string[]
	 */
	public const ALL_GROUPS = [
		self::GROUP_PRODUCTS,
		self::GROUP_RECOMMENDATIONS,
		self::GROUP_SEARCH,
		self::GROUP_FACETS,
		self::GROUP_STATS,
		self::GROUP_SEO,
	];

	/**
	 * Groups already flushed during this request (flushing twice is wasted I/O).
	 *
	 * @var array<string, bool>
	 */
	private array $flushed = [];

	/**
	 * Constructor.
	 *
	 * @param CacheManager    $cache  Cache manager.
	 * @param LoggerInterface $logger Logger.
	 */
	public function __construct( private CacheManager $cache, private LoggerInterface $logger ) {}

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'woocommerce_new_product', [ $this, 'on_product_change' ], 10, 1 );
		add_action( 'woocommerce_update_product', [ $this, 'on_product_change' ], 10, 1 );
		add_action( 'woocommerce_delete_product', [ $this, 'on_product_change' ], 10, 1 );
		add_action( 'woocommerce_trash_product', [ $this, 'on_product_change' ], 10, 1 );
		add_action( 'woocommerce_product_set_stock', [ $this, 'on_stock_change' ], 10, 1 );
		add_action( 'woocommerce_variation_set_stock', [ $this, 'on_stock_change' ], 10, 1 );

		add_action( 'created_product_cat', [ $this, 'on_taxonomy_change' ] );
		add_action( 'edited_product_cat', [ $this, 'on_taxonomy_change' ] );
		add_action( 'delete_product_cat', [ $this, 'on_taxonomy_change' ] );

		add_action( 'woocommerce_order_status_completed', [ $this, 'on_order_completed' ] );

		add_action( 'bhc_flush_all_caches', [ $this, 'flush_all' ] );
	}

	/**
	 * Flushes product derived caches.
	 *
	 * @param int|object $product Product id or object.
	 */
	public function on_product_change( $product = 0 ): void {
		$this->flush( self::GROUP_PRODUCTS, self::GROUP_RECOMMENDATIONS, self::GROUP_SEARCH, self::GROUP_FACETS, self::GROUP_SEO );
	}

	/**
	 * Flushes caches that embed stock state.
	 *
	 * @param mixed $product Product object.
	 */
	public function on_stock_change( $product = null ): void {
		$this->flush( self::GROUP_PRODUCTS, self::GROUP_SEO );
	}

	/**
	 * Flushes taxonomy derived caches.
	 *
	 * @param int $term_id Term id.
	 */
	public function on_taxonomy_change( int $term_id = 0 ): void {
		$this->flush( self::GROUP_FACETS, self::GROUP_SEARCH );
	}

	/**
	 * Flushes merchandising caches once an order completes.
	 *
	 * @param int $order_id Order id.
	 */
	public function on_order_completed( int $order_id = 0 ): void {
		$this->flush( self::GROUP_STATS );
	}

	/**
	 * Flushes every group the plugin owns.
	 */
	public function flush_all(): void {
		$this->flush( ...self::ALL_GROUPS );
	}

	/**
	 * Flushes the given groups once per request.
	 *
	 * @param string ...$groups Group names.
	 */
	private function flush( string ...$groups ): void {
		$flushed = [];

		foreach ( $groups as $group ) {
			if ( isset( $this->flushed[ $group ] ) ) {
				continue;
			}

			$this->flushed[ $group ] = true;
			$flushed[]               = $group;

			$this->cache->flush_group( $group );
		}

		if ( [] !== $flushed ) {
			$this->logger->debug( 'cache.flushed', [ 'groups' => $flushed ] );
		}
	}
}
