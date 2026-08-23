<?php
/**
 * PHPUnit bootstrap for the isolated unit suite.
 *
 * The unit suite deliberately runs **without** WordPress. The classes it covers
 * — money arithmetic, address validation, the container, the cache manager, the
 * logger's redaction rules — are the parts that must be provably correct, and
 * they are written so their WordPress touchpoints are either injected or thin
 * enough to stub here.
 *
 * WooCommerce-dependent behaviour is covered by the integration suite in
 * `tests/Integration`, which runs inside a real WordPress + WooCommerce install
 * through `wp eval-file` (see `bin/integration-tests.php`). Splitting the two
 * keeps the unit suite fast enough to run on every save.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

define( 'ABSPATH', __DIR__ . '/' );
define( 'BHC_CORE_VERSION', '1.0.0' );
define( 'BHC_CORE_FILE', dirname( __DIR__ ) . '/bhc-commerce-core.php' );

define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WEEK_IN_SECONDS', 604800 );
define( 'MONTH_IN_SECONDS', 2592000 );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

/**
 * Minimal WordPress function stubs.
 *
 * Only the functions the units under test actually call are defined, and each
 * one behaves like the real thing for the inputs the tests exercise. If a test
 * needs behaviour a stub does not provide, that is a signal the code belongs in
 * the integration suite instead.
 */

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Returns the value unchanged; filters are not part of unit behaviour.
	 *
	 * @param string $hook  Hook name.
	 * @param mixed  $value Value.
	 *
	 * @return mixed
	 */
	function apply_filters( string $hook, $value = null ) {
		return $value;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	/**
	 * No-op action dispatcher.
	 *
	 * @param string $hook Hook name.
	 * @param mixed  ...$args Arguments.
	 */
	function do_action( string $hook, ...$args ): void {}
}

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * No-op filter registration.
	 *
	 * @param string   $hook     Hook name.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority.
	 * @param int      $args     Accepted args.
	 */
	function add_filter( string $hook, $callback, int $priority = 10, int $args = 1 ): bool {
		return true;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * No-op action registration.
	 *
	 * @param string   $hook     Hook name.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority.
	 * @param int      $args     Accepted args.
	 */
	function add_action( string $hook, $callback, int $priority = 10, int $args = 1 ): bool {
		return true;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * Returns the default; the unit suite has no options table.
	 *
	 * @param string $name    Option name.
	 * @param mixed  $default Default value.
	 *
	 * @return mixed
	 */
	function get_option( string $name, $default = false ) {
		unset( $name );

		return $default;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * Escapes for HTML output.
	 *
	 * @param string $text Text.
	 */
	function esc_html( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	/**
	 * Escapes for an attribute value.
	 *
	 * @param string $text Text.
	 */
	function esc_attr( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'get_bloginfo' ) ) {
	/**
	 * Returns a fixed site name.
	 *
	 * @param string $show Which value.
	 */
	function get_bloginfo( string $show = 'name' ): string {
		return 'name' === $show ? 'Bone Horn Crafts' : '';
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * Pass-through translation.
	 *
	 * @param string $text   Text.
	 * @param string $domain Text domain.
	 */
	function __( string $text, string $domain = '' ): string {
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	/**
	 * Pass-through translation with escaping.
	 *
	 * @param string $text   Text.
	 * @param string $domain Text domain.
	 */
	function esc_html__( string $text, string $domain = '' ): string {
		return htmlspecialchars( $text, ENT_QUOTES );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * Lowercase alphanumeric key.
	 *
	 * @param string $key Raw key.
	 */
	function sanitize_key( string $key ): string {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) ) ?? '';
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Strips tags and trims.
	 *
	 * @param string $value Raw value.
	 */
	function sanitize_text_field( string $value ): string {
		return trim( strip_tags( $value ) );
	}
}

if ( ! function_exists( 'absint' ) ) {
	/**
	 * Absolute integer.
	 *
	 * @param mixed $value Raw value.
	 */
	function absint( $value ): int {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * JSON encode.
	 *
	 * @param mixed $data  Data.
	 * @param int   $flags Flags.
	 *
	 * @return string|false
	 */
	function wp_json_encode( $data, int $flags = 0 ) {
		return json_encode( $data, $flags );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	/**
	 * Strips tags.
	 *
	 * @param string $text Text.
	 */
	function wp_strip_all_tags( string $text ): string {
		return trim( strip_tags( $text ) );
	}
}

if ( ! function_exists( 'wp_salt' ) ) {
	/**
	 * Deterministic salt for signature tests.
	 *
	 * @param string $scheme Salt scheme.
	 */
	function wp_salt( string $scheme = 'auth' ): string {
		return 'unit-test-salt-' . $scheme;
	}
}

if ( ! function_exists( 'wc_get_price_decimals' ) ) {
	/**
	 * Price decimals.
	 */
	function wc_get_price_decimals(): int {
		return 2;
	}
}

if ( ! function_exists( 'wc_format_decimal' ) ) {
	/**
	 * Decimal formatting.
	 *
	 * @param mixed $value Raw value.
	 */
	function wc_format_decimal( $value ): string {
		return (string) (float) $value;
	}
}
