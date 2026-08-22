<?php
/**
 * Cookie backed wishlist storage for guests.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Wishlist;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Contracts\WishlistStorageInterface;
use BoneHornCrafts\Core\Security\SignedCookie;

/**
 * Keeps a guest wishlist in a signed cookie.
 *
 * No database row is created for a visitor who may never return, which keeps
 * the table small and avoids writing a row on anonymous traffic. The cookie
 * holds product ids only and is HMAC signed, so a tampered value is discarded
 * rather than trusted. On login the ids are merged into the customer's list.
 */
final class GuestWishlistStorage implements WishlistStorageInterface {

	public const COOKIE = 'bhc_wishlist';

	/**
	 * Memoised id list.
	 *
	 * @var int[]|null
	 */
	private ?array $ids = null;

	/**
	 * Constructor.
	 *
	 * @param SignedCookie $cookies   Signed cookie helper.
	 * @param int          $max_items Maximum stored items.
	 */
	public function __construct( private SignedCookie $cookies, private int $max_items = 40 ) {}

	/**
	 * {@inheritDoc}
	 *
	 * @return int[]
	 */
	public function all(): array {
		if ( null !== $this->ids ) {
			return $this->ids;
		}

		$payload = $this->cookies->read( self::COOKIE );
		$ids     = isset( $payload['ids'] ) && is_array( $payload['ids'] ) ? $payload['ids'] : [];

		$this->ids = array_slice(
			array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) ),
			0,
			$this->max_items
		);

		return $this->ids;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $product_id Product id.
	 */
	public function add( int $product_id ): bool {
		$product_id = absint( $product_id );

		if ( $product_id <= 0 || $this->has( $product_id ) ) {
			return false;
		}

		$ids = $this->all();

		if ( count( $ids ) >= $this->max_items ) {
			return false;
		}

		array_unshift( $ids, $product_id );

		return $this->persist( $ids );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $product_id Product id.
	 */
	public function remove( int $product_id ): bool {
		$product_id = absint( $product_id );
		$ids        = $this->all();

		if ( ! in_array( $product_id, $ids, true ) ) {
			return false;
		}

		return $this->persist( array_values( array_diff( $ids, [ $product_id ] ) ) );
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
		$this->ids = [];

		$this->cookies->delete( self::COOKIE );
	}

	/**
	 * {@inheritDoc}
	 */
	public function count(): int {
		return count( $this->all() );
	}

	/**
	 * Writes the id list back to the cookie.
	 *
	 * @param int[] $ids Product ids.
	 */
	private function persist( array $ids ): bool {
		$ids = array_slice( array_values( array_unique( array_map( 'absint', $ids ) ) ), 0, $this->max_items );

		$this->ids = $ids;

		return $this->cookies->write( self::COOKIE, [ 'ids' => $ids ], 90 * DAY_IN_SECONDS );
	}
}
