<?php
/**
 * Plugin Name:       Bone Horn Crafts Newsletter
 * Plugin URI:        https://www.bonehorncrafts.com/
 * Description:       Double opt-in newsletter subscriptions with a subscriber table, admin list and CSV export.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.2
 * Author:            Bone Horn Crafts
 * Author URI:        https://www.bonehorncrafts.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bhc-newsletter
 * Domain Path:       /languages
 *
 * @package BoneHornCrafts\Newsletter
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Newsletter;

defined( 'ABSPATH' ) || exit;

const VERSION = '1.0.0';

/**
 * PSR-4 autoloader.
 *
 * Hand-written rather than Composer's: this plugin has no production
 * dependencies, so `vendor/` would contain nothing but a generated autoloader,
 * and requiring a build step to install a plugin is a cost with no return.
 *
 * @param string $class_name Fully qualified class name.
 */
spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = __NAMESPACE__ . '\\';

		if ( ! str_starts_with( $class_name, $prefix ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( $prefix ) );
		$path     = __DIR__ . '/src/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

/**
 * Boots the plugin once WordPress and its plugins are loaded.
 */
add_action(
	'plugins_loaded',
	static function (): void {
		if ( version_compare( PHP_VERSION, '8.2', '<' ) ) {
			add_action(
				'admin_notices',
				static function (): void {
					printf(
						'<div class="notice notice-error"><p>%s</p></div>',
						esc_html__( 'Bone Horn Crafts Newsletter needs PHP 8.2 or newer.', 'bhc-newsletter' )
					);
				}
			);

			return;
		}

		Plugin::instance( __FILE__ )->boot();
	},
	5
);

register_activation_hook( __FILE__, [ Plugin::class, 'activate' ] );
