<?php
/**
 * Checkout field customisations.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Checkout;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Contracts\HookableInterface;

/**
 * Tunes the checkout form for international parcel shipping.
 *
 * No fields are added. Everything here is about making the existing fields
 * correct for the destination: the postcode label and placeholder follow the
 * country, the phone field carries its dial code and is required (couriers
 * refuse export parcels without a contact number), and address line 2 is shown
 * up front instead of behind a link, because "Unit 4, Maker's Yard" is where
 * half of these parcels go.
 */
final class CheckoutFieldCustomizer implements HookableInterface {

	/**
	 * Constructor.
	 *
	 * @param PostcodeValidator $postcodes Postcode helper.
	 * @param PhoneValidator    $phones    Phone helper.
	 */
	public function __construct( private PostcodeValidator $postcodes, private PhoneValidator $phones ) {}

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_filter( 'woocommerce_checkout_fields', [ $this, 'customise_fields' ], 20, 1 );
		add_filter( 'woocommerce_default_address_fields', [ $this, 'customise_address_fields' ], 20, 1 );
		add_filter( 'woocommerce_get_country_locale', [ $this, 'customise_locale' ], 20, 1 );
		add_filter( 'woocommerce_billing_fields', [ $this, 'customise_billing_fields' ], 20, 1 );
	}

	/**
	 * Adjusts the checkout field set.
	 *
	 * @param array<string, array<string, mixed>> $fields Checkout fields.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function customise_fields( array $fields ): array {
		$country = $this->current_country();

		if ( isset( $fields['billing']['billing_phone'] ) ) {
			$fields['billing']['billing_phone']['required']    = true;
			$fields['billing']['billing_phone']['type']        = 'tel';
			$fields['billing']['billing_phone']['placeholder'] = $this->phones->placeholder( $country );
			$fields['billing']['billing_phone']['label']       = __( 'Phone (with country code)', 'bhc-commerce-core' );
			$fields['billing']['billing_phone']['autocomplete'] = 'tel';
			$fields['billing']['billing_phone']['custom_attributes']['inputmode'] = 'tel';
			$fields['billing']['billing_phone']['custom_attributes']['data-bhc-phone'] = '1';
		}

		if ( isset( $fields['billing']['billing_email'] ) ) {
			$fields['billing']['billing_email']['priority'] = 25;
			$fields['billing']['billing_email']['description'] = __( 'Order updates and the tracking number are sent here.', 'bhc-commerce-core' );
		}

		if ( isset( $fields['order']['order_comments'] ) ) {
			$fields['order']['order_comments']['label']       = __( 'Notes for the workshop', 'bhc-commerce-core' );
			$fields['order']['order_comments']['placeholder'] = __( 'Matching preferences, delivery instructions, or a build deadline we should know about.', 'bhc-commerce-core' );
		}

		foreach ( [ 'billing', 'shipping' ] as $group ) {
			$postcode_key = $group . '_postcode';

			if ( isset( $fields[ $group ][ $postcode_key ] ) ) {
				$fields[ $group ][ $postcode_key ]['label']       = $this->postcodes->label( $country );
				$fields[ $group ][ $postcode_key ]['placeholder'] = $this->postcodes->example( $country );
				$fields[ $group ][ $postcode_key ]['custom_attributes']['data-bhc-postcode'] = '1';
			}

			$address_2 = $group . '_address_2';

			if ( isset( $fields[ $group ][ $address_2 ] ) ) {
				$fields[ $group ][ $address_2 ]['label']         = __( 'Apartment, unit, workshop (optional)', 'bhc-commerce-core' );
				$fields[ $group ][ $address_2 ]['label_class']   = [];
				$fields[ $group ][ $address_2 ]['placeholder']   = __( 'Unit 4, Makers Yard', 'bhc-commerce-core' );
			}
		}

		return $fields;
	}

	/**
	 * Adjusts the shared address field definitions.
	 *
	 * @param array<string, array<string, mixed>> $fields Address fields.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function customise_address_fields( array $fields ): array {
		if ( isset( $fields['city'] ) ) {
			$fields['city']['label'] = __( 'Town / City', 'bhc-commerce-core' );
		}

		if ( isset( $fields['state'] ) ) {
			$fields['state']['label'] = __( 'State / Province / Region', 'bhc-commerce-core' );
		}

		if ( isset( $fields['country'] ) ) {
			$fields['country']['priority'] = 40;
		}

		return $fields;
	}

	/**
	 * Applies our postcode labels to WooCommerce's country locale table.
	 *
	 * WooCommerce swaps these per country over AJAX when the shopper changes
	 * the country select, so labels stay correct without a page reload.
	 *
	 * @param array<string, array<string, array<string, mixed>>> $locales Country locales.
	 *
	 * @return array<string, array<string, array<string, mixed>>>
	 */
	public function customise_locale( array $locales ): array {
		foreach ( CountryProfile::all() as $country => $profile ) {
			$locales[ $country ]['postcode']['label']       = $profile['label'];
			$locales[ $country ]['postcode']['placeholder'] = $profile['example'];
			$locales[ $country ]['postcode']['required']    = true;
		}

		return $locales;
	}

	/**
	 * Keeps the "My account" address form aligned with checkout.
	 *
	 * @param array<string, array<string, mixed>> $fields Billing fields.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function customise_billing_fields( array $fields ): array {
		if ( isset( $fields['billing_phone'] ) ) {
			$fields['billing_phone']['required'] = true;
			$fields['billing_phone']['label']    = __( 'Phone (with country code)', 'bhc-commerce-core' );
		}

		return $fields;
	}

	/**
	 * Best guess at the customer's country for label purposes.
	 */
	private function current_country(): string {
		if ( function_exists( 'WC' ) && null !== WC()->customer ) {
			$country = (string) WC()->customer->get_shipping_country();

			if ( '' !== $country ) {
				return $country;
			}

			$country = (string) WC()->customer->get_billing_country();

			if ( '' !== $country ) {
				return $country;
			}
		}

		return (string) ( get_option( 'woocommerce_default_customer_address' ) ? substr( (string) get_option( 'woocommerce_default_country', 'US' ), 0, 2 ) : 'US' );
	}
}
