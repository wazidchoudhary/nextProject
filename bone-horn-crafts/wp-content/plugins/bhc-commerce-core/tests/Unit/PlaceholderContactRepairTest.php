<?php
/**
 * Placeholder contact repair tests.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Tests\Unit;

use BoneHornCrafts\Core\Store\PlaceholderContactRepair;
use BoneHornCrafts\Core\Support\Options;
use PHPUnit\Framework\TestCase;

/**
 * The repair must correct sample values and never touch a chosen one.
 */
final class PlaceholderContactRepairTest extends TestCase {

	/**
	 * Resets the in-memory options store between tests.
	 *
	 * `Options` memoises its settings, so each test gets a fresh instance too.
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['bhc_test_options'] = [];
	}

	/**
	 * Seeds the stored settings row, the way a seeded store would have it.
	 *
	 * @param array<string, mixed> $stored Stored settings.
	 */
	private function options( array $stored ): Options {
		if ( [] !== $stored ) {
			$GLOBALS['bhc_test_options'][ Options::OPTION_KEY ] = $stored;
		}

		return new Options();
	}

	/**
	 * The case that shipped: a sample US number published as the business
	 * telephone in structured data.
	 */
	public function test_replaces_the_sample_phone_and_email(): void {
		$options = $this->options(
			[
				'organization_phone' => '+1 302 555 0148',
				'organization_email' => 'hello@bonehorncrafts.com',
			]
		);

		$repair = new PlaceholderContactRepair( $options );

		$this->assertCount( 2, $repair->drift() );

		$changed = $repair->apply();

		$this->assertSame( '+91 87007 53517', $changed['organization_phone'] );
		$this->assertSame( 'info@bonehorncrafts.com', $changed['organization_email'] );
		$this->assertSame( '+91 87007 53517', $options->all()['organization_phone'] );
	}

	/**
	 * A number the merchant typed is theirs, whatever it is.
	 */
	public function test_leaves_a_chosen_value_alone(): void {
		$options = $this->options(
			[
				'organization_phone' => '+44 20 7946 0958',
				'organization_email' => 'sales@example.co.uk',
			]
		);

		$repair = new PlaceholderContactRepair( $options );

		$this->assertSame( [], $repair->drift() );
		$this->assertSame( [], $repair->apply() );
		$this->assertSame( '+44 20 7946 0958', $options->all()['organization_phone'] );
	}

	/**
	 * Running it twice must be a no-op the second time — it is wired to a
	 * migration hook that can fire again on the next schema bump.
	 */
	public function test_is_idempotent(): void {
		$options = $this->options( [ 'organization_phone' => '+1 302 555 0148' ] );
		$repair  = new PlaceholderContactRepair( $options );

		$this->assertCount( 1, $repair->apply() );
		$this->assertSame( [], $repair->apply() );
	}

	/**
	 * A store that never wrote a settings row already has the right defaults
	 * and must not be reported as drifting.
	 */
	public function test_a_fresh_store_reports_no_drift(): void {
		$repair = new PlaceholderContactRepair( $this->options( [] ) );

		$this->assertSame( [], $repair->drift() );
	}
}
