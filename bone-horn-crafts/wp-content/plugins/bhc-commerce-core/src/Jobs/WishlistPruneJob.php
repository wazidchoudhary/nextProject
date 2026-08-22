<?php
/**
 * Wishlist housekeeping.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Jobs;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Contracts\LoggerInterface;
use BoneHornCrafts\Core\Wishlist\WishlistRepository;

/**
 * Removes wishlist rows whose product no longer exists.
 *
 * Deleting a product does not cascade to a custom table, so without this the
 * table slowly fills with rows that render nothing. Runs weekly, in bounded
 * batches, and stops as soon as a pass finds nothing to remove.
 */
final class WishlistPruneJob extends AbstractBatchJob {

	public const HOOK = 'bhc_job_prune_wishlist';

	/**
	 * Constructor.
	 *
	 * @param WishlistRepository $wishlist Wishlist repository.
	 * @param LoggerInterface    $logger   Logger.
	 */
	public function __construct( private WishlistRepository $wishlist, LoggerInterface $logger ) {
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
		return 200;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $args Batch arguments.
	 *
	 * @return array{processed:int, next:?array<string, mixed>}
	 */
	protected function handle_batch( array $args ): array {
		$removed = $this->wishlist->prune_orphans( $this->batch_size() );

		if ( $removed < $this->batch_size() ) {
			return [
				'processed' => $removed,
				'next'      => null,
			];
		}

		return [
			'processed' => $removed,
			'next'      => [
				'page'    => absint( $args['page'] ?? 1 ) + 1,
				'attempt' => 1,
			],
		];
	}
}
