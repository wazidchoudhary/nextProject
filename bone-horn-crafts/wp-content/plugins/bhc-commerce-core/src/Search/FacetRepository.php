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
		$facets = [];

		foreach ( AttributeCatalog::all() as $slug => $definition ) {
			if ( empty( $definition['facet'] ) ) {
				continue;
			}

			$taxonomy = AttributeCatalog::taxonomy( $slug );
			$counts   = $this->counts_for_taxonomy( $taxonomy );
			$options  = [];

			foreach ( $definition['terms'] as $term_slug => $term_label ) {
				$count = $counts[ $term_slug ] ?? 0;

				if ( 0 === $count ) {
					continue;
				}

				$options[] = [
					'slug'  => $term_slug,
					'label' => wp_specialchars_decode( (string) $term_label ),
					'count' => $count,
				];
			}

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
