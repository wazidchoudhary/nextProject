<?php
/**
 * Capability helpers.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Central place for every capability the plugin checks.
 *
 * Capabilities are named, never hard-coded role names: a shop with a custom
 * "Merchandiser" role only has to grant `manage_bhc_commerce`.
 */
final class Capabilities {

	public const MANAGE_COMMERCE = 'manage_bhc_commerce';
	public const EDIT_PRODUCTS   = 'edit_products';
	public const EDIT_ORDERS     = 'edit_shop_orders';
	public const VIEW_REPORTS    = 'view_woocommerce_reports';

	/**
	 * Whether the current user may manage plugin settings and jobs.
	 */
	public static function can_manage(): bool {
		return current_user_can( self::MANAGE_COMMERCE ) || current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Whether the current user may edit a specific product.
	 *
	 * @param int $product_id Product id.
	 */
	public static function can_edit_product( int $product_id ): bool {
		return $product_id > 0 && current_user_can( 'edit_post', $product_id );
	}

	/**
	 * Whether the current user may read a specific order.
	 *
	 * @param \WC_Order $order Order object.
	 */
	public static function can_read_order( \WC_Order $order ): bool {
		if ( current_user_can( self::EDIT_ORDERS ) ) {
			return true;
		}

		$customer_id = $order->get_customer_id();

		return $customer_id > 0 && get_current_user_id() === $customer_id;
	}

	/**
	 * Grants the plugin capability to shop managers and administrators.
	 *
	 * Called once from the schema installer, not on every request.
	 */
	public static function install(): void {
		foreach ( [ 'administrator', 'shop_manager' ] as $role_name ) {
			$role = get_role( $role_name );

			if ( $role && ! $role->has_cap( self::MANAGE_COMMERCE ) ) {
				$role->add_cap( self::MANAGE_COMMERCE );
			}
		}
	}
}
