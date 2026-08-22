<?php
/**
 * Indexation policy.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\SEO;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Contracts\HookableInterface;

/**
 * Keeps transactional and infinite-permutation URLs out of the index.
 *
 * The rules, and why each one exists:
 *
 * * Cart, checkout, order-received and account pages — per-session content
 *   with no search value, and order-received URLs contain an order key.
 * * Internal search results — thin, duplicative pages that crawl budget is
 *   better spent elsewhere.
 * * Filtered catalogue views (`?material=…&finish=…`) — a facet grid produces
 *   thousands of near-duplicate URLs. They stay crawlable so a shopper's shared
 *   link still works, but they are `noindex, follow` and canonicalise to the
 *   clean archive.
 */
final class RobotsPolicy implements HookableInterface {

	/**
	 * Query parameters that mark a filtered catalogue view.
	 *
	 * @var string[]
	 */
	private const FILTER_PARAMS = [
		'material',
		'finish',
		'application',
		'colour',
		'size',
		'product-type',
		'min_price',
		'max_price',
		'in_stock',
		'on_sale',
		'orderby',
	];

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_filter( 'wp_robots', [ $this, 'filter_robots' ], 20, 1 );
		add_filter( 'robots_txt', [ $this, 'filter_robots_txt' ], 20, 2 );
	}

	/**
	 * Applies the indexation rules.
	 *
	 * @param array<string, bool|string> $robots Robots directives.
	 *
	 * @return array<string, bool|string>
	 */
	public function filter_robots( array $robots ): array {
		if ( $this->is_transactional_page() || is_search() || is_404() ) {
			$robots['noindex']  = true;
			$robots['follow']   = true;
			$robots['noarchive'] = true;

			unset( $robots['index'] );

			return $robots;
		}

		if ( $this->is_filtered_view() ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;

			unset( $robots['index'] );

			return $robots;
		}

		$robots['max-image-preview'] = 'large';
		$robots['max-snippet']       = '-1';

		return $robots;
	}

	/**
	 * Adds disallow rules to the virtual robots.txt.
	 *
	 * @param string $output Existing robots.txt body.
	 * @param bool   $public Whether the site is public.
	 */
	public function filter_robots_txt( string $output, $public ): string {
		if ( ! $public ) {
			return $output;
		}

		$rules = [
			'',
			'# Bone Horn Crafts',
			'Disallow: /cart/',
			'Disallow: /checkout/',
			'Disallow: /my-account/',
			'Disallow: /*add-to-cart=',
			'Disallow: /*?s=',
			'Disallow: /*?orderby=',
			'Allow: /wp-content/uploads/',
			'',
			'Sitemap: ' . esc_url_raw( home_url( '/wp-sitemap.xml' ) ),
		];

		return $output . implode( "\n", $rules ) . "\n";
	}

	/**
	 * Whether the current page is a cart/checkout/account page.
	 */
	public function is_transactional_page(): bool {
		if ( ! function_exists( 'is_cart' ) ) {
			return false;
		}

		return is_cart() || is_checkout() || is_account_page() || is_wc_endpoint_url();
	}

	/**
	 * Whether the current request carries catalogue filter parameters.
	 */
	public function is_filtered_view(): bool {
		foreach ( self::FILTER_PARAMS as $param ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only inspection of the query string.
			if ( isset( $_GET[ $param ] ) && '' !== $_GET[ $param ] ) {
				return true;
			}
		}

		return false;
	}
}
