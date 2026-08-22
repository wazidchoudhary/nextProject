<?php
/**
 * WordPress object cache store.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Cache;

defined( 'ABSPATH' ) || exit;

/**
 * Backs the cache with `wp_cache_*`.
 *
 * With a persistent backend (Redis, Memcached) this is shared across requests
 * and web nodes. Without one it is still useful as a request-level cache, so
 * the manager only prefers it when `wp_using_ext_object_cache()` is true.
 */
final class ObjectCacheStore implements StoreInterface {

	/**
	 * Constructor.
	 *
	 * @param string $group Object cache group.
	 */
	public function __construct( private string $group = 'bhc_core' ) {}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $key  Fully qualified key.
	 * @param mixed  $miss Sentinel returned on a miss.
	 *
	 * @return mixed
	 */
	public function read( string $key, mixed $miss ): mixed {
		$found = false;
		$value = wp_cache_get( $key, $this->group, false, $found );

		return $found ? $value : $miss;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $key   Fully qualified key.
	 * @param mixed  $value Value.
	 * @param int    $ttl   TTL in seconds.
	 */
	public function write( string $key, mixed $value, int $ttl ): bool {
		return (bool) wp_cache_set( $key, $value, $this->group, max( 0, $ttl ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $key Fully qualified key.
	 */
	public function forget( string $key ): bool {
		return (bool) wp_cache_delete( $key, $this->group );
	}

	/**
	 * {@inheritDoc}
	 */
	public function name(): string {
		return 'object-cache';
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_persistent(): bool {
		return function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache();
	}
}
