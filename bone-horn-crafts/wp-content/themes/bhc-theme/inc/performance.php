<?php
/**
 * Front-end performance controls.
 *
 * Every rule here exists because it moves a Core Web Vital, and each one names
 * the metric it targets. Nothing is disabled "because it is bloat" without a
 * reason a reviewer can check.
 *
 * @package BHC_Theme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Removes core output the storefront never uses.
 *
 * Targets: fewer bytes and fewer requests in the head, which shortens the
 * critical path before LCP.
 */
function bhc_trim_head(): void {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

	remove_action( 'wp_head', 'wp_generator' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wp_shortlink_wp_head' );
	remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head', 10 );
}

add_action( 'init', 'bhc_trim_head' );

/**
 * Drops the block library stylesheets on classic storefront pages.
 *
 * Target: LCP. `wp-block-library` plus WooCommerce's block styles are ~90KB of
 * CSS that a shortcode-rendered storefront never applies. They are kept
 * wherever block markup can appear (the editor, block-based cart/checkout, and
 * any page whose content actually contains blocks).
 */
function bhc_dequeue_block_styles(): void {
	if ( is_admin() ) {
		return;
	}

	if ( bhc_page_uses_blocks() ) {
		return;
	}

	wp_dequeue_style( 'wp-block-library' );
	wp_dequeue_style( 'wp-block-library-theme' );
	wp_dequeue_style( 'wc-blocks-style' );
	wp_dequeue_style( 'global-styles' );
	wp_dequeue_style( 'classic-theme-styles' );
}

add_action( 'wp_enqueue_scripts', 'bhc_dequeue_block_styles', 100 );

/**
 * Whether the current request can render block markup.
 */
function bhc_page_uses_blocks(): bool {
	if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() ) ) {
		return true;
	}

	$post = get_post();

	if ( $post instanceof WP_Post && function_exists( 'has_blocks' ) && has_blocks( $post ) ) {
		return true;
	}

	/**
	 * Filters whether block styles are required for this request.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $uses_blocks Whether block styles must load.
	 */
	return (bool) apply_filters( 'bhc_page_uses_blocks', false );
}

/**
 * Removes WooCommerce's own stylesheets.
 *
 * Target: LCP and design integrity. `woocommerce-layout`,
 * `woocommerce-smallscreen` and `woocommerce-general` total roughly 60KB, apply
 * a float grid and a purple button palette, and two thirds of their rules
 * target markup this theme replaces. Everything WooCommerce still emits is
 * styled in `assets/scss/components/_woocommerce.scss`.
 */
add_filter( 'woocommerce_enqueue_styles', '__return_empty_array' );

/**
 * Limits WooCommerce cart fragments to pages that display cart state live.
 *
 * Target: INP. `wc-cart-fragments` fires an uncached admin-ajax request on every
 * page load and is the most common source of a slow first interaction on a
 * WooCommerce site. The header cart count is server rendered, so fragments are
 * only needed where AJAX add-to-cart actually runs.
 */
function bhc_limit_cart_fragments(): void {
	if ( is_admin() || ! function_exists( 'is_woocommerce' ) ) {
		return;
	}

	if ( is_woocommerce() || is_cart() || is_checkout() ) {
		return;
	}

	wp_dequeue_script( 'wc-cart-fragments' );
}

add_action( 'wp_enqueue_scripts', 'bhc_limit_cart_fragments', 100 );

/**
 * Drops WooCommerce's jQuery bundle from pages that do not use it.
 *
 * Target: LCP and INP. WooCommerce enqueues jQuery, jquery-migrate, blockUI,
 * js-cookie and its own frontend helpers on every page of the store. On this
 * storefront almost none of it runs: product cards link to the product page
 * rather than adding to the cart in place, so `wc-add-to-cart` — the loop AJAX
 * handler — has nothing to bind to, and blockUI exists to grey out the cart and
 * checkout forms while they update.
 *
 * Cart and checkout keep the lot. Everywhere else this removes roughly 120KB of
 * JavaScript, three render-blocking requests, and jQuery itself.
 *
 * `jquery-migrate` goes on the whole frontend regardless: it is a compatibility
 * shim for jQuery 1.x-era code, and nothing here is jQuery-era code.
 */
