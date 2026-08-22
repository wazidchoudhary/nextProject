<?php
/**
 * Article schema.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\SEO\Schema;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\SEO\BrandProfile;
use BoneHornCrafts\Core\Support\Str;

/**
 * Emits `Article` data for workshop journal posts.
 */
final class ArticleSchema implements SchemaPieceInterface {

	/**
	 * Constructor.
	 *
	 * @param BrandProfile       $brand        Brand profile.
	 * @param OrganizationSchema $organization Organization piece.
	 */
	public function __construct( private BrandProfile $brand, private OrganizationSchema $organization ) {}

	/**
	 * {@inheritDoc}
	 */
	public function is_needed(): bool {
		return is_singular( 'post' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function build(): array {
		$post_id = get_queried_object_id();

		if ( $post_id <= 0 ) {
			return [];
		}

		$permalink = $this->brand->canonicalise( (string) get_permalink( $post_id ) );

		$node = [
			'@type'            => 'Article',
			'@id'              => $permalink . '#article',
			'headline'         => Str::truncate( (string) get_the_title( $post_id ), 110, '' ),
			'description'      => Str::truncate(
				wp_strip_all_tags( strip_shortcodes( (string) get_post_field( 'post_excerpt', $post_id ) ?: (string) get_post_field( 'post_content', $post_id ) ) ),
				200,
				''
			),
			'url'              => $permalink,
			'datePublished'    => (string) get_the_date( 'c', $post_id ),
			'dateModified'     => (string) get_the_modified_date( 'c', $post_id ),
			'inLanguage'       => get_bloginfo( 'language' ),
			'publisher'        => [ '@id' => $this->organization->id() ],
			'mainEntityOfPage' => $permalink,
			'author'           => [
				'@type' => 'Organization',
				'name'  => $this->brand->name(),
			],
		];

		$thumbnail_id = (int) get_post_thumbnail_id( $post_id );

		if ( $thumbnail_id > 0 ) {
			$src = wp_get_attachment_image_url( $thumbnail_id, 'large' );

			if ( is_string( $src ) ) {
				$node['image'] = [ $this->brand->canonicalise( $src ) ];
			}
		}

		return [ $node ];
	}
}
