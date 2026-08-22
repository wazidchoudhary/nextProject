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

	public function test_delete_removes_the_memoised_value_too(): void {
		$this->cache->set( 'key', 'value' );
		$this->cache->delete( 'key' );

		$this->assertNull( $this->cache->get( 'key' ) );
	}
}
