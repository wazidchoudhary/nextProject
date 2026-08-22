<?php
/**
 * Input sanitisation tests.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Tests\Unit;

use BoneHornCrafts\Core\Security\Sanitizer;
use PHPUnit\Framework\TestCase;

/**
 * @covers \BoneHornCrafts\Core\Security\Sanitizer
 */
final class SanitizerTest extends TestCase {

	public function test_id_list_casts_filters_and_deduplicates(): void {
		// `absint()` is WordPress's own semantic: a negative becomes positive,
		// non-numeric input becomes 0 and is dropped. Ids are still validated
		// against the catalogue downstream, so this is a normalisation step and
		// not an authorisation one.
		$this->assertSame( [ 12, 3, 7 ], Sanitizer::id_list( [ '12', 12, 'abc', -3, 0, 7 ] ) );
		$this->assertSame( [ 4, 9 ], Sanitizer::id_list( '4,9,x' ) );
	}

	public function test_id_list_is_capped(): void {
		$ids = range( 1, 500 );

		$this->assertCount( 100, Sanitizer::id_list( $ids ) );
		$this->assertCount( 5, Sanitizer::id_list( $ids, 5 ) );
	}

	public function test_country_requires_two_letters(): void {
		$this->assertSame( 'US', Sanitizer::country( 'us' ) );
		$this->assertSame( 'GB', Sanitizer::country( ' gb ' ) );
		$this->assertSame( '', Sanitizer::country( 'USA' ) );
		$this->assertSame( '', Sanitizer::country( 42 ) );
	}

	public function test_postcode_strips_unexpected_characters(): void {
		$this->assertSame( 'SW1A 1AA', Sanitizer::postcode( ' sw1a 1aa ' ) );

		// Markup characters are removed entirely; the remaining letters are
		// harmless and are rejected later by the country pattern.
		$injected = Sanitizer::postcode( '97205<script>alert(1)</script>' );

		$this->assertStringNotContainsString( '<', $injected );
		$this->assertStringNotContainsString( '(', $injected );
		$this->assertStringStartsWith( '97205', $injected );

		$this->assertSame( 12, strlen( Sanitizer::postcode( str_repeat( 'A', 40 ) ) ) );
	}

	public function test_text_is_trimmed_to_length(): void {
		$this->assertSame( 'hello', Sanitizer::text( '  hello  ' ) );
		$this->assertSame( 5, mb_strlen( Sanitizer::text( str_repeat( 'a', 50 ), 5 ) ) );
		$this->assertSame( '', Sanitizer::text( [ 'array' ] ) );
	}

	public function test_amount_is_never_negative(): void {
		$this->assertSame( 19.99, Sanitizer::amount( '19.99' ) );
		$this->assertSame( 0.0, Sanitizer::amount( '-5' ) );
		$this->assertSame( 0.0, Sanitizer::amount( 'free' ) );
	}

	public function test_slug_list_normalises_and_caps(): void {
		$this->assertSame( [ 'camel-bone', 'cattle-bone' ], Sanitizer::slug_list( 'camel-bone,Cattle-Bone,camel-bone' ) );
		$this->assertCount( 2, Sanitizer::slug_list( [ 'a', 'b', 'c' ], 2 ) );
	}
}
