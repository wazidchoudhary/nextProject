<?php
/**
 * WP-CLI commands.
 *
 * @package BoneHornCrafts\Newsletter
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Newsletter\CLI;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Newsletter\Repository\SubscriberRepository;
use BoneHornCrafts\Newsletter\Service\CsvExporter;
use WP_CLI;

/**
 * `wp bhc newsletter` — inspect and export the subscriber list.
 *
 * The admin export is a browser download, which is the wrong shape for a cron
 * job or a backup script. These commands write to a path or to stdout.
 */
final class SubscribersCommand {

	/**
	 * Constructor.
	 *
	 * @param SubscriberRepository $repository Storage.
	 * @param CsvExporter          $exporter   CSV export.
	 */
	public function __construct(
		private SubscriberRepository $repository,
		private CsvExporter $exporter
	) {}

	/**
	 * Exports subscribers as CSV.
	 *
	 * ## OPTIONS
	 *
	 * [--status=<status>]
	 * : Only export this state.
	 * ---
	 * options:
	 *   - pending
	 *   - confirmed
	 *   - unsubscribed
	 * ---
	 *
	 * [--file=<path>]
	 * : Write here instead of stdout.
	 *
	 * ## EXAMPLES
	 *
	 *     wp bhc newsletter export --status=confirmed
	 *     wp bhc newsletter export --file=/backup/subscribers.csv
	 *
	 * @param string[]              $args       Positional arguments.
	 * @param array<string, string> $assoc_args Options.
	 */
	public function export( array $args, array $assoc_args ): void {
		$status = (string) WP_CLI\Utils\get_flag_value( $assoc_args, 'status', '' );
		$file   = (string) WP_CLI\Utils\get_flag_value( $assoc_args, 'file', '' );

		// WP_Filesystem cannot stream: it reads and writes whole files, which is
		// precisely what an unbounded export must not do. A direct handle is
		// the only way to emit rows as they are read.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$handle = '' === $file ? fopen( 'php://stdout', 'w' ) : fopen( $file, 'w' );

		if ( false === $handle ) {
			WP_CLI::error( sprintf( 'Could not open %s for writing.', '' === $file ? 'stdout' : $file ) );
		}

		$written = $this->exporter->write( $handle, [ 'status' => $status ] );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $handle );

		if ( '' !== $file ) {
			WP_CLI::success( sprintf( '%d subscriber(s) written to %s.', $written, $file ) );
		}
	}

	/**
	 * Shows how many subscribers are in each state.
	 *
	 * ## EXAMPLES
	 *
	 *     wp bhc newsletter status
	 *
	 * @param string[]              $args       Positional arguments.
	 * @param array<string, string> $assoc_args Options.
	 */
	public function status( array $args, array $assoc_args ): void {
		$counts = $this->repository->counts();
		$rows   = [];

		foreach ( $counts as $state => $count ) {
			$rows[] = [
				'status' => $state,
				'count'  => $count,
			];
		}

		$rows[] = [
			'status' => 'total',
			'count'  => array_sum( $counts ),
		];

		WP_CLI\Utils\format_items( 'table', $rows, [ 'status', 'count' ] );
	}
}
