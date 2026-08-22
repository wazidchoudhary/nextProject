<?php
/**
 * Bounded product query builder.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Product;

defined( 'ABSPATH' ) || exit;

use WP_Query;

/**
 * Builds `WP_Query` product queries that lean on WooCommerce's lookup table.
 *
 * Why not `wc_get_products()` everywhere? Because the merchandising surfaces
 * need three things it does not offer together: ordering by `total_sales` /
 * `min_price` from `wc_product_meta_lookup` (indexed numeric columns instead of
 * a `postmeta.meta_value` LONGTEXT sort), arbitrary `tax_query` support for
 * multi-attribute facets, and control over `found_rows` and cache priming.
 *
 * Every query built here returns ids only — hydration is a separate, explicit
 * step — is bounded by an enforced limit, and excludes catalogue-hidden
 * products through the standard `product_visibility` taxonomy.
 */
final class ProductQuery {

	/**
	 * Orderby values that require the lookup table join.
	 *
	 * @var array<string, string>
	 */
	private const LOOKUP_ORDERBY = [
		'popularity' => 'total_sales',
		'price'      => 'min_price',
		'price-desc' => 'max_price',
		'rating'     => 'average_rating',
	];

	/**
	 * Column used for lookup-table ordering, empty when unused.
	 */
	private string $lookup_column = '';

	/**
	 * Direction used for lookup-table ordering.
	 */
	private string $lookup_order = 'DESC';

	/**
	 * Currently attached SQL filter callbacks, keyed by filter name.
	 *
	 * @var array<string, callable>
	 */
	private array $attached = [];

	/**
	 * Rows found by the most recent query that requested a total.
	 */
	private int $last_total = 0;

	/**
	 * Runs a query and returns product ids.
	 *
	 * @param array<string, mixed> $args Query arguments.
	 *
	 * @return int[]
	 */
	public function ids( array $args ): array {
		$args = array_merge( $this->defaults(), $args );

		$query_args = $this->build_query_args( $args );

		$price_filter = [
			'min'       => null === $args['min_price'] ? null : (float) $args['min_price'],
			'max'       => null === $args['max_price'] ? null : (float) $args['max_price'],
			'max_stock' => null === $args['max_stock'] ? null : (int) $args['max_stock'],
		];

		try {
			$this->attach_filters( $price_filter, (bool) $args['in_stock_only'], (bool) $args['on_sale'] );

			$query = new WP_Query( $query_args );

			$this->last_total = $args['count_total'] ? (int) $query->found_posts : 0;

			return array_values( array_map( 'absint', (array) $query->posts ) );
		} finally {
			$this->detach_filters();
		}
	}

	/**
	 * Total rows found by the most recent `count_total` query.
	 */
	public function last_total(): int {
		return $this->last_total;
	}

	/**
	 * Default arguments.
	 *
	 * @return array<string, mixed>
	 */
	private function defaults(): array {
		return [
			'limit'         => 8,
			'page'          => 1,
			'orderby'       => 'date',
			'order'         => 'DESC',
			'include'       => [],
			'exclude'       => [],
			'category'      => [],
			'tag'           => [],
			'tax_query'     => [],
			'meta_query'    => [],
			'search'        => '',
			'in_stock_only' => false,
			'visibility'    => 'catalog',
			'count_total'   => false,
			'min_price'     => null,
			'max_price'     => null,
			'on_sale'       => false,
			'max_stock'     => null,
		];
	}

	/**
	 * Translates repository arguments into `WP_Query` arguments.
	 *
	 * @param array<string, mixed> $args Arguments.
	 *
	 * @return array<string, mixed>
	 */
	private function build_query_args( array $args ): array {
		$query_args = [
			'post_type'              => 'product',
			'post_status'            => 'publish',
			'fields'                 => 'ids',
			'posts_per_page'         => max( 1, min( 60, (int) $args['limit'] ) ),
			'paged'                  => max( 1, (int) $args['page'] ),
			'no_found_rows'          => empty( $args['count_total'] ),
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'bhc_product_query'      => true,
		];

		$include = array_values( array_filter( array_map( 'absint', (array) $args['include'] ) ) );
		$exclude = array_values( array_filter( array_map( 'absint', (array) $args['exclude'] ) ) );

		if ( [] !== $include ) {
			$query_args['post__in'] = array_slice( $include, 0, 300 );
		}

		if ( [] !== $exclude ) {
			$query_args['post__not_in'] = array_slice( $exclude, 0, 100 );
		}

		if ( '' !== (string) $args['search'] ) {
			$query_args['s'] = sanitize_text_field( (string) $args['search'] );
		}

		$tax_query = $this->build_tax_query( $args );

		if ( [] !== $tax_query ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Bounded, indexed taxonomy query.
			$query_args['tax_query'] = $tax_query;
		}

		if ( is_array( $args['meta_query'] ) && [] !== $args['meta_query'] ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Callers pass bounded, indexed keys.
			$query_args['meta_query'] = $args['meta_query'];
		}

		$this->apply_ordering( $query_args, (string) $args['orderby'], (string) $args['order'] );

		/**
		 * Filters the generated `WP_Query` arguments.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $query_args WP_Query arguments.
		 * @param array<string, mixed> $args       Repository arguments.
		 */
		return (array) apply_filters( 'bhc_product_query_args', $query_args, $args );
	}

