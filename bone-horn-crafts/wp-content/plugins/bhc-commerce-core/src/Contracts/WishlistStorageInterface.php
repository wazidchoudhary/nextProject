<?php
/**
 * Wishlist storage contract.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Storage backend for wishlist items.
 *
 * Two implementations exist: a database backed store for authenticated
 * customers and a signed-cookie store for guests. The service layer depends on
 * this interface only (dependency inversion), so the guest-to-customer merge on
 * login is a single call in one place.
 */
interface WishlistStorageInterface {

	/**
	 * Returns the stored product IDs, newest first.
	 *
	 * @return int[]
	 */
	public function all(): array;

	/**
	 * Adds a product. Returns false when the item was already present.
	 *
	 * @param int $product_id Product ID.
	 */
	public function add( int $product_id ): bool;

	/**
	 * Removes a product. Returns false when the item was not present.
	 *
	 * @param int $product_id Product ID.
	 */
	public function remove( int $product_id ): bool;

	/**
	 * Whether a product is on the list.
	 *
	 * @param int $product_id Product ID.
	 */
	public function has( int $product_id ): bool;

	/**
	 * Empties the list.
	 */
	public function clear(): void;

	/**
	 * Number of items on the list.
	 */
	public function count(): int;
}
