<?php
/**
 * HMAC signed cookie storage.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes tamper-evident cookies for guest state.
 *
 * Guest wishlists and "recently viewed" must survive without a database write
 * per visitor, but a raw cookie is attacker-controlled input. Payloads are
 * therefore JSON encoded, signed with `wp_salt()` and verified in constant
 * time; a bad signature is treated as "no cookie" rather than an error.
 *
 * Cookies are HttpOnly by default and `Secure` whenever the site runs on HTTPS.
 * Nothing personal is ever stored — only product ids.
 */
final class SignedCookie {

	/**
	 * Maximum accepted cookie payload size, in bytes.
	 */
	private const MAX_BYTES = 2048;

	/**
	 * Reads and verifies a cookie payload.
	 *
	 * @param string $name Cookie name.
	 *
	 * @return array<int|string, mixed> Decoded payload, or an empty array.
	 */
	public function read( string $name ): array {
		if ( ! isset( $_COOKIE[ $name ] ) ) {
			return [];
		}

		$raw = (string) wp_unslash( $_COOKIE[ $name ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Verified and decoded below.

		if ( strlen( $raw ) > self::MAX_BYTES ) {
			return [];
		}

		$parts = explode( '.', $raw, 2 );

		if ( 2 !== count( $parts ) ) {
			return [];
		}

		[ $payload, $signature ] = $parts;

		if ( ! hash_equals( $this->sign( $payload ), $signature ) ) {
			return [];
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Transport encoding for the signed cookie payload, not obfuscation; the HMAC below is what establishes trust.
		$decoded = json_decode( (string) base64_decode( strtr( $payload, '-_', '+/' ), true ), true );

		return is_array( $decoded ) ? $decoded : [];
	}

	/**
	 * Writes a signed cookie.
	 *
	 * @param string                   $name    Cookie name.
	 * @param array<int|string, mixed> $payload Payload (must be JSON serialisable).
	 * @param int                      $ttl     Lifetime in seconds.
	 */
	public function write( string $name, array $payload, int $ttl = MONTH_IN_SECONDS ): bool {
		$json = (string) wp_json_encode( $payload );

		if ( strlen( $json ) > self::MAX_BYTES ) {
			return false;
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- URL-safe transport encoding so the JSON payload survives a cookie value.
		$encoded = rtrim( strtr( base64_encode( $json ), '+/', '-_' ), '=' );
		$value   = $encoded . '.' . $this->sign( $encoded );

		return $this->set_cookie( $name, $value, time() + $ttl );
	}

	/**
	 * Deletes a cookie.
	 *
	 * @param string $name Cookie name.
	 */
	public function delete( string $name ): bool {
		unset( $_COOKIE[ $name ] );

		return $this->set_cookie( $name, '', time() - DAY_IN_SECONDS );
	}

	/**
	 * Computes the payload signature.
	 *
	 * @param string $payload Base64url payload.
	 */
	private function sign( string $payload ): string {
		return hash_hmac( 'sha256', $payload, wp_salt( 'secure_auth' ) );
	}

	/**
	 * Sends the cookie with hardened attributes.
	 *
	 * @param string $name    Cookie name.
	 * @param string $value   Cookie value.
	 * @param int    $expires Expiry timestamp.
	 */
	private function set_cookie( string $name, string $value, int $expires ): bool {
		if ( headers_sent() ) {
			return false;
		}

		$options = [
			'expires'  => $expires,
			'path'     => defined( 'COOKIEPATH' ) ? COOKIEPATH : '/',
			'domain'   => defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '',
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		];

		// Keep the superglobal in sync so a write is visible to later reads
		// within the same request.
		if ( '' === $value ) {
			unset( $_COOKIE[ $name ] );
		} else {
			$_COOKIE[ $name ] = $value;
		}

		return setcookie( $name, $value, $options );
	}
}
