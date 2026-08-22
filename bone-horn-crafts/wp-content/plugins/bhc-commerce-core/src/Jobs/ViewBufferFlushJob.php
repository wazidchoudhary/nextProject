<?php
/**
 * Flushes buffered product views into the stats table.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Jobs;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Analytics\ProductStatsRepository;
use BoneHornCrafts\Core\Analytics\ProductViewTracker;
use BoneHornCrafts\Core\Contracts\LoggerInterface;

/**
 * Turns the in-memory view buffer into one bulk write.
 *
 * Single batch by design: the buffer is capped at 500 products, which is a
 * single chunked insert.
 */
final class ViewBufferFlushJob extends AbstractBatchJob {

	public const HOOK = 'bhc_job_flush_views';

	/**
	 * Constructor.
	 *
	 * @param ProductViewTracker     $tracker View buffer.
	 * @param ProductStatsRepository $stats   Stats repository.
	 * @param LoggerInterface        $logger  Logger.
	 */
	public function __construct(
		private ProductViewTracker $tracker,
		private ProductStatsRepository $stats,
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
		return 500;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $args Batch arguments.
	 *
	 * @return array{processed:int, next:?array<string, mixed>}
	 */
	protected function handle_batch( array $args ): array {
		$buffer = $this->tracker->drain();

		if ( [] === $buffer ) {
			return [
				'processed' => 0,
				'next'      => null,
			];
		}

		$written = $this->stats->add_views( $buffer );

		return [
			'processed' => $written,
			'next'      => null,
		];
	}
}
