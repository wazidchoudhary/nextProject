<?php
/**
 * Theme-level SEO support.
 *
 * The commerce plugin owns titles, descriptions, canonicals, Open Graph and the
 * JSON-LD graph, because those describe the *content* and must survive a theme
 * change. What belongs here is document-level presentation: the viewport, the
 * theme colour, the favicon fallback and the visible breadcrumb, which is a
 * template concern.
 *
 * @package BHC_Theme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Prints document-level head tags.
 */
function bhc_head_meta(): void {
	echo '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />' . "\n";
	echo '<meta name="theme-color" content="#f7f2e8" media="(prefers-color-scheme: light)" />' . "\n";
	echo '<meta name="theme-color" content="#2b2724" media="(prefers-color-scheme: dark)" />' . "\n";
	echo '<meta name="format-detection" content="telephone=no" />' . "\n";
}

add_action( 'wp_head', 'bhc_head_meta', 0 );

/**
 * Falls back to a plain title when the commerce plugin is not active.
 *
 * @param array<string, string> $parts Title parts.
 *
 * @return array<string, string>
 */
function bhc_document_title_fallback( array $parts ): array {
	if ( class_exists( \BoneHornCrafts\Core\SEO\MetaTagService::class ) ) {
		return $parts;
	}

	unset( $parts['tagline'] );

	return $parts;
}

add_filter( 'document_title_parts', 'bhc_document_title_fallback', 5 );

/**
 * Renders the shared breadcrumb trail.
 *
 * Delegates to the plugin so the visible breadcrumb and the `BreadcrumbList`
 * structured data can never disagree; falls back to nothing when the plugin is
 * inactive rather than shipping a second, divergent implementation.
 */
function bhc_breadcrumbs(): void {
	if ( ! class_exists( \BoneHornCrafts\Core\Plugin::class ) || is_front_page() ) {
		return;
	}

	$service = \BoneHornCrafts\Core\Plugin::resolve( \BoneHornCrafts\Core\SEO\BreadcrumbService::class );

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by the service.
	echo $service->render();
}
