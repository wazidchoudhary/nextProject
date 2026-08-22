<?php
/**
 * Postcode validation tests.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Tests\Unit;

use BoneHornCrafts\Core\Checkout\PostcodeValidator;
use PHPUnit\Framework\TestCase;

/**
 * @covers \BoneHornCrafts\Core\Checkout\PostcodeValidator
 * @covers \BoneHornCrafts\Core\Checkout\CountryProfile
 */
final class PostcodeValidatorTest extends TestCase {

	private PostcodeValidator $validator;

	protected function setUp(): void {
		$this->validator = new PostcodeValidator();
	}

	/**
	 * @dataProvider valid_postcodes
	 *
	 * @param string $postcode Postcode.
	 * @param string $country  Country code.
	 */
	public function test_accepts_valid_postcodes( string $postcode, string $country ): void {
		$this->assertTrue(
			$this->validator->is_valid( $postcode, $country ),
			sprintf( '%s should be valid for %s', $postcode, $country )
		);
	}

	/**
	 * Valid samples for each market the store ships to.
	 *
	 * @return array<int, array{0:string,1:string}>
	 */
	public static function valid_postcodes(): array {
		return [
			[ '97205', 'US' ],
			[ '97205-1234', 'US' ],
			[ 'K1A 0B1', 'CA' ],
			[ 'k1a0b1', 'CA' ],
			[ 'SW1A 1AA', 'GB' ],
			[ 'sw1a1aa', 'GB' ],
			[ '10115', 'DE' ],
			[ '1012 AB', 'NL' ],
			[ '226010', 'IN' ],
			[ '3000', 'AU' ],
			[ '00-001', 'PL' ],
			[ '100-0001', 'JP' ],
			[ '111 29', 'SE' ],
		];
	}

	/**
	 * @dataProvider invalid_postcodes
	 *
	 * @param string $postcode Postcode.
	 * @param string $country  Country code.
	 */
	public function test_rejects_invalid_postcodes( string $postcode, string $country ): void {
		$this->assertFalse(
			$this->validator->is_valid( $postcode, $country ),
			sprintf( '%s should be rejected for %s', $postcode, $country )
		);
	}

	/**
	 * Invalid samples, including the classic "right format, wrong country".
	 *
	 * @return array<int, array{0:string,1:string}>
	 */
	public static function invalid_postcodes(): array {
		return [
			[ 'ABCDE', 'US' ],
			[ '1234', 'US' ],
			[ 'SW1A 1AA', 'US' ],
			[ '97205', 'GB' ],
			[ '012345', 'IN' ],
			[ '02601', 'IN' ],
			[ '', 'DE' ],
			[ '123', 'DE' ],
		];
	}

	public function test_normalises_case_and_spacing(): void {
		$this->assertSame( 'SW1A 1AA', $this->validator->normalise( '  sw1a  1aa ', 'GB' ) );
		$this->assertSame( '226010', $this->validator->normalise( '226 010', 'IN' ), 'India uses no internal space.' );
	}

	public function test_labels_follow_the_destination(): void {
		$this->assertSame( 'ZIP code', $this->validator->label( 'US' ) );
		$this->assertSame( 'PIN code', $this->validator->label( 'IN' ) );
		$this->assertSame( 'Eircode', $this->validator->label( 'IE' ) );
	}

	public function test_unknown_countries_fall_back_to_a_permissive_rule(): void {
		// The store must not reject an address for a country it has no pattern
		// for; the courier is the authority there, not us.
		$this->assertTrue( $this->validator->is_valid( 'XY-1234', 'ZZ' ) );
		$this->assertFalse( $this->validator->is_valid( '', 'ZZ' ) );
	}

	public function test_error_message_names_the_field_and_an_example(): void {
		$message = $this->validator->error_for( 'nope', 'US' );

		$this->assertStringContainsString( 'ZIP code', $message );
		$this->assertStringContainsString( '97205', $message );
		$this->assertSame( '', $this->validator->error_for( '97205', 'US' ) );
	}
}
