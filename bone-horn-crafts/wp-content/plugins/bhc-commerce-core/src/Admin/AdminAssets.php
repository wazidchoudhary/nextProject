<?php
/**
 * Admin assets.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Admin;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Contracts\HookableInterface;
use BoneHornCrafts\Core\Plugin;
use BoneHornCrafts\Core\Product\Admin\QuickImageColumn;

/**
 * Loads the small admin stylesheet and the price-tier repeater script.
 *
 * Both are enqueued only on the screens that need them: the plugin adds zero
 * bytes to every other admin page.
 */
final class AdminAssets implements HookableInterface {

	/**
	 * Constructor.
	 *
	 * @param Plugin           $plugin Plugin instance (for asset URLs).
	 * @param QuickImageColumn $images List-table image editor.
	 */
	public function __construct( private Plugin $plugin, private QuickImageColumn $images ) {}

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	/**
	 * Enqueues assets on the relevant screens.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public function enqueue( string $hook_suffix ): void {
		$screen     = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$is_plugin  = str_contains( $hook_suffix, AdminMenu::SLUG );
		$is_product = null !== $screen && 'product' === $screen->id;
		$is_list    = null !== $screen && 'edit-product' === $screen->id;

		if ( ! $is_plugin && ! $is_product && ! $is_list ) {
			return;
		}

		wp_enqueue_style(
			'bhc-admin',
			$this->plugin->url() . 'assets/css/admin.css',
			[],
			$this->plugin->version()
		);

		if ( $is_product ) {
			wp_enqueue_script(
				'bhc-admin-product',
				$this->plugin->url() . 'assets/js/admin-product.js',
				[],
				$this->plugin->version(),
				true
			);
		}

		if ( ! $is_list ) {
			return;
		}

		// The media modal is not on the products list screen by default; it is
		// several hundred KB, so it is requested only here.
		wp_enqueue_media();

		wp_enqueue_script(
			'bhc-admin-quick-image',
			$this->plugin->url() . 'assets/js/admin-quick-image.js',
			[ 'media-editor' ],
			$this->plugin->version(),
			true
		);

		wp_add_inline_script(
			'bhc-admin-quick-image',
			'window.bhcQuickImage = ' . wp_json_encode( $this->images->script_data() ) . ';',
			'before'
		);
	}
}
