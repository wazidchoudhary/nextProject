<?php
/**
 * Wishlist façade.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Wishlist;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Contracts\HookableInterface;
use BoneHornCrafts\Core\Contracts\LoggerInterface;
use BoneHornCrafts\Core\Contracts\WishlistStorageInterface;
use BoneHornCrafts\Core\Product\ProductRepository;
use BoneHornCrafts\Core\Security\SignedCookie;
use BoneHornCrafts\Core\Support\Options;
use WC_Product;

/**
 * The only class the rest of the plugin talks to about wishlists.
 *
 * It selects the storage backend for the current visitor (database for
 * customers, signed cookie for guests) and owns the login merge. Callers never
 * know which backend is in play — that is the point of the storage interface.
 */
final class WishlistService implements HookableInterface {

	/**
	 * Resolved storage for the current request.
	 */
	private ?WishlistStorageInterface $storage = null;

	/**
	 * Constructor.
	 *
	 * @param WishlistRepository $repository Repository.
	 * @param SignedCookie       $cookies    Signed cookie helper.
	 * @param ProductRepository  $products   Product read model.
	 * @param Options            $options    Settings.
	 * @param LoggerInterface    $logger     Logger.
	 */
	public function __construct(
		private WishlistRepository $repository,
		private SignedCookie $cookies,
		private ProductRepository $products,
		private Options $options,
		private LoggerInterface $logger
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'wp_login', [ $this, 'merge_guest_list_on_login' ], 10, 2 );
	}

	/**
	 * Returns the storage backend for the current visitor.
	 */
	public function storage(): WishlistStorageInterface {
		if ( null !== $this->storage ) {
			return $this->storage;
		}

		$max = max( 1, $this->options->int( 'wishlist_max_items' ) );

		if ( is_user_logged_in() ) {
			return $this->storage = new UserWishlistStorage( $this->repository, get_current_user_id(), $max );
		}

		return $this->storage = new GuestWishlistStorage( $this->cookies, min( 40, $max ) );
	}

	/**
	 * Whether the wishlist feature is available to the current visitor.
	 */
	public function is_available(): bool {
		if ( ! $this->options->bool( 'wishlist_enabled' ) ) {
			return false;
		}

		return is_user_logged_in() || $this->options->bool( 'wishlist_guest_enabled' );
	}

	/**
	 * Adds a product after validating that it exists and is purchasable.
	 *
	 * @param int $product_id Product id.
	 *
	 * @return array{success:bool, in_list:bool, count:int, message:string}
	 */
	public function add( int $product_id ): array {
		$product = wc_get_product( absint( $product_id ) );

		if ( ! $product instanceof WC_Product || 'publish' !== $product->get_status() ) {
			return $this->result( false, false, __( 'That product is no longer available.', 'bhc-commerce-core' ) );
		}

		$added = $this->storage()->add( $product->get_id() );

		if ( ! $added && ! $this->storage()->has( $product->get_id() ) ) {
			return $this->result( false, false, __( 'Your wishlist is full. Remove an item and try again.', 'bhc-commerce-core' ) );
		}

		$this->logger->info(
			'wishlist.item_added',
			[
				'product_id' => $product->get_id(),
				'guest'      => ! is_user_logged_in(),
			]
		);

		return $this->result( true, true, __( 'Saved to your wishlist.', 'bhc-commerce-core' ) );
	}

	/**
	 * Removes a product.
	 *
	 * @param int $product_id Product id.
	 *
	 * @return array{success:bool, in_list:bool, count:int, message:string}
	 */
	public function remove( int $product_id ): array {
		$this->storage()->remove( absint( $product_id ) );

		$this->logger->info( 'wishlist.item_removed', [ 'product_id' => absint( $product_id ) ] );

		return $this->result( true, false, __( 'Removed from your wishlist.', 'bhc-commerce-core' ) );
	}

	/**
	 * Adds or removes depending on the current state.
	 *
	 * @param int $product_id Product id.
	 *
	 * @return array{success:bool, in_list:bool, count:int, message:string}
	 */
	public function toggle( int $product_id ): array {
		return $this->storage()->has( absint( $product_id ) )
			? $this->remove( $product_id )
			: $this->add( $product_id );
	}

	/**
	 * Whether a product is on the current visitor's list.
	 *
	 * @param int $product_id Product id.
	 */
	public function contains( int $product_id ): bool {
		return $this->storage()->has( absint( $product_id ) );
	}

	/**
	 * Saved product ids.
	 *
	 * @return int[]
	 */
	public function ids(): array {
		return $this->storage()->all();
	}

	/**
	 * Number of saved products.
	 */
	public function count(): int {
		return $this->storage()->count();
	}

	/**
	 * Hydrated products on the list.
	 *
	 * @return WC_Product[]
	 */
	public function products(): array {
		return $this->products->hydrate( $this->ids() );
	}

	/**
	 * Moves a guest cookie list into the customer's account after login.
	 *
	 * @param string   $user_login Username.
	 * @param \WP_User $user       User object.
	 */
	public function merge_guest_list_on_login( string $user_login, $user = null ): void {
		if ( ! $user instanceof \WP_User ) {
			return;
		}

		$guest = new GuestWishlistStorage( $this->cookies );
		$ids   = $guest->all();

		if ( [] === $ids ) {
			return;
		}

		$storage = new UserWishlistStorage( $this->repository, $user->ID, max( 1, $this->options->int( 'wishlist_max_items' ) ) );
		$merged  = $storage->merge( $ids );

		$guest->clear();

		$this->logger->info(
			'wishlist.guest_merged',
			[
				'user_id' => $user->ID,
				'merged'  => $merged,
			]
		);
	}

	/**
	 * Builds the response payload shared by REST and AJAX callers.
	 *
	 * @param bool   $success Whether the operation succeeded.
	 * @param bool   $in_list Whether the product is now on the list.
	 * @param string $message Human readable message.
	 *
	 * @return array{success:bool, in_list:bool, count:int, message:string}
	 */
	private function result( bool $success, bool $in_list, string $message ): array {
		return [
			'success' => $success,
			'in_list' => $in_list,
			'count'   => $this->count(),
			'message' => $message,
		];
	}
}
