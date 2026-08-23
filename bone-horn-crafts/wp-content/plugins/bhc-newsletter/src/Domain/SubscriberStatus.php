<?php
/**
 * Subscription lifecycle states.
 *
 * @package BoneHornCrafts\Newsletter
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Newsletter\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * The states a subscriber can be in.
 *
 * A backed enum rather than string constants so an invalid state cannot reach
 * the database: `from()` throws and `tryFrom()` returns null, both of which are
 * easier to handle correctly than a typo that silently stores 'confirmd'.
 */
enum SubscriberStatus: string {
	/**
	 * Address captured, confirmation email sent, link not yet followed.
	 */
	case Pending = 'pending';

	/**
	 * Confirmation link followed. The only state that may receive mail.
	 */
	case Confirmed = 'confirmed';

	/**
	 * Opted out. Kept rather than deleted so a later re-subscribe does not
	 * resurrect someone who asked to be left alone, and so the opt-out itself
	 * is auditable.
	 */
	case Unsubscribed = 'unsubscribed';

	/**
	 * Whether this state may be sent marketing mail.
	 */
	public function is_mailable(): bool {
		// phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this in a non-static enum method is the case itself, valid since PHP 8.1; the sniff predates enums and reads this as a plain function. Verified at runtime.
		return self::Confirmed === $this;
	}

	/**
	 * Human-readable label.
	 */
	public function label(): string {
		// phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- As above.
		return match ( $this ) {
			self::Pending      => __( 'Awaiting confirmation', 'bhc-newsletter' ),
			self::Confirmed    => __( 'Confirmed', 'bhc-newsletter' ),
			self::Unsubscribed => __( 'Unsubscribed', 'bhc-newsletter' ),
		};
	}

	/**
	 * All states, as value => label.
	 *
	 * @return array<string, string>
	 */
	public static function options(): array {
		$options = [];

		foreach ( self::cases() as $case ) {
			$options[ $case->value ] = $case->label();
		}

		return $options;
	}
}
