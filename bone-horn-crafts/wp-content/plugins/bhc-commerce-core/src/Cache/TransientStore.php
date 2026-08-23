<?php
/**
 * Transient backed cache store.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Cache;

defined( 'ABSPATH' ) || exit;

/**
 * Fallback store used when no persistent object cache is configured.
 *
 * Transients land in `wp_options`, so TTLs are kept short and values small.
 * Keys are hashed to stay inside the 172 character `option_name` limit.
 */
final class TransientStore implements StoreInterface {

	private const MAX_KEY_LENGTH = 150;

	/**
	 * Constructor.
	 *
	 * The store holds no default TTL of its own. CacheManager already applies
	 * one before it reaches here, and a second substitution at this layer is how
	 * the "0 means no expiry" contract got broken.
	 */
	public function __construct() {}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $key  Fully qualified key.
	 * @param mixed  $miss Sentinel returned on a miss.
	 *
	 * @return mixed
	 */
	public function read( string $key, mixed $miss ): mixed {
		$value = get_transient( $this->normalise( $key ) );

		return false === $value ? $miss : $value;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $key   Fully qualified key.
	 * @param mixed  $value Value.
	 * @param int    $ttl   TTL in seconds.
	 */
	public function write( string $key, mixed $value, int $ttl ): bool {
		if ( false === $value ) {
			// `get_transient()` cannot distinguish a stored false from a miss.
			$value = [ '__bhc_false' => true ];
		}

		$name = $this->normalise( $key );

		// A zero TTL means "no expiry", matching set_transient()'s own contract
		// and the other two stores. Substituting a default here — as this used
		// to — quietly broke cache invalidation on the fallback path: the group
		// version is written with a TTL of 0 precisely so that it outlives
		// everything it invalidates, and giving it an hour meant a flush undid
		// itself an hour later and every orphaned entry came back.
		if ( $ttl <= 0 ) {
			// set_transient() with an expiration of 0 updates the value but
			// leaves any existing timeout row untouched, so a key that once had
			// a TTL would keep expiring. Deleting first is what actually makes
			// "no expiry" true.
			delete_transient( $name );

			return set_transient( $name, $value, 0 );
		}

		return set_transient( $name, $value, $ttl );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $key Fully qualified key.
	 */
	public function forget( string $key ): bool {
		return delete_transient( $this->normalise( $key ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function name(): string {
		return 'transient';
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_persistent(): bool {
		return true;
	}

	/**
	 * Keeps transient names inside the option_name column limit.
	 *
	 * @param string $key Raw key.
	 */
	private function normalise( string $key ): string {
		return strlen( $key ) > self::MAX_KEY_LENGTH ? 'bhc_' . md5( $key ) : $key;
	}
}
