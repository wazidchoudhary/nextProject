<?php
/**
 * Outgoing subscription mail.
 *
 * @package BoneHornCrafts\Newsletter
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Newsletter\Service;

defined( 'ABSPATH' ) || exit;

/**
 * Sends the double opt-in confirmation.
 *
 * Plain text rather than HTML, deliberately. A confirmation is one sentence and
 * one link; an HTML template adds a rendering surface, image-blocking
 * behaviour and a spam-filter signal for no gain. It is also the message most
 * likely to be read on a phone in a workshop.
 */
final class ConfirmationMailer {

	/**
	 * Query variable carrying the token on confirm and unsubscribe links.
	 */
	public const TOKEN_VAR = 'bhc_nl_token';

	/**
	 * Query variable carrying the action.
	 */
	public const ACTION_VAR = 'bhc_nl';

	/**
	 * Sends the confirmation message.
	 *
	 * @param string $email Recipient.
	 * @param string $token Confirmation token.
	 */
	public function send_confirmation( string $email, string $token ): bool {
		$site = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );

		/* translators: %s: store name. */
		$subject = sprintf( __( 'Confirm your %s subscription', 'bhc-newsletter' ), $site );

		$body = implode(
			"\n\n",
			[
				/* translators: %s: store name. */
				sprintf( __( 'Someone — we hope you — asked for new-material emails from %s.', 'bhc-newsletter' ), $site ),
				__( 'Confirm by opening this link:', 'bhc-newsletter' ),
				$this->link( 'confirm', $token ),
				__( 'If it was not you, ignore this message. Nothing will be sent and the address will not be added.', 'bhc-newsletter' ),
			]
		);

		return $this->send( $email, $subject, $body );
	}

	/**
	 * Builds a confirm or unsubscribe URL.
	 *
	 * @param string $action confirm or unsubscribe.
	 * @param string $token  Token.
	 */
	public function link( string $action, string $token ): string {
		return add_query_arg(
			[
				self::ACTION_VAR => $action,
				self::TOKEN_VAR  => $token,
			],
			home_url( '/' )
		);
	}

	/**
	 * Sends a plain-text message.
	 *
	 * @param string $to      Recipient.
	 * @param string $subject Subject.
	 * @param string $body    Body.
	 */
	private function send( string $to, string $subject, string $body ): bool {
		$headers = [ 'Content-Type: text/plain; charset=UTF-8' ];

		/**
		 * Filters the headers on subscription mail.
		 *
		 * A store sending from its own domain should set a From address here
		 * that matches its SPF and DKIM records; WordPress's default
		 * `wordpress@yourdomain` frequently fails both.
		 *
		 * @param string[] $headers Mail headers.
		 * @param string   $to      Recipient.
		 */
		$headers = (array) apply_filters( 'bhc_newsletter_mail_headers', $headers, $to );

		return wp_mail( $to, $subject, $body, $headers );
	}
}
