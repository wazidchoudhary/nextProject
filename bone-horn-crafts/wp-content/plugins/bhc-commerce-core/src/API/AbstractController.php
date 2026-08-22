<?php
/**
 * REST controller base.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\API;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Contracts\HookableInterface;
use BoneHornCrafts\Core\Security\RestGuard;
use WP_REST_Response;

/**
 * Shared plumbing for the plugin's REST controllers.
 *
 * Every subclass registers inside the `bhc/v1` namespace and must supply a
 * permission callback from {@see RestGuard} — there is no route in this plugin
 * with `__return_true` as its permission callback.
 */
abstract class AbstractController implements HookableInterface {

	public const NAMESPACE = 'bhc/v1';

	/**
	 * Constructor.
	 *
	 * @param RestGuard $guard Permission callbacks.
	 */
	public function __construct( protected RestGuard $guard ) {}

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Registers this controller's routes.
	 */
	abstract public function register_routes(): void;

	/**
	 * Builds a success response with cache headers.
	 *
	 * @param array<string, mixed> $data       Response payload.
	 * @param int                  $status     HTTP status.
	 * @param int                  $cache_secs Browser/CDN cache lifetime.
	 */
	protected function respond( array $data, int $status = 200, int $cache_secs = 0 ): WP_REST_Response {
		$response = new WP_REST_Response( $data, $status );

		if ( $cache_secs > 0 ) {
			$response->header( 'Cache-Control', sprintf( 'public, max-age=%d, s-maxage=%d', $cache_secs, $cache_secs ) );
		} else {
			$response->header( 'Cache-Control', 'no-store, private' );
		}

		return $response;
	}

	/**
	 * Standard product id argument definition.
	 *
	 * @return array<string, mixed>
	 */
	protected function product_id_arg(): array {
		return [
			'required'          => true,
			'type'              => 'integer',
			'minimum'           => 1,
			'sanitize_callback' => 'absint',
			'validate_callback' => static fn ( $value ): bool => absint( $value ) > 0,
		];
	}
}
