<?php
/**
 * Creates the global product attributes and their terms.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Product\Attributes;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Contracts\LoggerInterface;

/**
 * Idempotent installer for the craft attribute taxonomy.
 *
 * Runs on schema install and from `wp bhc products sync --attributes`. Every
 * step checks for existence first, so re-running is a no-op rather than a
 * duplicate-taxonomy error.
 */
final class AttributeRegistrar {

	/**
	 * Constructor.
	 *
	 * @param LoggerInterface $logger Logger.
	 */
	public function __construct( private LoggerInterface $logger ) {}

	/**
	 * Creates any missing attributes and terms.
	 *
	 * @return array{created_attributes:int, created_terms:int}
	 */
	public function install(): array {
		if ( ! function_exists( 'wc_get_attribute_taxonomies' ) ) {
			return [
				'created_attributes' => 0,
				'created_terms'      => 0,
			];
		}

		$existing = wp_list_pluck( wc_get_attribute_taxonomies(), 'attribute_name' );

		$created_attributes = 0;
		$created_terms      = 0;

		foreach ( AttributeCatalog::all() as $slug => $definition ) {
			if ( ! in_array( $slug, $existing, true ) ) {
				$result = wc_create_attribute(
					[
						'name'         => $definition['label'],
						'slug'         => $slug,
						'type'         => 'select',
						'order_by'     => $definition['order_by'],
						'has_archives' => (bool) $definition['has_archives'],
					]
				);

				if ( is_wp_error( $result ) ) {
					$this->logger->error(
						'attributes.create_failed',
						[
							'slug'  => $slug,
							'error' => $result->get_error_message(),
						]
					);

					continue;
				}

				++$created_attributes;
			}

			$taxonomy = AttributeCatalog::taxonomy( $slug );

			// The taxonomy is only registered on the next request unless we do
			// it now, and terms cannot be inserted into an unknown taxonomy.
			if ( ! taxonomy_exists( $taxonomy ) ) {
				register_taxonomy(
					$taxonomy,
					[ 'product' ],
					[
						'hierarchical' => false,
						'show_ui'      => false,
						'query_var'    => true,
						'rewrite'      => false,
					]
				);
			}

			$menu_order = 0;

			foreach ( $definition['terms'] as $term_slug => $term_label ) {
				++$menu_order;

				if ( term_exists( $term_slug, $taxonomy ) ) {
					continue;
				}

				$term = wp_insert_term( wp_specialchars_decode( (string) $term_label ), $taxonomy, [ 'slug' => $term_slug ] );

				if ( is_wp_error( $term ) ) {
					continue;
				}

				++$created_terms;

				if ( 'menu_order' === $definition['order_by'] ) {
					update_term_meta( (int) $term['term_id'], 'order_' . $taxonomy, $menu_order );
				}
			}
		}

		if ( $created_attributes > 0 ) {
			delete_transient( 'wc_attribute_taxonomies' );

			if ( function_exists( 'wc_get_attribute_taxonomy_ids' ) ) {
				wc_get_attribute_taxonomy_ids();
			}
		}

		$this->logger->info(
			'attributes.installed',
			[
				'created_attributes' => $created_attributes,
				'created_terms'      => $created_terms,
			]
		);

		return [
			'created_attributes' => $created_attributes,
			'created_terms'      => $created_terms,
		];
	}
}
