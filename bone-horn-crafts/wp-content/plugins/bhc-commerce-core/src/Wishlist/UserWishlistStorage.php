<?php
/**
 * Database backed wishlist storage.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Wishlist;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Contracts\WishlistStorageInterface;

/**
 * Stores an authenticated customer's wishlist in the custom table.
 *
 * The id list is memoised for the request: a product archive asks "is this
 * saved?" once per card, and without the memo that would be one query per card.
 */
final class UserWishlistStorage implements WishlistStorageInterface {

	/**
	 * Memoised id list.
	 *
	 * @var int[]|null
	 */
	private ?array $ids = null;

	/**
	 * Constructor.
	 *
	 * @param WishlistRepository $repository Repository.
	 * @param int                $user_id    Customer id.
	 * @param int                $max_items  Maximum stored items.
	 */
	public function __construct(
		private WishlistRepository $repository,
		private int $user_id,
		private int $max_items = 60
	) {}

	/**
	 * {@inheritDoc}
	 *
	 * @return int[]
	 */
	public function all(): array {
		if ( null === $this->ids ) {
			$this->ids = $this->repository->ids_for_user( $this->user_id, $this->max_items );
		}

		return $this->ids;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $product_id Product id.
	 */
	public function add( int $product_id ): bool {
		if ( $this->count() >= $this->max_items && ! $this->has( $product_id ) ) {
			return false;
		}

		$added = $this->repository->add( $this->user_id, $product_id );

		if ( $added ) {
			$this->ids = null;
		}

		return $added;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $product_id Product id.
	 */
	public function remove( int $product_id ): bool {
		$removed = $this->repository->remove( $this->user_id, $product_id );

		if ( $removed ) {
			$this->ids = null;
		}

		return $removed;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $product_id Product id.
	 */
	public function has( int $product_id ): bool {
		return in_array( absint( $product_id ), $this->all(), true );
	}

	/**
	 * {@inheritDoc}
	 */
	public function clear(): void {
		$this->repository->clear( $this->user_id );

		$this->ids = [];
	}

	/**
	 * {@inheritDoc}
	 */
	public function count(): int {
		return count( $this->all() );
	}

	/**
	 * Merges ids from a guest list.
	 *
	 * @param int[] $product_ids Product ids.
	 */
	public function merge( array $product_ids ): int {
		$merged = $this->repository->bulk_add( $this->user_id, $product_ids );

		if ( $merged > 0 ) {
			$this->ids = null;
		}

		return $merged;
	}
}
