<?php
/**
 * Storefront shortcodes.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Frontend;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Contracts\HookableInterface;
use BoneHornCrafts\Core\Demo\ContentLibrary;
use BoneHornCrafts\Core\Product\ProductRepository;
use BoneHornCrafts\Core\Support\Template;

/**
 * Content-editor entry points into the merchandising services.
 *
 * Shortcodes are the right seam here: the same cached, bounded repository
 * queries the theme uses become available to any page without giving editors a
 * way to write an unbounded query.
 */
final class Shortcodes implements HookableInterface {

	/**
	 * Constructor.
	 *
	 * @param ProductRepository $products Product read model.
	 * @param Template          $template Template renderer.
	 */
	public function __construct( private ProductRepository $products, private Template $template ) {}

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_shortcode( 'bhc_product_grid', [ $this, 'product_grid' ] );
		add_shortcode( 'bhc_value_props', [ $this, 'value_props' ] );
	}

	/**
	 * `[bhc_product_grid source="new" limit="8" columns="4"]`.
	 *
	 * @param array<string, string>|string $atts Attributes.
	 */
	public function product_grid( $atts = [] ): string {
		$atts = shortcode_atts(
			[
				'source'   => 'new',
				'category' => '',
				'tag'      => '',
				'limit'    => 8,
				'columns'  => 4,
				'title'    => '',
			],
			is_array( $atts ) ? $atts : [],
			'bhc_product_grid'
		);

		$limit  = max( 1, min( 24, absint( $atts['limit'] ) ) );
		$source = sanitize_key( (string) $atts['source'] );

		$ids = match ( $source ) {
			'bestsellers' => $this->products->bestseller_ids( $limit ),
			'sale'        => $this->products->on_sale_ids( $limit ),
			'category'    => $this->products->category_ids( (string) $atts['category'], $limit ),
			'tag'         => $this->products->tag_ids( (string) $atts['tag'], $limit ),
			default       => $this->products->new_arrival_ids( $limit ),
		};

		$products = $this->products->hydrate( $ids );

		if ( [] === $products ) {
			return '';
		}

		return $this->template->render(
			'product/grid.php',
			[
				'products' => $products,
				'columns'  => max( 2, min( 4, absint( $atts['columns'] ) ) ),
				'title'    => sanitize_text_field( (string) $atts['title'] ),
			]
		);
	}

	/**
	 * `[bhc_value_props]` — the "why buy here" tiles.
	 */
	public function value_props(): string {
		return $this->template->render(
			'content/value-props.php',
			[ 'items' => ContentLibrary::value_props() ]
		);
	}
}
