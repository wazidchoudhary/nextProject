<?php
/**
 * Theme supports, menus and image sizes.
 *
 * @package BHC_Theme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Registers theme supports.
 *
 * Two omissions are deliberate:
 *
 * * `wc-product-gallery-slider` / `-zoom` / `-lightbox` are NOT declared. Those
 *   supports pull in flexslider, photoswipe and zoom — roughly 90KB of
 *   JavaScript for a gallery this theme renders in 40 lines of its own. Skipping
 *   them is the single largest JS saving on the product page.
 * * No `wp-block-styles`. The storefront is classic-template rendered, so the
 *   block library stylesheet would be dead weight on every page.
 */
function bhc_theme_setup(): void {
	load_theme_textdomain( 'bhc-theme', BHC_THEME_DIR . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'customize-selective-refresh-widgets' );

	add_theme_support(
		'html5',
		[
			'search-form',
			'comment-form',
			'comment-list',
			'gallery',
			'caption',
			'style',
			'script',
			'navigation-widgets',
		]
	);

	add_theme_support(
		'custom-logo',
		[
			'height'      => 64,
			'width'       => 240,
			'flex-height' => true,
			'flex-width'  => true,
		]
	);

	add_theme_support( 'woocommerce' );

	register_nav_menus(
		[
			'primary' => __( 'Primary navigation', 'bhc-theme' ),
			'footer'  => __( 'Footer navigation', 'bhc-theme' ),
			'utility' => __( 'Utility navigation', 'bhc-theme' ),
			'legal'   => __( 'Legal links', 'bhc-theme' ),
		]
	);

	// Product card imagery is square; hero and article imagery is landscape.
	add_image_size( 'bhc-card', 600, 600, true );
	add_image_size( 'bhc-card-2x', 1000, 1000, true );
	add_image_size( 'bhc-hero', 1200, 1320, true );
	add_image_size( 'bhc-wide', 1400, 900, true );

	// Content width used by embeds and the block editor.
	if ( ! isset( $GLOBALS['content_width'] ) ) {
		$GLOBALS['content_width'] = 1180;
	}
}

add_action( 'after_setup_theme', 'bhc_theme_setup' );

/**
 * Aligns WooCommerce's generated image sizes with the card grid.
 */
function bhc_theme_woocommerce_image_sizes(): void {
	update_option( 'woocommerce_thumbnail_cropping', '1:1' );
	update_option( 'woocommerce_thumbnail_image_width', 600 );
	update_option( 'woocommerce_single_image_width', 1000 );
}

add_action( 'after_switch_theme', 'bhc_theme_woocommerce_image_sizes' );

/**
 * Registers widget areas.
 */
function bhc_theme_widgets_init(): void {
	register_sidebar(
		[
			'name'          => __( 'Footer — workshop', 'bhc-theme' ),
			'id'            => 'footer-workshop',
			'description'   => __( 'Short workshop note shown in the first footer column.', 'bhc-theme' ),
			'before_widget' => '<div id="%1$s" class="footer-widget %2$s">',
			'after_widget'  => '</div>',
			'before_title'  => '<h2 class="footer-column__title">',
			'after_title'   => '</h2>',
		]
	);
}

add_action( 'widgets_init', 'bhc_theme_widgets_init' );

/**
 * Adds useful body classes for template-scoped styling.
 *
 * @param string[] $classes Existing classes.
 *
 * @return string[]
 */
function bhc_theme_body_classes( array $classes ): array {
	if ( ! is_active_sidebar( 'footer-workshop' ) ) {
		$classes[] = 'no-footer-widgets';
	}

	if ( function_exists( 'is_product' ) && is_product() ) {
		$classes[] = 'has-sticky-cart';
	}

	$template = get_post_meta( (int) get_queried_object_id(), '_bhc_page_template', true );

	if ( is_string( $template ) && '' !== $template ) {
		$classes[] = 'template-' . sanitize_html_class( $template );
	}

	return $classes;
}

add_filter( 'body_class', 'bhc_theme_body_classes' );
