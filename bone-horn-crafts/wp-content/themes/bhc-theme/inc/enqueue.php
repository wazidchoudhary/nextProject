<?php
/**
 * Asset loading.
 *
 * The loading strategy, in one place:
 *
 * 1. One stylesheet, loaded normally. At 7.8KB over the wire it is cheaper to
 *    block on than to work around: see bhc_async_main_stylesheet() for the
 *    measurement that settled it.
 * 2. The critical-CSS-inline plus async-swap pattern is built and ready behind
 *    the `bhc_async_main_stylesheet` filter, for a site whose stylesheet grows
 *    large enough to need it. Both halves switch together — inlining critical
 *    CSS while the full sheet still blocks only duplicates bytes.
 * 3. One deferred ES module carries every interaction. No jQuery is enqueued by
 *    the theme; WooCommerce still loads its own where it needs it.
 *
 * @package BHC_Theme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Returns a cache-busting version string for an asset.
 *
 * Uses the file modification time in development so a rebuild is visible
 * immediately, and the theme version in production so the URL is stable across
 * web nodes (a per-node mtime would fragment CDN caches).
 *
 * @param string $relative_path Path relative to the theme root.
 */
function bhc_asset_version( string $relative_path ): string {
	if ( 'production' !== wp_get_environment_type() ) {
		$path = BHC_THEME_DIR . '/' . ltrim( $relative_path, '/' );

		if ( is_readable( $path ) ) {
			return (string) filemtime( $path );
		}
	}

	return BHC_THEME_VERSION;
}

/**
 * Reads the critical stylesheet from disk.
 */
function bhc_critical_css(): string {
	static $css = null;

	if ( null !== $css ) {
		return $css;
	}

	$path = BHC_THEME_DIR . '/assets/css/critical.css';

	$css = is_readable( $path ) ? (string) file_get_contents( $path ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local theme asset.

	return $css;
}

/**
 * Prints the inline critical CSS as early as possible in the head.
 */
function bhc_print_critical_css(): void {
	// Only useful alongside the async swap. While the main stylesheet blocks
	// rendering, the page cannot paint before it arrives, so inlining a copy of
	// the above-the-fold rules adds ~12KB to every HTML response and buys
	// nothing. The two halves are deliberately governed by the same filter.
	if ( ! apply_filters( 'bhc_async_main_stylesheet', false ) ) {
		return;
	}

	$css = bhc_critical_css();

	if ( '' === $css ) {
		return;
	}

	printf( "<style id=\"bhc-critical-css\">%s</style>\n", $css ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static theme CSS.
}

add_action( 'wp_head', 'bhc_print_critical_css', 1 );

/**
 * Enqueues the front-end assets.
 */
function bhc_enqueue_assets(): void {
	wp_enqueue_style(
		'bhc-main',
		BHC_THEME_URI . '/assets/css/main.css',
		[],
		bhc_asset_version( 'assets/css/main.css' )
	);

	wp_enqueue_script(
		'bhc-theme',
		BHC_THEME_URI . '/assets/js/theme.js',
		[],
		bhc_asset_version( 'assets/js/theme.js' ),
		[
			'strategy'  => 'defer',
			'in_footer' => true,
		]
	);

	// No wp_localize_script for the theme bundle. It used to publish an
	// `ajaxUrl` and three UI strings that nothing ever read: the header renders
	// its own labels server-side, so they are translated and present without
	// JavaScript, and the storefront talks to the REST API rather than
	// admin-ajax. Shipping the object anyway is an inline <script> on every
	// page for nobody's benefit.

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}

add_action( 'wp_enqueue_scripts', 'bhc_enqueue_assets', 20 );

/**
 * Converts the main stylesheet link into a non-blocking preload.
 *
 * @param string $html   Link tag markup.
 * @param string $handle Stylesheet handle.
 *
 * @return string
 */
function bhc_async_main_stylesheet( string $html, string $handle ): string {
	if ( 'bhc-main' !== $handle || is_admin() ) {
		return $html;
	}

	/**
	 * Filters whether the main stylesheet is loaded asynchronously.
	 *
	 * Off by default, and that is a measured decision rather than an oversight.
	 * The critical-CSS-plus-async-swap pattern earns its keep when the main
	 * stylesheet is large enough that blocking on it delays the first paint.
	 * This one is 7.8KB over the wire. Swapping it in after paint bought
	 * nothing measurable on LCP and cost real CLS: whether the swap landed
	 * before or after the first paint was a race, so the same page measured
	 * 0.0000 on one run and 0.22 on the next. A single small render-blocking
	 * stylesheet is the better trade here.
	 *
	 * A site that grows a much larger stylesheet can turn this back on.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $async Whether to defer the main stylesheet.
	 */
	if ( ! apply_filters( 'bhc_async_main_stylesheet', false ) ) {
		return $html;
	}

	$async = str_replace(
		"rel='stylesheet'",
		"rel='preload' as='style' onload=\"this.onload=null;this.rel='stylesheet'\"",
		$html
	);

	// `str_replace` above targets WordPress's single-quoted output; bail out
	// untouched if a filter changed the markup shape rather than emitting a
	// broken tag.
	if ( $async === $html ) {
		return $html;
	}

	return $async . sprintf( '<noscript>%s</noscript>' . "\n", $html );
}

add_filter( 'style_loader_tag', 'bhc_async_main_stylesheet', 10, 2 );

/**
 * Adds an ES module type to the theme script.
 *
 * @param string $tag    Script tag.
 * @param string $handle Script handle.
 *
 * @return string
 */
function bhc_module_script_tag( string $tag, string $handle ): string {
	if ( 'bhc-theme' !== $handle ) {
		return $tag;
	}

	return str_replace( '<script ', '<script type="module" ', $tag );
}

add_filter( 'script_loader_tag', 'bhc_module_script_tag', 10, 2 );

/**
 * Enqueues the editor stylesheet so admin previews match the storefront.
 */
function bhc_editor_styles(): void {
	add_editor_style( 'assets/css/main.css' );
}

add_action( 'admin_init', 'bhc_editor_styles' );
