<?php
/**
 * Minimal PSR-4 autoloader used when Composer's autoloader is unavailable.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Support;

defined( 'ABSPATH' ) || exit;

/**
 * PSR-4 autoloader.
 *
 * Production deployments run `composer install --no-dev -o` and use the
 * optimised classmap. This fallback keeps the plugin functional when the
 * package is deployed straight from Git (for example on a demo server).
 */
final class Autoloader {

	/**
	 * Registers the autoloader for a namespace prefix.
	 *
	 * @param string $prefix   Namespace prefix, e.g. `BoneHornCrafts\Core`.
	 * @param string $base_dir Absolute directory that maps to the prefix.
	 */
	public static function register( string $prefix, string $base_dir ): void {
		$prefix   = trim( $prefix, '\\' ) . '\\';
		$base_dir = rtrim( $base_dir, '/\\' ) . '/';

		spl_autoload_register(
			static function ( string $class_name ) use ( $prefix, $base_dir ): void {
				if ( ! str_starts_with( $class_name, $prefix ) ) {
					return;
				}

				$relative = substr( $class_name, strlen( $prefix ) );
				$path     = $base_dir . str_replace( '\\', '/', $relative ) . '.php';

				if ( is_readable( $path ) ) {
					require_once $path;
				}
			}
		);
	}
}
