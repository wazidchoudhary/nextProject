<?php
/**
 * Checkout address validation.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Checkout;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Contracts\HookableInterface;
use BoneHornCrafts\Core\Contracts\LoggerInterface;
use WP_Error;

/**
 * Validates the submitted address before an order is created.
 *
 * Failing here costs the shopper one form correction. Failing at the courier
 * costs a returned parcel and a refund, which is why postcode and phone are
 * checked server side even though the front end checks them too — client
 * validation is a convenience, never a control.
 */
final class AddressValidator implements HookableInterface {

	/**
	 * Constructor.
	 *
	 * @param PostcodeValidator $postcodes Postcode helper.
	 * @param PhoneValidator    $phones    Phone helper.
	 * @param LoggerInterface   $logger    Logger.
	 */
	public function __construct(
		private PostcodeValidator $postcodes,
		private PhoneValidator $phones,
		private LoggerInterface $logger
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'woocommerce_after_checkout_validation', [ $this, 'validate' ], 10, 2 );
	}

	/**
	 * Runs the validation rules.
	 *
	 * @param array<string, mixed> $data   Posted checkout data (already sanitised by WooCommerce).
	 * @param WP_Error             $errors Error collector.
	 */
	public function validate( array $data, WP_Error $errors ): void {
		$ship_to_different = ! empty( $data['ship_to_different_address'] );
		$groups            = $ship_to_different ? [ 'billing', 'shipping' ] : [ 'billing' ];

		foreach ( $groups as $group ) {
			$country  = strtoupper( (string) ( $data[ $group . '_country' ] ?? '' ) );
			$postcode = (string) ( $data[ $group . '_postcode' ] ?? '' );

			if ( '' === $country || '' === $postcode ) {
				continue;
			}

			$error = $this->postcodes->error_for( $postcode, $country );

			if ( '' !== $error ) {
				$errors->add( 'bhc_' . $group . '_postcode', $error );
			}
		}

		$phone   = (string) ( $data['billing_phone'] ?? '' );
		$country = strtoupper( (string) ( $data['billing_country'] ?? '' ) );

		if ( '' !== $phone ) {
			$phone_error = $this->phones->error_for( $phone, $country );

			if ( '' !== $phone_error ) {
				$errors->add( 'bhc_billing_phone', $phone_error );
			}
		}

		$destination = $ship_to_different
			? strtoupper( (string) ( $data['shipping_country'] ?? '' ) )
			: $country;

		if ( '' !== $destination && ! CountryProfile::is_supported( $destination ) ) {
			$errors->add(
				'bhc_unsupported_destination',
				__( 'We cannot ship to that destination from the online store. Please contact the workshop for a freight quote.', 'bhc-commerce-core' )
			);
		}

		if ( $errors->has_errors() ) {
			// Only the error codes are logged — never the address itself.
			$this->logger->info( 'checkout.validation_failed', [ 'codes' => $errors->get_error_codes() ] );
		}
	}
}
