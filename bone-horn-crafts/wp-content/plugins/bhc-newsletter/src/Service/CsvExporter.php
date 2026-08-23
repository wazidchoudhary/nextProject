<?php
/**
 * Subscriber CSV export.
 *
 * @package BoneHornCrafts\Newsletter
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Newsletter\Service;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Newsletter\Domain\Subscriber;
use BoneHornCrafts\Newsletter\Repository\SubscriberRepository;

/**
 * Streams the subscriber list as CSV.
 *
 * Written to a stream rather than built as a string: the list is unbounded, and
 * an export that works for 500 subscribers and dies silently at 200,000 is
 * worse than one that never worked, because nobody finds out until they need
 * the data.
 */
final class CsvExporter {

	/**
	 * Column order in the exported file.
	 */
	private const COLUMNS = [ 'email', 'status', 'source', 'subscribed', 'confirmed', 'unsubscribed' ];

	/**
	 * Constructor.
	 *
	 * @param SubscriberRepository $repository Storage.
	 */
	public function __construct( private SubscriberRepository $repository ) {}

	/**
	 * Writes CSV rows to an open stream.
	 *
	 * @param resource             $handle Open, writable stream.
	 * @param array<string, mixed> $args   Filters passed to the repository.
	 *
	 * @return int Rows written, excluding the header.
	 */
	public function write( $handle, array $args = [] ): int {
		self::put( $handle, self::COLUMNS );

		return $this->repository->each(
			static function ( Subscriber $subscriber ) use ( $handle ): void {
				$row = $subscriber->to_export_row();

				self::put(
					$handle,
					array_map(
						static fn ( string $column ): string => self::defuse( $row[ $column ] ?? '' ),
						self::COLUMNS
					)
				);
			},
			$args
		);
	}

	/**
	 * Suggests a filename for a download.
	 *
	 * @param string $status Status filter, or an empty string for all.
	 */
	public function filename( string $status = '' ): string {
		return sprintf(
			'bhc-subscribers-%s%s.csv',
			'' === $status ? 'all' : sanitize_key( $status ) . '-',
			gmdate( 'Y-m-d' )
		);
	}

	/**
	 * Writes one CSV row.
	 *
	 * The escape parameter is passed explicitly because PHP 8.4 deprecates
	 * relying on its default and will change it in 9.0. An empty string
	 * disables PHP's non-standard backslash escaping, which is also the correct
	 * choice on its own merits: RFC 4180 escapes a quote by doubling it, and
	 * the legacy behaviour produces files Excel reads back wrongly whenever a
	 * value contains a backslash.
	 *
	 * @param resource $handle Open, writable stream.
	 * @param string[] $row    Row values.
	 */
	private static function put( $handle, array $row ): void {
		fputcsv( $handle, $row, ',', '"', '' );
	}

	/**
	 * Neutralises spreadsheet formula injection.
	 *
	 * Excel, Numbers and Sheets all evaluate a cell beginning `=`, `+`, `-` or
	 * `@` as a formula. Since every value here originates from a public form,
	 * an address like `=HYPERLINK("http://evil","click")` becomes a live link
	 * the moment somebody opens the export. Prefixing a tab keeps the value
	 * readable and stops it being parsed.
	 *
	 * @param string $value Cell value.
	 */
	private static function defuse( string $value ): string {
		return '' !== $value && str_contains( "=+-@\t\r", $value[0] )
			? "\t" . $value
			: $value;
	}
}
