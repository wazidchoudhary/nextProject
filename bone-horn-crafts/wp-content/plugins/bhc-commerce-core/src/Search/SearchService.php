<?php
/**
 * Catalogue search and filtering.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Search;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Cache\CacheManager;
use BoneHornCrafts\Core\Contracts\HookableInterface;
use BoneHornCrafts\Core\Product\ProductRepository;
use WC_Product;
use WP_Query;

/**
 * Runs filtered catalogue queries and improves WooCommerce's product search.
 *
 * Two jobs:
 *
 * 1. `results()` executes a validated `FilterRequest` and returns ids plus a
 *    total, cached per filter signature. The shop page, the AJAX filter
 *    endpoint and the REST endpoint all share it, so filtering behaves
 *    identically wherever it is triggered.
 * 2. SKU search. Makers search by SKU ("BHC-BS-1042") constantly, and core's
 *    search only looks at post title/excerpt/content. A narrow, indexed
 *    lookup on `wc_product_meta_lookup.sku` is added to the search query.
 */
final class SearchService implements HookableInterface {

	/**
	 * Shortest term that will also be matched against SKUs.
	 *
	 * Below this a SKU match is noise: two characters appear inside most of
	 * the catalogue's part numbers.
	 */
	private const MIN_SKU_SEARCH_LENGTH = 3;


	/**
	 * Constructor.
	 *
	 * @param ProductRepository $products       Product read model.
	 * @param FacetRepository   $facets         Facet counts.
	 * @param CacheManager      $cache          Cache manager.
	 * @param RequestParser     $request_parser Reads filter input from the request.
	 */
	public function __construct(
		private ProductRepository $products,
		private FacetRepository $facets,
		private CacheManager $cache,
		private RequestParser $request_parser
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_filter( 'posts_search', [ $this, 'add_sku_search' ], 10, 2 );
		add_action( 'woocommerce_product_query', [ $this, 'apply_archive_filters' ], 10, 2 );
		add_filter( 'woocommerce_get_catalog_ordering_args', [ $this, 'catalog_ordering_args' ], 10, 1 );
	}

	/**
	 * Executes a filter request.
	 *
	 * @param FilterRequest $request Validated request.
	 *
	 * @return array{ids:int[], total:int, pages:int}
	 */
	public function results( FilterRequest $request ): array {
		$key = 'results_' . $request->cache_key();

		return (array) $this->cache->for_group( 'search' )->remember(
			$key,
			function () use ( $request ): array {
				$ids   = $this->products->query( $request->to_query_args() );
				$total = $this->products->last_total();

				return [
					'ids'   => $ids,
					'total' => $total,
					'pages' => (int) ceil( $total / max( 1, $request->per_page ) ),
				];
			},
			10 * MINUTE_IN_SECONDS
		);
	}

	/**
	 * Executes a filter request and hydrates the products.
	 *
	 * @param FilterRequest $request Validated request.
	 *
	 * @return array{products:WC_Product[], total:int, pages:int}
	 */
	public function hydrated_results( FilterRequest $request ): array {
		$results = $this->results( $request );

		return [
			'products' => $this->products->hydrate( $results['ids'] ),
			'total'    => (int) $results['total'],
			'pages'    => (int) $results['pages'],
		];
	}

	/**
	 * Returns the facet model for the filter panel.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function facets(): array {
		return $this->facets->facets();
	}

	/**
	 * Returns the catalogue price bounds.
	 *
	 * @return array{min:float, max:float}
	 */
	public function price_range(): array {
		return $this->facets->price_range();
	}

	/**
	 * Extends product search to match SKUs.
	 *
	 * Two details here are load-bearing, and both were wrong once.
	 *
	 * **The prepared fragment must have its placeholder escaping removed.**
	 * `$wpdb->prepare()` replaces every literal `%` with a per-request hash and
	 * relies on `remove_placeholder_escape()` running once over the finished
	 * query to put them back. `WP_Query` has already escaped its own LIKE
	 * values that way by the time `posts_search` fires, and it un-escapes the
	 * whole string exactly once. Concatenating a second, separately prepared
	 * fragment breaks that accounting: the hashes survive into the executed
	 * SQL and every LIKE then matches the literal hash text rather than the
	 * search term. The symptom is not a broken SKU search — it is product
	 * search returning nothing at all, for every query.
	 *
	 * **The injection point is the search group's own closing parenthesis.**
	 * `WP_Query` appends ` AND (post_password = '')` to `$search` *before*
	 * running this filter, so the last `)` in the string closes the password
	 * clause, not the search. Injecting there ORs the SKU match against
	 * `post_password = ''`, which is true for every row — a clause that reads
	 * as if it works and matches nothing extra. The scan below walks
	 * parenthesis depth from the first `(` so it lands on the right one no
	 * matter what core appends afterwards.
	 *
	 * @param string   $search Search SQL.
	 * @param WP_Query $query  Query object.
	 */
	public function add_sku_search( string $search, WP_Query $query ): string {
		if ( is_admin() || '' === $search || ! $query->is_search() ) {
			return $search;
		}

		$post_types = (array) $query->get( 'post_type' );

		if ( ! in_array( 'product', $post_types, true ) && 'any' !== ( $post_types[0] ?? '' ) ) {
			return $search;
		}

		$term = trim( (string) $query->get( 's' ) );

		if ( strlen( $term ) < self::MIN_SKU_SEARCH_LENGTH ) {
			return $search;
		}

		$position = self::search_group_end( $search );

		if ( null === $position ) {
			return $search;
		}

		global $wpdb;

		$lookup = $wpdb->wc_product_meta_lookup;
		$like   = '%' . $wpdb->esc_like( $term ) . '%';

		// EXISTS rather than `ID IN (SELECT ... LIMIT n)`: MySQL and MariaDB both
		// reject a LIMIT inside an IN subquery outright — "This version of
		// MariaDB doesn't yet support 'LIMIT & IN/ALL/ANY/SOME subquery'" — and
		// WP_Query swallows the resulting error, so the whole product search
		// returned zero rows on the primary stack rather than failing loudly.
		// The correlated form also short-circuits on the lookup table's
		// product_id index instead of materialising a list.
		$sku_clause = $wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table identifiers cannot be bound; both come from $wpdb, and the search term is the %s placeholder.
			"OR EXISTS (SELECT 1 FROM {$lookup} bhc_sku WHERE bhc_sku.product_id = {$wpdb->posts}.ID AND bhc_sku.sku LIKE %s)",
			$like
		);

