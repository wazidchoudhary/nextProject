<?php
/**
 * Recommendation output.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Recommendations;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Contracts\HookableInterface;
use BoneHornCrafts\Core\Product\ProductRepository;
use BoneHornCrafts\Core\Product\RecentlyViewed\RecentlyViewedService;
use BoneHornCrafts\Core\Support\Template;
use WC_Product;

/**
 * Renders the "Complete your build", "Frequently bought together" and
 * "Recently viewed" rails.
 *
 * WooCommerce's stock `related_products` block is removed in favour of the
 * blended service: the default picks random products from the same category,
 * which on a materials catalogue means "here is the same scale in a different
 * colour" instead of the pins and epoxy the customer still needs.
 */
final class RecommendationRenderer implements HookableInterface {

	/**
	 * Constructor.
	 *
	 * @param RecommendationService $service         Recommendation service.
	 * @param RecentlyViewedService $recently_viewed Recently viewed service.
	 * @param ProductRepository     $products        Product read model.
	 * @param Template              $template        Template renderer.
	 */
	public function __construct(
		private RecommendationService $service,
		private RecentlyViewedService $recently_viewed,
		private ProductRepository $products,
		private Template $template
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20 );

		add_action( 'woocommerce_after_single_product_summary', [ $this, 'render_complete_your_build' ], 20 );
		add_action( 'woocommerce_after_single_product_summary', [ $this, 'render_recently_viewed' ], 24 );
		add_action( 'woocommerce_after_add_to_cart_form', [ $this, 'render_frequently_bought' ], 20 );

		add_shortcode( 'bhc_recommendations', [ $this, 'shortcode' ] );
	}

	/**
	 * Renders the main recommendation rail.
	 */
	public function render_complete_your_build(): void {
		global $product;

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		$this->render_rail(
			$this->service->products_for( $product, 8, 'complete_your_build' ),
			__( 'Complete your build', 'bhc-commerce-core' ),
			__( 'Pins, spacers and finishing stock that pair with this material.', 'bhc-commerce-core' ),
			'complete-your-build'
		);
	}

	/**
	 * Renders the compact "frequently bought together" strip.
	 */
	public function render_frequently_bought(): void {
		global $product;

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		$products = $this->service->products_for( $product, 3, 'frequently_bought_together' );

		if ( [] === $products ) {
			return;
		}

		$this->template->output(
			'recommendations/frequently-bought.php',
			[
				'seed'     => $product,
				'products' => $products,
			]
		);
	}

	/**
	 * Renders the recently viewed rail.
	 */
	public function render_recently_viewed(): void {
		global $product;

		$exclude = $product instanceof WC_Product ? $product->get_id() : 0;
		$ids     = $this->recently_viewed->ids_excluding( $exclude );

		if ( count( $ids ) < 2 ) {
			return;
		}

		$this->render_rail(
			$this->products->hydrate( array_slice( $ids, 0, 6 ) ),
			__( 'Recently viewed', 'bhc-commerce-core' ),
			'',
			'recently-viewed'
		);
	}

	/**
	 * `[bhc_recommendations product_id="12" limit="4"]` shortcode.
	 *
	 * @param array<string, string>|string $atts Attributes.
	 */
	public function shortcode( $atts = [] ): string {
		$atts = shortcode_atts(
			[
				'product_id' => 0,
				'limit'      => 4,
				'placement'  => 'complete_your_build',
				'title'      => __( 'You may also need', 'bhc-commerce-core' ),
			],
			is_array( $atts ) ? $atts : [],
			'bhc_recommendations'
		);

		$product = wc_get_product( absint( $atts['product_id'] ) ?: get_the_ID() );

		if ( ! $product instanceof WC_Product ) {
			return '';
		}

		return $this->template->render(
			'recommendations/rail.php',
			[
				'products'  => $this->service->products_for( $product, absint( $atts['limit'] ), sanitize_key( (string) $atts['placement'] ) ),
				'title'     => sanitize_text_field( (string) $atts['title'] ),
				'subtitle'  => '',
				'modifier'  => sanitize_html_class( (string) $atts['placement'] ),
			]
		);
	}

	/**
	 * Outputs a rail template when it has products to show.
	 *
	 * @param WC_Product[] $products Products.
	 * @param string       $title    Rail heading.
	 * @param string       $subtitle Rail subheading.
	 * @param string       $modifier CSS modifier.
	 */
	private function render_rail( array $products, string $title, string $subtitle, string $modifier ): void {
		if ( [] === $products ) {
			return;
		}

		$this->template->output(
			'recommendations/rail.php',
			[
				'products' => $products,
				'title'    => $title,
				'subtitle' => $subtitle,
				'modifier' => $modifier,
			]
		);
	}
}
