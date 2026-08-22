<?php
/**
 * Cache commands.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\CLI;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Cache\CacheManager;
use BoneHornCrafts\Core\Cache\Invalidator;
use BoneHornCrafts\Core\Jobs\CacheWarmJob;
use WP_CLI;

/**
 * `wp bhc cache` — cache maintenance for deploys.
 */
final class CacheCommand {

	/**
	 * Constructor.
	 *
	 * @param CacheWarmJob $warm  Warm job.
	 * @param CacheManager $cache Cache manager.
	 */
	public function __construct( private CacheWarmJob $warm, private CacheManager $cache ) {}

	/**
	 * Warms the caches the storefront reads first.
	 *
	 * ## EXAMPLES
	 *
	 *     wp bhc cache warm
	 *
	 * @param string[]              $args       Positional arguments.
	 * @param array<string, string> $assoc_args Options.
	 */
	public function warm( array $args, array $assoc_args ): void {
		$warmed = $this->warm->run_sync();

		WP_CLI::success(
			sprintf(
				'Warmed %d cache entries using the %s store.',
				$warmed,
				$this->cache->store_name()
			)
		);
	}

	/**
	 * Flushes plugin cache groups.
	 *
	 * ## OPTIONS
	 *
	 * [--group=<group>]
	 * : Group to flush. Defaults to every plugin group.
	 * ---
	 * default: all
	 * options:
	 *   - all
	 *   - products
	 *   - recommendations
	 *   - search
	 *   - facets
	 *   - stats
	 *   - seo
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp bhc cache flush
	 *     wp bhc cache flush --group=recommendations
	 *
	 * @param string[]              $args       Positional arguments.
	 * @param array<string, string> $assoc_args Options.
	 */
	public function flush( array $args, array $assoc_args ): void {
		$group  = sanitize_key( (string) ( $assoc_args['group'] ?? 'all' ) );
		$groups = 'all' === $group ? Invalidator::ALL_GROUPS : [ $group ];

		foreach ( $groups as $name ) {
			$this->cache->flush_group( $name );

			WP_CLI::log( sprintf( 'Flushed cache group: %s', $name ) );
		}

		WP_CLI::success( sprintf( '%d cache group(s) flushed.', count( $groups ) ) );
	}

	/**
	 * Shows which cache backend is active.
	 *
	 * ## EXAMPLES
	 *
	 *     wp bhc cache status
	 *
	 * @param string[]              $args       Positional arguments.
	 * @param array<string, string> $assoc_args Options.
	 */
	public function status( array $args, array $assoc_args ): void {
		WP_CLI::log( sprintf( 'Store: %s', $this->cache->store_name() ) );
		WP_CLI::log( sprintf( 'Persistent: %s', $this->cache->is_persistent() ? 'yes' : 'no' ) );
		WP_CLI::log( sprintf( 'Sample key: %s', $this->cache->build_key( 'example' ) ) );
	}
}
