<?php
/**
 * Delivery window estimator.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Checkout;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Product\ProductMeta;
use WC_Product;

/**
 * Estimates a dispatch and delivery window for a destination country.
 *
 * The window is workshop lead time (per product) plus the country's transit
 * range, rounded to whole days and expressed as a date range. It is an
 * estimate, and the copy says so — quoting a hard date the workshop cannot
 * meet is worse than quoting a range it can.
 */
final class DeliveryEstimator {

	/**
	 * Days between an order being placed and leaving the workshop, minimum.
	 */
	private const BASE_DISPATCH_DAYS = 1;

	/**
	 * Estimates the delivery window for a product and destination.
	 *
	 * @param WC_Product|null $product Product, or null for a cart-level estimate.
	 * @param string          $country ISO country code.
	 *
	 * @return array{supported:bool, country:string, dispatch_days:int, min_days:int, max_days:int, min_date:string, max_date:string, zone:string, label:string}
	 */
	public function estimate( ?WC_Product $product, string $country ): array {
		$country = strtoupper( trim( $country ) );
		$profile = CountryProfile::get( $country );

		$lead_time = $product instanceof WC_Product ? ProductMeta::lead_time_days( $product ) : 0;
		$dispatch  = max( self::BASE_DISPATCH_DAYS, $lead_time );

		$min_days = $dispatch + (int) $profile['transit'][0];
		$max_days = $dispatch + (int) $profile['transit'][1];

		$now = current_datetime();

		$min_date = $now->modify( sprintf( '+%d days', $min_days ) );
		$max_date = $now->modify( sprintf( '+%d days', $max_days ) );

		$estimate = [
			'supported'     => CountryProfile::is_supported( $country ),
			'country'       => $country,
			'dispatch_days' => $dispatch,
			'min_days'      => $min_days,
			'max_days'      => $max_days,
			'min_date'      => $min_date ? wp_date( get_option( 'date_format' ), $min_date->getTimestamp() ) : '',
			'max_date'      => $max_date ? wp_date( get_option( 'date_format' ), $max_date->getTimestamp() ) : '',
			'zone'          => (string) $profile['zone'],
			'label'         => '',
		];

		$estimate['label'] = $this->label( $estimate );

		/**
		 * Filters a delivery estimate.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $estimate Estimate payload.
		 * @param WC_Product|null      $product  Product.
		 * @param string               $country  Destination country.
		 */
		return (array) apply_filters( 'bhc_delivery_estimate', $estimate, $product, $country );
	}

	/**
	 * Builds the customer facing sentence for an estimate.
	 *
	 * @param array<string, mixed> $estimate Estimate payload.
	 */
	private function label( array $estimate ): string {
		if ( ! $estimate['supported'] ) {
			return __( 'We do not ship to this destination yet — contact the workshop for a freight quote.', 'bhc-commerce-core' );
		}

		return sprintf(
			/* translators: 1: earliest delivery date, 2: latest delivery date, 3: dispatch days. */
			__( 'Estimated delivery %1$s – %2$s. Dispatched from the workshop within %3$d working day(s).', 'bhc-commerce-core' ),
			(string) $estimate['min_date'],
			(string) $estimate['max_date'],
			(int) $estimate['dispatch_days']
		);
	}
}
