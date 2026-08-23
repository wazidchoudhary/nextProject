<?php
/**
 * Confirmation and unsubscribe links.
 *
 * @package BoneHornCrafts\Newsletter
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Newsletter\Http;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Newsletter\Service\ConfirmationMailer;
use BoneHornCrafts\Newsletter\Service\SubscriptionService;

/**
 * Handles the links sent in subscription email.
 *
 * These are GET requests arriving from a mail client, so they cannot carry a
 * nonce — the token in the URL is the credential. That is why the token is 64
 * CSPRNG characters and why it is reissued whenever someone re-subscribes.
 *
 * The handler runs on `template_redirect` and redirects to a clean URL rather
 * than rendering in place, so a refresh does not re-run the action and the
 * token does not stay in the address bar, in history, or in the referrer of
 * every asset the page then loads.
 */
final class LinkHandler {

	/**
	 * Query variable set on the destination page after handling.
	 */
	public const RESULT_VAR = 'bhc_nl_result';

	/**
	 * Constructor.
	 *
	 * @param SubscriptionService $subscriptions Subscription lifecycle.
	 */
	public function __construct( private SubscriptionService $subscriptions ) {}

	/**
	 * Registers hooks.
	 */
	public function register_hooks(): void {
		add_action( 'template_redirect', [ $this, 'maybe_handle' ] );
		add_action( 'wp_body_open', [ $this, 'render_notice' ] );
	}

	/**
	 * Acts on a confirm or unsubscribe link.
	 */
	public function maybe_handle(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.NoNonceVerification -- A link arriving from an email client cannot carry a nonce, so the 64-character CSPRNG token in the URL is the credential; it is single-purpose, reissued on re-subscribe, and looked up as an exact match.
		$action = isset( $_GET[ ConfirmationMailer::ACTION_VAR ] )
			? sanitize_key( wp_unslash( (string) $_GET[ ConfirmationMailer::ACTION_VAR ] ) )
			: '';

		if ( ! in_array( $action, [ 'confirm', 'unsubscribe' ], true ) ) {
			return;
		}

		$token = isset( $_GET[ ConfirmationMailer::TOKEN_VAR ] )
			? sanitize_text_field( wp_unslash( (string) $_GET[ ConfirmationMailer::TOKEN_VAR ] ) )
			: '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$result = 'confirm' === $action
			? $this->subscriptions->confirm( $token )
			: $this->subscriptions->unsubscribe( $token );

		// Mapped rather than derived: appending "ed" to the action produced
		// "unsubscribeed", and the result value is what selects the message
		// shown to the visitor.
		$outcome = is_wp_error( $result )
			? 'invalid'
			: ( 'confirm' === $action ? 'confirmed' : 'unsubscribed' );

		wp_safe_redirect( add_query_arg( self::RESULT_VAR, $outcome, home_url( '/' ) ) );

		exit;
	}

	/**
	 * Renders the outcome banner at the top of the page.
	 */
	public function render_notice(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Presentational only: the value is matched against the fixed list below, never stored, and never acted on.
		$result = isset( $_GET[ self::RESULT_VAR ] )
			? sanitize_key( wp_unslash( (string) $_GET[ self::RESULT_VAR ] ) )
			: '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$messages = [
			'confirmed'    => __( 'You are on the list. We will write when a batch is cut.', 'bhc-newsletter' ),
			'unsubscribed' => __( 'You have been removed. No further emails will be sent.', 'bhc-newsletter' ),
			'invalid'      => __( 'That link is no longer valid. It may already have been used.', 'bhc-newsletter' ),
		];

		if ( ! isset( $messages[ $result ] ) ) {
			return;
		}

		printf(
			'<div class="bhc-newsletter-banner bhc-newsletter-banner--%1$s" role="status"><p>%2$s</p></div>',
			esc_attr( 'invalid' === $result ? 'error' : 'success' ),
			esc_html( $messages[ $result ] )
		);
	}
}
