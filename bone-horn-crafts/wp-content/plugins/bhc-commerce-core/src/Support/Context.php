<?php
/**
 * Request context detection.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Describes the kind of request being served.
 *
 * Providers use this to avoid registering admin-only or CLI-only hooks on a
 * front-end page view. Detection is memoised because `wp_doing_ajax()` and
 * friends are called from several providers during bootstrap.
 */
final class Context {

	/**
	 * Memoised REST detection.
	 *
	 * @var bool|null
	 */
	private ?bool $is_rest = null;

	/**
	 * Whether the request targets wp-admin (excluding admin-ajax).
	 */
	public function is_admin(): bool {
		return is_admin() && ! $this->is_ajax();
	}

	/**
	 * Whether the request is an admin-ajax request.
	 */
	public function is_ajax(): bool {
		return function_exists( 'wp_doing_ajax' ) ? wp_doing_ajax() : ( defined( 'DOING_AJAX' ) && DOING_AJAX );
	}

	/**
	 * Whether the request is served by the REST API.
	 *
	 * `REST_REQUEST` is only defined once the REST server starts, so the URI
	 * prefix is inspected as a fallback during `plugins_loaded`.
	 */
	public function is_rest(): bool {
		if ( null !== $this->is_rest ) {
			return $this->is_rest;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			$this->is_rest = true;

			return $this->is_rest;
		}

		$uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) ) : '';

		if ( '' === $uri ) {
			$this->is_rest = false;

			return $this->is_rest;
		}

		$prefix = function_exists( 'rest_get_url_prefix' ) ? rest_get_url_prefix() : 'wp-json';

		$this->is_rest = str_contains( $uri, '/' . $prefix . '/' );

		return $this->is_rest;
	}

	/**
	 * Whether the request runs under WP-CLI.
	 */
	public function is_cli(): bool {
		return defined( 'WP_CLI' ) && WP_CLI;
	}

	/**
	 * Whether the request is a cron run (WP-Cron or Action Scheduler queue runner).
	 */
	public function is_cron(): bool {
		return ( defined( 'DOING_CRON' ) && DOING_CRON ) || ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() );
	}

	/**
	 * Whether the request renders a customer facing page.
	 */
	public function is_frontend(): bool {
		return ! $this->is_admin() && ! $this->is_cli() && ! $this->is_cron() && ! $this->is_rest();
	}

	/**
	 * Whether the site is running in a development environment.
	 */
	public function is_development(): bool {
		return function_exists( 'wp_get_environment_type' ) && in_array( wp_get_environment_type(), [ 'local', 'development' ], true );
	}
}
