<?php
/**
 * Catalogue filtering REST endpoints.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\API;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Search\FilterRequest;
use BoneHornCrafts\Core\Search\SearchService;
use BoneHornCrafts\Core\Support\Template;
use BoneHornCrafts\Core\Security\RestGuard;
use WP_REST_Request;
use WP_REST_Response;

/**
 * `bhc/v1/catalog` — filtered product grids without a page reload.
 *
 * The endpoint accepts exactly the same parameters as the shop URL, so the
 * AJAX result and the server-rendered page can never diverge, and a filtered
 * URL remains shareable and bookmarkable.
 */
final class CatalogController extends AbstractController {

	/**
	 * Constructor.
	 *
	 * @param SearchService    $search    Search service.
	 * @param ProductPresenter $presenter Product presenter.
	 * @param Template         $template  Card template renderer.
	 * @param RestGuard        $guard     Permission callbacks.
	 */
	public function __construct(
		private SearchService $search,
		private ProductPresenter $presenter,
		private Template $template,
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
			'/catalog',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_items' ],
				'permission_callback' => [ $this->guard, 'public_read' ],
				'args'                => [
					'material'     => [ 'type' => 'string' ],
					'finish'       => [ 'type' => 'string' ],
					'application'  => [ 'type' => 'string' ],
					'colour'       => [ 'type' => 'string' ],
					'size'         => [ 'type' => 'string' ],
					'product-type' => [ 'type' => 'string' ],
					'category'     => [ 'type' => 'string' ],
					'min_price'    => [ 'type' => 'number' ],
					'max_price'    => [ 'type' => 'number' ],
					'in_stock'     => [ 'type' => 'boolean' ],
					'on_sale'      => [ 'type' => 'boolean' ],
					'orderby'      => [
						'type'              => 'string',
						'enum'              => FilterRequest::ALLOWED_ORDERBY,
						'sanitize_callback' => 'sanitize_key',
					],
					's'            => [ 'type' => 'string' ],
					'page'         => [
						'type'              => 'integer',
						'default'           => 1,
						'minimum'           => 1,
						'maximum'           => 100,
						'sanitize_callback' => 'absint',
					],
					'per_page'     => [
						'type'              => 'integer',
						'default'           => 12,
						'minimum'           => 1,
						'maximum'           => 48,
						'sanitize_callback' => 'absint',
					],
					'facets'       => [
						'type'    => 'boolean',
						'default' => false,
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/catalog/facets',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_facets' ],
				'permission_callback' => [ $this->guard, 'public_read' ],
			]
		);
	}

	/**
	 * Returns a filtered product page.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function get_items( WP_REST_Request $request ): WP_REST_Response {
		$filters = FilterRequest::from_array( (array) $request->get_params() );
		$results = $this->search->hydrated_results( $filters );

		$payload = [
			'products' => $this->presenter->present_many( $results['products'] ),
			// The rendered cards, from the same template the archive uses.
			//
			// The browser used to build this markup itself from the `products`
			// array, which meant the card existed twice — once in
			// templates/product/card.php and once in JavaScript. The two had
			// already drifted: filtering the shop replaced cards that had a
			// star rating, a review count and a wishlist button with cards that
			// had none of them. One implementation, rendered where the data is.
			'html'     => $this->render_cards( $results['products'] ),
			'total'    => $results['total'],
			'pages'    => $results['pages'],
			'page'     => $filters->page,
			'query'    => $filters->to_query_string_args(),
		];

		if ( (bool) $request->get_param( 'facets' ) ) {
			$payload['facets']      = $this->search->facets();
			$payload['price_range'] = $this->search->price_range();
		}

		// Filtered grids are identical for every visitor, so a short shared
		// cache is safe and takes the repeat-filter load off PHP entirely.
		return $this->respond( $payload, 200, 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * Renders a set of products as catalogue cards.
	 *
	 * @param \WC_Product[] $products Products to render.
	 */
	private function render_cards( array $products ): string {
		if ( [] === $products ) {
			return '';
		}

		ob_start();

		foreach ( $products as $product ) {
			$this->template->output(
				'product/card.php',
				[
					'product' => $product,
					// Everything the filter or a "load more" appends is below
					// the fold by definition, so nothing here is the LCP
					// candidate and nothing should load eagerly.
					'eager'   => false,
				]
			);
		}

		return (string) ob_get_clean();
	}

	/**
	 * Returns the facet model on its own.
	 */
	public function get_facets(): WP_REST_Response {
		return $this->respond(
			[
				'facets'      => $this->search->facets(),
				'price_range' => $this->search->price_range(),
			],
			200,
			30 * MINUTE_IN_SECONDS
		);
	}
}
