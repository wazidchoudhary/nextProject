<?php
/**
 * PayPal credential verification.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Payments;

defined( 'ABSPATH' ) || exit;

/**
 * Asks PayPal whether the configured credentials actually work.
 *
 * Storing a client id and secret proves nothing: a typo, a sandbox key pasted
 * into a live store, or a secret that was rotated last week all look identical
 * from inside WordPress. The only honest check is an OAuth token request, which
 * is what the gateway itself does on the first payment — so this fails in a
 * terminal on a Tuesday rather than at somebody's checkout on a Friday.
 *
 * Nothing is charged and nothing is created. A client-credentials grant only
 * exchanges the pair for a bearer token.
 */
final class PayPalVerifier {

	/**
	 * Live API host.
	 */
	private const LIVE = 'https://api-m.paypal.com';

	/**
	 * Sandbox API host.
	 */
	private const SANDBOX = 'https://api-m.sandbox.paypal.com';

	/**
	 * Constructor.
	 *
	 * @param PayPalCredentials $credentials Credential bridge.
	 */
	public function __construct( private PayPalCredentials $credentials ) {}

	/**
	 * Requests a token and reports what happened.
	 *
	 * @return array{ok: bool, status: int, message: string, mode: string, scopes: string}
	 */
	public function verify(): array {
		$mode = $this->credentials->is_sandbox() ? 'sandbox' : 'live';

		if ( ! $this->credentials->is_configured() ) {
			return [
				'ok'      => false,
				'status'  => 0,
				'mode'    => $mode,
				'scopes'  => '',
				'message' => __( 'No PayPal credentials are configured. Define BHC_PAYPAL_CLIENT_ID and BHC_PAYPAL_CLIENT_SECRET in wp-config.php.', 'bhc-commerce-core' ),
			];
		}

		$settings = (array) get_option( 'woocommerce-ppcp-settings', [] );

		$response = wp_remote_post(
			( 'sandbox' === $mode ? self::SANDBOX : self::LIVE ) . '/v1/oauth2/token',
			[
				'timeout' => 30,
				'headers' => [
					// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- HTTP Basic auth is defined as base64 of "id:secret"; this is the wire format, not obfuscation.
					'Authorization' => 'Basic ' . base64_encode(
						(string) ( $settings['client_id'] ?? '' ) . ':' . (string) ( $settings['client_secret'] ?? '' )
					),
					'Content-Type'  => 'application/x-www-form-urlencoded',
				],
				'body'    => 'grant_type=client_credentials',
			]
		);

		if ( is_wp_error( $response ) ) {
			return [
				'ok'      => false,
				'status'  => 0,
				'mode'    => $mode,
				'scopes'  => '',
				/* translators: %s: transport error message. */
				'message' => sprintf( __( 'Could not reach PayPal: %s', 'bhc-commerce-core' ), $response->get_error_message() ),
			];
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$body   = is_array( $body ) ? $body : [];

		if ( 200 === $status ) {
			/* translators: 1: live or sandbox, 2: token lifetime in seconds. */
			$success = __( 'Authenticated against PayPal %1$s. Token valid for %2$d seconds.', 'bhc-commerce-core' );

			return [
				'ok'      => true,
				'status'  => $status,
				'mode'    => $mode,
				'scopes'  => (string) ( $body['scope'] ?? '' ),
				'message' => sprintf(
					$success,
					$mode,
					(int) ( $body['expires_in'] ?? 0 )
				),
			];
		}

		/* translators: 1: HTTP status, 2: PayPal error description. */
		$rejected = __( 'PayPal rejected the credentials (HTTP %1$d): %2$s', 'bhc-commerce-core' );

		return [
			'ok'      => false,
			'status'  => $status,
			'mode'    => $mode,
			'scopes'  => '',
			'message' => sprintf(
				$rejected,
				$status,
				(string) ( $body['error_description'] ?? ( $body['error'] ?? __( 'no detail given', 'bhc-commerce-core' ) ) )
			),
		];
	}
}
