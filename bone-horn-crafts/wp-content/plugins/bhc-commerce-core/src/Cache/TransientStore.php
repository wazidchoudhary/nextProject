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
	 * @param int $default_ttl TTL applied when a caller passes 0.
	 */
	public function __construct( private int $default_ttl = HOUR_IN_SECONDS ) {}

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

		return set_transient( $this->normalise( $key ), $value, $ttl > 0 ? $ttl : $this->default_ttl );
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
