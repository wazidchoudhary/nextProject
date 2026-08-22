<?php
/**
 * Phone number validation.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Checkout;

defined( 'ABSPATH' ) || exit;

/**
 * Validates international phone numbers well enough for a courier label.
 *
 * Deliberately not a full E.164 library: the store needs a number a courier can
 * dial, so the rules are "digits only after normalisation, a plausible length,
 * and a country dial code when the customer did not supply one".
 */
final class PhoneValidator {

	private const MIN_DIGITS = 7;
	private const MAX_DIGITS = 15;

	/**
	 * Strips formatting, keeping a leading `+`.
	 *
	 * @param string $phone Raw phone number.
	 */
	public function normalise( string $phone ): string {
		$phone = trim( $phone );
		$plus  = str_starts_with( $phone, '+' ) || str_starts_with( $phone, '00' );

		$digits = (string) preg_replace( '/\D+/', '', $phone );

		if ( str_starts_with( $digits, '00' ) ) {
			$digits = substr( $digits, 2 );
		}

		return $plus ? '+' . $digits : $digits;
	}

	/**
	 * Adds the country dial code when the number is given in national format.
	 *
	 * @param string $phone   Raw phone number.
	 * @param string $country ISO country code.
	 */
	public function to_international( string $phone, string $country ): string {
		$normalised = $this->normalise( $phone );

		if ( str_starts_with( $normalised, '+' ) ) {
			return $normalised;
		}

		$dial = (string) CountryProfile::get( $country )['dial'];

		if ( '' === $dial || '' === $normalised ) {
			return $normalised;
		}

		// A national number frequently carries a trunk prefix (0 in the UK,
		// India and most of Europe) that must be dropped before the dial code.
		$digits = ltrim( $normalised, '0' );

		return $dial . $digits;
	}

	/**
	 * Whether a number is plausible for a country.
	 *
	 * @param string $phone   Raw phone number.
	 * @param string $country ISO country code.
	 */
	public function is_valid( string $phone, string $country = '' ): bool {
		$digits = (string) preg_replace( '/\D+/', '', $this->to_international( $phone, $country ) );
		$length = strlen( $digits );

		return $length >= self::MIN_DIGITS && $length <= self::MAX_DIGITS;
	}

	/**
	 * Placeholder shown in the phone field for a country.
	 *
	 * @param string $country ISO country code.
	 */
	public function placeholder( string $country ): string {
		$dial = (string) CountryProfile::get( $country )['dial'];

		return '' === $dial ? '+00 000 000 0000' : $dial . ' 000 000 0000';
	}

	/**
	 * Validation error message, or an empty string when valid.
	 *
	 * @param string $phone   Raw phone number.
	 * @param string $country ISO country code.
	 */
	public function error_for( string $phone, string $country ): string {
		if ( $this->is_valid( $phone, $country ) ) {
			return '';
		}

		return __( 'Please enter a phone number the courier can reach you on, including the country code.', 'bhc-commerce-core' );
	}
}
