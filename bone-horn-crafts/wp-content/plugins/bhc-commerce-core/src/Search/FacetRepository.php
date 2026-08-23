<?php
/**
 * Facet count repository.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Search;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Cache\CacheManager;
use BoneHornCrafts\Core\Database\AbstractRepository;
use BoneHornCrafts\Core\Product\Attributes\AttributeCatalog;

/**
 * Counts how many published products carry each attribute term.
 *
 * Facet counts are the classic place where a filter UI turns into a query
 * storm: one `COUNT()` per checkbox, on every page load. Instead this runs one
 * grouped query per taxonomy, caches the whole map in the `facets` group, and
 * lets the invalidator bump it when the catalogue changes.
 */
final class FacetRepository extends AbstractRepository {

	/**
	 * Cache manager scoped to the facets group.
	 *
	 * @var CacheManager
	 */
	private CacheManager $cache;

	/**
	 * Constructor.
	 *
	 * @param CacheManager $cache Cache manager.
	 */
	public function __construct( CacheManager $cache ) {
		parent::__construct();

		$this->cache = $cache->for_group( 'facets' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function table(): string {
		return $this->db->term_relationships;
	}

	/**
	 * Returns term counts for one taxonomy.
	 *
	 * @param string $taxonomy Taxonomy name.
	 *
	 * @return array<string, int> term slug => product count.
	 */
	public function counts_for_taxonomy( string $taxonomy ): array {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return [];
		}

		return (array) $this->cache->remember(
			'counts_' . sanitize_key( $taxonomy ),
			function () use ( $taxonomy ): array {
				$relationships = $this->db->term_relationships;
				$taxonomies    = $this->db->term_taxonomy;
				$terms         = $this->db->terms;
				$posts         = $this->db->posts;

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Core table names; the taxonomy value is prepared.
				$rows = $this->db->get_results(
					$this->db->prepare(
						"SELECT t.slug AS slug, COUNT(DISTINCT p.ID) AS total
						 FROM {$terms} t
						 INNER JOIN {$taxonomies} tt ON tt.term_id = t.term_id
						 INNER JOIN {$relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
						 INNER JOIN {$posts} p ON p.ID = tr.object_id
						 WHERE tt.taxonomy = %s
						   AND p.post_type = 'product'
						   AND p.post_status = 'publish'
						 GROUP BY t.slug",
						$taxonomy
					),
					ARRAY_A
				);

				$counts = [];

				foreach ( (array) $rows as $row ) {
					$counts[ (string) $row['slug'] ] = (int) $row['total'];
				}

				return $counts;
			},
			2 * HOUR_IN_SECONDS
		);
	}

	/**
	 * Returns the full facet model for the storefront filter panel.
	 *
	 * @return array<int, array{slug:string, label:string, options:array<int, array{slug:string,label:string,count:int}>}>
	 */
	public function facets(): array {
		$facets = $this->category_facet();

		foreach ( AttributeCatalog::all() as $slug => $definition ) {
			if ( empty( $definition['facet'] ) ) {
				continue;
			}

			$taxonomy = AttributeCatalog::taxonomy( $slug );
			$counts   = $this->counts_for_taxonomy( $taxonomy );
			$options  = [];

			// The options come from the taxonomy tables, never from the
			// hardcoded vocabulary in AttributeCatalog. That vocabulary is what
			// the installer creates and the demo seeder assigns — on a store
			// that imported a real catalogue, the live terms are different, and
			// building the panel from the hardcoded list meant real terms were
			// invisible and could not be filtered on, while the panel offered
			// demo terms that matched nothing. The catalog still decides which
			// attributes are facets and what the group is labelled; the
			// database decides what is in them.
			//
			// No orderby is passed on purpose: WooCommerce's get_terms_defaults
			// filter orders an attribute taxonomy by its configured sort.
			$terms = get_terms(
				[
					'taxonomy'   => $taxonomy,
					'hide_empty' => true,
				]
			);

			if ( is_wp_error( $terms ) ) {
				$terms = [];
			}

			foreach ( $terms as $term ) {
				// hide_empty counts any attached object; ours count published
				// products only, so a term used solely by drafts still drops.
				$count = $counts[ (string) $term->slug ] ?? 0;

				if ( 0 === $count ) {
					continue;
				}

				$options[] = [
					'slug'  => (string) $term->slug,
					'label' => wp_specialchars_decode( (string) $term->name ),
					'count' => $count,
				];
			}

			$options = $this->cap_options( $options, $slug );

			if ( [] === $options ) {
				continue;
			}

			$facets[] = [
				'slug'    => $slug,
				'label'   => (string) $definition['label'],
				'options' => $options,
			];
		}

		return $facets;
	}

	/**
	 * Caps a facet's option list at a usable length.
	 *
	 * The imported catalogue carries 144 size terms. Rendering all of them
	 * turns a filter into a directory, so past the cap only the most-populated
	 * terms are kept — the long tail is reachable through search and the
	 * category pages, and a term used by two products was never going to be
	 * how anyone navigates. The survivors keep the taxonomy's own order so the
	 * list does not reshuffle as stock moves.
	 *
	 * @param array<int, array{slug:string, label:string, count:int}> $options Options in taxonomy order.
	 * @param string                                                  $slug    Attribute slug, for the filter.
	 *
	 * @return array<int, array{slug:string, label:string, count:int}>
	 */
	private function cap_options( array $options, string $slug ): array {
		/**
		 * Filters the maximum number of options one facet may show.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $limit Maximum options. Zero or below disables the cap.
		 * @param string $slug  Attribute slug.
		 */
		$limit = (int) apply_filters( 'bhc_facet_option_limit', 20, $slug );

		if ( $limit <= 0 || count( $options ) <= $limit ) {
			return $options;
		}

		$by_count = $options;

		usort( $by_count, static fn ( array $a, array $b ): int => $b['count'] <=> $a['count'] );

		$keep = array_column( array_slice( $by_count, 0, $limit ), 'slug' );
		$keep = array_flip( $keep );

		return array_values(
			array_filter( $options, static fn ( array $option ): bool => isset( $keep[ $option['slug'] ] ) )
		);
	}

	/**
	 * Builds the product-category facet.
	 *
	 * Categories are the shelf a customer thinks in — "show me horn scales" is a
	 * more natural first filter than any attribute — so this facet leads the
	 * panel. It is not part of AttributeCatalog because `product_cat` is a
	 * WooCommerce taxonomy rather than a product attribute; only the counting is
	 * shared.
	 *
	 * Only top-level categories are offered. Nesting the full tree into a
	 * checkbox list turns a filter into a sitemap, and the child terms are
	 * reachable from the category pages themselves.
	 *
	 * @return array<int, array{slug:string, label:string, options:array<int, array{slug:string,label:string,count:int}>}>
	 */
	private function category_facet(): array {
		if ( ! taxonomy_exists( 'product_cat' ) ) {
			return [];
		}

		$counts = $this->counts_for_taxonomy( 'product_cat' );

		if ( [] === $counts ) {
			return [];
		}

		$terms = get_terms(
			[
				'taxonomy'   => 'product_cat',
				'parent'     => 0,
				'hide_empty' => true,
				'orderby'    => 'name',
			]
		);

		if ( is_wp_error( $terms ) || [] === $terms ) {
			return [];
		}

		$options = [];

		foreach ( $terms as $term ) {
			$count = $counts[ $term->slug ] ?? 0;

			// "Uncategorized" is a WordPress default, not a shelf. It has no
			// products in a seeded store and would only ever confuse.
			if ( 0 === $count || 'uncategorized' === $term->slug ) {
				continue;
			}

			$options[] = [
				'slug'  => (string) $term->slug,
				'label' => wp_specialchars_decode( (string) $term->name ),
				'count' => $count,
			];
		}

		if ( [] === $options ) {
			return [];
		}

		return [
			[
				'slug'    => 'category',
				'label'   => __( 'Category', 'bhc-commerce-core' ),
				'options' => $options,
			],
		];
	}

	/**
	 * Price range across the published catalogue, for the price slider bounds.
	 *
	 * @return array{min:float, max:float}
	 */
	public function price_range(): array {
		return (array) $this->cache->remember(
			'price_range',
			function (): array {
				$lookup = $this->db->wc_product_meta_lookup;
				$posts  = $this->db->posts;

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Internal table names, no user input.
				$row = $this->db->get_row(
					"SELECT MIN(l.min_price) AS min_price, MAX(l.max_price) AS max_price
					 FROM {$lookup} l
					 INNER JOIN {$posts} p ON p.ID = l.product_id
					 WHERE p.post_status = 'publish' AND p.post_type = 'product'",
					ARRAY_A
				);

				return [
					'min' => (float) ( $row['min_price'] ?? 0 ),
					'max' => (float) ( $row['max_price'] ?? 0 ),
				];
			},
			2 * HOUR_IN_SECONDS
		);
	}
}
