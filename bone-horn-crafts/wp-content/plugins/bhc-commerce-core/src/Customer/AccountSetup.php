<?php
/**
 * Customer account configuration.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Customer;

defined( 'ABSPATH' ) || exit;

/**
 * Turns on customer registration.
 *
 * This lived in the demo seeder, which was the wrong home for it. Whether a
 * store lets people create accounts is store policy, not sample data — so a
 * shop that imported a real catalogue and never ran the seeder got the code and
 * none of the settings, and My Account went on offering a login form with no
 * way to create the account it was asking people to sign into.
 *
 * Three switches have to agree and they are independent, which is why this
 * keeps being missed: WordPress's own `users_can_register` gates it at the
 * platform level, WooCommerce's `..._myaccount_registration` controls the My
 * Account page, and `..._signup_and_login_from_checkout` controls the checkout.
 * Setting one and testing that page makes the other two look fine.
 *
 * Applied once, on activation, and recorded — so a merchant who later decides
 * to turn registration off is not overruled on the next plugin update. Re-apply
 * deliberately with `wp bhc setup accounts`.
 */
final class AccountSetup {

	/**
	 * Option recording that the defaults have been applied.
	 */
	private const APPLIED_OPTION = 'bhc_accounts_configured';

	/**
	 * Settings that make accounts work, as option => value.
	 */
	private const SETTINGS = [
		'users_can_register'                         => 1,
		'default_role'                               => 'customer',
		'woocommerce_enable_myaccount_registration'  => 'yes',
		'woocommerce_enable_signup_and_login_from_checkout' => 'yes',
		'woocommerce_enable_checkout_login_reminder' => 'yes',
		'woocommerce_registration_generate_username' => 'yes',
		'woocommerce_registration_generate_password' => 'yes',

		// Guest checkout stays on. Forcing an account before a first order is
		// the most reliable way to lose one.
		'woocommerce_enable_guest_checkout'          => 'yes',

		// A customer who just registered belongs in their account, not in
		// wp-admin, and should never see the admin bar.
		'woocommerce_lock_down_admin'                => 'yes',
	];

	/**
	 * Applies the defaults unless they have been applied before.
	 *
	 * @return bool Whether anything was written.
	 */
	public function apply_once(): bool {
		if ( '' !== (string) get_option( self::APPLIED_OPTION, '' ) ) {
			return false;
		}

		$this->apply();

		return true;
	}

	/**
	 * Applies the defaults unconditionally.
	 *
	 * @return array<string, mixed> The settings that were written.
	 */
	public function apply(): array {
		foreach ( self::SETTINGS as $option => $value ) {
			update_option( $option, $value );
		}

		update_option( self::APPLIED_OPTION, gmdate( 'c' ), false );

		return self::SETTINGS;
	}

	/**
	 * Reports which settings do not currently match.
	 *
	 * @return array<string, array{expected: mixed, actual: mixed}>
	 */
	public function drift(): array {
		$drift = [];

		foreach ( self::SETTINGS as $option => $expected ) {
			$actual = get_option( $option );

			if ( (string) $actual !== (string) $expected ) {
				$drift[ $option ] = [
					'expected' => $expected,
					'actual'   => $actual,
				];
			}
		}

		return $drift;
	}

	/**
	 * Whether registration is currently possible.
	 */
	public function registration_open(): bool {
		return (bool) get_option( 'users_can_register' )
			&& 'yes' === (string) get_option( 'woocommerce_enable_myaccount_registration' );
	}
}
