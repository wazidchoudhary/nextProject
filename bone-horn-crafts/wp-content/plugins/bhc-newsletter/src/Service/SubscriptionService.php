<?php
/**
 * Subscription lifecycle.
 *
 * @package BoneHornCrafts\Newsletter
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Newsletter\Service;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Newsletter\Domain\Subscriber;
use BoneHornCrafts\Newsletter\Domain\SubscriberStatus;
use BoneHornCrafts\Newsletter\Repository\SubscriberRepository;
use WP_Error;

/**
 * Subscribe, confirm and unsubscribe.
 *
 * Double opt-in is not optional here. Anyone can type anyone else's address
 * into a footer form, so a single-opt-in list is a list of addresses that did
 * not ask to be on it — which is a deliverability problem before it is a legal
 * one, because the resulting spam complaints are what get a sending domain
 * blocked. Confirmation also proves the address exists, which is the difference
 * between a list of 400 and a list of 400 that can be reached.
 */
final class SubscriptionService {

	/**
	 * Constructor.
	 *
	 * @param SubscriberRepository $repository Storage.
	 * @param ConfirmationMailer   $mailer     Outgoing mail.
	 */
	public function __construct(
		private SubscriberRepository $repository,
		private ConfirmationMailer $mailer
	) {}

	/**
	 * Handles a subscribe request.
	 *
	 * The response is deliberately identical whether the address is new,
	 * already pending, or already confirmed. Distinguishing them turns the form
	 * into an oracle that will tell anyone whether a given address is on the
	 * list, which is a privacy leak about the store's customers.
	 *
	 * @param string $email  Submitted address.
	 * @param string $source Capture point.
	 *
	 * @return true|WP_Error
	 */
	public function subscribe( string $email, string $source = 'footer' ) {
		$email = strtolower( trim( $email ) );

		if ( ! is_email( $email ) ) {
			return new WP_Error(
				'bhc_newsletter_invalid_email',
				__( 'That does not look like an email address.', 'bhc-newsletter' ),
				[ 'status' => 400 ]
			);
		}

		$existing = $this->repository->find_by_email( $email );

		if ( $existing instanceof Subscriber ) {
			return $this->resubscribe( $existing );
		}

		$token = $this->token();

		$id = $this->repository->insert( $email, $token, $source, $this->ip_hash() );

		if ( 0 === $id ) {
			return new WP_Error(
				'bhc_newsletter_store_failed',
				__( 'We could not save that just now. Please try again.', 'bhc-newsletter' ),
				[ 'status' => 500 ]
			);
		}

		$this->mailer->send_confirmation( $email, $token );

		/**
		 * Fires after a new address is captured and sent a confirmation.
		 *
		 * @param string $email Address.
		 * @param string $source Capture point.
		 */
		do_action( 'bhc_newsletter_subscribed', $email, $source );

		return true;
	}

	/**
	 * Handles an address that is already on the list.
	 *
	 * @param Subscriber $subscriber Existing record.
	 *
	 * @return true|WP_Error
	 */
	private function resubscribe( Subscriber $subscriber ) {
		// Already confirmed: nothing to do, and nothing to disclose.
		if ( SubscriberStatus::Confirmed === $subscriber->status ) {
			return true;
		}

		// Pending or previously unsubscribed both get a fresh token and a new
		// confirmation. Reusing the old token would keep alive a link that may
		// be sitting in a forwarded email.
		$token = $this->token();

		$this->repository->set_token( $subscriber->id, $token );
		$this->repository->set_status( $subscriber->id, SubscriberStatus::Pending );
		$this->mailer->send_confirmation( $subscriber->email, $token );

		return true;
	}

	/**
	 * Confirms a subscription from a token.
	 *
	 * @param string $token Token from the confirmation link.
	 *
	 * @return true|WP_Error
	 */
	public function confirm( string $token ) {
		$subscriber = $this->repository->find_by_token( $token );

		if ( ! $subscriber instanceof Subscriber ) {
			return new WP_Error(
				'bhc_newsletter_unknown_token',
				__( 'That confirmation link is not valid. It may already have been used.', 'bhc-newsletter' ),
				[ 'status' => 404 ]
			);
		}

		if ( SubscriberStatus::Confirmed === $subscriber->status ) {
			return true;
		}

		$this->repository->set_status( $subscriber->id, SubscriberStatus::Confirmed );

		/**
		 * Fires when a subscription is confirmed.
		 *
		 * @param string $email Address.
		 */
		do_action( 'bhc_newsletter_confirmed', $subscriber->email );

		return true;
	}

	/**
	 * Unsubscribes from a token.
	 *
	 * @param string $token Token from the unsubscribe link.
	 *
	 * @return true|WP_Error
	 */
	public function unsubscribe( string $token ) {
		$subscriber = $this->repository->find_by_token( $token );

		if ( ! $subscriber instanceof Subscriber ) {
			return new WP_Error(
				'bhc_newsletter_unknown_token',
				__( 'That unsubscribe link is not valid.', 'bhc-newsletter' ),
				[ 'status' => 404 ]
			);
		}

		$this->repository->set_status( $subscriber->id, SubscriberStatus::Unsubscribed );

		/**
		 * Fires when someone opts out.
		 *
		 * @param string $email Address.
		 */
		do_action( 'bhc_newsletter_unsubscribed', $subscriber->email );

		return true;
	}

	/**
	 * Generates a confirmation token.
	 *
	 * `wp_generate_password()` with special characters off yields 64
	 * alphanumeric characters from the same CSPRNG WordPress uses for auth
	 * cookies. A guessable token is a way to confirm somebody else's address.
	 */
	private function token(): string {
		return wp_generate_password( 64, false, false );
	}

	/**
	 * Hashes the client address.
	 *
	 * Stored hashed rather than raw: it exists to spot a flood of signups from
	 * one place, which a hash serves as well as the address itself, and a hash
	 * is not personal data sitting in a table that gets exported to CSV.
	 */
	private function ip_hash(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) )
			: '';

		return '' === $ip ? '' : wp_hash( $ip );
	}
}
