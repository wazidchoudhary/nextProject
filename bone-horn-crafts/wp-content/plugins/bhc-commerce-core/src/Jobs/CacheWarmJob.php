<?php
/**
 * Cache warming job.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Jobs;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Contracts\LoggerInterface;
use BoneHornCrafts\Core\Product\ProductRepository;
use BoneHornCrafts\Core\Search\FacetRepository;

/**
 * Re-populates the caches the homepage and shop page read first.
 *
 * Run straight after a deploy (`wp bhc cache warm`) or after the nightly index
 * so the first visitor does not pay for the cold cache. Warming is strictly
 * read-only: it never writes catalogue data, so it is safe to run at any time.
 */
final class CacheWarmJob extends AbstractBatchJob {

	public const HOOK = 'bhc_job_cache_warm';

	/**
	 * Constructor.
	 *
	 * @param ProductRepository $products Product read model.
	 * @param FacetRepository   $facets   Facet counts.
	 * @param LoggerInterface   $logger   Logger.
	 */
	public function __construct(
		private ProductRepository $products,
		private FacetRepository $facets,
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
		return 1;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $args Batch arguments.
	 *
	 * @return array{processed:int, next:?array<string, mixed>}
	 */
	protected function handle_batch( array $args ): array {
		$warmed = 0;

		$this->products->new_arrival_ids( 8 );
		++$warmed;

		$this->products->bestseller_ids( 8 );
		++$warmed;

		$this->products->on_sale_ids( 8 );
		++$warmed;

		$this->products->published_count();
		++$warmed;

		$this->facets->facets();
		++$warmed;

		$this->facets->price_range();
		++$warmed;

		foreach ( [ 'knife-handle-scales', 'guitar-parts', 'drinking-horns-mugs', 'pen-blanks' ] as $slug ) {
			$this->products->category_ids( $slug, 8 );
			++$warmed;
		}

		return [
			'processed' => $warmed,
			'next'      => null,
		];
	}
}
