<?php
/**
 * Postcode validation.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Checkout;

defined( 'ABSPATH' ) || exit;

/**
 * Validates and normalises postal codes per country.
 *
 * Pure logic, no WordPress state, so it is unit tested directly
 * (`tests/Unit/PostcodeValidatorTest.php`).
 */
final class PostcodeValidator {

	/**
	 * Whether a postcode looks valid for a country.
	 *
	 * @param string $postcode Raw postcode.
	 * @param string $country  ISO country code.
	 */
	public function is_valid( string $postcode, string $country ): bool {
		$postcode = $this->normalise( $postcode, $country );

		if ( '' === $postcode ) {
			return false;
		}

		$profile = CountryProfile::get( $country );

		return 1 === preg_match( $profile['pattern'], $postcode );
	}

	/**
	 * Normalises a postcode: trimmed, upper case, single spaces.
	 *
	 * @param string $postcode Raw postcode.
	 * @param string $country  ISO country code.
	 */
	public function normalise( string $postcode, string $country = '' ): string {
		$postcode = strtoupper( trim( $postcode ) );
		$postcode = (string) preg_replace( '/\s+/', ' ', $postcode );

		// India, Germany and other numeric-only formats never contain spaces.
		if ( in_array( strtoupper( $country ), [ 'IN', 'DE', 'FR', 'IT', 'ES', 'AT', 'BE', 'CH', 'DK', 'NO', 'AU', 'NZ', 'FI', 'SG', 'ZA' ], true ) ) {
			$postcode = (string) preg_replace( '/\s+/', '', $postcode );
		}

		return $postcode;
	}

	/**
	 * Human readable label for a country's postal code field.
	 *
	 * @param string $country ISO country code.
	 */
	public function label( string $country ): string {
		return (string) CountryProfile::get( $country )['label'];
	}

	/**
	 * Example postcode used as the field placeholder.
	 *
	 * @param string $country ISO country code.
	 */
	public function example( string $country ): string {
		return (string) CountryProfile::get( $country )['example'];
	}

	/**
	 * Returns a validation error message, or an empty string when valid.
	 *
	 * @param string $postcode Raw postcode.
	 * @param string $country  ISO country code.
	 */
	public function error_for( string $postcode, string $country ): string {
		if ( $this->is_valid( $postcode, $country ) ) {
			return '';
		}

		$profile = CountryProfile::get( $country );

		if ( '' !== $profile['example'] ) {
			return sprintf(
				/* translators: 1: postcode field label, 2: example postcode. */
				__( 'Please check the %1$s — it does not look right for this country (example: %2$s).', 'bhc-commerce-core' ),
				$profile['label'],
				$profile['example']
			);
		}

		return sprintf(
			/* translators: %s: postcode field label. */
			__( 'Please enter a valid %s.', 'bhc-commerce-core' ),
			$profile['label']
		);
	}
}
