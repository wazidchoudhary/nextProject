<?php
/**
 * Typed product meta accessor.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Product;

defined( 'ABSPATH' ) || exit;

use WC_Product;

/**
 * Single source of truth for the plugin's product meta keys.
 *
 * Reads go through the WooCommerce CRUD layer (`$product->get_meta()`), which
 * is already primed when the product object was loaded — so a template that
 * asks for five meta values costs zero extra queries. Writes go through
 * `update_meta_data()` + `save()` so HPOS, caches and CRUD hooks stay correct.
 */
final class ProductMeta {

	public const BADGES            = '_bhc_badges';
	public const LIMITED_BATCH     = '_bhc_limited_batch';
	public const PAIR_MATCHED      = '_bhc_pair_matched';
	public const HSN_CODE          = '_bhc_hsn_code';
	public const GST_RATE          = '_bhc_gst_rate';
	public const BATCH_REFERENCE   = '_bhc_batch_reference';
	public const WHOLESALE_ENABLED = '_bhc_wholesale_enabled';
	public const PRICE_TIERS       = '_bhc_price_tiers';
	public const CARE_INSTRUCTIONS = '_bhc_care_instructions';
	public const LEAD_TIME_DAYS    = '_bhc_lead_time_days';
	public const ORIGIN_COUNTRY    = '_bhc_origin_country';
	public const EXPORT_HS_DESC    = '_bhc_export_hs_description';
	public const UNIT_OF_SALE      = '_bhc_unit_of_sale';

	/**
	 * Every key the plugin owns. Used by the demo reset command and by the
	 * REST schema so nothing drifts out of sync.
	 *
	 * @return string[]
	 */
	public static function keys(): array {
		return [
			self::BADGES,
			self::LIMITED_BATCH,
			self::PAIR_MATCHED,
			self::HSN_CODE,
			self::GST_RATE,
			self::BATCH_REFERENCE,
			self::WHOLESALE_ENABLED,
			self::PRICE_TIERS,
			self::CARE_INSTRUCTIONS,
			self::LEAD_TIME_DAYS,
			self::ORIGIN_COUNTRY,
			self::EXPORT_HS_DESC,
			self::UNIT_OF_SALE,
		];
	}

	/**
	 * Manually assigned badge slugs.
	 *
	 * @param WC_Product $product Product.
	 *
	 * @return string[]
	 */
	public static function badges( WC_Product $product ): array {
		$value = $product->get_meta( self::BADGES, true );

		if ( is_string( $value ) && '' !== $value ) {
			$value = array_map( 'trim', explode( ',', $value ) );
		}

		return is_array( $value ) ? array_values( array_filter( array_map( 'sanitize_key', $value ) ) ) : [];
	}

	/**
	 * Whether the product is flagged as a limited batch.
	 *
	 * @param WC_Product $product Product.
	 */
	public static function is_limited_batch( WC_Product $product ): bool {
		return 'yes' === $product->get_meta( self::LIMITED_BATCH, true );
	}

	/**
	 * Whether scales/blanks are supplied as matched pairs.
	 *
	 * @param WC_Product $product Product.
	 */
	public static function is_pair_matched( WC_Product $product ): bool {
		return 'yes' === $product->get_meta( self::PAIR_MATCHED, true );
	}

	/**
	 * Whether wholesale tier pricing is enabled for the product.
	 *
	 * @param WC_Product $product Product.
	 */
	public static function wholesale_enabled( WC_Product $product ): bool {
		return 'yes' === $product->get_meta( self::WHOLESALE_ENABLED, true );
	}

	/**
	 * Raw price tiers as stored.
	 *
	 * @param WC_Product $product Product.
	 *
	 * @return array<int, array{min_qty:int, price:float}>
	 */
	public static function price_tiers( WC_Product $product ): array {
		$tiers = $product->get_meta( self::PRICE_TIERS, true );

		if ( ! is_array( $tiers ) ) {
			return [];
		}

		$clean = [];

		foreach ( $tiers as $tier ) {
			if ( ! is_array( $tier ) || ! isset( $tier['min_qty'], $tier['price'] ) ) {
				continue;
			}

			$min_qty = absint( $tier['min_qty'] );
			$price   = (float) $tier['price'];

			if ( $min_qty < 2 || $price <= 0 ) {
				continue;
			}

			$clean[] = [
				'min_qty' => $min_qty,
				'price'   => round( $price, 2 ),
			];
		}

		usort( $clean, static fn ( array $a, array $b ): int => $a['min_qty'] <=> $b['min_qty'] );

		return $clean;
	}

	/**
	 * HSN code used for Indian domestic GST documents.
	 *
	 * @param WC_Product $product Product.
	 */
	public static function hsn_code( WC_Product $product ): string {
		return (string) $product->get_meta( self::HSN_CODE, true );
	}

	/**
	 * GST rate percentage stored against the product.
	 *
	 * @param WC_Product $product Product.
	 */
	public static function gst_rate( WC_Product $product ): float {
		return (float) $product->get_meta( self::GST_RATE, true );
	}

	/**
	 * Internal batch/lot reference.
	 *
	 * @param WC_Product $product Product.
	 */
	public static function batch_reference( WC_Product $product ): string {
		return (string) $product->get_meta( self::BATCH_REFERENCE, true );
	}

	/**
	 * Care and finishing instructions shown on the product page.
	 *
	 * @param WC_Product $product Product.
	 */
	public static function care_instructions( WC_Product $product ): string {
		return (string) $product->get_meta( self::CARE_INSTRUCTIONS, true );
	}

	/**
	 * Workshop lead time in days (0 = ships from stock).
	 *
	 * @param WC_Product $product Product.
	 */
	public static function lead_time_days( WC_Product $product ): int {
		return absint( $product->get_meta( self::LEAD_TIME_DAYS, true ) );
	}

	/**
	 * Country of manufacture, ISO 3166-1 alpha-2.
	 *
	 * @param WC_Product $product Product.
	 */
	public static function origin_country( WC_Product $product ): string {
		$code = strtoupper( (string) $product->get_meta( self::ORIGIN_COUNTRY, true ) );

		return 2 === strlen( $code ) ? $code : 'IN';
	}

	/**
	 * How the item is sold ("per pair", "per blank", "set of 6").
	 *
	 * @param WC_Product $product Product.
	 */
	public static function unit_of_sale( WC_Product $product ): string {
		return (string) $product->get_meta( self::UNIT_OF_SALE, true );
	}

	/**
	 * Writes a sanitised meta value through the CRUD layer.
	 *
	 * @param WC_Product $product Product.
	 * @param string     $key     One of the class constants.
	 * @param mixed      $value   Value.
	 */
	public static function set( WC_Product $product, string $key, mixed $value ): void {
		if ( ! in_array( $key, self::keys(), true ) ) {
			return;
		}

		$product->update_meta_data( $key, $value );
	}
}
