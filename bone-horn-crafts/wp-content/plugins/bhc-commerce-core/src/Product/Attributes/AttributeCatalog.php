<?php
/**
 * Craft attribute catalogue.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Product\Attributes;

defined( 'ABSPATH' ) || exit;

/**
 * Declarative definition of the store's global product attributes.
 *
 * Keeping the taxonomy in data rather than in an installer script means the
 * same definition drives creation, the admin filters, the faceted search
 * facets and the demo seeder — there is exactly one list to change.
 */
final class AttributeCatalog {

	public const MATERIAL     = 'material';
	public const FINISH       = 'finish';
	public const APPLICATION  = 'application';
	public const COLOUR       = 'colour';
	public const SIZE         = 'size';
	public const PRODUCT_TYPE = 'product-type';

	/**
	 * Attribute definitions keyed by attribute slug (without the `pa_` prefix).
	 *
	 * @return array<string, array{label:string, order_by:string, has_archives:bool, facet:bool, terms:array<string,string>}>
	 */
	public static function all(): array {
		$definitions = [
			self::MATERIAL     => [
				'label'        => __( 'Material', 'bhc-commerce-core' ),
				'order_by'     => 'name',
				'has_archives' => true,
				'facet'        => true,
				'terms'        => [
					'camel-bone'         => __( 'Camel Bone', 'bhc-commerce-core' ),
					'cattle-bone'        => __( 'Cattle Bone', 'bhc-commerce-core' ),
					'water-buffalo-horn' => __( 'Water Buffalo Horn', 'bhc-commerce-core' ),
					'rams-horn'          => __( 'Rams Horn', 'bhc-commerce-core' ),
					'stabilized-wood'    => __( 'Stabilized Wood', 'bhc-commerce-core' ),
					'hardwood'           => __( 'Hardwood', 'bhc-commerce-core' ),
					'acrylic'            => __( 'Acrylic', 'bhc-commerce-core' ),
					'brass-pin-stock'    => __( 'Brass Pin Stock', 'bhc-commerce-core' ),
				],
			],
			self::FINISH       => [
				'label'        => __( 'Finish', 'bhc-commerce-core' ),
				'order_by'     => 'name',
				'has_archives' => false,
				'facet'        => true,
				'terms'        => [
					'smooth'           => __( 'Smooth', 'bhc-commerce-core' ),
					'jigged'           => __( 'Jigged', 'bhc-commerce-core' ),
					'dyed-stabilized'  => __( 'Dyed &amp; Stabilized', 'bhc-commerce-core' ),
					'bark-natural-edge' => __( 'Bark / Natural Edge', 'bhc-commerce-core' ),
					'natural'          => __( 'Natural', 'bhc-commerce-core' ),
					'hand-polished'    => __( 'Hand Polished', 'bhc-commerce-core' ),
					'sanded-400-grit'  => __( 'Sanded to 400 Grit', 'bhc-commerce-core' ),
				],
			],
			self::APPLICATION  => [
				'label'        => __( 'Application', 'bhc-commerce-core' ),
				'order_by'     => 'name',
				'has_archives' => true,
				'facet'        => true,
				'terms'        => [
					'knife-making'   => __( 'Knife Making', 'bhc-commerce-core' ),
					'guitar-luthier' => __( 'Guitar &amp; Luthier', 'bhc-commerce-core' ),
					'pen-turning'    => __( 'Pen Turning', 'bhc-commerce-core' ),
					'leather-work'   => __( 'Leather Work', 'bhc-commerce-core' ),
					'home-table'     => __( 'Home &amp; Table', 'bhc-commerce-core' ),
					'jewellery'      => __( 'Jewellery &amp; Beading', 'bhc-commerce-core' ),
				],
			],
			self::COLOUR       => [
				'label'        => __( 'Colour', 'bhc-commerce-core' ),
				'order_by'     => 'name',
				'has_archives' => false,
				'facet'        => true,
				'terms'        => [
					'natural-white' => __( 'Natural White', 'bhc-commerce-core' ),
					'cream'         => __( 'Cream', 'bhc-commerce-core' ),
					'amber'         => __( 'Amber', 'bhc-commerce-core' ),
					'honey'         => __( 'Honey', 'bhc-commerce-core' ),
					'black'         => __( 'Black', 'bhc-commerce-core' ),
					'charcoal'      => __( 'Charcoal', 'bhc-commerce-core' ),
					'two-tone'      => __( 'Two Tone', 'bhc-commerce-core' ),
					'marbled'       => __( 'Marbled', 'bhc-commerce-core' ),
					'forest-green'  => __( 'Forest Green', 'bhc-commerce-core' ),
					'indigo'        => __( 'Indigo', 'bhc-commerce-core' ),
				],
			],
			self::SIZE         => [
				'label'        => __( 'Size', 'bhc-commerce-core' ),
				'order_by'     => 'menu_order',
				'has_archives' => false,
				'facet'        => true,
				'terms'        => [
					'4-5x1-25x0-25' => __( '4.5 x 1.25 x 0.25 in', 'bhc-commerce-core' ),
					'5x1-5x0-3'     => __( '5 x 1.5 x 0.30 in', 'bhc-commerce-core' ),
					'5x1-5x0-375'   => __( '5 x 1.5 x 0.375 in', 'bhc-commerce-core' ),
					'6x2x0-375'     => __( '6 x 2 x 0.375 in', 'bhc-commerce-core' ),
					'blank-6x1x1'   => __( 'Blank 6 x 1 x 1 in', 'bhc-commerce-core' ),
					'blank-5x0-75'  => __( 'Blank 5 x 0.75 x 0.75 in', 'bhc-commerce-core' ),
					'nut-blank'     => __( 'Nut Blank 45 x 6 x 9 mm', 'bhc-commerce-core' ),
					'saddle-blank'  => __( 'Saddle Blank 80 x 3 x 10 mm', 'bhc-commerce-core' ),
					'one-size'      => __( 'One Size', 'bhc-commerce-core' ),
				],
			],
			self::PRODUCT_TYPE => [
				'label'        => __( 'Product Type', 'bhc-commerce-core' ),
				'order_by'     => 'name',
				'has_archives' => false,
				'facet'        => true,
				'terms'        => [
					'knife-scales'     => __( 'Knife Scales', 'bhc-commerce-core' ),
					'nut-saddle'       => __( 'Nut &amp; Saddle Blank', 'bhc-commerce-core' ),
					'bridge-pin-blank' => __( 'Bridge Pin Blank', 'bhc-commerce-core' ),
					'pick-blank'       => __( 'Pick Blank', 'bhc-commerce-core' ),
					'pen-blank'        => __( 'Pen Blank', 'bhc-commerce-core' ),
					'comb'             => __( 'Comb', 'bhc-commerce-core' ),
					'bead'             => __( 'Bead', 'bhc-commerce-core' ),
					'drinking-horn'    => __( 'Drinking Horn', 'bhc-commerce-core' ),
					'horn-mug'         => __( 'Horn Mug', 'bhc-commerce-core' ),
					'bone-folder'      => __( 'Bone Folder', 'bhc-commerce-core' ),
					'shoe-horn'        => __( 'Shoe Horn', 'bhc-commerce-core' ),
					'cutlery'          => __( 'Cutlery', 'bhc-commerce-core' ),
					'plate-spacer'     => __( 'Plate &amp; Spacer', 'bhc-commerce-core' ),
					'button'           => __( 'Button', 'bhc-commerce-core' ),
					'dice'             => __( 'Dice', 'bhc-commerce-core' ),
				],
			],
		];

		/**
		 * Filters the global attribute catalogue.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, array<string, mixed>> $definitions Attribute definitions.
		 */
		return (array) apply_filters( 'bhc_attribute_catalog', $definitions );
	}

	/**
	 * Attribute slugs that are exposed as storefront facets, in display order.
	 *
	 * @return string[]
	 */
	public static function facet_slugs(): array {
		$facets = [];

		foreach ( self::all() as $slug => $definition ) {
			if ( ! empty( $definition['facet'] ) ) {
				$facets[] = $slug;
			}
		}

		return $facets;
	}

	/**
	 * Returns the taxonomy name for an attribute slug.
	 *
	 * @param string $slug Attribute slug without prefix.
	 */
	public static function taxonomy( string $slug ): string {
		return function_exists( 'wc_attribute_taxonomy_name' )
			? wc_attribute_taxonomy_name( $slug )
			: 'pa_' . $slug;
	}

	/**
	 * Returns the label for an attribute slug.
	 *
	 * @param string $slug Attribute slug without prefix.
	 */
	public static function label( string $slug ): string {
		$definition = self::all()[ $slug ] ?? null;

		return null === $definition ? ucfirst( $slug ) : (string) $definition['label'];
	}
}
