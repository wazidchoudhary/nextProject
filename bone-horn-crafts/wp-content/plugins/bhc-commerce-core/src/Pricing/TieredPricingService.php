<?php
/**
 * Quantity based pricing.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Pricing;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Contracts\HookableInterface;
use BoneHornCrafts\Core\Contracts\LoggerInterface;
use BoneHornCrafts\Core\Contracts\PricingRuleInterface;
use BoneHornCrafts\Core\Product\ProductMeta;
use BoneHornCrafts\Core\Support\Options;
use BoneHornCrafts\Core\Support\Template;
use WC_Cart;
use WC_Product;

/**
 * Applies wholesale price breaks to the cart and renders the tier table.
 *
 * Implementation notes that matter in production:
 *
 * * Prices are set on the cart item's product *clone*
 *   (`$cart_item['data']`), never on the catalogue product. Writing to the
 *   shared object would leak a wholesale price into someone else's session and
 *   into the object cache.
 * * The handler runs once per `woocommerce_before_calculate_totals` pass and
 *   is a no-op in the admin, so bulk edits and reports never see adjusted
 *   prices.
 * * Rules are injected as a list of `PricingRuleInterface`, so a future
 *   "returning customer" or "clearance lot" rule is a new class plus one
 *   filter — not an edit to this file.
 */
final class TieredPricingService implements HookableInterface {

	/**
	 * Constructor.
	 *
	 * @param DiscountCalculator     $calculator Discount maths.
	 * @param PricingRuleInterface[] $rules      Ordered pricing rules.
	 * @param Template               $template   Template renderer.
	 * @param Options                $options    Settings.
	 * @param LoggerInterface        $logger     Logger.
	 */
	public function __construct(
		private DiscountCalculator $calculator,
		private array $rules,
		private Template $template,
		private Options $options,
		private LoggerInterface $logger
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		if ( ! $this->options->bool( 'tiered_pricing_enabled' ) ) {
			return;
		}

		add_action( 'woocommerce_before_calculate_totals', [ $this, 'apply_to_cart' ], 20, 1 );
		add_filter( 'woocommerce_get_item_data', [ $this, 'cart_item_notice' ], 10, 2 );
		add_action( 'woocommerce_after_add_to_cart_form', [ $this, 'render_tier_table' ], 5 );
	}

	/**
	 * Applies the pricing rules to every cart line.
	 *
	 * @param WC_Cart $cart Cart instance.
	 */
	public function apply_to_cart( WC_Cart $cart ): void {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		if ( did_action( 'woocommerce_before_calculate_totals' ) > 1 && doing_action( 'woocommerce_before_calculate_totals' ) === false ) {
			return;
		}

		$customer_id  = get_current_user_id();
		$is_wholesale = (bool) apply_filters( 'bhc_is_wholesale_customer', false, $customer_id );

		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			$product = $cart_item['data'] ?? null;

			if ( ! $product instanceof WC_Product ) {
				continue;
			}

			$quantity   = (int) ( $cart_item['quantity'] ?? 1 );
			$base_price = (float) $product->get_price( 'edit' );

			if ( $base_price <= 0.0 ) {
				continue;
			}

			$context = new PriceContext( $product, $base_price, $quantity, $customer_id, $is_wholesale );
			$price   = $base_price;
			$applied = '';

			foreach ( $this->rules as $rule ) {
				if ( ! $rule instanceof PricingRuleInterface || ! $rule->applies( $context ) ) {
					continue;
				}

				$candidate = $rule->apply( $context->with_price( $price ) );

				if ( $candidate > 0.0 && $candidate < $price ) {
					$price   = $candidate;
					$applied = $rule->id();
				}
			}

			if ( $applied && $price < $base_price ) {
				$product->set_price( $price );

				$cart->cart_contents[ $cart_item_key ]['bhc_pricing_rule'] = $applied;
				$cart->cart_contents[ $cart_item_key ]['bhc_unit_price']   = $price;
			}
		}
	}

	/**
	 * Adds a short "bulk price applied" line to the cart item.
	 *
	 * @param array<int, array<string, string>> $item_data Existing item data.
	 * @param array<string, mixed>              $cart_item Cart item.
	 *
	 * @return array<int, array<string, string>>
	 */
	public function cart_item_notice( array $item_data, array $cart_item ): array {
		if ( empty( $cart_item['bhc_pricing_rule'] ) ) {
			return $item_data;
		}

		$item_data[] = [
			'key'     => __( 'Pricing', 'bhc-commerce-core' ),
			'value'   => sprintf(
				/* translators: %s: formatted unit price. */
				__( 'Quantity price applied — %s per unit', 'bhc-commerce-core' ),
				wp_strip_all_tags( wc_price( (float) ( $cart_item['bhc_unit_price'] ?? 0 ) ) )
			),
			'display' => '',
		];

		return $item_data;
	}

	/**
	 * Returns the tier table rows for a product.
	 *
	 * @param WC_Product $product Product.
	 *
	 * @return array<int, array{min_qty:int, price:float, saving_percent:int}>
	 */
	public function rows_for( WC_Product $product ): array {
		if ( ! ProductMeta::wholesale_enabled( $product ) ) {
			return [];
		}

		return $this->calculator->tier_rows(
			ProductMeta::price_tiers( $product ),
			(float) $product->get_price()
		);
	}

	/**
	 * Renders the quantity price table under the add-to-cart form.
	 */
	public function render_tier_table(): void {
		global $product;

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		$rows = $this->rows_for( $product );

		if ( [] === $rows ) {
			return;
		}

		$this->template->output(
			'pricing/tier-table.php',
			[
				'product' => $product,
				'rows'    => $rows,
			]
		);
	}
}
