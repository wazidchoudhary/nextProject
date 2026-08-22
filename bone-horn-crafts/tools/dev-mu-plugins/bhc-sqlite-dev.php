<?php
/**
 * Plugin Name: Bone Horn Crafts — SQLite development shims
 * Description: Compensates for the two WooCommerce queries that the SQLite emulation layer cannot execute. DEVELOPMENT ONLY — never deploy this to a MySQL/MariaDB environment.
 * Version:     1.0.0
 *
 * Background
 * ----------
 * The reference environment for this build runs WordPress on SQLite (via the
 * WordPress SQLite Database Integration plugin) so the whole store can be
 * installed without a database server. Almost everything works unchanged. The
 * exception is WooCommerce's checkout stock reservation, which issues:
 *
 *     INSERT INTO wc_reserved_stock ( ... )
 *     SELECT ... FROM DUAL
 *     WHERE ( SELECT COALESCE( SUM( stock_quantity ), 0 ) ... ) + %d <= ( ... )
 *     ON DUPLICATE KEY UPDATE ...
 *
 * `FROM DUAL`, `INTERVAL` arithmetic and `ON DUPLICATE KEY UPDATE` do not
 * survive the translation layer, so the statement affects zero rows and
 * WooCommerce reports "Not enough units ... are available in stock" for a
 * product that is plainly in stock.
 *
 * Rather than patch WooCommerce, this shim uses WooCommerce's own supported
 * switch: `woocommerce_order_hold_stock_minutes` returning 0 skips the
 * reservation entirely (the same effect as clearing "Hold stock (minutes)" in
 * WooCommerce → Settings → Products → Inventory).
 *
 * On MySQL this file must not be installed: stock reservation is a real
 * protection against overselling during concurrent checkouts.
 *
 * @package BHC_Dev
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

add_filter(
	'woocommerce_order_hold_stock_minutes',
	static function (): int {
		return 0;
	},
	10,
	0
);

add_action(
	'admin_notices',
	static function (): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p><strong>%s</strong> %s</p></div>',
			esc_html__( 'SQLite development shims are active.', 'bhc-theme' ),
			esc_html__( 'Checkout stock reservation is disabled because the SQLite layer cannot run WooCommerce\'s reservation query. Do not deploy this mu-plugin to production.', 'bhc-theme' )
		);
	}
);
