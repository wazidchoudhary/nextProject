<?php
/**
 * Whether the storefront is visible to the public.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Store;

defined( 'ABSPATH' ) || exit;

/**
 * Reports and clears WooCommerce's "Coming soon" mode.
 *
 * WooCommerce ships Coming Soon enabled from the onboarding wizard, and it is
 * the single most dangerous piece of state a live store can carry: it hides the
 * shop from every logged-out visitor and from Googlebot, while an administrator
 * — who is signed in — sees a perfectly normal storefront. Nothing about the
 * admin's own experience reveals it. A store can sit unreachable for weeks and
 * the owner's only clue is silence.
 *
 * Two options decide it. `woocommerce_coming_soon` turns it on;
 * `woocommerce_store_pages_only` narrows it to the shop, cart, checkout,
 * account and product pages, which is why a home page can look live while every
 * page that sells anything is not.
 */
final class StoreVisibility {

	private const COMING_SOON  = 'woocommerce_coming_soon';
	private const STORE_ONLY   = 'woocommerce_store_pages_only';
	private const PRIVATE_LINK = 'woocommerce_private_link';

	/**
	 * Whether Coming Soon is on in any form.
	 */
	public function is_coming_soon(): bool {
		return 'yes' === get_option( self::COMING_SOON, 'no' );
	}

	/**
	 * Whether Coming Soon covers only the store pages rather than the whole site.
	 */
	public function is_store_pages_only(): bool {
		return $this->is_coming_soon() && 'yes' === get_option( self::STORE_ONLY, 'no' );
	}

	/**
	 * A short description of what the public can currently see.
	 */
	public function describe(): string {
		if ( ! $this->is_coming_soon() ) {
			return __( 'Live. The storefront is visible to everyone.', 'bhc-commerce-core' );
		}

		if ( $this->is_store_pages_only() ) {
			return __( 'Coming soon, store pages only. Shop, cart, checkout, account and product pages are hidden from logged-out visitors and from search engines; the rest of the site is visible. Signed in, you see the real store, which is why this is easy to miss.', 'bhc-commerce-core' );
		}

		return __( 'Coming soon, whole site. Nothing is visible to logged-out visitors or to search engines. Signed in, you see the real site, which is why this is easy to miss.', 'bhc-commerce-core' );
	}

	/**
	 * Takes the store live.
	 *
	 * Only clears the flags; it never turns Coming Soon on, because that is a
	 * deliberate act and not something a deploy should be able to do.
	 *
	 * @return bool Whether anything changed.
	 */
	public function go_live(): bool {
		if ( ! $this->is_coming_soon() ) {
			return false;
		}

		update_option( self::COMING_SOON, 'no' );
		update_option( self::STORE_ONLY, 'no' );
		update_option( self::PRIVATE_LINK, 'no' );

		return true;
	}
}
