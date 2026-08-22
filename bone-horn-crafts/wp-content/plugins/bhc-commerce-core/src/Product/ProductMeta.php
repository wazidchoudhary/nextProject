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
 * Reads go through WordPress's post meta cache, which ProductRepository::prime()
 * warms for a whole grid in one query — see read() for why the WooCommerce CRUD
 * accessor is not used on that path. Writes still go through
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
	 * Reads one meta value for a product.
	 *
	 * WooCommerce's `$product->get_meta()` loads the object's meta through
	 * WC_Data_Store_WP::read_meta(), which is an uncached
	 * `SELECT ... WHERE post_id = %d` run once per object. On a listing page
	 * that is one query per card: a twelve-card grid asking for a badge list
	 * costs twelve queries no matter how well the page is otherwise primed.
	 *
	 * `get_post_meta()` reads the same rows out of WordPress's post meta cache,
	 * which ProductRepository::prime() fills for every product on the page in a
	 * single query.
	 *
	 * The CRUD accessor is still used whenever it may hold something the
	 * database does not: an unsaved product, or one that is still being built
	 * (`get_object_read()` is false until WooCommerce has loaded it). Writers
	 * therefore keep full CRUD semantics; only settled reads take the fast path.
	 *
	 * @param WC_Product $product Product.
	 * @param string     $key     Meta key.
	 *
	 * @return mixed Stored value, or an empty string when absent.
	 */
	private static function read( WC_Product $product, string $key ): mixed {
		$id = $product->get_id();

		if ( $id > 0 && $product->get_object_read() ) {
			return get_post_meta( $id, $key, true );
		}

		return $product->get_meta( $key, true );
	}

	/**
	 * Manually assigned badge slugs.
	 *
	 * @param WC_Product $product Product.
	 *
	 * @return string[]
	 */
	public static function badges( WC_Product $product ): array {
		$value = self::read( $product, self::BADGES );

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
		return 'yes' === self::read( $product, self::LIMITED_BATCH );
	}

	/**
	 * Whether scales/blanks are supplied as matched pairs.
	 *
	 * @param WC_Product $product Product.
	 */
	public static function is_pair_matched( WC_Product $product ): bool {
		return 'yes' === self::read( $product, self::PAIR_MATCHED );
	}

	/**
	 * Whether wholesale tier pricing is enabled for the product.
	 *
	 * @param WC_Product $product Product.
	 */
	public static function wholesale_enabled( WC_Product $product ): bool {
		return 'yes' === self::read( $product, self::WHOLESALE_ENABLED );
	}

	/**
	 * Raw price tiers as stored.
	 *
	 * @param WC_Product $product Product.
	 *
	 * @return array<int, array{min_qty:int, price:float}>
	 */
	public static function price_tiers( WC_Product $product ): array {
		$tiers = self::read( $product, self::PRICE_TIERS );

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
		return (string) self::read( $product, self::HSN_CODE );
	}

	/**
	 * GST rate percentage stored against the product.
	 *
	 * @param WC_Product $product Product.
	 */
	public static function gst_rate( WC_Product $product ): float {
		return (float) self::read( $product, self::GST_RATE );
	}

	/**
	 * Internal batch/lot reference.
	 *
	 * @param WC_Product $product Product.
	 */
	public static function batch_reference( WC_Product $product ): string {
		return (string) self::read( $product, self::BATCH_REFERENCE );
	}

	/**
	 * Care and finishing instructions shown on the product page.
	 *
	 * @param WC_Product $product Product.
	 */
	public static function care_instructions( WC_Product $product ): string {
		return (string) self::read( $product, self::CARE_INSTRUCTIONS );
	}

	/**
	 * Workshop lead time in days (0 = ships from stock).
	 *
	 * @param WC_Product $product Product.
	 */
	public static function lead_time_days( WC_Product $product ): int {
		return absint( self::read( $product, self::LEAD_TIME_DAYS ) );
	}

	/**
	 * Country of manufacture, ISO 3166-1 alpha-2.
	 *
	 * @param WC_Product $product Product.
	 */
	public static function origin_country( WC_Product $product ): string {
		$code = strtoupper( (string) self::read( $product, self::ORIGIN_COUNTRY ) );

		return 2 === strlen( $code ) ? $code : 'IN';
	}

	/**
	 * How the item is sold ("per pair", "per blank", "set of 6").
	 *
	 * @param WC_Product $product Product.
	 */
	public static function unit_of_sale( WC_Product $product ): string {
		return (string) self::read( $product, self::UNIT_OF_SALE );
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
