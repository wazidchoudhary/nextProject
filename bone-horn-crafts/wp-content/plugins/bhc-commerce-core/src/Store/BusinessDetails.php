<?php
/**
 * Registered business details.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Store;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Support\Options;

/**
 * The single source of truth for where the business actually is.
 *
 * The same postal address has to appear in at least four places — the footer,
 * the contact page, the privacy policy and the Organization JSON-LD — and a
 * search engine treats a mismatch between them as a weaker entity signal, not a
 * typo. Holding it in one object means correcting it once.
 *
 * Values that an administrator can already edit (email, phone) come from
 * `Options`; the rest are defaults that a filter can override, because a
 * postal address is not something a store owner should be able to half-change
 * from a settings screen and leave inconsistent with the schema.
 */
final class BusinessDetails {

	/**
	 * Constructor.
	 *
	 * @param Options $options Plugin settings.
	 */
	public function __construct( private Options $options ) {}

	/**
	 * Trading name.
	 */
	public function name(): string {
		return (string) get_bloginfo( 'name' );
	}

	/**
	 * The company that manufactures, shown as a credit rather than a brand.
	 */
	public function legal_entity(): string {
		return $this->options->string( 'legal_entity' );
	}

	/**
	 * Street address, first line.
	 */
	public function street(): string {
		return $this->value( 'street', 'Khasra No. 535-536, Garima Garden' );
	}

	/**
	 * Town or district.
	 */
	public function locality(): string {
		return $this->value( 'locality', 'Sahibabad, Ghaziabad' );
	}

	/**
	 * State or province.
	 */
	public function region(): string {
		return $this->value( 'region', 'Uttar Pradesh' );
	}

	/**
	 * Postal code.
	 */
	public function postcode(): string {
		return $this->value( 'postcode', '201005' );
	}

	/**
	 * ISO 3166-1 alpha-2 country code.
	 */
	public function country_code(): string {
		return $this->value( 'country_code', 'IN' );
	}

	/**
	 * Country name, for display.
	 */
	public function country(): string {
		return $this->value( 'country', 'India' );
	}

	/**
	 * Contact email.
	 */
	public function email(): string {
		$email = $this->options->string( 'organization_email' );

		return '' !== $email ? $email : 'info@bonehorncrafts.com';
	}

	/**
	 * Contact telephone, formatted for display.
	 */
	public function phone(): string {
		$phone = $this->options->string( 'organization_phone' );

		return '' !== $phone ? $phone : '+91 87007 53517';
	}

	/**
	 * Telephone in `tel:` form.
	 *
	 * Derived from whatever `phone()` resolves to rather than stored a second
	 * time, so an edited number cannot leave the link pointing at the old one.
	 */
	public function phone_href(): string {
		return (string) preg_replace( '/[^0-9+]/', '', $this->phone() );
	}

	/**
	 * The address as display lines, in postal order.
	 *
	 * @return string[]
	 */
	public function address_lines(): array {
		return array_values(
			array_filter(
				[
					$this->street(),
					$this->locality(),
					trim( $this->region() . ' ' . $this->postcode() ),
					$this->country(),
				],
				static fn ( string $line ): bool => '' !== trim( $line )
			)
		);
	}

	/**
	 * The address on one line, for prose and meta tags.
	 */
	public function address_inline(): string {
		return implode( ', ', $this->address_lines() );
	}

	/**
	 * The address as a schema.org `PostalAddress` node.
	 *
	 * @return array<string, string>
	 */
	public function postal_address_schema(): array {
		return [
			'@type'           => 'PostalAddress',
			'streetAddress'   => $this->street(),
			'addressLocality' => $this->locality(),
			'addressRegion'   => $this->region(),
			'postalCode'      => $this->postcode(),
			'addressCountry'  => $this->country_code(),
		];
	}

	/**
	 * Resolves a detail, allowing a site to override it without a code change.
	 *
	 * @param string $part    Detail name.
	 * @param string $default Shipped value.
	 */
	private function value( string $part, string $default ): string {
		/**
		 * Filters a business detail.
		 *
		 * @since 1.0.0
		 *
		 * @param string $default Shipped value.
		 * @param string $part    Which detail was requested.
		 */
		return (string) apply_filters( 'bhc_business_detail', $default, $part );
	}
}
