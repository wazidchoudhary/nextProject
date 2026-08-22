<?php
/**
 * Recommendation REST endpoints.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\API;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Recommendations\RecommendationService;
use BoneHornCrafts\Core\Security\RestGuard;
use WC_Product;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * `bhc/v1/products/{id}/recommendations` — the blended recommendation list.
 *
 * Public and cacheable: the payload depends only on the seed product, so it
 * carries a short `s-maxage` for edge caches.
 */
final class RecommendationsController extends AbstractController {

	/**
	 * Constructor.
	 *
	 * @param RecommendationService $service   Recommendation service.
	 * @param ProductPresenter      $presenter Product presenter.
	 * @param RestGuard             $guard     Permission callbacks.
	 */
	public function __construct(
		private RecommendationService $service,
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
			'/products/(?P<product_id>\d+)/recommendations',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_items' ],
				'permission_callback' => [ $this->guard, 'public_read' ],
				'args'                => [
					'product_id' => $this->product_id_arg(),
					'limit'      => [
						'type'              => 'integer',
						'default'           => 4,
						'minimum'           => 1,
						'maximum'           => 12,
						'sanitize_callback' => 'absint',
					],
					'placement'  => [
						'type'              => 'string',
						'default'           => 'complete_your_build',
						'enum'              => [ 'complete_your_build', 'frequently_bought_together', 'cart_upsell' ],
						'sanitize_callback' => 'sanitize_key',
					],
				],
			]
		);
	}

	/**
	 * Returns recommendations for a product.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( WP_REST_Request $request ) {
		$product = wc_get_product( (int) $request->get_param( 'product_id' ) );

		if ( ! $product instanceof WC_Product || 'publish' !== $product->get_status() ) {
			return new WP_Error(
				'bhc_product_not_found',
				__( 'Product not found.', 'bhc-commerce-core' ),
				[ 'status' => 404 ]
			);
		}

		$products = $this->service->products_for(
			$product,
			(int) $request->get_param( 'limit' ),
			(string) $request->get_param( 'placement' )
		);

		return $this->respond(
			[
				'seed'      => $product->get_id(),
				'placement' => (string) $request->get_param( 'placement' ),
				'products'  => $this->presenter->present_many( $products ),
			],
			200,
			10 * MINUTE_IN_SECONDS
		);
	}
}
