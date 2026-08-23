<?php
/**
 * Organization schema.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\SEO\Schema;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\SEO\BrandProfile;
use BoneHornCrafts\Core\Store\BusinessDetails;

/**
 * Describes the storefront brand.
 *
 * The customer-facing entity is Bone Horn Crafts. The manufacturing company is
 * declared as `manufacturer` — the one place where naming it is factually
 * correct rather than brand noise.
 */
final class OrganizationSchema implements SchemaPieceInterface {

	/**
	 * Constructor.
	 *
	 * @param BrandProfile    $brand    Brand profile.
	 * @param BusinessDetails $business Registered business details.
	 */
	public function __construct( private BrandProfile $brand, private BusinessDetails $business ) {}

	/**
	 * Stable node id used by other pieces.
	 */
	public function id(): string {
		return $this->brand->canonical_host() . '/#organization';
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_needed(): bool {
		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function build(): array {
		$host  = $this->brand->canonical_host();
		$image = $this->brand->social_image();

		$node = [
			'@type'       => [ 'Organization', 'OnlineStore' ],
			'@id'         => $this->id(),
			'name'        => $this->brand->name(),
			'url'         => $host . '/',
			'slogan'      => $this->brand->tagline(),
			'description' => __( 'Workshop-finished bone, horn and wood materials for knife makers, luthiers, pen turners and leather workers, shipped worldwide.', 'bhc-commerce-core' ),
			'sameAs'      => $this->brand->same_as(),
		];

		if ( '' !== $image ) {
			$node['logo'] = [
				'@type' => 'ImageObject',
				'@id'   => $host . '/#logo',
				'url'   => $image,
			];

			$node['image'] = [ '@id' => $host . '/#logo' ];
		}

		$email = $this->brand->email();
		$phone = $this->brand->phone();

		if ( '' !== $email || '' !== $phone ) {
			$contact = [
				'@type'             => 'ContactPoint',
				'contactType'       => 'customer support',
				'availableLanguage' => [ 'en' ],
			];

			if ( '' !== $email ) {
				$contact['email'] = $email;
			}

			if ( '' !== $phone ) {
				$contact['telephone'] = $phone;
			}

			$node['contactPoint'] = [ $contact ];
		}

		// A postal address is one of the stronger entity signals available to a
		// business that trades internationally, and it was simply absent. It
		// comes from BusinessDetails rather than being written out again here,
		// so the schema and the footer can never disagree.
		$node['address'] = $this->business->postal_address_schema();

		$node['telephone'] = $this->business->phone();
		$node['email']     = $this->business->email();

		$manufacturer = $this->brand->legal_entity();

		if ( '' !== $manufacturer ) {
			$node['parentOrganization'] = [
				'@type' => 'Organization',
				'name'  => $manufacturer,
			];
		}

		return [ $node ];
	}
}
