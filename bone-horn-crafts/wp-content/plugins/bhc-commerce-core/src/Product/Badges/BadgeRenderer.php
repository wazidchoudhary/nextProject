<?php
/**
 * Badge output.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Product\Badges;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Contracts\HookableInterface;
use WC_Product;

/**
 * Prints badge markup on archive cards and product pages.
 *
 * Markup is plain, semantic and self-contained: a `<ul>` of `<li>` chips with a
 * screen-reader-only prefix so the badges read as "Highlights: Bestseller"
 * rather than as loose text next to the product name.
 */
final class BadgeRenderer implements HookableInterface {

	/**
	 * Constructor.
	 *
	 * @param BadgeResolver $resolver Badge resolver.
	 */
	public function __construct( private BadgeResolver $resolver ) {}

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'woocommerce_before_shop_loop_item_title', [ $this, 'render_loop_badges' ], 8 );
		add_action( 'woocommerce_single_product_summary', [ $this, 'render_single_badges' ], 4 );
		add_shortcode( 'bhc_product_badges', [ $this, 'shortcode' ] );
	}

	/**
	 * Renders badges inside a product loop card.
	 */
	public function render_loop_badges(): void {
		global $product;

		if ( $product instanceof WC_Product ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in markup().
			echo $this->markup( $product, 2 );
		}
	}

	/**
	 * Renders badges on the single product summary.
	 */
	public function render_single_badges(): void {
		global $product;

		if ( $product instanceof WC_Product ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in markup().
			echo $this->markup( $product, 3 );
		}
	}

	/**
	 * `[bhc_product_badges id="123"]` shortcode.
	 *
	 * @param array<string, string>|string $atts Shortcode attributes.
	 */
	public function shortcode( $atts = [] ): string {
		$atts = shortcode_atts(
			[
				'id'    => 0,
				'limit' => 3,
			],
			is_array( $atts ) ? $atts : [],
			'bhc_product_badges'
		);

		$product = wc_get_product( absint( $atts['id'] ) ?: get_the_ID() );

		return $product instanceof WC_Product ? $this->markup( $product, absint( $atts['limit'] ) ) : '';
	}

	/**
	 * Builds the escaped badge markup for a product.
	 *
	 * @param WC_Product $product Product.
	 * @param int        $limit   Maximum badges.
	 */
	public function markup( WC_Product $product, int $limit = 2 ): string {
		$badges = $this->resolver->for_product( $product, $limit );

		if ( [] === $badges ) {
			return '';
		}

		$items = '';

		foreach ( $badges as $badge ) {
			$items .= sprintf(
				'<li class="bhc-badge bhc-badge--%1$s">%2$s</li>',
				esc_attr( $badge->tone ),
				esc_html( $badge->label )
			);
		}

		return sprintf(
			'<ul class="bhc-badges" aria-label="%1$s">%2$s</ul>',
			esc_attr__( 'Product highlights', 'bhc-commerce-core' ),
			$items
		);
	}
}
