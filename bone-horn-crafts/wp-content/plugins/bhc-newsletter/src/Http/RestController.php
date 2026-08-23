<?php
/**
 * Subscription REST endpoint.
 *
 * @package BoneHornCrafts\Newsletter
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Newsletter\Http;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Newsletter\Service\SubscriptionService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * `bhc-newsletter/v1/subscribe`.
 *
 * Public by necessity — a signup form nobody can reach is not a signup form —
 * so the protection is a nonce plus a rate limit rather than a capability.
 */
final class RestController {

	/**
	 * REST namespace.
	 */
	private const NAMESPACE = 'bhc-newsletter/v1';

	/**
	 * Constructor.
	 *
	 * @param SubscriptionService $subscriptions Subscription lifecycle.
	 */
	public function __construct( private SubscriptionService $subscriptions ) {}

	/**
	 * Registers hooks.
	 */
	public function register_hooks(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Registers the route.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/subscribe',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'subscribe' ],
				'permission_callback' => [ $this, 'may_subscribe' ],
				'args'                => [
					'email'  => [
						'type'     => 'string',
						'required' => true,
					],
					'source' => [
						'type'    => 'string',
						'default' => 'footer',
					],
				],
			]
		);
	}

	/**
	 * Gate for the subscribe route.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return true|WP_Error
	 */
	public function may_subscribe( WP_REST_Request $request ) {
		if ( ! wp_verify_nonce( (string) $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ) {
			return new WP_Error(
				'bhc_newsletter_bad_nonce',
				__( 'Your session expired. Please refresh the page and try again.', 'bhc-newsletter' ),
				[ 'status' => 403 ]
			);
		}

		// Five attempts per address-holder per minute. Enough for a typo and a
		// correction, not enough to walk a list of addresses through the form
		// to find out which are already subscribed.
		if ( ! $this->within_rate_limit() ) {
			return new WP_Error(
				'bhc_newsletter_rate_limited',
				__( 'Too many attempts. Please wait a moment and try again.', 'bhc-newsletter' ),
				[ 'status' => 429 ]
			);
		}

		return true;
	}

	/**
	 * Handles a subscribe request.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function subscribe( WP_REST_Request $request ) {
		$result = $this->subscriptions->subscribe(
			(string) $request->get_param( 'email' ),
			sanitize_key( (string) $request->get_param( 'source' ) )
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response(
			[
				'success' => true,
				'message' => __( 'Almost there — open the confirmation email we just sent.', 'bhc-newsletter' ),
			],
			200
		);
	}

	/**
	 * Fixed-window rate limit keyed on the client.
	 *
	 * Uses the core plugin's limiter when it is installed, so the two share one
	 * policy and one cache backend, and falls back to a transient otherwise —
	 * this plugin is useful without the other one and should not hard-depend on
	 * it for something this small.
	 */
	private function within_rate_limit(): bool {
		if ( class_exists( \BoneHornCrafts\Core\Plugin::class ) ) {
			try {
				$limiter = \BoneHornCrafts\Core\Plugin::resolve( \BoneHornCrafts\Core\Security\RateLimiter::class );

				if ( $limiter instanceof \BoneHornCrafts\Core\Security\RateLimiter ) {
					return $limiter->allow( 'newsletter_subscribe', 5, MINUTE_IN_SECONDS );
				}
			} catch ( \Throwable $exception ) {
				unset( $exception ); // Fall through to the transient limiter below.
			}
		}

		$ip  = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) )
			: 'unknown';
		$key = 'bhc_nl_rl_' . md5( $ip );

		$hits = (int) get_transient( $key );

		if ( $hits >= 5 ) {
			return false;
		}

		set_transient( $key, $hits + 1, MINUTE_IN_SECONDS );

		return true;
	}
}