function bhc_trim_woocommerce_scripts(): void {
	if ( is_admin() || ! function_exists( 'is_cart' ) ) {
		return;
	}

	// jquery-migrate is never needed on the front of this site.
	$jquery = wp_scripts()->registered['jquery'] ?? null;

	if ( $jquery && is_array( $jquery->deps ) ) {
		$jquery->deps = array_values( array_diff( $jquery->deps, [ 'jquery-migrate' ] ) );
	}

	// Cart and checkout genuinely need the bundle: quantity updates, coupon
	// application, shipping recalculation and the blocking overlay all run
	// through it.
	if ( is_cart() || is_checkout() || is_account_page() ) {
		return;
	}

	foreach ( [ 'wc-add-to-cart', 'woocommerce', 'jquery-blockui', 'js-cookie' ] as $handle ) {
		wp_dequeue_script( $handle );
	}

	// Order attribution records how a shopper arrived, and is only read when an
	// order is created. Loading its two scripts on every page of the catalogue
	// buys nothing.
	foreach ( [ 'wc-order-attribution', 'sourcebuster-js' ] as $handle ) {
		wp_dequeue_script( $handle );
	}
}

/**
 * Why jQuery is deliberately NOT deferred.
 *
 * Product pages still load jQuery, because WooCommerce's variation form and its
 * review star-rating widget are built on it. Deferring it looks like an easy win
 * — it is the only render-blocking script left — and it is not: WooCommerce
 * prints inline `jQuery(...)` calls in the body, which execute before a deferred
 * script has loaded and throw "jQuery is not defined". Tried, measured, reverted.
 *
 * The real fix is to stop loading jQuery on product pages at all, which means
 * re-implementing the variation form. That is a larger change than it looks and
 * is not attempted here.
 */

add_action( 'wp_enqueue_scripts', 'bhc_trim_woocommerce_scripts', 100 );

/**
 * Preloads the LCP image and the main stylesheet.
 *
 * Target: LCP. Only two resources are ever preloaded — preloading more pushes
 * genuinely critical bytes further down the queue.
 */
function bhc_resource_hints(): void {
	printf(
		"<link rel=\"preload\" as=\"style\" href=\"%s\" />\n",
		esc_url( BHC_THEME_URI . '/assets/css/main.css?ver=' . bhc_asset_version( 'assets/css/main.css' ) )
	);

	// A banner bundled with the theme has no attachment id, so it is preloaded
	// by URL. Same element, same priority — it just cannot be resolved through
	// the attachment helpers.
	if ( is_front_page() && function_exists( 'bhc_hero_banner_id' ) && 0 === bhc_hero_banner_id() ) {
		$bundled = function_exists( 'bhc_hero_banner_fallback_url' ) ? bhc_hero_banner_fallback_url() : '';

		if ( '' !== $bundled ) {
			printf( "<link rel=\"preload\" as=\"image\" href=\"%s\" fetchpriority=\"high\" />\n", esc_url( $bundled ) );
		}
	}

	$lcp_image_id = bhc_lcp_image_id();

	if ( $lcp_image_id > 0 ) {
		// The front page renders the banner at full width, so the preload has
		// to request the same candidate the markup will: a 'bhc-hero' preload
		// beside a 'full' <img> downloads two different files.
		$size = is_front_page() ? 'full' : 'bhc-hero';

		$src    = wp_get_attachment_image_url( $lcp_image_id, $size );
		$srcset = wp_get_attachment_image_srcset( $lcp_image_id, $size );

		if ( is_string( $src ) ) {
			printf(
				"<link rel=\"preload\" as=\"image\" href=\"%s\"%s fetchpriority=\"high\" />\n",
				esc_url( $src ),
				is_string( $srcset ) && '' !== $srcset ? ' imagesrcset="' . esc_attr( $srcset ) . '"' : ''
			);
		}
	}
}

add_action( 'wp_head', 'bhc_resource_hints', 2 );

/**
 * Resolves the largest contentful image for the current request.
 *
 * Returning 0 means "no preload", which is the right answer on pages whose LCP
 * element is text.
 */
