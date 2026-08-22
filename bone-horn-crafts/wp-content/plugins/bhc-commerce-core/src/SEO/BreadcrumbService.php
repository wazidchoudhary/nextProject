<?php
/**
 * Breadcrumb trail builder.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\SEO;

defined( 'ABSPATH' ) || exit;

use WC_Product;
use WP_Term;

/**
 * Builds one breadcrumb trail that both the visible breadcrumb and the
 * `BreadcrumbList` schema consume.
 *
 * Sharing the trail is the point: a visible breadcrumb that disagrees with the
 * structured data is a Search Console warning waiting to happen.
 */
final class BreadcrumbService {

	/**
	 * Memoised trail for the current request.
	 *
	 * @var array<int, array{label:string, url:string}>|null
	 */
	private ?array $trail = null;

	/**
	 * Constructor.
	 *
	 * @param BrandProfile $brand Brand profile.
	 */
	public function __construct( private BrandProfile $brand ) {}

	/**
	 * Returns the trail for the current request.
	 *
	 * @return array<int, array{label:string, url:string}>
	 */
	public function trail(): array {
		if ( null !== $this->trail ) {
			return $this->trail;
		}

		$trail = [
			[
				'label' => __( 'Home', 'bhc-commerce-core' ),
				'url'   => home_url( '/' ),
			],
		];

		if ( function_exists( 'is_shop' ) && is_shop() ) {
			$shop_id = wc_get_page_id( 'shop' );

			$trail[] = [
				'label' => $shop_id > 0 ? get_the_title( $shop_id ) : __( 'Shop', 'bhc-commerce-core' ),
				'url'   => '',
			];

			/** This filter is documented later in this method. */
			return $this->trail = (array) apply_filters( 'bhc_breadcrumb_trail', $trail );
		}

		if ( function_exists( 'is_product' ) && ( is_product_category() || is_product_tag() || is_product() ) ) {
			$shop_id = wc_get_page_id( 'shop' );

			if ( $shop_id > 0 ) {
				$trail[] = [
					'label' => get_the_title( $shop_id ),
					'url'   => (string) get_permalink( $shop_id ),
				];
			}
		}

		if ( function_exists( 'is_product' ) && is_product() ) {
			$product = wc_get_product( get_queried_object_id() );

			if ( $product instanceof WC_Product ) {
				$term = $this->primary_term( $product->get_id() );

				if ( $term instanceof WP_Term ) {
					foreach ( $this->ancestor_terms( $term ) as $ancestor ) {
						$trail[] = [
							'label' => $ancestor->name,
							'url'   => (string) get_term_link( $ancestor ),
						];
					}
				}

				$trail[] = [
					'label' => $product->get_name(),
					'url'   => (string) $product->get_permalink(),
				];
			}
		} elseif ( function_exists( 'is_product_category' ) && is_product_category() ) {
			$term = get_queried_object();

			if ( $term instanceof WP_Term ) {
				foreach ( $this->ancestor_terms( $term ) as $ancestor ) {
					$trail[] = [
						'label' => $ancestor->name,
						'url'   => (string) get_term_link( $ancestor ),
					];
				}

				$trail[] = [
					'label' => $term->name,
					'url'   => (string) get_term_link( $term ),
				];
			}
		} elseif ( is_singular() ) {
			$post_id = get_queried_object_id();

			if ( 'post' === get_post_type( $post_id ) ) {
				$blog_id = (int) get_option( 'page_for_posts' );

				if ( $blog_id > 0 ) {
					$trail[] = [
						'label' => get_the_title( $blog_id ),
						'url'   => (string) get_permalink( $blog_id ),
					];
				}
			}

			foreach ( array_reverse( (array) get_post_ancestors( $post_id ) ) as $ancestor_id ) {
				$trail[] = [
					'label' => get_the_title( (int) $ancestor_id ),
					'url'   => (string) get_permalink( (int) $ancestor_id ),
				];
			}

			$trail[] = [
				'label' => get_the_title( $post_id ),
				'url'   => (string) get_permalink( $post_id ),
			];
		} elseif ( is_search() ) {
			$trail[] = [
				'label' => sprintf(
					/* translators: %s: search term. */
					__( 'Search results for “%s”', 'bhc-commerce-core' ),
					get_search_query()
				),
				'url'   => '',
			];
		} elseif ( is_archive() ) {
			$trail[] = [
				'label' => wp_strip_all_tags( (string) get_the_archive_title() ),
				'url'   => '',
			];
		}

		/**
		 * Filters the breadcrumb trail.
		 *
		 * @since 1.0.0
		 *
		 * @param array<int, array{label:string, url:string}> $trail Trail items.
		 */
		return $this->trail = (array) apply_filters( 'bhc_breadcrumb_trail', $trail );
	}

	/**
	 * Returns the deepest category assigned to a product.
	 *
	 * @param int $product_id Product id.
	 */
	private function primary_term( int $product_id ): ?WP_Term {
		$terms = get_the_terms( $product_id, 'product_cat' );

		if ( ! is_array( $terms ) || [] === $terms ) {
			return null;
		}

		usort(
			$terms,
			static fn ( WP_Term $a, WP_Term $b ): int => count( get_ancestors( $b->term_id, 'product_cat' ) ) <=> count( get_ancestors( $a->term_id, 'product_cat' ) )
		);

		return $terms[0];
	}

	/**
	 * Returns a term plus its ancestors, ordered from the top down.
	 *
	 * @param WP_Term $term Term.
	 *
	 * @return WP_Term[]
	 */
	private function ancestor_terms( WP_Term $term ): array {
		$ancestors = array_reverse( (array) get_ancestors( $term->term_id, $term->taxonomy ) );
		$terms     = [];

		foreach ( $ancestors as $ancestor_id ) {
			$ancestor = get_term( (int) $ancestor_id, $term->taxonomy );

			if ( $ancestor instanceof WP_Term ) {
				$terms[] = $ancestor;
			}
		}

		return $terms;
	}

	/**
	 * Renders the visible breadcrumb markup.
	 */
	public function render(): string {
		$trail = $this->trail();

		if ( count( $trail ) < 2 ) {
			return '';
		}

		$items = '';
		$last  = count( $trail ) - 1;

		foreach ( $trail as $index => $crumb ) {
			$label = esc_html( (string) $crumb['label'] );

			if ( $index === $last || '' === $crumb['url'] ) {
				$items .= sprintf( '<li class="bhc-breadcrumbs__item"><span aria-current="page">%s</span></li>', $label );

				continue;
			}

			$items .= sprintf(
				'<li class="bhc-breadcrumbs__item"><a href="%1$s">%2$s</a></li>',
				esc_url( (string) $crumb['url'] ),
				$label
			);
		}

		return sprintf(
			'<nav class="bhc-breadcrumbs" aria-label="%1$s"><ol class="bhc-breadcrumbs__list">%2$s</ol></nav>',
			esc_attr__( 'Breadcrumb', 'bhc-commerce-core' ),
			$items
		);
	}
}
