<?php
/**
 * Validated catalogue filter request.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Search;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Product\Attributes\AttributeCatalog;
use BoneHornCrafts\Core\Security\Sanitizer;

/**
 * Parses and validates storefront filter input.
 *
 * Every filter the shop page understands is declared here once, so the same
 * validated object serves the server-rendered archive, the AJAX filter endpoint
 * and the canonical-URL builder. Unknown parameters are dropped rather than
 * passed through, which is what stops a crafted query string from turning into
 * an expensive or unindexed query.
 */
final class FilterRequest {

	public const ALLOWED_ORDERBY = [ 'relevance', 'date', 'popularity', 'price', 'price-desc', 'rating', 'title' ];

	/**
	 * Selected attribute term slugs keyed by attribute slug.
	 *
	 * @var array<string, string[]>
	 */
	public readonly array $attributes;

	/**
	 * Constructor.
	 *
	 * @param array<string, string[]> $attributes Attribute selections.
	 * @param string[]                $categories Category slugs.
	 * @param float|null              $min_price  Minimum price.
	 * @param float|null              $max_price  Maximum price.
	 * @param bool                    $in_stock   Restrict to in-stock items.
	 * @param bool                    $on_sale    Restrict to sale items.
	 * @param string                  $orderby    Ordering key.
	 * @param string                  $search     Search term.
	 * @param int                     $page       Page number.
	 * @param int                     $per_page   Results per page.
	 */
	private function __construct(
		array $attributes,
		public readonly array $categories = [],
		public readonly ?float $min_price = null,
		public readonly ?float $max_price = null,
		public readonly bool $in_stock = false,
		public readonly bool $on_sale = false,
		public readonly string $orderby = 'date',
		public readonly string $search = '',
		public readonly int $page = 1,
		public readonly int $per_page = 12
	) {
		$this->attributes = $attributes;
	}

	/**
	 * Builds a request from raw input (query string or REST parameters).
	 *
	 * @param array<string, mixed> $input Raw input.
	 */
	public static function from_array( array $input ): self {
		$attributes = [];

		foreach ( AttributeCatalog::facet_slugs() as $slug ) {
			if ( ! isset( $input[ $slug ] ) ) {
				continue;
			}

			$selected = Sanitizer::slug_list( $input[ $slug ], 12 );

			// Only terms that exist in the live taxonomy are accepted. This
			// used to check against AttributeCatalog's hardcoded vocabulary,
			// which is the installer's term list, not the store's: a catalogue
			// imported from outside carries its own terms, and every one of
			// them was being rejected here — the URL said ?colour=walnut, the
			// panel offered it, and the filter silently returned everything.
			$valid = self::existing_term_slugs( $slug, $selected );

			if ( [] !== $valid ) {
				$attributes[ $slug ] = $valid;
			}
		}

		$min_price = isset( $input['min_price'] ) && is_numeric( $input['min_price'] ) ? (float) $input['min_price'] : null;
		$max_price = isset( $input['max_price'] ) && is_numeric( $input['max_price'] ) ? (float) $input['max_price'] : null;

		if ( null !== $min_price && null !== $max_price && $min_price > $max_price ) {
			[ $min_price, $max_price ] = [ $max_price, $min_price ];
		}

		$orderby = Sanitizer::key( $input['orderby'] ?? 'date' );

		return new self(
			$attributes,
			Sanitizer::slug_list( $input['category'] ?? [], 6 ),
			null === $min_price ? null : max( 0.0, round( $min_price, 2 ) ),
			null === $max_price ? null : max( 0.0, round( $max_price, 2 ) ),
			! empty( $input['in_stock'] ),
			! empty( $input['on_sale'] ),
			in_array( $orderby, self::ALLOWED_ORDERBY, true ) ? $orderby : 'date',
			Sanitizer::text( $input['s'] ?? ( $input['search'] ?? '' ), 120 ),
			max( 1, min( 100, absint( $input['page'] ?? 1 ) ) ),
			max( 1, min( 48, absint( $input['per_page'] ?? 12 ) ) )
		);
	}

	/**
	 * Whether any filter is active.
	 */
	public function has_filters(): bool {
		return [] !== $this->attributes
			|| [] !== $this->categories
			|| null !== $this->min_price
			|| null !== $this->max_price
			|| $this->in_stock
			|| $this->on_sale
			|| '' !== $this->search;
	}

