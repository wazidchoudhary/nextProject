<?php
/**
 * Brand identity used across SEO output.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\SEO;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Support\Options;

/**
 * Single source of truth for how the brand is named in metadata.
 *
 * Everything customer facing — titles, Open Graph, schema, breadcrumbs — says
 * "Bone Horn Crafts". The manufacturing entity behind the brand appears only
 * as the footer credit and as the `manufacturer` property of the Organization
 * and Product schema, where it is factually the manufacturer.
 */
final class BrandProfile {

	public const NAME    = 'Bone Horn Crafts';
	public const TAGLINE = 'Natural Materials, Handcrafted for Makers';

	/**
	 * Constructor.
	 *
	 * @param Options $options Settings.
	 */
	public function __construct( private Options $options ) {}

	/**
	 * Brand name.
	 */
	public function name(): string {
		return self::NAME;
	}

	/**
	 * Brand tagline.
	 */
	public function tagline(): string {
		return self::TAGLINE;
	}

	/**
	 * Legal manufacturing entity.
	 */
	public function legal_entity(): string {
		return $this->options->string( 'legal_entity' );
	}

	/**
	 * Canonical site host, without a trailing slash.
	 *
	 * Production runs on the canonical host, so `home_url()` is correct. The
	 * configured host is only used to normalise absolute URLs emitted for
	 * social and schema consumers when the site is served from a staging
	 * domain, which stops staging URLs leaking into shared metadata.
	 */
	public function canonical_host(): string {
		$configured = untrailingslashit( $this->options->string( 'canonical_host' ) );

		/**
		 * Filters whether absolute SEO URLs are rewritten to the canonical host.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $force Defaults to true outside production environments.
		 */
		$force = (bool) apply_filters( 'bhc_force_canonical_host', 'production' !== wp_get_environment_type() );

		return ( $force && '' !== $configured ) ? $configured : untrailingslashit( home_url() );
	}

	/**
	 * Rewrites a URL onto the canonical host.
	 *
	 * @param string $url Absolute URL.
	 */
	public function canonicalise( string $url ): string {
		if ( '' === $url ) {
			return '';
		}

		$host = $this->canonical_host();
		$home = untrailingslashit( home_url() );

		if ( $host === $home ) {
			return $url;
		}

		return str_replace( $home, $host, $url );
	}

	/**
	 * Contact email published in schema.
	 */
	public function email(): string {
		return $this->options->string( 'organization_email' );
	}

	/**
	 * Contact phone published in schema.
	 */
	public function phone(): string {
		return $this->options->string( 'organization_phone' );
	}

	/**
	 * Twitter/X handle.
	 */
	public function social_handle(): string {
		return $this->options->string( 'twitter_handle' );
	}

	/**
	 * Social sharing image URL, falling back to the site icon.
	 */
	public function social_image(): string {
		$image_id = $this->options->int( 'social_image_id' );

		if ( $image_id > 0 ) {
			$src = wp_get_attachment_image_url( $image_id, 'full' );

			if ( is_string( $src ) ) {
				return $this->canonicalise( $src );
			}
		}

		$icon = get_site_icon_url( 512 );

		return is_string( $icon ) ? $this->canonicalise( $icon ) : '';
	}

	/**
	 * Public profile URLs used for `sameAs`.
	 *
	 * @return string[]
	 */
	public function same_as(): array {
		/**
		 * Filters the brand's public profile URLs.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $profiles Profile URLs.
		 */
		return array_values(
			array_filter(
				(array) apply_filters(
					'bhc_brand_profiles',
					[
						'https://www.instagram.com/bonehorncrafts/',
						'https://www.pinterest.com/bonehorncrafts/',
						'https://www.youtube.com/@bonehorncrafts',
					]
				)
			)
		);
	}
}