function bhc_lcp_image_id(): int {
	if ( function_exists( 'is_product' ) && is_product() ) {
		$product = wc_get_product( get_queried_object_id() );

		return $product ? (int) $product->get_image_id() : 0;
	}

	if ( is_singular() && has_post_thumbnail() ) {
		return (int) get_post_thumbnail_id();
	}

	// The home page hero is the busiest LCP element on the site, and it used to
	// be the one that never got preloaded: the hero template registered a
	// `bhc_lcp_image_id` filter, but templates run long after wp_head has
	// already emitted its hints. Resolving it here means the preload is in the
	// head where it is useful. The lookup is the same cached repository call
	// the hero itself makes, so it costs nothing extra.
	// The front page LCP element is the hero banner. It is a background image
	// inside the section, so the preload scanner finds it in the markup, but
	// the head hint still gets it started ahead of the stylesheet's own
	// dependencies.
	if ( is_front_page() && function_exists( 'bhc_hero_banner_id' ) ) {
		$banner = bhc_hero_banner_id();

		if ( $banner > 0 ) {
			return $banner;
		}
	}

	/**
	 * Filters the attachment id preloaded as the LCP image.
	 *
	 * @since 1.0.0
	 *
	 * @param int $attachment_id Attachment id, or 0 for no preload.
	 */
	return (int) apply_filters( 'bhc_lcp_image_id', 0 );
}

/**
 * Stops WordPress lazy-loading the first image on a page.
 *
 * Target: LCP. A lazily loaded LCP image is discovered late by the preload
 * scanner and typically costs several hundred milliseconds.
 *
 * @param string|bool $value   Current loading attribute value.
 * @param string      $image   Image markup.
 * @param string      $context Where the image is rendered.
 *
 * @return string|bool
 */
function bhc_skip_lazy_for_first_image( $value, $image = '', $context = '' ) {
	static $seen = 0;

	if ( is_admin() || 'the_content' !== $context ) {
		return $value;
	}

	++$seen;

	return 1 === $seen ? false : $value;
}

add_filter( 'wp_lazy_loading_enabled', 'bhc_skip_lazy_for_first_image', 10, 3 );


/**
 * Caps WordPress's srcset generation.
 *
 * Target: transfer size. Ten candidate sizes for a 600px card is wasted markup;
 * four covers every realistic viewport and DPR combination.
 *
 * @param array<int, array<string, mixed>> $sources Srcset sources.
 *
 * @return array<int, array<string, mixed>>
 */
function bhc_limit_srcset( array $sources ): array {
	if ( count( $sources ) <= 4 ) {
		return $sources;
	}

	$widths = array_keys( $sources );

	sort( $widths );

	$keep    = [ $widths[0], $widths[ (int) floor( count( $widths ) / 3 ) ], $widths[ (int) floor( 2 * count( $widths ) / 3 ) ], end( $widths ) ];
	$trimmed = [];

	foreach ( array_unique( $keep ) as $width ) {
		if ( isset( $sources[ $width ] ) ) {
			$trimmed[ $width ] = $sources[ $width ];
		}
	}

	return $trimmed;
}

add_filter( 'wp_calculate_image_srcset', 'bhc_limit_srcset' );

/**
 * Disables WordPress's scaled "big image" duplicate for uploads.
 *
 * Product photography is uploaded at the size it should be served at; the
 * -scaled duplicate only adds storage and confuses srcset generation.
 */
add_filter( 'big_image_size_threshold', '__return_false' );

/**
 * Writes WebP derivatives for uploaded JPEGs.
 *
 * Target: LCP and total page weight. WebP is 25–35% smaller than JPEG at
 * equivalent quality and is supported by every browser this store targets, so
 * the sub-sizes that actually get served — cards, gallery, hero — are better off
 * as WebP.
 *
 * Only the *derivatives* change format. WordPress keeps the original upload in
 * its own format, so nothing is lost and an editor downloading the full-size
 * file still gets the JPEG they put in. PNG is left alone: it is used for
 * artwork with flat colour and transparency, where WebP's gains are smaller and
 * its lossy default is a real risk.
 *
 * If the server's image editor cannot write WebP, WordPress ignores this and
 * carries on producing JPEGs — there is nothing to feature-detect.
 *
 * @param array<string, string> $formats Source mime => output mime.
 *
 * @return array<string, string>
 */
function bhc_webp_subsizes( array $formats ): array {
	$formats['image/jpeg'] = 'image/webp';

	return $formats;
}

add_filter( 'image_editor_output_format', 'bhc_webp_subsizes' );
