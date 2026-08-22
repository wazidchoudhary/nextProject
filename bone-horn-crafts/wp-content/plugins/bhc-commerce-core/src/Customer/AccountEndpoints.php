<?php
/**
 * My Account additions.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Customer;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Contracts\HookableInterface;
use BoneHornCrafts\Core\Wishlist\WishlistRenderer;

/**
 * Adds a "Wishlist" tab to the WooCommerce account area.
 *
 * The rewrite rule is only flushed when the endpoint is first registered, not
 * on every request — flushing rewrites on `init` is one of the most common
 * causes of a slow WooCommerce site.
 */
final class AccountEndpoints implements HookableInterface {

	public const ENDPOINT      = 'wishlist';
	private const FLUSH_OPTION = 'bhc_account_endpoints_version';
	private const VERSION      = '2';

	/**
	 * Constructor.
	 *
	 * @param WishlistRenderer $wishlist Wishlist renderer.
	 */
	public function __construct( private WishlistRenderer $wishlist ) {}

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'init', [ $this, 'register_endpoint' ] );
		add_filter( 'woocommerce_account_menu_items', [ $this, 'add_menu_item' ], 10, 1 );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', [ $this, 'render_endpoint' ] );
		add_filter( 'woocommerce_get_query_vars', [ $this, 'add_query_var' ], 10, 1 );
	}

	/**
	 * Registers the rewrite endpoint.
	 */
	public function register_endpoint(): void {
		// EP_PAGES only. Registering at EP_ROOT as well would claim `/wishlist/`
		// at the site root and 404 the standalone wishlist page, which is a
		// genuinely confusing collision to debug.
		add_rewrite_endpoint( self::ENDPOINT, EP_PAGES );

		if ( self::VERSION !== get_option( self::FLUSH_OPTION ) ) {
			update_option( self::FLUSH_OPTION, self::VERSION, false );

			flush_rewrite_rules( false );
		}
	}

	/**
	 * Registers the WooCommerce query var.
	 *
	 * @param array<string, string> $vars Query vars.
	 *
	 * @return array<string, string>
	 */
	public function add_query_var( array $vars ): array {
		$vars[ self::ENDPOINT ] = self::ENDPOINT;

		return $vars;
	}

	/**
	 * Inserts the menu item before "Logout".
	 *
	 * @param array<string, string> $items Menu items.
	 *
	 * @return array<string, string>
	 */
	public function add_menu_item( array $items ): array {
		$logout = $items['customer-logout'] ?? null;

		unset( $items['customer-logout'] );

		$items[ self::ENDPOINT ] = __( 'Wishlist', 'bhc-commerce-core' );

		if ( null !== $logout ) {
			$items['customer-logout'] = $logout;
		}

		return $items;
	}

	/**
	 * Renders the endpoint content.
	 */
	public function render_endpoint(): void {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template escapes its own output.
		echo $this->wishlist->render_page();
	}
}
