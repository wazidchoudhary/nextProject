<?php
/**
 * Cache facade with group versioning.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Cache;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Contracts\CacheInterface;

/**
 * The single entry point every module uses to cache data.
 *
 * Key layout: `bhc:{schema}:{group}:v{group-version}:{key}`
 *
 * * `schema` is the plugin version, so a deploy never serves stale structures
 *   written by an older release.
 * * `group-version` is an integer stored in the cache itself. Invalidating a
 *   group is a single increment — no key enumeration, which matters because
 *   Redis `KEYS`/`SCAN` sweeps are exactly what you do not want on a busy
 *   store.
 * * A request-level memo array in front of the store keeps repeated reads of
 *   the same key (badges rendered in a loop, for example) free.
 */
final class CacheManager implements CacheInterface {

	/**
	 * Sentinel used to distinguish "not cached" from a cached null/false.
	 */
	private const MISS = "\0bhc-miss\0";

	/**
	 * Request-level memo.
	 *
	 * @var array<string, mixed>
	 */
	private array $memo = [];

	/**
	 * Memoised group versions.
	 *
	 * @var array<string, int>
	 */
	private array $versions = [];

	/**
	 * Constructor.
	 *
	 * @param StoreInterface $store       Backing store.
	 * @param string         $schema      Schema token (plugin version).
	 * @param string         $group       Default logical group.
	 * @param int            $default_ttl Default TTL in seconds.
	 */
	public function __construct(
		private StoreInterface $store,
		private string $schema = '1',
		private string $group = 'general',
		private int $default_ttl = HOUR_IN_SECONDS
	) {}

	/**
	 * Returns a manager scoped to another logical group.
	 *
	 * @param string $group Group name.
	 */
	public function for_group( string $group ): self {
		$clone        = clone $this;
		$clone->group = sanitize_key( $group );
		$clone->memo  = [];

		return $clone;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $key     Cache key.
	 * @param mixed  $default Value returned on a miss.
	 *
	 * @return mixed
	 */
	public function get( string $key, mixed $default = null ): mixed {
		$full = $this->build_key( $key );

		if ( array_key_exists( $full, $this->memo ) ) {
			return $this->memo[ $full ];
		}

		$value = $this->store->read( $full, self::MISS );

		if ( self::MISS === $value ) {
			return $default;
		}

		if ( is_array( $value ) && isset( $value['__bhc_false'] ) ) {
			$value = false;
		}

		$this->memo[ $full ] = $value;

		return $this->memo[ $full ];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $key   Cache key.
	 * @param mixed  $value Value to store.
	 * @param int    $ttl   TTL in seconds.
	 */
	public function set( string $key, mixed $value, int $ttl = 0 ): bool {
		$full = $this->build_key( $key );

		$this->memo[ $full ] = $value;

		return $this->store->write( $full, $value, $ttl > 0 ? $ttl : $this->default_ttl );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $key Cache key.
	 */
	public function delete( string $key ): bool {
		$full = $this->build_key( $key );

		unset( $this->memo[ $full ] );

		return $this->store->forget( $full );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string   $key      Cache key.
	 * @param callable $callback Producer invoked on a miss.
	 * @param int      $ttl      TTL in seconds.
	 *
	 * @return mixed
	 */
	public function remember( string $key, callable $callback, int $ttl = 0 ): mixed {
		$full = $this->build_key( $key );

		if ( array_key_exists( $full, $this->memo ) ) {
			return $this->memo[ $full ];
		}

		$value = $this->store->read( $full, self::MISS );

		if ( self::MISS !== $value ) {
			if ( is_array( $value ) && isset( $value['__bhc_false'] ) ) {
				$value = false;
			}

			$this->memo[ $full ] = $value;

			return $this->memo[ $full ];
		}

		$value = $callback();

		$this->memo[ $full ] = $value;

		$this->store->write( $full, $value, $ttl > 0 ? $ttl : $this->default_ttl );

		return $value;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $group Group name.
	 */
	public function flush_group( string $group ): void {
		$group   = sanitize_key( $group );
		$version = $this->group_version( $group ) + 1;

		$this->versions[ $group ] = $version;
		$this->memo               = [];

		$this->store->write( $this->version_key( $group ), $version, 0 );

		/**
		 * Fires after a cache group has been invalidated.
		 *
		 * @since 1.0.0
		 *
		 * @param string $group   Group name.
		 * @param int    $version New group version.
		 */
		do_action( 'bhc_cache_group_flushed', $group, $version );
	}

	/**
	 * Drops the request-level memo (used between CLI batches).
	 */
	public function reset_memo(): void {
		$this->memo     = [];
		$this->versions = [];
	}

	/**
	 * Name of the backing store, for the health screen.
	 */
	public function store_name(): string {
		return $this->store->name();
	}

	/**
	 * Whether the backing store persists between requests.
	 */
	public function is_persistent(): bool {
		return $this->store->is_persistent();
	}

	/**
	 * Builds the fully qualified key for the current group.
	 *
	 * @param string $key Caller supplied key.
	 */
	public function build_key( string $key ): string {
		$key = sanitize_key( str_replace( [ ' ', ':' ], '_', $key ) );

		return sprintf( 'bhc:%s:%s:v%d:%s', $this->schema, $this->group, $this->group_version( $this->group ), $key );
	}

	/**
	 * Reads (and memoises) the current version of a group.
	 *
	 * @param string $group Group name.
	 */
	private function group_version( string $group ): int {
		if ( isset( $this->versions[ $group ] ) ) {
			return $this->versions[ $group ];
		}

		$stored = $this->store->read( $this->version_key( $group ), self::MISS );

		if ( self::MISS === $stored ) {
			$stored = 1;

			$this->store->write( $this->version_key( $group ), $stored, 0 );
		}

		$this->versions[ $group ] = (int) $stored;

		return $this->versions[ $group ];
	}

	/**
	 * Key under which a group's version integer is stored.
	 *
	 * @param string $group Group name.
	 */
	private function version_key( string $group ): string {
		return sprintf( 'bhc:%s:groupver:%s', $this->schema, $group );
	}
}
