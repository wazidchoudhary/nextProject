<?php
/**
 * Fixed-window rate limiter.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Security;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Contracts\CacheInterface;

/**
 * Throttles unauthenticated write endpoints (wishlist toggles, estimators).
 *
 * A fixed window counter is intentional: it is one cache read plus one write,
 * it needs no storage of its own, and the failure mode when the object cache is
 * cold is "allow", which is the right default for a storefront.
 */
final class RateLimiter {

	/**
	 * Constructor.
	 *
	 * @param CacheInterface $cache Cache implementation.
	 */
	public function __construct( private CacheInterface $cache ) {}

	/**
	 * Consumes one token from a bucket.
	 *
	 * @param string $bucket     Logical bucket, e.g. `wishlist_toggle`.
	 * @param int    $limit      Allowed hits per window.
	 * @param int    $window     Window length in seconds.
	 * @param string $identifier Caller identity; defaults to user id or hashed IP.
	 *
	 * @return bool True when the request is allowed.
	 */
	public function allow( string $bucket, int $limit = 60, int $window = MINUTE_IN_SECONDS, string $identifier = '' ): bool {
		$identifier = '' !== $identifier ? $identifier : $this->identity();
		$slot       = (int) floor( time() / max( 1, $window ) );
		$key        = sprintf( 'rl_%s_%s_%d', sanitize_key( $bucket ), $identifier, $slot );

		$hits = (int) $this->cache->get( $key, 0 );

		if ( $hits >= $limit ) {
			return false;
		}

		$this->cache->set( $key, $hits + 1, $window + 5 );

		return true;
	}

	/**
	 * Builds a stable, non-identifying caller key.
	 *
	 * The raw IP is never stored: only a salted hash, which keeps the limiter
	 * useful without turning the cache into a log of visitor addresses.
	 */
	private function identity(): string {
		$user_id = get_current_user_id();

		if ( $user_id > 0 ) {
			return 'u' . $user_id;
		}

		$ip = '';

		if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) );
		}

		return 'a' . substr( hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) ), 0, 16 );
	}
}
