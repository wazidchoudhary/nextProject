<?php
/**
 * Per-country checkout and shipping data.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Checkout;

defined( 'ABSPATH' ) || exit;

/**
 * Reference data for the markets the store ships to.
 *
 * One table drives four things: the postcode label a customer sees, the
 * postcode pattern we validate against, the dial code prefilled next to the
 * phone field, and the transit window quoted by the delivery estimator.
 * Keeping them together stops the four from drifting apart.
 *
 * Patterns are intentionally permissive — they reject obvious mistakes (a UK
 * postcode typed into a US order) without blocking a valid address the pattern
 * has not seen. Address correctness is ultimately the carrier's business.
 */
final class CountryProfile {

	/**
	 * Country profiles keyed by ISO 3166-1 alpha-2 code.
	 *
	 * @return array<string, array{label:string, pattern:string, example:string, dial:string, transit:array{0:int,1:int}, zone:string}>
	 */
	public static function all(): array {
		$profiles = [
			'US' => [
				'label'   => 'ZIP code',
				'pattern' => '/^\d{5}(-\d{4})?$/',
				'example' => '97205',
				'dial'    => '+1',
				'transit' => [ 6, 9 ],
				'zone'    => 'north-america',
			],
			'CA' => [
				'label'   => 'Postal code',
				'pattern' => '/^[A-Z]\d[A-Z] ?\d[A-Z]\d$/i',
				'example' => 'K1A 0B1',
				'dial'    => '+1',
				'transit' => [ 7, 11 ],
				'zone'    => 'north-america',
			],
			'GB' => [
				'label'   => 'Postcode',
				'pattern' => '/^[A-Z]{1,2}\d[A-Z\d]? ?\d[A-Z]{2}$/i',
				'example' => 'SW1A 1AA',
				'dial'    => '+44',
				'transit' => [ 5, 8 ],
				'zone'    => 'uk',
			],
			'IE' => [
				'label'   => 'Eircode',
				'pattern' => '/^[A-Z]\d{2} ?[A-Z\d]{4}$/i',
				'example' => 'D02 AF30',
				'dial'    => '+353',
				'transit' => [ 6, 9 ],
				'zone'    => 'europe',
			],
			'DE' => [
				'label'   => 'Postleitzahl',
				'pattern' => '/^\d{5}$/',
				'example' => '10115',
				'dial'    => '+49',
				'transit' => [ 6, 9 ],
				'zone'    => 'europe',
			],
			'FR' => [
				'label'   => 'Code postal',
				'pattern' => '/^\d{5}$/',
				'example' => '75008',
				'dial'    => '+33',
				'transit' => [ 6, 9 ],
				'zone'    => 'europe',
			],
			'IT' => [
				'label'   => 'CAP',
				'pattern' => '/^\d{5}$/',
				'example' => '00184',
				'dial'    => '+39',
				'transit' => [ 7, 10 ],
				'zone'    => 'europe',
			],
			'ES' => [
				'label'   => 'Código postal',
				'pattern' => '/^\d{5}$/',
				'example' => '28013',
				'dial'    => '+34',
				'transit' => [ 7, 10 ],
				'zone'    => 'europe',
			],
			'NL' => [
				'label'   => 'Postcode',
				'pattern' => '/^\d{4} ?[A-Z]{2}$/i',
				'example' => '1012 AB',
				'dial'    => '+31',
				'transit' => [ 6, 9 ],
				'zone'    => 'europe',
			],
			'BE' => [
				'label'   => 'Postcode',
				'pattern' => '/^\d{4}$/',
				'example' => '1000',
				'dial'    => '+32',
				'transit' => [ 6, 9 ],
				'zone'    => 'europe',
			],
			'SE' => [
				'label'   => 'Postnummer',
				'pattern' => '/^\d{3} ?\d{2}$/',
				'example' => '111 29',
				'dial'    => '+46',
				'transit' => [ 7, 10 ],
				'zone'    => 'europe',
			],
			'NO' => [
				'label'   => 'Postnummer',
				'pattern' => '/^\d{4}$/',
				'example' => '0150',
				'dial'    => '+47',
				'transit' => [ 7, 11 ],
				'zone'    => 'europe',
			],
			'DK' => [
				'label'   => 'Postnummer',
				'pattern' => '/^\d{4}$/',
				'example' => '1050',
				'dial'    => '+45',
				'transit' => [ 6, 9 ],
				'zone'    => 'europe',
			],
			'FI' => [
				'label'   => 'Postinumero',
				'pattern' => '/^\d{5}$/',
				'example' => '00100',
				'dial'    => '+358',
				'transit' => [ 7, 11 ],
				'zone'    => 'europe',
			],
			'PL' => [
				'label'   => 'Kod pocztowy',
				'pattern' => '/^\d{2}-\d{3}$/',
				'example' => '00-001',
				'dial'    => '+48',
				'transit' => [ 7, 10 ],
				'zone'    => 'europe',
			],
			'AT' => [
				'label'   => 'Postleitzahl',
				'pattern' => '/^\d{4}$/',
				'example' => '1010',
				'dial'    => '+43',
				'transit' => [ 6, 9 ],
				'zone'    => 'europe',
			],
			'CH' => [
				'label'   => 'Postleitzahl',
				'pattern' => '/^\d{4}$/',
				'example' => '8001',
				'dial'    => '+41',
				'transit' => [ 7, 10 ],
				'zone'    => 'europe',
			],
			'AU' => [
				'label'   => 'Postcode',
				'pattern' => '/^\d{4}$/',
				'example' => '3000',
				'dial'    => '+61',
				'transit' => [ 7, 12 ],
				'zone'    => 'oceania',
			],
			'NZ' => [
				'label'   => 'Postcode',
				'pattern' => '/^\d{4}$/',
				'example' => '6011',
				'dial'    => '+64',
				'transit' => [ 8, 13 ],
				'zone'    => 'oceania',
			],
			'IN' => [
				'label'   => 'PIN code',
				'pattern' => '/^[1-9]\d{5}$/',
				'example' => '226010',
				'dial'    => '+91',
				'transit' => [ 2, 5 ],
				'zone'    => 'domestic',
			],
			'JP' => [
				'label'   => 'Postal code',
				'pattern' => '/^\d{3}-?\d{4}$/',
				'example' => '100-0001',
				'dial'    => '+81',
				'transit' => [ 6, 10 ],
				'zone'    => 'asia',
			],
			'SG' => [
				'label'   => 'Postal code',
				'pattern' => '/^\d{6}$/',
				'example' => '018956',
				'dial'    => '+65',
				'transit' => [ 4, 7 ],
				'zone'    => 'asia',
			],
			'AE' => [
				'label'    => 'PO Box / area',
				// The UAE has no postal code system. The field stays, because
				// couriers use the PO box or area, but demanding one is asking
				// a customer for something their country does not issue.
				'optional' => true,
				'pattern'  => '/^.{0,12}$/',
				'example'  => '00000',
				'dial'     => '+971',
				'transit'  => [ 4, 7 ],
				'zone'     => 'middle-east',
			],
			'ZA' => [
				'label'   => 'Postal code',
				'pattern' => '/^\d{4}$/',
				'example' => '8001',
				'dial'    => '+27',
				'transit' => [ 8, 14 ],
				'zone'    => 'africa',
			],
		];

		/**
		 * Filters the country profile table.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, array<string, mixed>> $profiles Profiles keyed by country code.
		 */
		return (array) apply_filters( 'bhc_country_profiles', $profiles );
	}

	/**
	 * Returns a single profile, or a permissive default.
	 *
	 * @param string $country ISO country code.
	 *
	 * @return array{label:string, pattern:string, example:string, dial:string, transit:array{0:int,1:int}, zone:string}
	 */
	public static function get( string $country ): array {
		$country = strtoupper( trim( $country ) );

		$profiles = self::all();

		if ( isset( $profiles[ $country ] ) ) {
			return $profiles[ $country ];
		}

		return [
			'label'   => __( 'Postal code', 'bhc-commerce-core' ),
			'pattern' => '/^.{2,12}$/',
			'example' => '',
			'dial'    => '',
			'transit' => [ 9, 16 ],
			'zone'    => 'rest-of-world',
		];
	}

	/**
	 * Whether the store ships to a country (WooCommerce settings decide).
	 *
	 * @param string $country ISO country code.
	 */
	public static function is_supported( string $country ): bool {
		$country = strtoupper( trim( $country ) );

		if ( 2 !== strlen( $country ) ) {
			return false;
		}

		if ( ! function_exists( 'WC' ) || null === WC()->countries ) {
			return isset( self::all()[ $country ] );
		}

		$allowed = WC()->countries->get_shipping_countries();

		return isset( $allowed[ $country ] );
	}
}