		// See the docblock: without this the placeholder hashes leak into the
		// executed query and break every LIKE in it, not just this one.
		$sku_clause = $wpdb->remove_placeholder_escape( $sku_clause );

		return substr( $search, 0, $position ) . ' ' . $sku_clause . ' ' . substr( $search, $position );
	}

	/**
	 * Finds the closing parenthesis of the leading ` AND ( ... )` search group.
	 *
	 * Written as a depth scan rather than a regular expression because the
	 * group contains nested parentheses of its own — one per searched column,
	 * plus whatever other plugins have already added — and a regex either stops
	 * at the first `)` or runs to the last one. Both are wrong.
	 *
	 * @param string $search Search SQL as `posts_search` receives it.
	 *
	 * @return int|null Offset of the closing parenthesis, or null if the string
	 *                  is not the shape this expects.
	 */
	private static function search_group_end( string $search ): ?int {
		$open = strpos( $search, '(' );

		if ( false === $open ) {
			return null;
		}

		$depth  = 0;
		$length = strlen( $search );

		for ( $i = $open; $i < $length; $i++ ) {
			if ( '(' === $search[ $i ] ) {
				++$depth;

				continue;
			}

			if ( ')' !== $search[ $i ] ) {
				continue;
			}

			--$depth;

			if ( 0 === $depth ) {
				return $i;
			}
		}

		return null;
	}

	/**
	 * Applies storefront filters to the main WooCommerce archive query.
	 *
	 * Hooking `woocommerce_product_query` (rather than a raw `pre_get_posts`)
	 * means WooCommerce's own visibility, stock and ordering handling still
	 * runs, and the layered-nav widgets keep working.
	 *
	 * @param WP_Query $query Main product query.
	 * @param mixed    $wc_query WooCommerce query object.
	 */
	public function apply_archive_filters( WP_Query $query, $wc_query = null ): void {
		if ( is_admin() ) {
			return;
		}

		$request = $this->request_parser->current();

		$tax_query = $request->tax_query();

		if ( [] !== $tax_query ) {
			$existing = (array) $query->get( 'tax_query' );

			$query->set( 'tax_query', array_merge( $existing, $tax_query ) );
		}

		if ( $request->in_stock ) {
			$query->set( 'bhc_in_stock_only', true );

			add_filter( 'posts_clauses', [ $this, 'in_stock_clause' ], 10, 2 );
		}

		if ( $request->on_sale ) {
			$query->set( 'bhc_on_sale_only', true );

			add_filter( 'posts_clauses', [ $this, 'on_sale_clause' ], 10, 2 );
		}
	}

	/**
	 * Adds an in-stock restriction through the lookup table.
	 *
	 * @param array<string, string> $clauses SQL clauses.
	 * @param WP_Query              $query   Query object.
	 *
	 * @return array<string, string>
	 */
	public function in_stock_clause( array $clauses, WP_Query $query ): array {
		if ( ! $query->get( 'bhc_in_stock_only' ) ) {
			return $clauses;
		}

		global $wpdb;

		$clauses['where'] .= " AND {$wpdb->posts}.ID IN (SELECT product_id FROM {$wpdb->wc_product_meta_lookup} WHERE stock_status = 'instock') ";

		return $clauses;
	}

	/**
	 * Adds an on-sale restriction through the lookup table.
	 *
	 * @param array<string, string> $clauses SQL clauses.
	 * @param WP_Query              $query   Query object.
	 *
	 * @return array<string, string>
	 */
	public function on_sale_clause( array $clauses, WP_Query $query ): array {
		if ( ! $query->get( 'bhc_on_sale_only' ) ) {
			return $clauses;
		}

		global $wpdb;

		$clauses['where'] .= " AND {$wpdb->posts}.ID IN (SELECT product_id FROM {$wpdb->wc_product_meta_lookup} WHERE onsale = 1) ";

		return $clauses;
	}

	/**
	 * Whitelists the storefront's extra ordering options.
	 *
	 * @param array<string, mixed> $args Ordering args.
	 *
	 * @return array<string, mixed>
	 */
	public function catalog_ordering_args( array $args ): array {
		$orderby = $this->request_parser->orderby();

		if ( 'newest' === $orderby ) {
			$args['orderby'] = 'date ID';
			$args['order']   = 'DESC';
		}

		return $args;
	}
}
