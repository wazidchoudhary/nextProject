<?php
/**
 * Object cache / Redis detection.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Cache;

defined( 'ABSPATH' ) || exit;

/**
 * Reports what caching backend the site is actually running.
 *
 * Nothing here assumes Redis exists: the plugin works on shared hosting with
 * no object cache at all, and this class is what tells the admin screen and
 * `wp bhc health-check` which path is active.
 */
final class RedisStatus {

	/**
	 * Whether a persistent (external) object cache drop-in is active.
	 */
	public function has_persistent_object_cache(): bool {
		return function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache();
	}

	/**
	 * Whether the active object cache is Redis backed.
	 *
	 * Detection is deliberately duck-typed: several drop-ins exist (Redis
	 * Object Cache, Object Cache Pro, custom drop-ins) and none of them share
	 * a common interface.
	 */
	public function is_redis(): bool {
		if ( ! $this->has_persistent_object_cache() ) {
			return false;
		}

		if ( function_exists( 'wp_cache_supports' ) && wp_cache_supports( 'flush_group' ) && class_exists( '\\Redis' ) ) {
			// Not conclusive on its own; combined with the checks below it is.
			$maybe_redis = true;
		}

		global $wp_object_cache;

		if ( is_object( $wp_object_cache ) ) {
			$class = strtolower( get_class( $wp_object_cache ) );

			if ( str_contains( $class, 'redis' ) ) {
				return true;
			}

			if ( property_exists( $wp_object_cache, 'redis' ) ) {
				return true;
			}
		}

		return isset( $maybe_redis ) && defined( 'WP_REDIS_HOST' );
	}

	/**
	 * Whether the PHP Redis extension is loaded.
	 */
	public function extension_loaded(): bool {
		return extension_loaded( 'redis' );
	}

	/**
	 * Human readable summary for the admin health screen.
	 *
	 * @return array<string, string|bool>
	 */
	public function summary(): array {
		global $wp_object_cache;

		return [
			'persistent'        => $this->has_persistent_object_cache(),
			'redis'             => $this->is_redis(),
			'redis_extension'   => $this->extension_loaded(),
			'implementation'    => is_object( $wp_object_cache ) ? get_class( $wp_object_cache ) : 'none',
			'dropin_installed'  => file_exists( WP_CONTENT_DIR . '/object-cache.php' ),
		];
	}
}
