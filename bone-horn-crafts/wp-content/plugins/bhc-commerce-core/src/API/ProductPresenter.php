<?php
/**
 * Product API representation.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\API;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Pricing\PriceFormatter;
use BoneHornCrafts\Core\Product\Badges\BadgeResolver;
use BoneHornCrafts\Core\Product\ProductMeta;
use WC_Product;

/**
 * Turns a `WC_Product` into the compact payload the storefront JavaScript uses.
 *
 * One presenter for every endpoint means the AJAX filter grid, the
 * recommendation rail and the wishlist drawer all render identical cards, and
 * a field added here appears everywhere at once.
 */
final class ProductPresenter {

	/**
	 * Constructor.
	 *
	 * @param BadgeResolver  $badges Badge resolver.
	 * @param PriceFormatter $prices Price helper.
	 */
	public function __construct( private BadgeResolver $badges, private PriceFormatter $prices ) {}

	/**
	 * Presents a single product.
	 *
	 * @param WC_Product $product Product.
	 *
	 * @return array<string, mixed>
	 */
	public function present( WC_Product $product ): array {
		$image_id = (int) $product->get_image_id();

		$payload = [
			'id'           => $product->get_id(),
			'name'         => $product->get_name(),
			'sku'          => $product->get_sku(),
			'permalink'    => (string) $product->get_permalink(),
			'price_html'   => (string) $product->get_price_html(),
			'price'        => (float) wc_get_price_to_display( $product ),
			'currency'     => get_woocommerce_currency(),
			'on_sale'      => $product->is_on_sale(),
			'discount'     => $this->prices->discount_percentage( $product ),
			'in_stock'     => $product->is_in_stock(),
			'unit_of_sale' => ProductMeta::unit_of_sale( $product ),
			'rating'       => round( (float) $product->get_average_rating(), 2 ),
			'review_count' => (int) $product->get_review_count(),
			'is_variable'  => $product->is_type( 'variable' ),
			'add_to_cart'  => [
				'url'   => (string) $product->add_to_cart_url(),
				'label' => wp_strip_all_tags( (string) $product->add_to_cart_text() ),
				'ajax'  => $product->supports( 'ajax_add_to_cart' ) && $product->is_purchasable() && $product->is_in_stock(),
			],
			'badges'       => array_map(
				static fn ( $badge ): array => $badge->to_array(),
				$this->badges->for_product( $product, 2 )
			),
			'image'        => $this->image( $image_id ),
		];

		/**
		 * Filters the API representation of a product.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $payload Payload.
		 * @param WC_Product           $product Product.
		 */
		return (array) apply_filters( 'bhc_product_payload', $payload, $product );
	}

	/**
	 * Presents a list of products.
	 *
	 * @param WC_Product[] $products Products.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function present_many( array $products ): array {
		return array_values(
			array_map(
				fn ( WC_Product $product ): array => $this->present( $product ),
				array_filter( $products, static fn ( $p ): bool => $p instanceof WC_Product )
			)
		);
	}

	/**
	 * Builds the responsive image payload.
	 *
	 * Width and height are always included so the client can reserve space and
	 * avoid layout shift before the image decodes.
	 *
	 * @param int $image_id Attachment id.
	 *
	 * @return array{src:string, srcset:string, sizes:string, width:int, height:int, alt:string}
	 */
	private function image( int $image_id ): array {
		$size = 'woocommerce_thumbnail';

		if ( $image_id <= 0 ) {
			return [
				'src'    => (string) wc_placeholder_img_src( $size ),
				'srcset' => '',
				'sizes'  => '',
				'width'  => 400,
				'height' => 400,
				'alt'    => '',
			];
		}

		$source = wp_get_attachment_image_src( $image_id, $size );

		return [
			'src'    => is_array( $source ) ? (string) $source[0] : '',
			'srcset' => (string) wp_get_attachment_image_srcset( $image_id, $size ),
			'sizes'  => (string) wp_get_attachment_image_sizes( $image_id, $size ),
			'width'  => is_array( $source ) ? (int) $source[1] : 0,
			'height' => is_array( $source ) ? (int) $source[2] : 0,
			'alt'    => (string) get_post_meta( $image_id, '_wp_attachment_image_alt', true ),
		];
	}
}
