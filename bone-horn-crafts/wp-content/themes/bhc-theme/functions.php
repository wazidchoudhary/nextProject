<?php
/**
 * Theme bootstrap.
 *
 * Business logic lives in the `bhc-commerce-core` plugin. This file only wires
 * up presentation concerns, and it does so by loading focused modules rather
 * than accumulating functions — a `functions.php` that grows past a hundred
 * lines is where themes go to die.
 *
 * @package BHC_Theme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

define( 'BHC_THEME_VERSION', '1.1.0' );
define( 'BHC_THEME_DIR', get_template_directory() );
define( 'BHC_THEME_URI', get_template_directory_uri() );

/**
 * Theme modules, in load order.
 *
 * @var string[] $bhc_theme_modules
 */
$bhc_theme_modules = [
	'setup',
	'enqueue',
	'performance',
	'security',
	'seo',
	'template-tags',
	'woocommerce',
];

foreach ( $bhc_theme_modules as $bhc_theme_module ) {
	$bhc_theme_module_path = BHC_THEME_DIR . '/inc/' . $bhc_theme_module . '.php';

	if ( is_readable( $bhc_theme_module_path ) ) {
		require_once $bhc_theme_module_path;
	}
}

unset( $bhc_theme_modules, $bhc_theme_module, $bhc_theme_module_path );
