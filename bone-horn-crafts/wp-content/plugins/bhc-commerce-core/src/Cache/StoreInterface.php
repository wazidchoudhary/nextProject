<?php
/**
 * Cache store contract.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Cache;

defined( 'ABSPATH' ) || exit;

/**
 * Low level key/value storage used by the cache manager.
 */
interface StoreInterface {

	/**
	 * Reads a raw value. Returns the sentinel `$miss` when absent.
	 *
	 * @param string $key  Fully qualified key.
	 * @param mixed  $miss Sentinel returned on a miss.
	 *
	 * @return mixed
	 */
	public function read( string $key, mixed $miss ): mixed;

	/**
	 * Writes a raw value.
	 *
	 * @param string $key   Fully qualified key.
	 * @param mixed  $value Value.
	 * @param int    $ttl   TTL in seconds (0 = store default).
	 */
	public function write( string $key, mixed $value, int $ttl ): bool;

	/**
	 * Deletes a key.
	 *
	 * @param string $key Fully qualified key.
	 */
	public function forget( string $key ): bool;

	/**
	 * Human readable name shown on the health screen.
	 */
	public function name(): string;

	/**
	 * Whether the store survives between requests.
	 */
	public function is_persistent(): bool;
}
