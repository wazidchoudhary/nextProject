<?php
/**
 * Theme-level hardening.
 *
 * Application security lives in the plugin (capabilities, nonces, prepared SQL,
 * REST guards). This file covers the presentation surface only: what the theme
 * exposes in markup and which core endpoints the storefront does not need.
 *
 * @package BHC_Theme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Disables XML-RPC.
 *
 * The storefront has no XML-RPC clients, and the endpoint is a standing
 * brute-force and pingback-amplification target.
 */
add_filter( 'xmlrpc_enabled', '__return_false' );

/**
 * Removes the X-Pingback header that advertises the endpoint.
 *
 * @param array<string, string> $headers Response headers.
 *
 * @return array<string, string>
 */
function bhc_remove_pingback_header( array $headers ): array {
	unset( $headers['X-Pingback'] );

	return $headers;
}

add_filter( 'wp_headers', 'bhc_remove_pingback_header', 30 );

/**
 * Returns a generic login error.
 *
 * The default message confirms whether a username exists, which is free
 * reconnaissance for a credential-stuffing run.
 */
function bhc_generic_login_error(): string {
	return __( 'Those details did not match an account.', 'bhc-theme' );
}

add_filter( 'login_errors', 'bhc_generic_login_error' );

/**
 * Blocks author enumeration via `?author=N`.
 *
 * The store publishes under the brand name, so author archives serve no
 * purpose and only leak usernames.
 */
function bhc_block_author_enumeration(): void {
	if ( is_admin() || ! is_author() ) {
		return;
	}

	wp_safe_redirect( home_url( '/' ), 301 );

	exit;
}

add_action( 'template_redirect', 'bhc_block_author_enumeration' );

/**
 * Removes user endpoints from the REST API for anonymous callers.
 *
 * @param array<string, mixed> $endpoints REST endpoints.
 *
 * @return array<string, mixed>
 */
function bhc_restrict_rest_users( array $endpoints ): array {
	if ( is_user_logged_in() ) {
		return $endpoints;
	}

	unset( $endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );

	return $endpoints;
}

add_filter( 'rest_endpoints', 'bhc_restrict_rest_users' );

/**
 * Escapes and shortens a comment author's URL field.
 *
 * @param string $url Comment author URL.
 */
function bhc_sanitize_comment_author_url( string $url ): string {
	return esc_url_raw( substr( $url, 0, 200 ) );
}

add_filter( 'pre_comment_author_url', 'bhc_sanitize_comment_author_url' );

/**
 * Adds rel="noopener" to user-supplied links in content.
 *
 * @param string $content Post content.
 */
function bhc_harden_content_links( string $content ): string {
	if ( ! str_contains( $content, 'target="_blank"' ) ) {
		return $content;
	}

	return str_replace( 'target="_blank"', 'target="_blank" rel="noopener noreferrer"', $content );
}

add_filter( 'the_content', 'bhc_harden_content_links', 30 );
