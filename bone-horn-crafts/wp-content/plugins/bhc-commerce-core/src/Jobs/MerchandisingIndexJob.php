<?php
/**
 * Nightly merchandising index rebuild.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Jobs;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Analytics\MerchandisingIndexer;
use BoneHornCrafts\Core\Cache\CacheManager;
use BoneHornCrafts\Core\Cache\Invalidator;
use BoneHornCrafts\Core\Contracts\LoggerInterface;
use BoneHornCrafts\Core\Product\ProductRepository;
use BoneHornCrafts\Core\Support\Options;

/**
 * Rebuilds sales statistics, the bought-together index and the bestseller
 * ranking, one page of products at a time.
 *
 * This is the realistic background process the store depends on: the homepage
 * bestseller rail, the "Bestseller" badge and the product page
 * recommendations all read what this job writes. If it never ran, the store
 * would still work — every reader has a fallback — which is deliberate: a
 * failed index degrades merchandising, it does not break the shop.
 */
final class MerchandisingIndexJob extends AbstractBatchJob {

	public const HOOK = 'bhc_job_merchandising_index';

	/**
	 * Constructor.
	 *
	 * @param MerchandisingIndexer $indexer  Index builder.
	 * @param ProductRepository    $products Product read model.
	 * @param CacheManager         $cache    Cache manager.
	 * @param Options              $options  Settings.
	 * @param LoggerInterface      $logger   Logger.
	 */
	public function __construct(
		private MerchandisingIndexer $indexer,
		private ProductRepository $products,
		private CacheManager $cache,
		private Options $options,
		LoggerInterface $logger
	) {
		parent::__construct( $logger );
	}

	/**
	 * {@inheritDoc}
	 */
	public function hook(): string {
		return self::HOOK;
	}

	/**
	 * {@inheritDoc}
	 */
	public function batch_size(): int {
		return max( 10, min( 200, $this->options->int( 'index_batch_size' ) ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $args Batch arguments.
	 *
	 * @return array{processed:int, next:?array<string, mixed>}
	 */
	protected function handle_batch( array $args ): array {
		$page  = max( 1, absint( $args['page'] ?? 1 ) );
		$size  = $this->batch_size();

		$product_ids = $this->products->query(
			[
				'limit'      => $size,
				'page'       => $page,
				'orderby'    => 'menu_order',
				'order'      => 'ASC',
				'visibility' => 'any',
			]
		);

		if ( [] === $product_ids ) {
			// Final pass. Orphan rows are pruned *before* ranking, because a
			// rank assigned to a deleted product silently empties the rail that
			// reads it.
			$pruned = $this->indexer->prune_orphans();
			$ranked = $this->indexer->rebuild_ranks( max( 10, $this->options->int( 'bestseller_limit' ) * 5 ) );

			$this->logger->info(
				'index.finalised',
				[
					'pruned' => $pruned,
					'ranked' => $ranked,
				]
			);

			$this->cache->flush_group( Invalidator::GROUP_STATS );
			$this->cache->flush_group( Invalidator::GROUP_RECOMMENDATIONS );

			return [
				'processed' => $ranked,
				'next'      => null,
			];
		}

		$stats_written = $this->indexer->rebuild_stats( $product_ids, 30 );
		$affinity_seed = $this->indexer->rebuild_affinity( $product_ids, 180 );

		$this->logger->debug(
			'index.batch',
			[
				'page'     => $page,
				'products' => count( $product_ids ),
				'stats'    => $stats_written,
				'affinity' => $affinity_seed,
			]
		);

		return [
			'processed' => count( $product_ids ),
			'next'      => [
				'page'    => $page + 1,
				'attempt' => 1,
			],
		];
	}
}
