<?php
/**
 * Document metadata: titles, descriptions, canonicals and social tags.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\SEO;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Contracts\HookableInterface;
use BoneHornCrafts\Core\Support\Str;
use WC_Product;
use WP_Term;

/**
 * Emits the head metadata the storefront needs, with no SEO plugin installed.
 *
 * The plugin deliberately does not fight a dedicated SEO plugin: if Yoast, Rank
 * Math or SEOPress is active, `should_output()` stands down and lets it own the
 * head. That keeps the demo self-sufficient while remaining a good citizen on a
 * real site.
 */
final class MetaTagService implements HookableInterface {

	/**
	 * Constructor.
	 *
	 * @param BrandProfile $brand Brand profile.
	 */
	public function __construct( private BrandProfile $brand ) {}

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		if ( ! $this->should_output() ) {
			return;
		}

		add_filter( 'document_title_parts', [ $this, 'title_parts' ], 20, 1 );
		add_filter( 'document_title_separator', [ $this, 'title_separator' ], 20 );
		add_action( 'wp_head', [ $this, 'render_meta' ], 2 );

		// Core prints its own canonical; ours is context aware (paged archives,
		// filtered shop views), so core's is replaced rather than duplicated.
		remove_action( 'wp_head', 'rel_canonical' );
	}

	/**
	 * Whether this service should own the document head.
	 */
	public function should_output(): bool {
		$owned_by_seo_plugin = defined( 'WPSEO_VERSION' )
			|| defined( 'RANK_MATH_VERSION' )
			|| defined( 'SEOPRESS_VERSION' )
			|| class_exists( '\\All_in_One_SEO_Pack' );

		/**
		 * Filters whether the plugin emits document metadata.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $should_output True when no dedicated SEO plugin is active.
		 */
		return (bool) apply_filters( 'bhc_output_seo_meta', ! $owned_by_seo_plugin );
	}

	/**
	 * Builds the document title parts.
	 *
	 * @param array<string, string> $parts Title parts.
	 *
	 * @return array<string, string>
	 */
	public function title_parts( array $parts ): array {
		$parts['site']    = $this->brand->name();
		$parts['tagline'] = $this->brand->tagline();

		if ( is_front_page() ) {
			$parts['title'] = $this->brand->name();
			$parts['site']  = $this->brand->tagline();

			unset( $parts['tagline'] );

			return $parts;
		}

		if ( function_exists( 'is_shop' ) && is_shop() ) {
			$parts['title'] = __( 'Shop Bone, Horn &amp; Wood Craft Supplies', 'bhc-commerce-core' );
		}

		if ( function_exists( 'is_product' ) && is_product() ) {
			$product = wc_get_product( get_queried_object_id() );

			if ( $product instanceof WC_Product ) {
				$parts['title'] = $product->get_name();
			}
		}

		unset( $parts['tagline'] );

		return $parts;
	}

	/**
	 * Title separator.
	 */
	public function title_separator(): string {
		return '|';
	}

	/**
	 * Prints canonical, description and social metadata.
	 */
	public function render_meta(): void {
		$description = $this->description();
		$canonical   = $this->canonical_url();
		$image       = $this->image_url();
		$title       = wp_strip_all_tags( (string) wp_get_document_title() );

		if ( '' !== $canonical ) {
			printf( "<link rel=\"canonical\" href=\"%s\" />\n", esc_url( $canonical ) );
		}

		$this->render_pagination_links();

		if ( '' !== $description ) {
			printf( "<meta name=\"description\" content=\"%s\" />\n", esc_attr( $description ) );
		}

		$keywords = $this->keywords();

		if ( '' !== $keywords ) {
			printf( "<meta name=\"keywords\" content=\"%s\" />\n", esc_attr( $keywords ) );
		}

		printf( "<meta property=\"og:site_name\" content=\"%s\" />\n", esc_attr( $this->brand->name() ) );
		printf( "<meta property=\"og:type\" content=\"%s\" />\n", esc_attr( $this->og_type() ) );
		printf( "<meta property=\"og:title\" content=\"%s\" />\n", esc_attr( $title ) );
		printf( "<meta property=\"og:locale\" content=\"%s\" />\n", esc_attr( str_replace( '-', '_', get_bloginfo( 'language' ) ) ) );

		if ( '' !== $canonical ) {
			printf( "<meta property=\"og:url\" content=\"%s\" />\n", esc_url( $canonical ) );
		}

		if ( '' !== $description ) {
			printf( "<meta property=\"og:description\" content=\"%s\" />\n", esc_attr( $description ) );
		}

		if ( '' !== $image ) {
			printf( "<meta property=\"og:image\" content=\"%s\" />\n", esc_url( $image ) );
			printf( "<meta property=\"og:image:alt\" content=\"%s\" />\n", esc_attr( $title ) );
		}

		$this->render_product_meta();

		printf( "<meta name=\"twitter:card\" content=\"%s\" />\n", '' !== $image ? 'summary_large_image' : 'summary' );

		$handle = $this->brand->social_handle();

		if ( '' !== $handle ) {
			printf( "<meta name=\"twitter:site\" content=\"%s\" />\n", esc_attr( $handle ) );
		}

		printf( "<meta name=\"twitter:title\" content=\"%s\" />\n", esc_attr( $title ) );

		if ( '' !== $description ) {
			printf( "<meta name=\"twitter:description\" content=\"%s\" />\n", esc_attr( $description ) );
		}

		if ( '' !== $image ) {
			printf( "<meta name=\"twitter:image\" content=\"%s\" />\n", esc_url( $image ) );
		}
	}

	/**
	 * Prints product specific Open Graph properties.
	 */
	private function render_product_meta(): void {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		$product = wc_get_product( get_queried_object_id() );

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		printf( "<meta property=\"product:price:amount\" content=\"%s\" />\n", esc_attr( (string) wc_get_price_to_display( $product ) ) );
		printf( "<meta property=\"product:price:currency\" content=\"%s\" />\n", esc_attr( get_woocommerce_currency() ) );
		printf( "<meta property=\"product:availability\" content=\"%s\" />\n", esc_attr( $product->is_in_stock() ? 'in stock' : 'out of stock' ) );
		printf( "<meta property=\"product:brand\" content=\"%s\" />\n", esc_attr( $this->brand->name() ) );

		$sku = $product->get_sku();

		if ( '' !== $sku ) {
			printf( "<meta property=\"product:retailer_item_id\" content=\"%s\" />\n", esc_attr( $sku ) );
		}
	}

	/**
	 * Prints rel=prev/next for paged archives.
	 */
	private function render_pagination_links(): void {
		if ( ! is_archive() && ! is_home() && ! ( function_exists( 'is_shop' ) && is_shop() ) ) {
			return;
		}

		global $wp_query;

		$paged = max( 1, (int) get_query_var( 'paged' ) );
		$pages = isset( $wp_query->max_num_pages ) ? (int) $wp_query->max_num_pages : 1;

		if ( $paged > 1 ) {
			// Canonicalised like every other absolute URL in the head. Leaving
			// these on the request host while the canonical points elsewhere
			// tells a crawler two different things about the same page.
			printf( "<link rel=\"prev\" href=\"%s\" />\n", esc_url( $this->brand->canonicalise( (string) get_pagenum_link( $paged - 1 ) ) ) );
		}

		if ( $paged < $pages ) {
			printf( "<link rel=\"next\" href=\"%s\" />\n", esc_url( $this->brand->canonicalise( (string) get_pagenum_link( $paged + 1 ) ) ) );
		}
	}

	/**
	 * Resolves the canonical URL for the current request.
	 */
	public function canonical_url(): string {
		$url = '';

		if ( is_front_page() ) {
			$url = home_url( '/' );
		} elseif ( is_singular() ) {
			$url = (string) get_permalink( get_queried_object_id() );
		} elseif ( function_exists( 'is_shop' ) && is_shop() ) {
			$shop_id = wc_get_page_id( 'shop' );
			$url     = $shop_id > 0 ? (string) get_permalink( $shop_id ) : home_url( '/' );
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();

			if ( $term instanceof WP_Term ) {
				$link = get_term_link( $term );
				$url  = is_string( $link ) ? $link : '';
			}
		} elseif ( is_post_type_archive() ) {
			$url = (string) get_post_type_archive_link( (string) get_query_var( 'post_type' ) );
		} elseif ( is_home() ) {
			$blog_id = (int) get_option( 'page_for_posts' );
			$url     = $blog_id > 0 ? (string) get_permalink( $blog_id ) : home_url( '/' );
		}

		if ( '' === $url ) {
			return '';
		}

		// Paged archives are distinct documents and must self-canonicalise;
		// filtered views collapse onto the unfiltered archive so the facet
		// combinations never fragment the index.
		$paged = max( 1, (int) get_query_var( 'paged' ) );

		if ( $paged > 1 ) {
			$url = trailingslashit( $url ) . 'page/' . $paged . '/';
		}

		/**
		 * Filters the canonical URL.
		 *
		 * @since 1.0.0
		 *
		 * @param string $url Canonical URL.
		 */
		return (string) apply_filters( 'bhc_canonical_url', $this->brand->canonicalise( $url ) );
	}

	/**
	 * Resolves the meta description for the current request.
	 */
	public function description(): string {
		$description = '';

		if ( is_front_page() ) {
			$description = __( 'Hand-selected bone, horn and wood craft materials cut, sanded and matched in pairs for knife makers, luthiers, pen turners and leather workers. Worldwide export from our workshop.', 'bhc-commerce-core' );
		} elseif ( function_exists( 'is_product' ) && is_product() ) {
			$product = wc_get_product( get_queried_object_id() );

			if ( $product instanceof WC_Product ) {
				$description = $product->get_short_description() ?: $product->get_description();
			}
		} elseif ( is_singular() ) {
			$post_id     = get_queried_object_id();
			$description = (string) get_post_field( 'post_excerpt', $post_id );

			if ( '' === $description ) {
				$description = (string) get_post_field( 'post_content', $post_id );
			}
		} elseif ( is_tax() || is_category() || is_tag() ) {
			$term = get_queried_object();

			if ( $term instanceof WP_Term ) {
				$description = $term->description;

				// An imported catalogue arrives with category names and no
				// term descriptions, which left every category page with no
				// meta description at all — search engines then invent one
				// from whatever text is nearest the top of the template.
				// A generated sentence built from the term's own name, parent
				// and product count is not prose, but it is accurate and it is
				// specific to the page. Writing real copy into the term
				// description overrides it.
				if ( '' === trim( (string) $description ) ) {
					$description = $this->generated_term_description( $term );
				}
			}
		} elseif ( function_exists( 'is_shop' ) && is_shop() ) {
			$description = __( 'Browse knife handle scales, guitar parts, pen blanks, drinking horns and finishing stock in camel bone, cattle bone, water buffalo horn, rams horn and stabilized wood.', 'bhc-commerce-core' );
		} elseif ( is_home() ) {
			// The posts page is neither singular nor an archive object, so it
			// fell through every branch above and shipped with no description
			// at all.
			$posts_page = (int) get_option( 'page_for_posts' );

			$description = $posts_page > 0
				? (string) get_post_field( 'post_excerpt', $posts_page )
				: '';

			if ( '' === $description ) {
				$description = __( 'Notes from the workshop on choosing, cutting and finishing bone, horn and wood.', 'bhc-commerce-core' );
			}
		}

		$description = wp_strip_all_tags( strip_shortcodes( (string) $description ) );

		// Page 2 of an archive is a different page and needs a different
		// description; repeating page one's verbatim is a duplicate-content
		// signal on every paginated view in the store.
		$paged = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );

		if ( $paged > 1 && '' !== $description ) {
			$description = trim( Str::truncate( $description, 130, '' ) ) . sprintf(
				/* translators: %d: page number. */
				__( ' — page %d.', 'bhc-commerce-core' ),
				$paged
			);
		}

		/**
		 * Filters the meta description.
		 *
		 * @since 1.0.0
		 *
		 * @param string $description Meta description.
		 */
		return (string) apply_filters( 'bhc_meta_description', Str::truncate( $description, 155, '' ) );
	}

	/**
	 * Composes a description for a term that has none of its own.
	 *
	 * @param WP_Term $term Queried term.
	 */
	private function generated_term_description( WP_Term $term ): string {
		$parent = null;

		if ( $term->parent > 0 ) {
			$found = get_term( (int) $term->parent, $term->taxonomy );

			if ( $found instanceof WP_Term ) {
				$parent = $found;
			}
		}

		$count = (int) $term->count;

		if ( $count > 0 && null !== $parent ) {
			return sprintf(
				/* translators: 1: number of products, 2: category name, 3: parent category name. */
				_n(
					'%1$d %2$s product in our %3$s range, cut and finished in our workshop and shipped worldwide from India.',
					'%1$d %2$s products in our %3$s range, cut and finished in our workshop and shipped worldwide from India.',
					$count,
					'bhc-commerce-core'
				),
				$count,
				$term->name,
				$parent->name
			);
		}

		if ( $count > 0 ) {
			return sprintf(
				/* translators: 1: number of products, 2: category name. */
				_n(
					'%1$d %2$s product, cut and finished in our workshop and shipped worldwide from India. Wholesale and sample quantities available.',
					'%1$d %2$s products, cut and finished in our workshop and shipped worldwide from India. Wholesale and sample quantities available.',
					$count,
					'bhc-commerce-core'
				),
				$count,
				$term->name
			);
		}

		return sprintf(
			/* translators: %s: category name. */
			__( '%s from Bone Horn Crafts — hand-finished bone, horn and wood craft materials, made to order and shipped worldwide from India.', 'bhc-commerce-core' ),
			$term->name
		);
	}

	/**
	 * Builds the meta keywords value.
	 *
	 * A note on what this is worth, because it is easy to over-sell: Google
	 * stopped using the keywords meta tag for ranking in 2009 and has said so
	 * publicly since. Bing treats it, at best, as a spam signal. Nothing here
	 * will move a Google ranking.
	 *
	 * It is emitted anyway for two defensible reasons. Several regional search
	 * engines and a lot of B2B product-directory crawlers still read it, and it
	 * is what most SEO audit tools tick off. What matters is that the value is
	 * derived from taxonomy terms the product genuinely carries — categories,
	 * tags, material and colour — rather than stuffed. A tag listing terms the
	 * page does not sell is the version that actually hurts.
	 *
	 * Return an empty string from `bhc_meta_keywords` to drop the tag.
	 */
	public function keywords(): string {
		$terms = [];

		if ( function_exists( 'is_product' ) && is_product() ) {
			$terms = $this->product_keywords( get_queried_object_id() );
		} elseif ( is_tax( 'product_cat' ) || is_tax( 'product_tag' ) ) {
			$term = get_queried_object();

			if ( $term instanceof WP_Term ) {
				$terms = $this->term_keywords( $term );
			}
		} elseif ( is_front_page() || ( function_exists( 'is_shop' ) && is_shop() ) ) {
			$terms = $this->catalogue_keywords();
		}

		$terms = $this->normalise_keywords( $terms );

		/**
		 * Filters the meta keywords list.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $terms Keyword terms, already de-duplicated.
		 */
		$terms = (array) apply_filters( 'bhc_meta_keywords', $terms );

		return implode( ', ', array_map( 'strval', $terms ) );
	}

	/**
	 * Keywords for a single product: its own taxonomy terms.
	 *
	 * @param int $product_id Product id.
	 *
	 * @return string[]
	 */
	private function product_keywords( int $product_id ): array {
		$terms = [];

		foreach ( [ 'product_cat', 'product_tag' ] as $taxonomy ) {
			$names = wp_get_post_terms( $product_id, $taxonomy, [ 'fields' => 'names' ] );

			if ( ! is_wp_error( $names ) ) {
				$terms = array_merge( $terms, array_map( 'strval', $names ) );
			}
		}

		$product = wc_get_product( $product_id );

		if ( $product instanceof WC_Product ) {
			array_unshift( $terms, $product->get_name() );

			foreach ( $product->get_attributes() as $attribute ) {
				if ( ! $attribute instanceof \WC_Product_Attribute || ! $attribute->is_taxonomy() ) {
					continue;
				}

				$names = wc_get_product_terms( $product_id, $attribute->get_name(), [ 'fields' => 'names' ] );

				if ( ! is_wp_error( $names ) ) {
					$terms = array_merge( $terms, array_map( 'strval', $names ) );
				}
			}
		}

		return $terms;
	}

	/**
	 * Keywords for a category or tag archive: the term, its ancestors and its
	 * immediate children.
	 *
	 * @param WP_Term $term Queried term.
	 *
	 * @return string[]
	 */
	private function term_keywords( WP_Term $term ): array {
		$terms = [ $term->name ];

		foreach ( (array) get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) as $ancestor_id ) {
			$ancestor = get_term( (int) $ancestor_id, $term->taxonomy );

			if ( $ancestor instanceof WP_Term ) {
				$terms[] = $ancestor->name;
			}
		}

		$children = get_terms(
			[
				'taxonomy'   => $term->taxonomy,
				'parent'     => $term->term_id,
				'hide_empty' => true,
				'number'     => 8,
				'fields'     => 'names',
			]
		);

		if ( ! is_wp_error( $children ) ) {
			$terms = array_merge( $terms, array_map( 'strval', $children ) );
		}

		return $terms;
	}

	/**
	 * Keywords for the front page and shop: the top-level categories that
	 * actually hold stock.
	 *
	 * @return string[]
	 */
	private function catalogue_keywords(): array {
		$names = get_terms(
			[
				'taxonomy'   => 'product_cat',
				'parent'     => 0,
				'hide_empty' => true,
				'number'     => 12,
				'orderby'    => 'count',
				'order'      => 'DESC',
				'fields'     => 'names',
			]
		);

		if ( is_wp_error( $names ) ) {
			return [];
		}

		return array_map( 'strval', $names );
	}

	/**
	 * Trims, de-duplicates case-insensitively and caps the keyword list.
	 *
	 * @param string[] $terms Raw terms.
	 *
	 * @return string[]
	 */
	private function normalise_keywords( array $terms ): array {
		$seen = [];

		foreach ( $terms as $term ) {
			$term = trim( wp_strip_all_tags( (string) $term ) );

			if ( '' === $term ) {
				continue;
			}

			// Case-insensitive so "Bone" and "bone" do not both survive, but
			// the first spelling encountered is the one kept.
			$key = strtolower( $term );

			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = $term;

			if ( count( $seen ) >= 15 ) {
				break;
			}
		}

		return array_values( $seen );
	}

	/**
	 * Resolves the social image for the current request.
	 */
	public function image_url(): string {
		if ( is_singular() ) {
			$thumbnail_id = (int) get_post_thumbnail_id( get_queried_object_id() );

			if ( $thumbnail_id > 0 ) {
				$src = wp_get_attachment_image_url( $thumbnail_id, 'large' );

				if ( is_string( $src ) ) {
					return $this->brand->canonicalise( $src );
				}
			}
		}

		return $this->brand->social_image();
	}

	/**
	 * Open Graph object type for the current request.
	 */
	private function og_type(): string {
		if ( function_exists( 'is_product' ) && is_product() ) {
			return 'product';
		}

		if ( is_singular( 'post' ) ) {
			return 'article';
		}

		return 'website';
	}
}
