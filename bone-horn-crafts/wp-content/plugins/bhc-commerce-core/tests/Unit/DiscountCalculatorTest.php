<?php
/**
 * Pricing arithmetic tests.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Tests\Unit;

use BoneHornCrafts\Core\Pricing\DiscountCalculator;
use PHPUnit\Framework\TestCase;

/**
 * The money rules. If anything in this file is wrong, customers are charged the
 * wrong amount — which is why the edge cases are as heavily covered as the
 * happy path.
 *
 * @covers \BoneHornCrafts\Core\Pricing\DiscountCalculator
 */
final class DiscountCalculatorTest extends TestCase {

	private DiscountCalculator $calculator;

	protected function setUp(): void {
		$this->calculator = new DiscountCalculator();
	}

	public function test_percentage_off_rounds_to_the_nearest_whole_percent(): void {
		$this->assertSame( 17, $this->calculator->percentage_off( 29.99, 24.99 ) );
		$this->assertSame( 50, $this->calculator->percentage_off( 20.00, 10.00 ) );
	}

	public function test_percentage_off_is_zero_when_there_is_no_discount(): void {
		$this->assertSame( 0, $this->calculator->percentage_off( 24.99, 24.99 ) );
		$this->assertSame( 0, $this->calculator->percentage_off( 24.99, 29.99 ), 'A higher "sale" price is not a discount.' );
		$this->assertSame( 0, $this->calculator->percentage_off( 0.0, 10.0 ), 'A zero regular price cannot produce a percentage.' );
	}

	public function test_savings_are_rounded_to_two_decimals(): void {
		$this->assertSame( 5.0, $this->calculator->savings( 29.99, 24.99 ) );
		$this->assertSame( 0.0, $this->calculator->savings( 10.0, 12.0 ) );
	}

	public function test_tier_price_returns_the_base_price_below_the_first_threshold(): void {
		$tiers = [
			[
				'min_qty' => 10,
				'price'   => 17.49,
			],
		];

		$this->assertSame( 19.99, $this->calculator->tier_price( $tiers, 9, 19.99 ) );
	}

	public function test_tier_price_applies_the_highest_reached_threshold(): void {
		$tiers = [
			[
				'min_qty' => 10,
				'price'   => 17.49,
			],
			[
				'min_qty' => 25,
				'price'   => 15.99,
			],
			[
				'min_qty' => 50,
				'price'   => 14.49,
			],
		];

		$this->assertSame( 17.49, $this->calculator->tier_price( $tiers, 10, 19.99 ) );
		$this->assertSame( 17.49, $this->calculator->tier_price( $tiers, 24, 19.99 ) );
		$this->assertSame( 15.99, $this->calculator->tier_price( $tiers, 25, 19.99 ) );
		$this->assertSame( 14.49, $this->calculator->tier_price( $tiers, 500, 19.99 ) );
	}

	public function test_tier_price_never_raises_the_price(): void {
		// A tier configured above the current (sale) price must be ignored:
		// quantity pricing is a discount mechanism, never a surcharge.
		$tiers = [
			[
				'min_qty' => 10,
				'price'   => 24.99,
			],
		];

		$this->assertSame( 18.00, $this->calculator->tier_price( $tiers, 20, 18.00 ) );
	}

	public function test_tier_price_ignores_malformed_rows(): void {
		$tiers = [
			[
				'min_qty' => 1,
				'price'   => 5.0,
			],
			[
				'min_qty' => 10,
				'price'   => -4.0,
			],
			[ 'nonsense' => true ],
			[
				'min_qty' => 12,
				'price'   => 15.0,
			],
		];

		$this->assertSame( 19.99, $this->calculator->tier_price( $tiers, 5, 19.99 ), 'A min_qty below 2 is not a quantity break.' );
		$this->assertSame( 15.0, $this->calculator->tier_price( $tiers, 12, 19.99 ) );
	}

	public function test_next_tier_reports_the_upcoming_threshold(): void {
		$tiers = [
			[
				'min_qty' => 10,
				'price'   => 17.49,
			],
			[
				'min_qty' => 25,
				'price'   => 15.99,
			],
		];

		$this->assertSame( 10, $this->calculator->next_tier( $tiers, 4 )['min_qty'] );
		$this->assertSame( 25, $this->calculator->next_tier( $tiers, 10 )['min_qty'] );
		$this->assertNull( $this->calculator->next_tier( $tiers, 25 ) );
	}

	public function test_line_total_multiplies_the_effective_unit_price(): void {
		$tiers = [
			[
				'min_qty' => 10,
				'price'   => 17.49,
			],
		];

		$this->assertSame( 174.90, $this->calculator->line_total( $tiers, 10, 19.99 ) );
		$this->assertSame( 179.91, $this->calculator->line_total( $tiers, 9, 19.99 ), 'Below the threshold the base price applies.' );
		$this->assertSame( 0.0, $this->calculator->line_total( $tiers, 0, 19.99 ) );
	}

	public function test_normalise_tiers_sorts_and_deduplicates(): void {
		$tiers = [
			[
				'min_qty' => 25,
				'price'   => 15.99,
			],
			[
				'min_qty' => 10,
				'price'   => 17.49,
			],
			[
				'min_qty' => 10,
				'price'   => 16.99,
			],
		];

		$normalised = $this->calculator->normalise_tiers( $tiers );

		$this->assertCount( 2, $normalised );
		$this->assertSame( 10, $normalised[0]['min_qty'] );
		$this->assertSame( 16.99, $normalised[0]['price'], 'The later row wins for a duplicate threshold.' );
		$this->assertSame( 25, $normalised[1]['min_qty'] );
	}

	public function test_tier_rows_include_the_saving_percentage(): void {
		$rows = $this->calculator->tier_rows(
			[
				[
					'min_qty' => 10,
					'price'   => 15.00,
				],
			],
			20.00
		);

		$this->assertSame( 25, $rows[0]['saving_percent'] );
	}
}
