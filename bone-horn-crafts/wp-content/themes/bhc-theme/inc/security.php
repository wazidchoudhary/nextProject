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

/**
 * Replaces Gravatar with a locally rendered initial.
 *
 * Every review on a product page would otherwise fire a request to
 * secure.gravatar.com, which sends the visitor's IP and a hash of the
 * commenter's e-mail address to a third party on page load. It is also a
 * render-blocking round trip per review for an image that, for anyone without a
 * Gravatar account, is a generic silhouette.
 *
 * The replacement is an inline SVG data URI: no request, no third party, no
 * layout shift, and a monogram that at least distinguishes one reviewer from
 * the next.
 *
 * @param string|null          $avatar    Pre-rendered avatar markup, if any.
 * @param mixed                $id_or_email User id, e-mail, comment or user object.
 * @param array<string, mixed> $args      Avatar arguments.
 *
 * @return string Avatar markup.
 */
function bhc_local_avatar( ?string $avatar, mixed $id_or_email, array $args ): string {
	$size = isset( $args['size'] ) ? max( 16, (int) $args['size'] ) : 60;
	$name = '';

	if ( $id_or_email instanceof WP_Comment ) {
		$name = (string) $id_or_email->comment_author;
	} elseif ( $id_or_email instanceof WP_User ) {
		$name = (string) $id_or_email->display_name;
	} elseif ( is_numeric( $id_or_email ) ) {
		$user = get_userdata( (int) $id_or_email );
		$name = $user instanceof WP_User ? (string) $user->display_name : '';
	} elseif ( is_string( $id_or_email ) ) {
		$name = $id_or_email;
	}

	$initial = strtoupper( mb_substr( trim( wp_strip_all_tags( $name ) ), 0, 1 ) );

	if ( '' === $initial || ! preg_match( '/\p{L}/u', $initial ) ) {
		$initial = '·';
	}

	// A stable hue per name, so the same reviewer keeps the same colour.
	$hue = crc32( $name ) % 360;

	$svg = sprintf(
		'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" role="img" aria-hidden="true">'
			. '<rect width="64" height="64" rx="32" fill="hsl(%1$d 24%% 88%%)"/>'
			. '<text x="32" y="42" font-family="Georgia, serif" font-size="28" fill="hsl(%1$d 30%% 32%%)" text-anchor="middle">%2$s</text>'
			. '</svg>',
		(int) $hue,
		esc_html( $initial )
	);

	return sprintf(
		'<img alt="" src="data:image/svg+xml;base64,%s" class="avatar avatar-%d photo bhc-avatar" width="%d" height="%d" decoding="async" />',
		base64_encode( $svg ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Data URI encoding for an inline SVG, not obfuscation.
		(int) $size,
		(int) $size,
		(int) $size
	);
}

add_filter( 'pre_get_avatar', 'bhc_local_avatar', 10, 3 );
