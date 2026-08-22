<?php
/**
 * WebSite schema.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\SEO\Schema;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\SEO\BrandProfile;

/**
 * Declares the site and its search action.
 */
final class WebSiteSchema implements SchemaPieceInterface {

	/**
	 * Constructor.
	 *
	 * @param BrandProfile       $brand        Brand profile.
	 * @param OrganizationSchema $organization Organization piece, for the publisher reference.
	 */
	public function __construct( private BrandProfile $brand, private OrganizationSchema $organization ) {}

	/**
	 * Stable node id.
	 */
	public function id(): string {
		return $this->brand->canonical_host() . '/#website';
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
		$host = $this->brand->canonical_host();

		return [
			[
				'@type'           => 'WebSite',
				'@id'             => $this->id(),
				'url'             => $host . '/',
				'name'            => $this->brand->name(),
				'description'     => $this->brand->tagline(),
				'inLanguage'      => get_bloginfo( 'language' ),
				'publisher'       => [ '@id' => $this->organization->id() ],
				'potentialAction' => [
					[
						'@type'       => 'SearchAction',
						'target'      => [
							'@type'       => 'EntryPoint',
							'urlTemplate' => $host . '/?s={search_term_string}&post_type=product',
						],
						'query-input' => 'required name=search_term_string',
					],
				],
			],
		];
	}
}
