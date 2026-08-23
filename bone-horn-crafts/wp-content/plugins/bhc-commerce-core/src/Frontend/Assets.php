<?php
/**
 * Storefront asset loading.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Frontend;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Contracts\HookableInterface;
use BoneHornCrafts\Core\Plugin;
use BoneHornCrafts\Core\Support\Options;
use BoneHornCrafts\Core\Wishlist\WishlistService;

/**
 * Loads the plugin's front-end JavaScript, conditionally.
 *
 * Performance rules applied here:
 *
 * * One small ES module, no framework, no jQuery. It is loaded with `defer`
 *   so it never blocks rendering.
 * * It is only enqueued on pages that can use it (anything with a product,
 *   the shop, the cart, the wishlist page), so a policy page ships zero
 *   JavaScript from this plugin.
 * * Configuration is passed through a single inline JSON blob rather than a
 *   second HTTP request, and it contains no user data beyond the REST nonce.
 */
final class Assets implements HookableInterface {

	public const HANDLE = 'bhc-storefront';

	/**
	 * Constructor.
	 *
	 * @param Plugin          $plugin   Plugin instance.
	 * @param WishlistService $wishlist Wishlist service.
	 * @param Options         $options  Settings.
	 */
	public function __construct(
		private Plugin $plugin,
		private WishlistService $wishlist,
		private Options $options
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ], 20 );
		add_filter( 'script_loader_tag', [ $this, 'add_module_attributes' ], 10, 3 );
	}

	/**
	 * Enqueues the storefront module when the page can use it.
	 */
	public function enqueue(): void {
		if ( ! $this->is_needed() ) {
			return;
		}

		wp_register_script(
			self::HANDLE,
			$this->plugin->url() . 'assets/js/storefront.js',
			[],
			$this->plugin->version(),
			true
		);

		wp_add_inline_script(
			self::HANDLE,
			'window.bhcCommerce = ' . wp_json_encode( $this->config() ) . ';',
			'before'
		);

		wp_enqueue_script( self::HANDLE );

		wp_enqueue_style(
			self::HANDLE,
			$this->plugin->url() . 'assets/css/storefront.css',
			[],
			$this->plugin->version()
		);
	}

	/**
	 * Marks the storefront script as a deferred ES module.
	 *
	 * @param string $tag    Script tag.
	 * @param string $handle Script handle.
	 * @param string $src    Script source.
	 */
	public function add_module_attributes( string $tag, string $handle, string $src ): string {
		if ( self::HANDLE !== $handle ) {
			return $tag;
		}

		return str_replace( '<script ', '<script type="module" ', $tag );
	}

	/**
	 * Whether the current request needs the module.
	 */
	private function is_needed(): bool {
		if ( ! function_exists( 'is_woocommerce' ) ) {
			return false;
		}

		$wishlist_page = (int) get_option( 'bhc_wishlist_page_id', 0 );

		return is_woocommerce()
			|| is_cart()
			|| is_checkout()
			|| is_account_page()
			|| ( $wishlist_page > 0 && is_page( $wishlist_page ) )
			|| is_search()
			|| is_front_page();
	}

	/**
	 * Builds the client configuration payload.
	 *
	 * @return array<string, mixed>
	 */
	private function config(): array {
		return [
			'restUrl'   => esc_url_raw( rest_url( 'bhc/v1/' ) ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'wishlist'  => [
				'enabled' => $this->wishlist->is_available(),
				'count'   => $this->wishlist->count(),
				'ids'     => $this->wishlist->ids(),
			],
			'strings'   => [
				'saved'       => __( 'Saved', 'bhc-commerce-core' ),
				'save'        => __( 'Save', 'bhc-commerce-core' ),
				'error'       => __( 'Something went wrong. Please try again.', 'bhc-commerce-core' ),
				'loading'     => __( 'Loading…', 'bhc-commerce-core' ),
				'noResults'   => __( 'No material matches those filters yet.', 'bhc-commerce-core' ),
				'addedToCart' => __( 'Added to your cart', 'bhc-commerce-core' ),

				// Both forms cross to JavaScript rather than a pre-built
				// string: the count is only known client side, and a language
				// whose plural rules differ from English cannot be served by
				// appending an "s" there.
				'resultOne'   => __( '1 result', 'bhc-commerce-core' ),
				/* translators: %s: number of results. */
				'resultMany'  => __( '%s results', 'bhc-commerce-core' ),
			],
			'currency'  => [
				'code'   => get_woocommerce_currency(),
				'symbol' => html_entity_decode( get_woocommerce_currency_symbol() ),
			],
			'estimator' => $this->options->bool( 'delivery_estimator_enabled' ),
		];
	}
}
