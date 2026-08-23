<?php
/**
 * PayPal credential bridge.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Payments;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Contracts\HookableInterface;

/**
 * Feeds PayPal credentials from wp-config constants rather than the database.
 *
 * WooCommerce PayPal Payments keeps its client id and secret in the
 * `woocommerce-ppcp-settings` option. That is convenient and it is the wrong
 * place for a live secret: options end up in every database dump, every staging
 * clone made from production, and every backup that gets emailed around. A
 * secret that can move money should live where the rest of the deployment
 * secrets live — in `wp-config.php`, outside the repository, different per
 * environment.
 *
 * The bridge works in both directions, and the second half is the part people
 * forget:
 *
 * - **On read**, the constants are merged into the option, so the gateway sees
 *   credentials it never had to store.
 * - **On write**, those same keys are stripped back out before the option is
 *   saved, so opening the settings screen and pressing Save does not quietly
 *   persist to the database exactly what this class exists to keep out of it.
 *
 * Defining nothing changes nothing: without the constants the plugin behaves
 * normally and stores its own credentials, which is the right default for a
 * store that connects through PayPal's onboarding button.
 */
final class PayPalCredentials implements HookableInterface {

	/**
	 * Option the gateway stores its settings in.
	 */
	private const OPTION = 'woocommerce-ppcp-settings';

	/**
	 * Constant name => settings key.
	 */
	private const MAP = [
		'BHC_PAYPAL_CLIENT_ID'     => 'client_id',
		'BHC_PAYPAL_CLIENT_SECRET' => 'client_secret',
		'BHC_PAYPAL_MERCHANT_ID'   => 'merchant_id',
		'BHC_PAYPAL_MERCHANT_MAIL' => 'merchant_email',
	];

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		if ( ! $this->has_any() ) {
			return;
		}

		add_filter( 'option_' . self::OPTION, [ $this, 'inject' ] );
		add_filter( 'default_option_' . self::OPTION, [ $this, 'inject' ] );
		add_filter( 'pre_update_option_' . self::OPTION, [ $this, 'strip' ] );
	}

	/**
	 * Merges the configured constants into the stored settings.
	 *
	 * @param mixed $settings Stored option value.
	 *
	 * @return mixed
	 */
	public function inject( mixed $settings ): mixed {
		if ( ! is_array( $settings ) ) {
			$settings = [];
		}

		foreach ( self::MAP as $constant => $key ) {
			$value = $this->constant( $constant );

			if ( '' !== $value ) {
				$settings[ $key ] = $value;
			}
		}

		// Live unless the environment says otherwise. A store that has been
		// given production credentials and is quietly running in sandbox takes
		// orders that never settle, and nothing on the storefront says so.
		if ( ! isset( $settings['sandbox_on'] ) ) {
			$settings['sandbox_on'] = defined( 'BHC_PAYPAL_SANDBOX' ) && BHC_PAYPAL_SANDBOX;
		}

		return $settings;
	}

	/**
	 * Removes constant-provided values before the option is written.
	 *
	 * @param mixed $settings Value about to be saved.
	 *
	 * @return mixed
	 */
	public function strip( mixed $settings ): mixed {
		if ( ! is_array( $settings ) ) {
			return $settings;
		}

		foreach ( self::MAP as $constant => $key ) {
			if ( '' !== $this->constant( $constant ) ) {
				unset( $settings[ $key ] );
			}
		}

		return $settings;
	}

	/**
	 * Whether any credential constant is defined.
	 */
	public function has_any(): bool {
		foreach ( array_keys( self::MAP ) as $constant ) {
			if ( '' !== $this->constant( $constant ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether a usable pair of credentials is configured.
	 */
	public function is_configured(): bool {
		return '' !== $this->constant( 'BHC_PAYPAL_CLIENT_ID' )
			&& '' !== $this->constant( 'BHC_PAYPAL_CLIENT_SECRET' );
	}

	/**
	 * Whether the configured credentials are sandbox ones.
	 */
	public function is_sandbox(): bool {
		return defined( 'BHC_PAYPAL_SANDBOX' ) && (bool) constant( 'BHC_PAYPAL_SANDBOX' );
	}

	/**
	 * Reads a constant as a trimmed string.
	 *
	 * @param string $name Constant name.
	 */
	private function constant( string $name ): string {
		if ( ! defined( $name ) ) {
			return '';
		}

		$value = constant( $name );

		return is_string( $value ) ? trim( $value ) : '';
	}
}
