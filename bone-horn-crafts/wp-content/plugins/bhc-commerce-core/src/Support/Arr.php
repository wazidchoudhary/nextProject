<?php
/**
 * Array helpers.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Small, dependency free array utilities.
 */
final class Arr {

	/**
	 * Returns a value from a nested array using dot notation.
	 *
	 * @param array<string, mixed> $array_data Source array.
	 * @param string               $key        Dot separated key.
	 * @param mixed                $default    Fallback value.
	 *
	 * @return mixed
	 */
	public static function get( array $array_data, string $key, mixed $default = null ): mixed {
		$segments = explode( '.', $key );
		$value    = $array_data;

		foreach ( $segments as $segment ) {
			if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
				return $default;
			}

			$value = $value[ $segment ];
		}

		return $value;
	}

	/**
	 * Casts a mixed value to a list of positive integers.
	 *
	 * @param mixed $value Raw value (array, CSV string or scalar).
	 *
	 * @return int[]
	 */
	public static function to_int_list( mixed $value ): array {
		if ( is_string( $value ) ) {
			$value = explode( ',', $value );
		}

		if ( ! is_array( $value ) ) {
			$value = [ $value ];
		}

		$ints = array_map( 'absint', $value );

		return array_values( array_unique( array_filter( $ints ) ) );
	}

	/**
	 * Sorts an id => score map by score descending and returns the top N ids.
	 *
	 * @param array<int, float> $scores Score map.
	 * @param int               $limit  Maximum number of ids.
	 *
	 * @return int[]
	 */
	public static function top_scores( array $scores, int $limit ): array {
		arsort( $scores, SORT_NUMERIC );

		return array_slice( array_keys( $scores ), 0, max( 0, $limit ), false );
	}
}
