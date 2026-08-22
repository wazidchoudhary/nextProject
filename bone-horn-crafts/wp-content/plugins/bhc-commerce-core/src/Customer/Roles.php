<?php
/**
 * Customer roles.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Customer;

defined( 'ABSPATH' ) || exit;

/**
 * Installs the wholesale customer role.
 *
 * The role is a plain customer with one extra capability, so wholesale buyers
 * keep every standard WooCommerce account feature and the pricing layer only
 * has to ask "can this user see wholesale pricing?".
 */
final class Roles {

	public const WHOLESALE_ROLE = 'bhc_wholesale_customer';
	public const WHOLESALE_CAP  = 'bhc_view_wholesale_pricing';

	/**
	 * Creates or updates the role. Idempotent.
	 */
	public static function install(): void {
		$customer = get_role( 'customer' );

		$capabilities = $customer instanceof \WP_Role ? $customer->capabilities : [ 'read' => true ];

		$capabilities[ self::WHOLESALE_CAP ] = true;

		$existing = get_role( self::WHOLESALE_ROLE );

		if ( null === $existing ) {
			add_role( self::WHOLESALE_ROLE, __( 'Wholesale Customer', 'bhc-commerce-core' ), $capabilities );

			return;
		}

		if ( ! $existing->has_cap( self::WHOLESALE_CAP ) ) {
			$existing->add_cap( self::WHOLESALE_CAP );
		}
	}

	/**
	 * Removes the role. Only used by an explicit uninstall.
	 */
	public static function remove(): void {
		if ( null !== get_role( self::WHOLESALE_ROLE ) ) {
			remove_role( self::WHOLESALE_ROLE );
		}
	}
}