	/**
	 * Builds the taxonomy query, including catalogue visibility.
	 *
	 * @param array<string, mixed> $args Arguments.
	 *
	 * @return array<int|string, mixed>
	 */
	private function build_tax_query( array $args ): array {
		$tax_query = is_array( $args['tax_query'] ) ? $args['tax_query'] : [];

		$categories = array_values( array_filter( array_map( 'sanitize_title', (array) $args['category'] ) ) );

		if ( [] !== $categories ) {
			$tax_query[] = [
				'taxonomy' => 'product_cat',
				'field'    => 'slug',
				'terms'    => $categories,
			];
		}

		$tags = array_values( array_filter( array_map( 'sanitize_title', (array) $args['tag'] ) ) );

		if ( [] !== $tags ) {
			$tax_query[] = [
				'taxonomy' => 'product_tag',
				'field'    => 'slug',
				'terms'    => $tags,
			];
		}

		if ( 'any' !== $args['visibility'] && taxonomy_exists( 'product_visibility' ) ) {
			$tax_query[] = [
				'taxonomy' => 'product_visibility',
				'field'    => 'name',
				'terms'    => [ 'search' === $args['visibility'] ? 'exclude-from-search' : 'exclude-from-catalog' ],
				'operator' => 'NOT IN',
			];
		}

		return $tax_query;
	}

	/**
	 * Applies ordering, routing numeric sorts through the lookup table.
	 *
	 * @param array<string, mixed> $query_args Query arguments, by reference.
	 * @param string               $orderby    Requested ordering.
	 * @param string               $order      Requested direction.
	 */
	private function apply_ordering( array &$query_args, string $orderby, string $order ): void {
		$order = 'ASC' === strtoupper( $order ) ? 'ASC' : 'DESC';

		$this->lookup_column = '';
		$this->lookup_order  = $order;

		if ( isset( self::LOOKUP_ORDERBY[ $orderby ] ) ) {
			$this->lookup_column = self::LOOKUP_ORDERBY[ $orderby ];
			$this->lookup_order  = 'price' === $orderby ? $order : ( 'price-desc' === $orderby ? 'DESC' : $order );

			$query_args['orderby'] = 'none';

			return;
		}

		$query_args['order'] = $order;

		$query_args['orderby'] = match ( $orderby ) {
			'title'      => 'title',
			'menu_order' => 'menu_order title',
			'modified'   => 'modified',
			'rand'       => 'rand',
			'post__in'   => 'post__in',
			'relevance'  => 'relevance',
			default      => 'date ID',
		};
	}

	/**
	 * Attaches the lookup-table join/where/orderby filters for one query.
	 *
	 * The callbacks are scoped by the `bhc_product_query` query var, so a
	 * nested query fired by another plugin during `pre_get_posts` is never
	 * touched, and they are always removed in the `finally` block.
	 *
	 * @param array{min:?float, max:?float, max_stock:?int} $price_filter Lookup filters.
	 * @param bool                                          $in_stock     Restrict to in-stock products.
	 * @param bool                                          $on_sale      Restrict to products on sale.
	 */
	private function attach_filters( array $price_filter, bool $in_stock, bool $on_sale ): void {
		$needs_lookup = '' !== $this->lookup_column
			|| null !== $price_filter['min']
			|| null !== $price_filter['max']
			|| null !== $price_filter['max_stock']
			|| $in_stock
			|| $on_sale;

		if ( ! $needs_lookup ) {
			return;
		}

		global $wpdb;

		$lookup = $wpdb->wc_product_meta_lookup;
		$posts  = $wpdb->posts;

		$join = static function ( string $join, WP_Query $query ) use ( $lookup, $posts ): string {
			if ( ! $query->get( 'bhc_product_query' ) || str_contains( $join, 'bhc_lookup' ) ) {
				return $join;
			}

			return $join . " INNER JOIN {$lookup} AS bhc_lookup ON {$posts}.ID = bhc_lookup.product_id ";
		};

		$where = static function ( string $where, WP_Query $query ) use ( $wpdb, $price_filter, $in_stock, $on_sale ): string {
			if ( ! $query->get( 'bhc_product_query' ) ) {
				return $where;
			}

			if ( null !== $price_filter['min'] ) {
				$where .= $wpdb->prepare( ' AND bhc_lookup.min_price >= %f ', $price_filter['min'] );
			}

			if ( null !== $price_filter['max'] ) {
				$where .= $wpdb->prepare( ' AND bhc_lookup.max_price <= %f ', $price_filter['max'] );
			}

			if ( $in_stock ) {
				$where .= " AND bhc_lookup.stock_status = 'instock' ";
			}

			if ( $on_sale ) {
				$where .= ' AND bhc_lookup.onsale = 1 ';
			}

			if ( null !== $price_filter['max_stock'] ) {
				$where .= $wpdb->prepare(
					' AND bhc_lookup.stock_quantity IS NOT NULL AND bhc_lookup.stock_quantity > 0 AND bhc_lookup.stock_quantity <= %d ',
					$price_filter['max_stock']
				);
			}

			return $where;
		};

		add_filter( 'posts_join', $join, 10, 2 );
		add_filter( 'posts_where', $where, 10, 2 );

		$this->attached['posts_join']  = $join;
		$this->attached['posts_where'] = $where;

		if ( '' !== $this->lookup_column ) {
			$column = $this->lookup_column;
			$dir    = $this->lookup_order;

			$orderby = static function ( string $orderby, WP_Query $query ) use ( $posts, $column, $dir ): string {
				if ( ! $query->get( 'bhc_product_query' ) ) {
					return $orderby;
				}

				return sprintf( 'bhc_lookup.%s %s, %s.ID DESC', $column, $dir, $posts );
			};

			add_filter( 'posts_orderby', $orderby, 10, 2 );

			$this->attached['posts_orderby'] = $orderby;
		}
	}

	/**
	 * Removes every query-scoped filter. Always runs, even on exceptions.
	 */
	private function detach_filters(): void {
		foreach ( $this->attached as $hook => $callback ) {
			remove_filter( $hook, $callback, 10 );
		}

		$this->attached = [];
	}
}
