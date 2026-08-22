<?php
/**
 * String helpers.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Small, dependency free string utilities.
 */
final class Str {

	/**
	 * Truncates text on a word boundary.
	 *
	 * @param string $text   Source text.
	 * @param int    $length Maximum length in characters.
	 * @param string $append Suffix appended when truncated.
	 */
	public static function truncate( string $text, int $length, string $append = '…' ): string {
		$text = trim( wp_strip_all_tags( $text ) );

		if ( mb_strlen( $text ) <= $length ) {
			return $text;
		}

		$cut  = mb_substr( $text, 0, $length );
		$last = mb_strrpos( $cut, ' ' );

		if ( false !== $last && $last > (int) ( $length * 0.6 ) ) {
			$cut = mb_substr( $cut, 0, $last );
		}

		return rtrim( $cut, ' ,.;:-' ) . $append;
	}

	/**
	 * Builds a deterministic cache-safe key fragment from mixed parts.
	 *
	 * @param mixed ...$parts Key parts.
	 */
	public static function key_fragment( mixed ...$parts ): string {
		$normalised = array_map(
			static function ( mixed $part ): string {
				if ( is_scalar( $part ) || null === $part ) {
					return (string) $part;
				}

				return (string) wp_json_encode( $part );
			},
			$parts
		);

		$joined = implode( '|', $normalised );

		return strlen( $joined ) > 64 ? md5( $joined ) : sanitize_key( $joined );
	}
}
