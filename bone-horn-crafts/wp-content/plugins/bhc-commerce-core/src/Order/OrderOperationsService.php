<?php
/**
 * Operational order metadata capture.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Order;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Checkout\CountryProfile;
use BoneHornCrafts\Core\Contracts\HookableInterface;
use BoneHornCrafts\Core\Contracts\LoggerInterface;
use BoneHornCrafts\Core\Product\ProductMeta;
use WC_Order;
use WC_Order_Item_Product;
use WC_Product;

/**
 * Captures everything the workshop and the shipping desk need, at the moment
 * the order is created.
 *
 * Why snapshot instead of looking it up later: an order shipped in March must
 * still print the HSN code, GST rate and material lot that applied in March,
 * even if the product was re-priced or re-coded in April. Orders are records,
 * not live views of the catalogue.
 */
final class OrderOperationsService implements HookableInterface {

	/**
	 * Constructor.
	 *
	 * @param LoggerInterface $logger Logger.
	 */
	public function __construct( private LoggerInterface $logger ) {}

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'capture_line_item_meta' ], 10, 4 );
		add_action( 'woocommerce_checkout_create_order', [ $this, 'capture_order_meta' ], 10, 2 );
		add_action( 'woocommerce_store_api_checkout_update_order_meta', [ $this, 'capture_block_checkout_meta' ], 10, 1 );
		add_action( 'woocommerce_new_order', [ $this, 'summarise_order' ], 20, 2 );
	}

	/**
	 * Copies craft/tax reference data onto each line item.
	 *
	 * @param WC_Order_Item_Product $item          Line item.
	 * @param string                $cart_item_key Cart item key.
	 * @param array<string, mixed>  $values        Cart item values.
	 * @param WC_Order              $order         Order.
	 */
	public function capture_line_item_meta( $item, $cart_item_key, $values, $order ): void {
		if ( ! $item instanceof WC_Order_Item_Product ) {
			return;
		}

		$product = $item->get_product();

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		$hsn   = ProductMeta::hsn_code( $product );
		$batch = ProductMeta::batch_reference( $product );

		if ( '' !== $hsn ) {
			$item->add_meta_data( OrderMeta::ITEM_HSN_CODE, $hsn, true );
		}

		if ( '' !== $batch ) {
			$item->add_meta_data( OrderMeta::ITEM_BATCH_REF, $batch, true );
		}

		$item->add_meta_data( OrderMeta::ITEM_GST_RATE, ProductMeta::gst_rate( $product ), true );
		$item->add_meta_data( OrderMeta::ITEM_ORIGIN, ProductMeta::origin_country( $product ), true );
	}

	/**
	 * Records order level operational metadata during classic checkout.
	 *
	 * @param WC_Order             $order Order.
	 * @param array<string, mixed> $data  Posted checkout data.
	 */
	public function capture_order_meta( $order, $data = [] ): void {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$country = strtoupper( (string) ( $order->get_shipping_country() ?: $order->get_billing_country() ) );

		$order->update_meta_data(
			OrderMeta::EXPORT_TYPE,
			'IN' === $country ? OrderMeta::DOMESTIC_GST : OrderMeta::EXPORT_ZERO_RATED
		);

		$order->update_meta_data( OrderMeta::SHIPPING_ZONE, (string) CountryProfile::get( $country )['zone'] );

		$customer_id = $order->get_customer_id();

		/** This filter is documented in src/Pricing/TieredPricingService.php */
		$is_wholesale = (bool) apply_filters( 'bhc_is_wholesale_customer', false, $customer_id );

		$order->update_meta_data( OrderMeta::IS_WHOLESALE, $is_wholesale ? 'yes' : 'no' );
	}

	/**
	 * Block checkout equivalent of `capture_order_meta()`.
	 *
	 * @param WC_Order $order Order.
	 */
	public function capture_block_checkout_meta( $order ): void {
		if ( $order instanceof WC_Order ) {
			$this->capture_order_meta( $order );
		}
	}

	/**
	 * Builds the HSN summary, lot list and declared value once items exist.
	 *
	 * @param int      $order_id Order id.
	 * @param WC_Order $order    Order object.
	 */
	public function summarise_order( $order_id, $order = null ): void {
		$order = $order instanceof WC_Order ? $order : wc_get_order( absint( $order_id ) );

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$summary  = [];
		$batches  = [];
		$declared = 0.0;

		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}

			$quantity = (int) $item->get_quantity();
			$total    = (float) $item->get_total();
			$hsn      = (string) $item->get_meta( OrderMeta::ITEM_HSN_CODE, true );
			$gst      = (float) $item->get_meta( OrderMeta::ITEM_GST_RATE, true );
			$batch    = (string) $item->get_meta( OrderMeta::ITEM_BATCH_REF, true );

			$declared += $total;

			if ( '' !== $batch ) {
				$batches[ $batch ] = true;
			}

			if ( '' === $hsn ) {
				continue;
			}

			if ( ! isset( $summary[ $hsn ] ) ) {
				$summary[ $hsn ] = [
					'qty'      => 0,
					'value'    => 0.0,
					'gst_rate' => $gst,
				];
			}

			$summary[ $hsn ]['qty']  += $quantity;
			$summary[ $hsn ]['value'] = round( $summary[ $hsn ]['value'] + $total, 2 );
		}

		$order->update_meta_data( OrderMeta::HSN_SUMMARY, $summary );
		$order->update_meta_data( OrderMeta::BATCH_REFERENCES, array_keys( $batches ) );
		$order->update_meta_data( OrderMeta::DECLARED_VALUE, round( $declared, 2 ) );

		$order->save();

		$this->logger->info(
			'order.operations_captured',
			[
				'order_id'   => $order->get_id(),
				'hsn_groups' => count( $summary ),
				'lots'       => count( $batches ),
			]
		);
	}
}
