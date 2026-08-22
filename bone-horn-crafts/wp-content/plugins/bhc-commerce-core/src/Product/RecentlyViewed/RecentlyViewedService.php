<?php
/**
 * Recently viewed products.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Product\RecentlyViewed;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Contracts\HookableInterface;
use BoneHornCrafts\Core\Security\SignedCookie;
use BoneHornCrafts\Core\Support\Options;
use WC_Product;

/**
 * Tracks the last N products a visitor opened.
 *
 * Storage is a signed cookie holding nothing but product ids. That choice is
 * deliberate: no database writes on a page view, no session bootstrap for
 * anonymous traffic (which would defeat full-page caching), and no personal
 * data at rest. Pages remain cacheable because the list is rendered from the
 * cookie on the client-visible portion only — the server renders it on the
 * product page, which is already personalised by the cart fragment.
 */
final class RecentlyViewedService implements HookableInterface {

	public const COOKIE = 'bhc_recently_viewed';

	/**
	 * Constructor.
	 *
	 * @param SignedCookie $cookies Signed cookie helper.
	 * @param Options      $options Plugin settings.
	 */
	public function __construct( private SignedCookie $cookies, private Options $options ) {}

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'template_redirect', [ $this, 'track_current_product' ] );
	}

	/**
	 * Records the product currently being viewed.
	 */
	public function track_current_product(): void {
		if ( is_admin() || ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		$product_id = (int) get_queried_object_id();

		if ( $product_id > 0 ) {
			$this->record( $product_id );
		}
	}

	/**
	 * Pushes a product id onto the front of the list.
	 *
	 * @param int $product_id Product id.
	 */
	public function record( int $product_id ): void {
		$product_id = absint( $product_id );

		if ( $product_id <= 0 ) {
			return;
		}

		$ids = $this->ids();

		array_unshift( $ids, $product_id );

		$ids = array_slice( array_values( array_unique( $ids ) ), 0, $this->limit() );

		$this->cookies->write( self::COOKIE, [ 'ids' => $ids ], MONTH_IN_SECONDS );
	}

	/**
	 * Returns the stored ids, newest first.
	 *
	 * @return int[]
	 */
	public function ids(): array {
		$payload = $this->cookies->read( self::COOKIE );
		$ids     = isset( $payload['ids'] ) && is_array( $payload['ids'] ) ? $payload['ids'] : [];

		return array_slice( array_values( array_filter( array_map( 'absint', $ids ) ) ), 0, $this->limit() );
	}

	/**
	 * Returns the ids excluding the product currently being viewed.
	 *
	 * @param int $exclude_id Product id to drop.
	 *
	 * @return int[]
	 */
	public function ids_excluding( int $exclude_id ): array {
		return array_values( array_diff( $this->ids(), [ absint( $exclude_id ) ] ) );
	}

	/**
	 * Clears the list.
	 */
	public function clear(): void {
		$this->cookies->delete( self::COOKIE );
	}

	/**
	 * Maximum stored ids.
	 */
	private function limit(): int {
		return max( 2, min( 20, $this->options->int( 'recently_viewed_limit' ) ) );
	}
}
