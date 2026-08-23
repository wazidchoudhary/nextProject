<?php
/**
 * Business details tests.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Tests\Unit;

use BoneHornCrafts\Core\Content\PolicyPageContent;
use BoneHornCrafts\Core\Store\BusinessDetails;
use BoneHornCrafts\Core\Support\Options;
use PHPUnit\Framework\TestCase;

/**
 * Covers the one place the postal address is allowed to live.
 */
final class BusinessDetailsTest extends TestCase {

	/**
	 * Subject under test.
	 */
	private function details(): BusinessDetails {
		return new BusinessDetails( new Options() );
	}

	/**
	 * The tel: href must survive a display number full of spaces, because it is
	 * derived from it rather than stored separately.
	 */
	public function test_phone_href_strips_formatting(): void {
		$href = $this->details()->phone_href();

		$this->assertSame( '+918700753517', $href );
		$this->assertDoesNotMatchRegularExpression( '/[^0-9+]/', $href );
	}

	/**
	 * Postal order matters: a schema consumer reads these positionally.
	 */
	public function test_address_lines_are_in_postal_order(): void {
		$lines = $this->details()->address_lines();

		$this->assertCount( 4, $lines );
		$this->assertStringContainsString( 'Garima Garden', $lines[0] );
		$this->assertStringContainsString( 'Ghaziabad', $lines[1] );
		$this->assertStringContainsString( '201005', $lines[2] );
		$this->assertSame( 'India', $lines[3] );
	}

	/**
	 * The schema node needs the ISO country code, not the display name — a
	 * `addressCountry` of "India" is accepted but weaker than "IN".
	 */
	public function test_postal_address_schema_uses_iso_country(): void {
		$schema = $this->details()->postal_address_schema();

		$this->assertSame( 'PostalAddress', $schema['@type'] );
		$this->assertSame( 'IN', $schema['addressCountry'] );
		$this->assertSame( '201005', $schema['postalCode'] );
		$this->assertSame( 'Uttar Pradesh', $schema['addressRegion'] );
	}

	/**
	 * Every policy page must carry the address, because that is the whole point
	 * of publishing them — and the footer, the schema and these pages agreeing
	 * is what stops the entity signal fragmenting.
	 */
	public function test_every_policy_page_carries_the_contact_details(): void {
		$content = new PolicyPageContent( $this->details() );
		$pages   = $content->all();

		$this->assertArrayHasKey( 'contact', $pages );
		$this->assertArrayHasKey( 'privacy-policy', $pages );
		$this->assertArrayHasKey( 'terms-conditions', $pages );

		foreach ( [ 'contact', 'privacy-policy', 'terms-conditions', 'shipping-delivery', 'returns-refunds' ] as $slug ) {
			$body = $pages[ $slug ]['content'];

			$this->assertStringContainsString( 'Garima Garden', $body, $slug . ' is missing the street address' );
			$this->assertStringContainsString( 'info@bonehorncrafts.com', $body, $slug . ' is missing the email' );
			$this->assertStringContainsString( 'tel:+918700753517', $body, $slug . ' is missing the phone link' );
			$this->assertNotSame( '', trim( $pages[ $slug ]['excerpt'] ), $slug . ' has no excerpt' );
		}
	}

	/**
	 * The pages describe how the store runs; they are not legal advice, and
	 * they must keep saying so.
	 */
	public function test_privacy_and_terms_ask_to_be_reviewed(): void {
		$pages = ( new PolicyPageContent( $this->details() ) )->all();

		foreach ( [ 'privacy-policy', 'terms-conditions' ] as $slug ) {
			$this->assertStringContainsString( 'not legal advice', $pages[ $slug ]['content'], $slug );
		}
	}

	/**
	 * The old copy carried a "demonstration copy" disclaimer. It is a real
	 * store now, and the reason those pages exist is that a client will read
	 * them.
	 */
	public function test_no_demo_disclaimer_survives(): void {
		foreach ( ( new PolicyPageContent( $this->details() ) )->all() as $slug => $page ) {
			$this->assertStringNotContainsStringIgnoringCase( 'demonstration copy', $page['content'], $slug );
			$this->assertStringNotContainsStringIgnoringCase( 'demo store', $page['content'], $slug );
		}
	}
}
