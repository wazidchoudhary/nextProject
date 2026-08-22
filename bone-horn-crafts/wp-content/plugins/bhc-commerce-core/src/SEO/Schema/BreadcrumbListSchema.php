<?php
/**
 * BreadcrumbList schema.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\SEO\Schema;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\SEO\BrandProfile;
use BoneHornCrafts\Core\SEO\BreadcrumbService;

/**
 * Serialises the shared breadcrumb trail.
 */
final class BreadcrumbListSchema implements SchemaPieceInterface {

	/**
	 * Constructor.
	 *
	 * @param BreadcrumbService $breadcrumbs Breadcrumb builder.
	 * @param BrandProfile      $brand       Brand profile.
	 */
	public function __construct( private BreadcrumbService $breadcrumbs, private BrandProfile $brand ) {}

	/**
	 * {@inheritDoc}
	 */
	public function is_needed(): bool {
		return count( $this->breadcrumbs->trail() ) > 1 && ! is_front_page();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function build(): array {
		$items = [];
		$position = 0;

		foreach ( $this->breadcrumbs->trail() as $crumb ) {
			++$position;

			$item = [
				'@type'    => 'ListItem',
				'position' => $position,
				'name'     => wp_strip_all_tags( (string) $crumb['label'] ),
			];

			if ( '' !== (string) $crumb['url'] ) {
				$item['item'] = $this->brand->canonicalise( (string) $crumb['url'] );
			}

			$items[] = $item;
		}

		return [
			[
				'@type'           => 'BreadcrumbList',
				'@id'             => $this->current_url() . '#breadcrumb',
				'itemListElement' => $items,
			],
		];
	}

	/**
	 * Canonical URL of the current document, without the query string.
	 */
	private function current_url(): string {
		$path = isset( $_SERVER['REQUEST_URI'] )
			? (string) wp_parse_url( esc_url_raw( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH )
			: '/';

		return $this->brand->canonicalise( home_url( '' === $path ? '/' : $path ) );
	}
}
