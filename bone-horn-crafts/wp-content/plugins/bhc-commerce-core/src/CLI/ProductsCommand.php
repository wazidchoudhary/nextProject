<?php
/**
 * Product index commands.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\CLI;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Analytics\MerchandisingIndexer;
use BoneHornCrafts\Core\Cache\CacheManager;
use BoneHornCrafts\Core\Cache\Invalidator;
use BoneHornCrafts\Core\Jobs\MerchandisingIndexJob;
use BoneHornCrafts\Core\Product\Attributes\AttributeRegistrar;
use BoneHornCrafts\Core\Product\ProductRepository;
use WP_CLI;

/**
 * `wp bhc products` — catalogue maintenance.
 */
final class ProductsCommand {

	/**
	 * Constructor.
	 *
	 * @param MerchandisingIndexJob $job         Index job.
	 * @param MerchandisingIndexer  $indexer     Indexer.
	 * @param AttributeRegistrar    $attributes  Attribute installer.
	 * @param ProductRepository     $products    Product read model.
	 * @param CacheManager          $cache       Cache manager.
	 */
	public function __construct(
		private MerchandisingIndexJob $job,
		private MerchandisingIndexer $indexer,
		private AttributeRegistrar $attributes,
		private ProductRepository $products,
		private CacheManager $cache
	) {}

	/**
	 * Rebuilds the merchandising indexes.
	 *
	 * ## OPTIONS
	 *
	 * [--job=<job>]
	 * : Which part to rebuild.
	 * ---
	 * default: all
	 * options:
	 *   - all
	 *   - stats
	 *   - affinity
	 *   - ranks
	 * ---
	 *
	 * [--attributes]
	 * : Also create any missing craft attributes and terms.
	 *
	 * [--batch=<size>]
	 * : Products per batch. Default 40.
	 *
	 * [--async]
	 * : Queue the work through Action Scheduler instead of running it now.
	 *
	 * ## EXAMPLES
	 *
	 *     wp bhc products sync
	 *     wp bhc products sync --job=affinity --batch=100
	 *     wp bhc products sync --async
	 *
	 * @param string[]              $args       Positional arguments.
	 * @param array<string, string> $assoc_args Options.
	 */
	public function sync( array $args, array $assoc_args ): void {
		$job   = (string) ( $assoc_args['job'] ?? 'all' );
		$batch = max( 5, min( 200, (int) ( $assoc_args['batch'] ?? 40 ) ) );

		if ( isset( $assoc_args['attributes'] ) ) {
			$result = $this->attributes->install();

			WP_CLI::log(
				sprintf(
					'Attributes: %d created, %d terms created.',
					(int) $result['created_attributes'],
					(int) $result['created_terms']
				)
			);
		}

		if ( isset( $assoc_args['async'] ) ) {
			$this->job->start();

			WP_CLI::success( 'Merchandising index queued through Action Scheduler.' );

			return;
		}

		if ( 'all' === $job ) {
			$processed = $this->job->run_sync();

			WP_CLI::success( sprintf( 'Merchandising index rebuilt (%d items processed).', $processed ) );

			return;
		}

		$page      = 1;
		$processed = 0;

		do {
			$ids = $this->products->query(
				[
					'limit'      => $batch,
					'page'       => $page,
					'orderby'    => 'menu_order',
					'order'      => 'ASC',
					'visibility' => 'any',
				]
			);

			if ( [] === $ids ) {
				break;
			}

			if ( 'stats' === $job ) {
				$processed += $this->indexer->rebuild_stats( $ids, 30 );
			} elseif ( 'affinity' === $job ) {
				$processed += $this->indexer->rebuild_affinity( $ids, 180 );
			}

			WP_CLI::log( sprintf( 'Batch %d: %d products.', $page, count( $ids ) ) );

			++$page;
		} while ( 'ranks' !== $job );

		if ( 'ranks' === $job ) {
			$processed = $this->indexer->rebuild_ranks( 60 );
		}

		$this->cache->flush_group( Invalidator::GROUP_STATS );
		$this->cache->flush_group( Invalidator::GROUP_RECOMMENDATIONS );

		WP_CLI::success( sprintf( '%s rebuilt (%d rows).', ucfirst( $job ), $processed ) );
	}
}
