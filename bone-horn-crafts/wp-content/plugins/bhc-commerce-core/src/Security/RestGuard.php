<?php
/**
 * REST permission callbacks.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Security;

defined( 'ABSPATH' ) || exit;

use WP_Error;
use WP_REST_Request;

/**
 * Reusable permission callbacks for the plugin's REST routes.
 *
 * Every route in `bhc/v1` points its `permission_callback` at one of these
 * methods. Centralising them means an audit is one file, and no route can ship
 * with `permission_callback => '__return_true'` by accident.
 *
 * CSRF: WordPress cookie authentication only applies when the `X-WP-Nonce`
 * header validates, so a state-changing route that also serves guests still
 * verifies the REST nonce explicitly.
 */
final class RestGuard {

	/**
	 * Constructor.
	 *
	 * @param RateLimiter $limiter Rate limiter.
	 */
	public function __construct( private RateLimiter $limiter ) {}

	/**
	 * Read-only public endpoints (recommendations, delivery estimates).
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return true|WP_Error
	 */
	public function public_read( WP_REST_Request $request ) {
		if ( ! $this->limiter->allow( 'rest_read', 120, MINUTE_IN_SECONDS ) ) {
			return $this->too_many_requests();
		}

		return true;
	}

	/**
	 * Public write endpoints used by the storefront (guest wishlist toggles).
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return true|WP_Error
	 */
	public function session_write( WP_REST_Request $request ) {
		if ( ! $this->verify_rest_nonce( $request ) ) {
			return new WP_Error(
				'bhc_invalid_nonce',
				__( 'Your session expired. Please refresh the page and try again.', 'bhc-commerce-core' ),
				[ 'status' => 403 ]
			);
		}

		if ( ! $this->limiter->allow( 'rest_write', 40, MINUTE_IN_SECONDS ) ) {
			return $this->too_many_requests();
		}

		return true;
	}

	/**
	 * Endpoints restricted to the authenticated customer.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return true|WP_Error
	 */
	public function customer( WP_REST_Request $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'bhc_not_authenticated',
				__( 'You must be signed in to do that.', 'bhc-commerce-core' ),
				[ 'status' => 401 ]
			);
		}

		return $this->session_write( $request );
	}

	/**
	 * Endpoints restricted to store managers.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return true|WP_Error
	 */
	public function manage( WP_REST_Request $request ) {
		if ( ! Capabilities::can_manage() ) {
			return new WP_Error(
				'bhc_forbidden',
				__( 'You do not have permission to access this resource.', 'bhc-commerce-core' ),
				[ 'status' => is_user_logged_in() ? 403 : 401 ]
			);
		}

		return $this->verify_rest_nonce( $request )
			? true
			: new WP_Error(
				'bhc_invalid_nonce',
				__( 'Invalid or expired security token.', 'bhc-commerce-core' ),
				[ 'status' => 403 ]
			);
	}

	/**
	 * Verifies the REST nonce when one is present or required.
	 *
	 * Application-password and OAuth authenticated requests carry no cookie and
	 * no nonce; those are already authenticated by WordPress, so the nonce is
	 * only enforced for cookie-based or anonymous sessions.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	private function verify_rest_nonce( WP_REST_Request $request ): bool {
		if ( is_user_logged_in() && ! $this->is_cookie_authenticated() ) {
			return true;
		}

		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( null === $nonce || '' === $nonce ) {
			$nonce = (string) $request->get_param( '_wpnonce' );
		}

		return '' !== (string) $nonce && (bool) wp_verify_nonce( sanitize_text_field( (string) $nonce ), 'wp_rest' );
	}

	/**
	 * Whether the current request authenticated through the auth cookie.
	 */
	private function is_cookie_authenticated(): bool {
		return isset( $_COOKIE[ LOGGED_IN_COOKIE ] ) || ( defined( 'AUTH_COOKIE' ) && isset( $_COOKIE[ AUTH_COOKIE ] ) );
	}

	/**
	 * Builds the 429 response.
	 */
	private function too_many_requests(): WP_Error {
		return new WP_Error(
			'bhc_rate_limited',
			__( 'Too many requests. Please slow down and try again shortly.', 'bhc-commerce-core' ),
			[ 'status' => 429 ]
		);
	}
}
