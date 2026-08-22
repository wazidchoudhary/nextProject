<?php
/**
 * Logger redaction tests.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Tests\Unit;

use BoneHornCrafts\Core\Logging\Logger;
use PHPUnit\Framework\TestCase;

/**
 * The logger is the last line of defence against a secret reaching disk, so the
 * redaction rules get their own tests.
 *
 * @covers \BoneHornCrafts\Core\Logging\Logger
 */
final class LoggerRedactionTest extends TestCase {

	private Logger $logger;

	protected function setUp(): void {
		$this->logger = new Logger( 'test' );
	}

	public function test_sensitive_keys_are_redacted(): void {
		$clean = $this->logger->redact(
			[
				'user_id'       => 42,
				'password'      => 'hunter2',
				'api_key'       => 'sk_live_123',
				'authorization' => 'Bearer abc',
				'card_number'   => '4111111111111111',
				'session_token' => 'abc123',
			]
		);

		$this->assertSame( 42, $clean['user_id'] );

		foreach ( [ 'password', 'api_key', 'authorization', 'card_number', 'session_token' ] as $key ) {
			$this->assertSame( '[redacted]', $clean[ $key ], $key . ' must never reach the log' );
		}
	}

	public function test_redaction_matches_substrings_and_case(): void {
		$clean = $this->logger->redact(
			[
				'Stripe_API_KEY' => 'sk_live_123',
				'refreshToken'   => 'rt_123',
				'customer_email' => 'maker@example.com',
			]
		);

		$this->assertSame( '[redacted]', $clean['Stripe_API_KEY'] );
		$this->assertSame( '[redacted]', $clean['refreshToken'] );
		$this->assertSame( 'maker@example.com', $clean['customer_email'], 'Email is contact data, not a secret; it is kept so orders can be traced.' );
	}

	public function test_nested_context_is_redacted(): void {
		$clean = $this->logger->redact(
			[
				'order' => [
					'id'      => 7,
					'payment' => [ 'token' => 'tok_123' ],
				],
			]
		);

		$this->assertSame( '[redacted]', $clean['order']['payment']['token'] );
		$this->assertSame( 7, $clean['order']['id'] );
	}

	public function test_deep_structures_are_truncated(): void {
		$deep = [ 'a' => [ 'b' => [ 'c' => [ 'd' => [ 'e' => [ 'f' => 'too deep' ] ] ] ] ] ];

		$clean = $this->logger->redact( $deep );

		$this->assertSame( [ 'truncated' => true ], $clean['a']['b']['c']['d']['e'] );
	}

	public function test_long_strings_are_trimmed(): void {
		$clean = $this->logger->redact( [ 'body' => str_repeat( 'x', 900 ) ] );

		$this->assertLessThan( 900, strlen( (string) $clean['body'] ) );
	}

	public function test_objects_are_reduced_to_class_names(): void {
		$clean = $this->logger->redact( [ 'thing' => new \stdClass() ] );

		$this->assertSame( 'stdClass', $clean['thing'] );
	}
}