	/**
	 * Builds the taxonomy query fragment for the selected attributes.
	 *
	 * Multiple values inside one attribute are OR-ed (show me black *and*
	 * charcoal horn); different attributes are AND-ed (black horn *and* jigged
	 * finish). That is the behaviour shoppers expect and it keeps the query
	 * planner on indexed term relationships.
	 *
	 * @return array<int|string, mixed>
	 */
	public function tax_query(): array {
		$tax_query = [];

		// Categories belong here, not only in to_query_args(). The shop archive
		// applies filters by merging this into the main WooCommerce query,
		// while the AJAX grid goes through ProductQuery — so a filter that
		// exists only in the args array silently does nothing on the archive.
		// That is exactly what happened to the category facet: the panel
		// offered it, the URL carried it, and /shop/?category=horn-scales
		// returned the whole catalogue.
		if ( [] !== $this->categories ) {
			$tax_query[] = [
				'taxonomy'         => 'product_cat',
				'field'            => 'slug',
				'terms'            => $this->categories,
				'operator'         => 'IN',
				// A parent category should show what is on its child shelves.
				'include_children' => true,
			];
		}

		foreach ( $this->attributes as $slug => $terms ) {
			$tax_query[] = [
				'taxonomy'         => AttributeCatalog::taxonomy( $slug ),
				'field'            => 'slug',
				'terms'            => $terms,
				'operator'         => 'IN',
				'include_children' => false,
			];
		}

		if ( count( $tax_query ) > 1 ) {
			$tax_query['relation'] = 'AND';
		}

		return $tax_query;
	}

	/**
	 * Converts the request into `ProductQuery` arguments.
	 *
	 * @return array<string, mixed>
	 */
	public function to_query_args(): array {
		return [
			'limit'         => $this->per_page,
			'page'          => $this->page,
			'orderby'       => 'relevance' === $this->orderby && '' === $this->search ? 'date' : $this->orderby,
			'order'         => 'price' === $this->orderby || 'title' === $this->orderby ? 'ASC' : 'DESC',
			// Categories are expressed in tax_query() so both the archive and
			// the AJAX path go through one implementation.
			'tax_query'     => $this->tax_query(),
			'search'        => $this->search,
			'min_price'     => $this->min_price,
			'max_price'     => $this->max_price,
			'in_stock_only' => $this->in_stock,
			'on_sale'       => $this->on_sale,
			'count_total'   => true,
			'visibility'    => '' !== $this->search ? 'search' : 'catalog',
		];
	}

	/**
	 * The subset of the selected slugs that exist in the attribute's taxonomy.
	 *
	 * One `get_terms()` per attribute that actually appears in the input, so an
	 * unfiltered request costs nothing, and the lookup is served from the term
	 * cache on a warm store. Input order is preserved: the canonical query
	 * string sorts later, and the visitor's order is otherwise meaningful.
	 *
	 * @param string   $slug     Attribute slug without prefix.
	 * @param string[] $selected Sanitised candidate slugs.
	 *
	 * @return string[]
	 */
	private static function existing_term_slugs( string $slug, array $selected ): array {
		if ( [] === $selected ) {
			return [];
		}

		$taxonomy = AttributeCatalog::taxonomy( $slug );

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return [];
		}

		$existing = get_terms(
			[
				'taxonomy'   => $taxonomy,
				'slug'       => $selected,
				'hide_empty' => false,
				'fields'     => 'slugs',
			]
		);

		if ( is_wp_error( $existing ) ) {
			return [];
		}

		return array_values( array_intersect( $selected, array_map( 'strval', $existing ) ) );
	}

	/**
	 * Rebuilds the canonical query string for the active filters.
	 *
	 * Parameters are emitted in a fixed order so the same selection always
	 * produces the same URL — important for caching and for the canonical tag.
	 *
	 * @return array<string, string>
	 */
	public function to_query_string_args(): array {
		$args = [];

		foreach ( AttributeCatalog::facet_slugs() as $slug ) {
			if ( isset( $this->attributes[ $slug ] ) ) {
				$terms = $this->attributes[ $slug ];

				sort( $terms );

				$args[ $slug ] = implode( ',', $terms );
			}
		}

		if ( [] !== $this->categories ) {
			$args['category'] = implode( ',', $this->categories );
		}

		if ( null !== $this->min_price ) {
			$args['min_price'] = (string) $this->min_price;
		}

		if ( null !== $this->max_price ) {
			$args['max_price'] = (string) $this->max_price;
		}

		if ( $this->in_stock ) {
			$args['in_stock'] = '1';
		}

		if ( $this->on_sale ) {
			$args['on_sale'] = '1';
		}

		if ( 'date' !== $this->orderby ) {
			$args['orderby'] = $this->orderby;
		}

		if ( '' !== $this->search ) {
			$args['s'] = $this->search;
		}

		return $args;
	}

	/**
	 * Stable cache fragment for this filter selection.
	 */
	public function cache_key(): string {
		return substr( md5( (string) wp_json_encode( $this->to_query_string_args() ) . '|' . $this->page . '|' . $this->per_page ), 0, 20 );
	}
}
