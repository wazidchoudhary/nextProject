<?php
/**
 * Order metadata keys and accessors.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Order;

defined( 'ABSPATH' ) || exit;

use WC_Order;

/**
 * Operational metadata attached to every order.
 *
 * All reads and writes use the WooCommerce CRUD API, so the same code works
 * whether the store runs legacy post storage or High Performance Order Storage
 * — the plugin declares HPOS compatibility and never touches `postmeta`
 * directly for orders.
 *
 * Tax fields are reference data captured at the time of sale (HSN code, GST
 * rate, export flag). They exist so packing lists and invoices can be produced
 * from the order alone, even after a product's own settings change. They do not
 * calculate tax and make no compliance claim.
 */
final class OrderMeta {

	public const BATCH_REFERENCES = '_bhc_batch_references';
	public const PACKING_NOTES    = '_bhc_packing_notes';
	public const IS_WHOLESALE     = '_bhc_is_wholesale';
	public const EXPORT_TYPE      = '_bhc_export_type';
	public const HSN_SUMMARY      = '_bhc_hsn_summary';
	public const DECLARED_VALUE   = '_bhc_declared_value';
	public const SHIPPING_ZONE    = '_bhc_shipping_zone';

	public const ITEM_HSN_CODE  = '_bhc_hsn_code';
	public const ITEM_GST_RATE  = '_bhc_gst_rate';
	public const ITEM_BATCH_REF = '_bhc_batch_reference';
	public const ITEM_ORIGIN    = '_bhc_origin_country';

	public const EXPORT_ZERO_RATED = 'export_zero_rated';
	public const DOMESTIC_GST      = 'domestic_gst';

	/**
	 * Whether the order was placed by a wholesale account.
	 *
	 * @param WC_Order $order Order.
	 */
	public static function is_wholesale( WC_Order $order ): bool {
		return 'yes' === $order->get_meta( self::IS_WHOLESALE, true );
	}

	/**
	 * Invoicing treatment recorded for the order.
	 *
	 * @param WC_Order $order Order.
	 */
	public static function export_type( WC_Order $order ): string {
		$type = (string) $order->get_meta( self::EXPORT_TYPE, true );

		return in_array( $type, [ self::EXPORT_ZERO_RATED, self::DOMESTIC_GST ], true ) ? $type : self::EXPORT_ZERO_RATED;
	}

	/**
	 * Internal packing notes.
	 *
	 * @param WC_Order $order Order.
	 */
	public static function packing_notes( WC_Order $order ): string {
		return (string) $order->get_meta( self::PACKING_NOTES, true );
	}

	/**
	 * Material lot references included in the shipment.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return string[]
	 */
	public static function batch_references( WC_Order $order ): array {
		$refs = $order->get_meta( self::BATCH_REFERENCES, true );

		return is_array( $refs ) ? array_values( array_filter( array_map( 'strval', $refs ) ) ) : [];
	}

	/**
	 * HSN totals captured for the order.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return array<string, array{qty:int, value:float, gst_rate:float}>
	 */
	public static function hsn_summary( WC_Order $order ): array {
		$summary = $order->get_meta( self::HSN_SUMMARY, true );

		return is_array( $summary ) ? $summary : [];
	}

	/**
	 * Declared customs value.
	 *
	 * @param WC_Order $order Order.
	 */
	public static function declared_value( WC_Order $order ): float {
		return (float) $order->get_meta( self::DECLARED_VALUE, true );
	}

	/**
	 * Human readable label for an export type.
	 *
	 * @param string $type Export type.
	 */
	public static function export_type_label( string $type ): string {
		return self::DOMESTIC_GST === $type
			? __( 'Domestic sale (GST applicable)', 'bhc-commerce-core' )
			: __( 'Export shipment (zero rated)', 'bhc-commerce-core' );
	}
}
