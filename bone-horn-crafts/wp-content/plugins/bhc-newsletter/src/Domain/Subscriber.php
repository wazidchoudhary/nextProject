<?php
/**
 * Subscriber value object.
 *
 * @package BoneHornCrafts\Newsletter
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Newsletter\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * One row of the subscriber table, as an immutable object.
 *
 * Readonly because a subscriber is a record of something that happened — an
 * address was given at a time, from a page, and confirmed or not. Changing one
 * in place would lose that. State transitions go through the repository and
 * produce a new instance.
 */
final class Subscriber {

	/**
	 * Constructor.
	 *
	 * @param int              $id           Row id.
	 * @param string           $email        Email address, already normalised.
	 * @param SubscriberStatus $status       Lifecycle state.
	 * @param string           $token        Confirmation / unsubscribe token.
	 * @param string           $source       Where the address was captured.
	 * @param string           $created_at   MySQL datetime, GMT.
	 * @param string|null      $confirmed_at MySQL datetime, GMT, or null.
	 * @param string|null      $ended_at     Unsubscribe datetime, GMT, or null.
	 */
	public function __construct(
		public readonly int $id,
		public readonly string $email,
		public readonly SubscriberStatus $status,
		public readonly string $token,
		public readonly string $source,
		public readonly string $created_at,
		public readonly ?string $confirmed_at = null,
		public readonly ?string $ended_at = null
	) {}

	/**
	 * Builds one from a database row.
	 *
	 * @param array<string, mixed> $row Row as an associative array.
	 */
	public static function from_row( array $row ): self {
		return new self(
			(int) ( $row['id'] ?? 0 ),
			(string) ( $row['email'] ?? '' ),
			SubscriberStatus::tryFrom( (string) ( $row['status'] ?? '' ) ) ?? SubscriberStatus::Pending,
			(string) ( $row['token'] ?? '' ),
			(string) ( $row['source'] ?? '' ),
			(string) ( $row['created_at'] ?? '' ),
			isset( $row['confirmed_at'] ) ? (string) $row['confirmed_at'] : null,
			isset( $row['ended_at'] ) ? (string) $row['ended_at'] : null
		);
	}

	/**
	 * Row shape for a CSV export.
	 *
	 * @return array<string, string>
	 */
	public function to_export_row(): array {
		return [
			'email'        => $this->email,
			'status'       => $this->status->value,
			'source'       => $this->source,
			'subscribed'   => $this->created_at,
			'confirmed'    => (string) $this->confirmed_at,
			'unsubscribed' => (string) $this->ended_at,
		];
	}
}
