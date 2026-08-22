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
	 * Constructor.
	 *
	 * @param ProductRepository $products Product read model.
	 * @param FacetRepository   $facets   Facet counts.
	 * @param CacheManager      $cache    Cache manager.
	 */
	public function __construct(
		private ProductRepository $products,
		private FacetRepository $facets,
		private CacheManager $cache
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

		if ( strlen( $term ) < 3 ) {
			return $search;
		}

		global $wpdb;

		$lookup = $wpdb->wc_product_meta_lookup;
		$like   = '%' . $wpdb->esc_like( $term ) . '%';

		$sku_clause = $wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table identifiers cannot be bound; both come from $wpdb, and the search term is the %s placeholder.
			"OR ({$wpdb->posts}.ID IN (SELECT product_id FROM {$lookup} WHERE sku LIKE %s LIMIT 50))",
			$like
		);

		// `$search` always arrives wrapped in " AND (...)"; inject before the
		// closing parenthesis so the SKU match ORs with the text match.
		$position = strrpos( $search, ')' );

		if ( false === $position ) {
			return $search;
		}

		return substr( $search, 0, $position ) . ' ' . $sku_clause . ' ' . substr( $search, $position );
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

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filtering of a public archive.
		$request = FilterRequest::from_array( wp_unslash( $_GET ) );

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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only ordering of a public archive.
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( (string) $_GET['orderby'] ) ) : '';

		if ( 'newest' === $orderby ) {
			$args['orderby'] = 'date ID';
			$args['order']   = 'DESC';
		}

		return $args;
	}
}
