<?php
/**
 * In-memory cache store.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Cache;

defined( 'ABSPATH' ) || exit;

/**
 * Non-persistent store used by unit tests and long running CLI commands.
 */
final class ArrayStore implements StoreInterface {

	/**
	 * Stored values.
	 *
	 * @var array<string, array{value:mixed,expires:int}>
	 */
	private array $items = [];

	/**
	 * {@inheritDoc}
	 *
	 * @param string $key  Fully qualified key.
	 * @param mixed  $miss Sentinel returned on a miss.
	 *
	 * @return mixed
	 */
	public function read( string $key, mixed $miss ): mixed {
		if ( ! isset( $this->items[ $key ] ) ) {
			return $miss;
		}

		$item = $this->items[ $key ];

		if ( 0 !== $item['expires'] && $item['expires'] < time() ) {
			unset( $this->items[ $key ] );

			return $miss;
		}

		return $item['value'];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $key   Fully qualified key.
	 * @param mixed  $value Value.
	 * @param int    $ttl   TTL in seconds.
	 */
	public function write( string $key, mixed $value, int $ttl ): bool {
		$this->items[ $key ] = [
			'value' => $value,
			// 0 means "no expiry" (the interface's "cache lifetime"); any other
			// value is an absolute expiry, so a negative TTL is already stale.
			'expires' => 0 === $ttl ? 0 : time() + $ttl,
		];

		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $key Fully qualified key.
	 */
	public function forget( string $key ): bool {
		unset( $this->items[ $key ] );

		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function name(): string {
		return 'array';
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_persistent(): bool {
		return false;
	}

	/**
	 * Empties the store. Test helper.
	 */
	public function reset(): void {
		$this->items = [];
	}
}
