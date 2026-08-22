<?php
/**
 * Cache contract.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Storage-agnostic cache contract.
 *
 * Implementations must never throw: a cache failure degrades to a cache miss.
 */
interface CacheInterface {

	/**
	 * Reads a value.
	 *
	 * @param string $key     Cache key (unprefixed).
	 * @param mixed  $default Value returned on a miss.
	 *
	 * @return mixed
	 */
	public function get( string $key, mixed $default = null ): mixed;

	/**
	 * Writes a value.
	 *
	 * @param string $key   Cache key (unprefixed).
	 * @param mixed  $value Value to store. Must be serialisable.
	 * @param int    $ttl   Time to live in seconds. 0 means "cache lifetime".
	 */
	public function set( string $key, mixed $value, int $ttl = 0 ): bool;

	/**
	 * Deletes a single key.
	 *
	 * @param string $key Cache key (unprefixed).
	 */
	public function delete( string $key ): bool;

	/**
	 * Reads a value, computing and storing it on a miss.
	 *
	 * @param string   $key      Cache key (unprefixed).
	 * @param callable $callback Producer invoked on a miss.
	 * @param int      $ttl      Time to live in seconds.
	 *
	 * @return mixed
	 */
	public function remember( string $key, callable $callback, int $ttl = 0 ): mixed;

	/**
	 * Invalidates every key in a logical group by bumping its version salt.
	 *
	 * @param string $group Group name, e.g. `products`.
	 */
	public function flush_group( string $group ): void;
}
