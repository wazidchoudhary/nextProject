<?php
/**
 * Cache behaviour tests.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Tests\Unit;

use BoneHornCrafts\Core\Cache\ArrayStore;
use BoneHornCrafts\Core\Cache\CacheManager;
use PHPUnit\Framework\TestCase;

/**
 * @covers \BoneHornCrafts\Core\Cache\CacheManager
 * @covers \BoneHornCrafts\Core\Cache\ArrayStore
 */
final class CacheManagerTest extends TestCase {

	private ArrayStore $store;
	private CacheManager $cache;

	protected function setUp(): void {
		$this->store = new ArrayStore();
		$this->cache = new CacheManager( $this->store, 'test', 'products', 60 );
	}

	public function test_remember_computes_once_and_reuses_the_value(): void {
		$calls = 0;

		$producer = function () use ( &$calls ): string {
			++$calls;

			return 'value';
		};

		$this->assertSame( 'value', $this->cache->remember( 'key', $producer ) );
		$this->assertSame( 'value', $this->cache->remember( 'key', $producer ) );
		$this->assertSame( 1, $calls, 'The producer must run once per cached key.' );
	}

	public function test_a_cached_false_is_not_treated_as_a_miss(): void {
		$calls = 0;

		$producer = function () use ( &$calls ): bool {
			++$calls;

			return false;
		};

		$this->assertFalse( $this->cache->remember( 'flag', $producer ) );
		$this->assertFalse( $this->cache->remember( 'flag', $producer ) );
		$this->assertSame( 1, $calls, 'false is a legitimate cached value, not an empty result.' );
	}

	public function test_flush_group_invalidates_every_key_in_that_group(): void {
		$this->cache->set( 'one', 'a' );
		$this->cache->set( 'two', 'b' );

		$this->cache->flush_group( 'products' );

		$this->assertNull( $this->cache->get( 'one' ) );
		$this->assertNull( $this->cache->get( 'two' ) );
	}

	public function test_flushing_one_group_leaves_others_intact(): void {
		$stats = $this->cache->for_group( 'stats' );

		$this->cache->set( 'product-key', 'product-value' );
		$stats->set( 'stats-key', 'stats-value' );

		$this->cache->flush_group( 'products' );

		$this->assertNull( $this->cache->get( 'product-key' ) );
		$this->assertSame( 'stats-value', $stats->get( 'stats-key' ) );
	}

	public function test_keys_are_namespaced_by_schema_and_group(): void {
		$key = $this->cache->build_key( 'bestsellers_8' );

		$this->assertStringStartsWith( 'bhc:test:products:v', $key );
		$this->assertStringEndsWith( 'bestsellers_8', $key );
	}

	public function test_group_scoping_returns_an_isolated_manager(): void {
		$stats = $this->cache->for_group( 'stats' );

		$this->assertNotSame( $this->cache->build_key( 'x' ), $stats->build_key( 'x' ) );
	}

	public function test_expired_entries_are_treated_as_a_miss(): void {
		$this->store->write( $this->cache->build_key( 'stale' ), 'old', -10 );

		$this->assertNull( $this->cache->get( 'stale' ) );
	}

	/**
	 * A zero TTL must mean "no expiry" in every store.
	 *
	 * CacheManager writes each group's version with a TTL of 0 so it outlives
	 * the entries it invalidates. When TransientStore quietly substituted its
	 * own default instead, a flush undid itself an hour later and every
	 * orphaned entry came back — on the fallback path that runs on any host
	 * without an object cache, which is most of them.
	 */
	public function test_a_zero_ttl_never_expires(): void {
		$this->store->write( 'permanent', 'value', 0 );

		// Far enough forward that any real TTL would have lapsed.
		$this->assertSame( 'value', $this->store->read( 'permanent', 'miss' ) );

		$this->store->write( 'temporary', 'value', -1 );

		$this->assertSame( 'miss', $this->store->read( 'temporary', 'miss' ) );
	}

	public function test_flushing_a_group_survives_the_version_entry_ttl(): void {
		$manager = new CacheManager( $this->store, '1.0.0', 'products' );

		$manager->set( 'key', 'original', 60 );
		$manager->flush_group( 'products' );

		// A fresh manager, as a later request would build: it must read the
		// bumped version rather than falling back to 1 and finding the old
		// entry still sitting there under the old key.
		$fresh = new CacheManager( $this->store, '1.0.0', 'products' );

		$this->assertSame( 'recomputed', $fresh->remember( 'key', static fn (): string => 'recomputed', 60 ) );
	}

	public function test_delete_removes_the_memoised_value_too(): void {
		$this->cache->set( 'key', 'value' );
		$this->cache->delete( 'key' );

		$this->assertNull( $this->cache->get( 'key' ) );
	}
}
