<?php
/**
 * Wishlist REST endpoints.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\API;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Security\RestGuard;
use BoneHornCrafts\Core\Wishlist\WishlistService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * `bhc/v1/wishlist` — read, toggle and clear the visitor's wishlist.
 *
 * Responses are never cached (`no-store`): the payload is per-visitor, and a
 * shared cache in front of the site must not serve one shopper's list to
 * another.
 */
final class WishlistController extends AbstractController {

	/**
	 * Constructor.
	 *
	 * @param WishlistService  $wishlist  Wishlist service.
	 * @param ProductPresenter $presenter Product presenter.
	 * @param RestGuard        $guard     Permission callbacks.
	 */
	public function __construct(
		private WishlistService $wishlist,
		private ProductPresenter $presenter,
		RestGuard $guard
	) {
		parent::__construct( $guard );
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/wishlist',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'get_items' ],
					'permission_callback' => [ $this->guard, 'public_read' ],
					'args'                => [
						'expand' => [
							'type'    => 'boolean',
							'default' => true,
						],
					],
				],
				[
					'methods'             => 'DELETE',
					'callback'            => [ $this, 'clear' ],
					'permission_callback' => [ $this->guard, 'session_write' ],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/wishlist/toggle',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'toggle' ],
				'permission_callback' => [ $this->guard, 'session_write' ],
				'args'                => [
					'product_id' => $this->product_id_arg(),
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/wishlist/(?P<product_id>\d+)',
			[
				'methods'             => 'DELETE',
				'callback'            => [ $this, 'remove' ],
				'permission_callback' => [ $this->guard, 'session_write' ],
				'args'                => [
					'product_id' => $this->product_id_arg(),
				],
			]
		);
	}

	/**
	 * Returns the current wishlist.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function get_items( WP_REST_Request $request ): WP_REST_Response {
		$expand = (bool) $request->get_param( 'expand' );

		return $this->respond(
			[
				'count'    => $this->wishlist->count(),
				'ids'      => $this->wishlist->ids(),
				'products' => $expand ? $this->presenter->present_many( $this->wishlist->products() ) : [],
			]
		);
	}

	/**
	 * Adds or removes a product.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function toggle( WP_REST_Request $request ) {
		if ( ! $this->wishlist->is_available() ) {
			return new WP_Error(
				'bhc_wishlist_unavailable',
				__( 'The wishlist is not available. Please sign in to save products.', 'bhc-commerce-core' ),
				[ 'status' => 403 ]
			);
		}

		$result = $this->wishlist->toggle( (int) $request->get_param( 'product_id' ) );

		return $this->respond( $result, $result['success'] ? 200 : 422 );
	}

	/**
	 * Removes a product.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function remove( WP_REST_Request $request ): WP_REST_Response {
		return $this->respond( $this->wishlist->remove( (int) $request->get_param( 'product_id' ) ) );
	}

	/**
	 * Empties the wishlist.
	 */
	public function clear(): WP_REST_Response {
		$this->wishlist->storage()->clear();

		return $this->respond(
			[
				'success' => true,
				'count'   => 0,
				'ids'     => [],
			]
		);
	}
}
