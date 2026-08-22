<?php
/**
 * Plugin Name:       Bone Horn Crafts Commerce Core
 * Plugin URI:        https://www.bonehorncrafts.com/
 * Description:       Business logic for the Bone Horn Crafts store: merchandising badges, craft attributes, tiered pricing, wishlist, recommendations, faceted search, export-ready order metadata, REST API, caching, background jobs and WP-CLI tooling.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.2
 * Author:            Bone Horn Crafts
 * Author URI:        https://www.bonehorncrafts.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bhc-commerce-core
 * Domain Path:       /languages
 * WC requires at least: 8.0
 * WC tested up to:   10.9
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core;

defined( 'ABSPATH' ) || exit;

const VERSION      = '1.0.0';
const MIN_PHP      = '8.2';
const MIN_WP       = '6.5';
const MIN_WC       = '8.0';
const PLUGIN_FILE  = __FILE__;
const TEXT_DOMAIN  = 'bhc-commerce-core';

if ( ! defined( 'BHC_CORE_VERSION' ) ) {
	define( 'BHC_CORE_VERSION', VERSION );
	define( 'BHC_CORE_FILE', __FILE__ );
	define( 'BHC_CORE_DIR', plugin_dir_path( __FILE__ ) );
	define( 'BHC_CORE_URL', plugin_dir_url( __FILE__ ) );
	define( 'BHC_CORE_BASENAME', plugin_basename( __FILE__ ) );
}

require_once __DIR__ . '/src/Support/Autoloader.php';

/**
 * Bootstraps the plugin once the environment has been validated.
 *
 * Procedural code is deliberately limited to this file: everything else lives
 * behind the service container in `src/`.
 */
function bootstrap(): void {
	$requirements = new Support\Requirements(
		[
			'php' => MIN_PHP,
			'wp'  => MIN_WP,
			'wc'  => MIN_WC,
		]
	);

	if ( ! $requirements->satisfied() ) {
		add_action( 'admin_notices', [ $requirements, 'render_notice' ] );

		return;
	}

	Plugin::instance( __FILE__ )->boot();
}

// Register the PSR-4 autoloader before anything else touches a class name.
( static function (): void {
	$composer = __DIR__ . '/vendor/autoload.php';

	if ( is_readable( $composer ) ) {
		require_once $composer;

		return;
	}

	Support\Autoloader::register( __NAMESPACE__, __DIR__ . '/src' );
} )();

// WooCommerce feature compatibility declarations (HPOS, cart/checkout blocks).
add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( ! class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			return;
		}

		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
	}
);

add_action( 'plugins_loaded', __NAMESPACE__ . '\\bootstrap', 5 );

register_activation_hook( __FILE__, [ Database\Installer::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ Database\Installer::class, 'deactivate' ] );
