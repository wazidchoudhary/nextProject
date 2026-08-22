<?php
/**
 * Response hardening headers.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Security;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Contracts\HookableInterface;

/**
 * Adds conservative security headers to front-end responses.
 *
 * The set is deliberately limited to headers that cannot break a WooCommerce
 * checkout: no Content-Security-Policy is emitted by default because payment
 * gateways inject their own scripts and iframes, and a wrong CSP silently
 * breaks payments. A site that has audited its gateways can opt in through the
 * `bhc_security_headers` filter.
 */
final class Headers implements HookableInterface {

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_filter( 'wp_headers', [ $this, 'add_headers' ], 20, 1 );
		add_filter( 'rest_pre_serve_request', [ $this, 'add_rest_headers' ], 10, 1 );
	}

	/**
	 * Adds headers to standard page responses.
	 *
	 * @param array<string, string> $headers Existing headers.
	 *
	 * @return array<string, string>
	 */
	public function add_headers( array $headers ): array {
		$defaults = [
			'X-Content-Type-Options' => 'nosniff',
			'Referrer-Policy'        => 'strict-origin-when-cross-origin',
			'X-Frame-Options'        => 'SAMEORIGIN',
			'Permissions-Policy'     => 'geolocation=(), microphone=(), camera=(), interest-cohort=()',
		];

		/**
		 * Filters the security headers added to front-end responses.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, string> $defaults Header map.
		 */
		$defaults = (array) apply_filters( 'bhc_security_headers', $defaults );

		return array_merge( $headers, $defaults );
	}

	/**
	 * Ensures REST responses are never sniffed as HTML.
	 *
	 * @param bool $served Whether the request has already been served.
	 */
	public function add_rest_headers( bool $served ): bool {
		if ( ! headers_sent() ) {
			header( 'X-Content-Type-Options: nosniff' );
		}

		return $served;
	}
}
