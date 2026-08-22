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
	 * @param Plugin $plugin Plugin instance (for asset URLs).
	 */
	public function __construct( private Plugin $plugin ) {}

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
		$screen    = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$is_plugin = str_contains( $hook_suffix, AdminMenu::SLUG );
		$is_product = null !== $screen && 'product' === $screen->id;

		if ( ! $is_plugin && ! $is_product ) {
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
	}
}
