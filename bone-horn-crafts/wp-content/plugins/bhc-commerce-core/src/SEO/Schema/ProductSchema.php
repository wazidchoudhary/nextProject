<?php
/**
 * Product schema.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\SEO\Schema;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Pricing\PriceFormatter;
use BoneHornCrafts\Core\Product\Attributes\AttributeCatalog;
use BoneHornCrafts\Core\Product\ProductMeta;
use BoneHornCrafts\Core\SEO\BrandProfile;
use BoneHornCrafts\Core\Support\Str;
use WC_Product;

/**
 * Emits `Product` structured data for the product page.
 *
 * Notes on correctness, because this is where stores usually get flagged:
 *
 * * `aggregateRating` is emitted **only** when the product actually has
 *   approved reviews with a rating. Inventing ratings — or emitting a rating
 *   node with a zero count — is a policy violation, so the node is simply
 *   absent when there is nothing to report. On this demo store the reviews are
 *   part of the clearly labelled fictional demo dataset.
 * * Variable products use an `AggregateOffer` with the real low/high prices
 *   instead of pretending a range is a single price.
 * * `brand` is Bone Horn Crafts; `manufacturer` is the production company.
 */
final class ProductSchema implements SchemaPieceInterface {

	/**
	 * Constructor.
	 *
	 * @param BrandProfile       $brand        Brand profile.
	 * @param PriceFormatter     $prices       Price helper.
	 * @param OrganizationSchema $organization Organization piece.
	 */
	public function __construct(
		private BrandProfile $brand,
		private PriceFormatter $prices,
		private OrganizationSchema $organization
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function is_needed(): bool {
		return function_exists( 'is_product' ) && is_product() && wc_get_product( get_queried_object_id() ) instanceof WC_Product;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function build(): array {
		$product = wc_get_product( get_queried_object_id() );

		if ( ! $product instanceof WC_Product ) {
			return [];
		}

		$permalink = $this->brand->canonicalise( (string) $product->get_permalink() );

		$node = [
			'@type'       => 'Product',
			'@id'         => $permalink . '#product',
			'name'        => $product->get_name(),
			'url'         => $permalink,
			'description' => Str::truncate(
				wp_strip_all_tags( strip_shortcodes( $product->get_short_description() ?: $product->get_description() ) ),
				320,
				''
			),
			'brand'       => [
				'@type' => 'Brand',
				'name'  => $this->brand->name(),
			],
			'seller'      => [ '@id' => $this->organization->id() ],
		];

		$sku = $product->get_sku();

		if ( '' !== $sku ) {
			$node['sku']       = $sku;
			$node['mpn']       = $sku;
			$node['productID'] = $sku;
		}

		$manufacturer = $this->brand->legal_entity();

		if ( '' !== $manufacturer ) {
			$node['manufacturer'] = [
				'@type' => 'Organization',
				'name'  => $manufacturer,
			];
		}

		$images = $this->images( $product );

		if ( [] !== $images ) {
			$node['image'] = $images;
		}

		$node['offers'] = $this->offers( $product, $permalink );

		$properties = $this->additional_properties( $product );

		if ( [] !== $properties ) {
			$node['additionalProperty'] = $properties;
		}

		$material = $this->attribute_value( $product, AttributeCatalog::MATERIAL );

		if ( '' !== $material ) {
			$node['material'] = $material;
		}

		$colour = $this->attribute_value( $product, AttributeCatalog::COLOUR );

		if ( '' !== $colour ) {
			$node['color'] = $colour;
		}

		$weight = (float) $product->get_weight();

		if ( $weight > 0 ) {
			$node['weight'] = [
				'@type'    => 'QuantitativeValue',
				'value'    => $weight,
				'unitCode' => 'GRM' === strtoupper( (string) get_option( 'woocommerce_weight_unit' ) ) ? 'GRM' : strtoupper( (string) get_option( 'woocommerce_weight_unit', 'kg' ) ),
			];
		}

		$rating = $this->aggregate_rating( $product );

		if ( [] !== $rating ) {
			$node['aggregateRating'] = $rating;
		}

		$origin = ProductMeta::origin_country( $product );

		if ( '' !== $origin ) {
			$node['countryOfOrigin'] = $origin;
		}

		return [ $node ];
	}

	/**
	 * Builds the offer node(s).
	 *
	 * @param WC_Product $product   Product.
	 * @param string     $permalink Canonical product URL.
	 *
	 * @return array<string, mixed>
	 */
	private function offers( WC_Product $product, string $permalink ): array {
		$currency     = get_woocommerce_currency();
		$availability = $product->is_in_stock()
			? 'https://schema.org/InStock'
			: 'https://schema.org/OutOfStock';

		if ( $product->is_type( 'variable' ) && $this->prices->has_price_range( $product ) ) {
			return [
				'@type'         => 'AggregateOffer',
				'url'           => $permalink,
				'priceCurrency' => $currency,
				'lowPrice'      => $this->format_price( $this->prices->lowest_price( $product ) ),
				'highPrice'     => $this->format_price( $this->prices->highest_price( $product ) ),
				'offerCount'    => count( $product->get_children() ),
				'availability'  => $availability,
				'seller'        => [ '@id' => $this->organization->id() ],
			];
		}

		$offer = [
			'@type'           => 'Offer',
			'url'             => $permalink,
			'priceCurrency'   => $currency,
			'price'           => $this->format_price( (float) wc_get_price_to_display( $product ) ),
			'availability'    => $availability,
			'itemCondition'   => 'https://schema.org/NewCondition',
			'seller'          => [ '@id' => $this->organization->id() ],
		];

		$sale_end = $product->get_date_on_sale_to();

		if ( null !== $sale_end ) {
			$offer['priceValidUntil'] = gmdate( 'Y-m-d', $sale_end->getTimestamp() );
		}

		return $offer;
	}

	/**
	 * Builds the aggregateRating node when real review data exists.
	 *
	 * @param WC_Product $product Product.
	 *
	 * @return array<string, mixed>
	 */
	private function aggregate_rating( WC_Product $product ): array {
		$count   = (int) $product->get_rating_count();
		$average = (float) $product->get_average_rating();

		if ( $count < 1 || $average <= 0.0 ) {
			return [];
		}

		return [
			'@type'       => 'AggregateRating',
			'ratingValue' => round( $average, 1 ),
			'reviewCount' => $count,
			'bestRating'  => 5,
			'worstRating' => 1,
		];
	}

	/**
	 * Collects gallery image URLs.
	 *
	 * @param WC_Product $product Product.
	 *
	 * @return string[]
	 */
	private function images( WC_Product $product ): array {
		$ids = array_merge( [ (int) $product->get_image_id() ], array_map( 'absint', $product->get_gallery_image_ids() ) );
		$ids = array_values( array_unique( array_filter( $ids ) ) );

		$urls = [];

		foreach ( array_slice( $ids, 0, 6 ) as $id ) {
			$src = wp_get_attachment_image_url( $id, 'large' );

			if ( is_string( $src ) ) {
				$urls[] = $this->brand->canonicalise( $src );
			}
		}

		return $urls;
	}

	/**
	 * Maps craft attributes onto `additionalProperty` entries.
	 *
	 * @param WC_Product $product Product.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function additional_properties( WC_Product $product ): array {
		$properties = [];

		foreach ( [ AttributeCatalog::FINISH, AttributeCatalog::APPLICATION, AttributeCatalog::SIZE, AttributeCatalog::PRODUCT_TYPE ] as $slug ) {
			$value = $this->attribute_value( $product, $slug );

			if ( '' === $value ) {
				continue;
			}

			$properties[] = [
				'@type' => 'PropertyValue',
				'name'  => AttributeCatalog::label( $slug ),
				'value' => $value,
			];
		}

		$unit = ProductMeta::unit_of_sale( $product );

		if ( '' !== $unit ) {
			$properties[] = [
				'@type' => 'PropertyValue',
				'name'  => __( 'Unit of sale', 'bhc-commerce-core' ),
				'value' => $unit,
			];
		}

		return $properties;
	}

	/**
	 * Returns a comma separated attribute value.
	 *
	 * @param WC_Product $product Product.
	 * @param string     $slug    Attribute slug.
	 */
	private function attribute_value( WC_Product $product, string $slug ): string {
		$value = $product->get_attribute( AttributeCatalog::taxonomy( $slug ) );

		return is_string( $value ) ? trim( $value ) : '';
	}

	/**
	 * Formats a price with the store's decimal precision.
	 *
	 * @param float $price Price.
	 */
	private function format_price( float $price ): string {
		return number_format( $price, wc_get_price_decimals(), '.', '' );
	}
}
