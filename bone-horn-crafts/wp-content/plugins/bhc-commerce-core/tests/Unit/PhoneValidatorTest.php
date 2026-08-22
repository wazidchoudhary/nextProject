<?php
/**
 * Phone validation tests.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Tests\Unit;

use BoneHornCrafts\Core\Checkout\PhoneValidator;
use PHPUnit\Framework\TestCase;

/**
 * @covers \BoneHornCrafts\Core\Checkout\PhoneValidator
 */
final class PhoneValidatorTest extends TestCase {

	private PhoneValidator $validator;

	protected function setUp(): void {
		$this->validator = new PhoneValidator();
	}

	public function test_normalise_strips_formatting_but_keeps_the_plus(): void {
		$this->assertSame( '+15035550142', $this->validator->normalise( '+1 (503) 555-0142' ) );
		$this->assertSame( '5035550142', $this->validator->normalise( '503 555 0142' ) );
		$this->assertSame( '+915505550142', $this->validator->normalise( '0091 55055 50142' ) );
	}

	public function test_national_numbers_gain_the_country_dial_code(): void {
		$this->assertSame( '+441144960118', $this->validator->to_international( '0114 496 0118', 'GB' ) );
		$this->assertSame( '+918055503311', $this->validator->to_international( '080 5550 3311', 'IN' ) );
	}

	public function test_international_numbers_are_left_alone(): void {
		$this->assertSame( '+15035550142', $this->validator->to_international( '+1 503 555 0142', 'GB' ) );
	}

	public function test_plausible_numbers_are_accepted(): void {
		$this->assertTrue( $this->validator->is_valid( '+1 503 555 0142', 'US' ) );
		$this->assertTrue( $this->validator->is_valid( '0114 496 0118', 'GB' ) );
		$this->assertTrue( $this->validator->is_valid( '+61 3 5550 0177', 'AU' ) );
	}

	public function test_implausible_numbers_are_rejected(): void {
		$this->assertFalse( $this->validator->is_valid( '12345', 'US' ), 'Too short for any dialling plan.' );
		$this->assertFalse( $this->validator->is_valid( '+1 5035550142 5550142 55501', 'US' ), 'Longer than E.164 allows.' );
		$this->assertFalse( $this->validator->is_valid( '', 'US' ) );
	}

	public function test_placeholder_uses_the_country_dial_code(): void {
		$this->assertStringStartsWith( '+49', $this->validator->placeholder( 'DE' ) );
		$this->assertStringStartsWith( '+', $this->validator->placeholder( 'ZZ' ) );
	}
}
