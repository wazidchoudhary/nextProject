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
			printf( "<link rel=\"prev\" href=\"%s\" />\n", esc_url( (string) get_pagenum_link( $paged - 1 ) ) );
		}

		if ( $paged < $pages ) {
			printf( "<link rel=\"next\" href=\"%s\" />\n", esc_url( (string) get_pagenum_link( $paged + 1 ) ) );
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
			}
		} elseif ( function_exists( 'is_shop' ) && is_shop() ) {
			$description = __( 'Browse knife handle scales, guitar parts, pen blanks, drinking horns and finishing stock in camel bone, cattle bone, water buffalo horn, rams horn and stabilized wood.', 'bhc-commerce-core' );
		}

		$description = wp_strip_all_tags( strip_shortcodes( (string) $description ) );

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
