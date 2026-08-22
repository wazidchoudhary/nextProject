<?php
/**
 * Input sanitisation helpers.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Typed sanitisers used by every entry point (REST, admin POST, cookies).
 *
 * Having one place for this means a new field cannot accidentally skip
 * sanitisation, and the rules are unit-testable in isolation.
 */
final class Sanitizer {

	/**
	 * Sanitises a positive integer.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function id( mixed $value ): int {
		return absint( $value );
	}

	/**
	 * Sanitises a list of positive integers with a hard cap.
	 *
	 * The cap matters: an unbounded id list becomes an unbounded `IN ()` query.
	 *
	 * @param mixed $value Raw value (array or CSV string).
	 * @param int   $max   Maximum number of ids kept.
	 *
	 * @return int[]
	 */
	public static function id_list( mixed $value, int $max = 100 ): array {
		if ( is_string( $value ) ) {
			$value = explode( ',', $value );
		}

		if ( ! is_array( $value ) ) {
			$value = [ $value ];
		}

		$ids = array_values( array_unique( array_filter( array_map( 'absint', $value ) ) ) );

		return array_slice( $ids, 0, max( 1, $max ) );
	}

	/**
	 * Sanitises a short free-text value.
	 *
	 * @param mixed $value  Raw value.
	 * @param int   $length Maximum length.
	 */
	public static function text( mixed $value, int $length = 200 ): string {
		$clean = sanitize_text_field( (string) ( is_scalar( $value ) ? $value : '' ) );

		return mb_substr( $clean, 0, $length );
	}

	/**
	 * Sanitises a slug/key.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function key( mixed $value ): string {
		return sanitize_key( (string) ( is_scalar( $value ) ? $value : '' ) );
	}

	/**
	 * Sanitises a list of slugs.
	 *
	 * @param mixed $value Raw value (array or CSV string).
	 * @param int   $max   Maximum number of slugs kept.
	 *
	 * @return string[]
	 */
	public static function slug_list( mixed $value, int $max = 30 ): array {
		if ( is_string( $value ) ) {
			$value = explode( ',', $value );
		}

		if ( ! is_array( $value ) ) {
			return [];
		}

		$slugs = array_filter( array_map( [ self::class, 'key' ], $value ) );

		return array_slice( array_values( array_unique( $slugs ) ), 0, max( 1, $max ) );
	}

	/**
	 * Sanitises a monetary amount.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function amount( mixed $value ): float {
		$number = is_numeric( $value ) ? (float) $value : 0.0;

		return round( max( 0.0, $number ), (int) ( function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2 ) );
	}

	/**
	 * Sanitises rich text intended for the storefront.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function rich_text( mixed $value ): string {
		return wp_kses(
			(string) ( is_scalar( $value ) ? $value : '' ),
			[
				'p'      => [],
				'br'     => [],
				'strong' => [],
				'em'     => [],
				'ul'     => [],
				'ol'     => [],
				'li'     => [],
				'a'      => [
					'href'   => [],
					'title'  => [],
					'rel'    => [],
					'target' => [],
				],
			]
		);
	}

	/**
	 * Sanitises an ISO country code.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function country( mixed $value ): string {
		$code = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) ( is_scalar( $value ) ? $value : '' ) ) ?? '' );

		return 2 === strlen( $code ) ? $code : '';
	}

	/**
	 * Sanitises a postcode-ish string (letters, digits, spaces and hyphens).
	 *
	 * @param mixed $value Raw value.
	 */
	public static function postcode( mixed $value ): string {
		$clean = strtoupper( trim( (string) ( is_scalar( $value ) ? $value : '' ) ) );
		$clean = preg_replace( '/[^A-Z0-9\- ]/', '', $clean ) ?? '';

		return mb_substr( $clean, 0, 12 );
	}
}
