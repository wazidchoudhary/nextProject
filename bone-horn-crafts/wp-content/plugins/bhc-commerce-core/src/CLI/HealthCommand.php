<?php
/**
 * Health check command.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\CLI;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Admin\HealthReport;
use WP_CLI;

/**
 * `wp bhc health-check` — deployment smoke test.
 *
 * Exits non-zero when any check fails, so it can gate a deployment pipeline.
 */
final class HealthCommand {

	/**
	 * Constructor.
	 *
	 * @param HealthReport $report Health report.
	 */
	public function __construct( private HealthReport $report ) {}

	/**
	 * Runs the health check.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 * ---
	 *
	 * [--strict]
	 * : Exit with an error when any check reports a warning.
	 *
	 * ## EXAMPLES
	 *
	 *     wp bhc health-check
	 *     wp bhc health-check --format=json
	 *
	 * @param string[]              $args       Positional arguments.
	 * @param array<string, string> $assoc_args Options.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$format = (string) ( $assoc_args['format'] ?? 'table' );
		$checks = $this->report->checks();

		if ( 'json' === $format ) {
			WP_CLI::log(
				(string) wp_json_encode(
					[
						'checks' => $checks,
						'report' => $this->report->build(),
					],
					JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
				)
			);
		} else {
			$rows = array_map(
				static fn ( array $check ): array => [
					'check'  => (string) $check['label'],
					'status' => strtoupper( (string) $check['status'] ),
					'detail' => (string) $check['detail'],
				],
				$checks
			);

			WP_CLI\Utils\format_items( 'table', $rows, [ 'check', 'status', 'detail' ] );
		}

		$failures = array_filter( $checks, static fn ( array $check ): bool => 'fail' === $check['status'] );
		$warnings = array_filter( $checks, static fn ( array $check ): bool => 'warn' === $check['status'] );

		if ( [] !== $failures ) {
			WP_CLI::error( sprintf( '%d check(s) failing.', count( $failures ) ) );
		}

		if ( [] !== $warnings ) {
			// --strict turns a warning into a non-zero exit, for a deploy gate.
			// Without it the command still has to say what it found: printing
			// "All checks passed" under a table with an Attention row in it is
			// the kind of summary that gets believed instead of the table.
			$message = sprintf(
				/* translators: %d: number of checks needing attention. */
				_n( '%d check needs attention.', '%d checks need attention.', count( $warnings ), 'bhc-commerce-core' ),
				count( $warnings )
			);

			if ( isset( $assoc_args['strict'] ) ) {
				WP_CLI::error( $message . ' ' . __( '(strict mode)', 'bhc-commerce-core' ) );
			}

			WP_CLI::warning( $message );

			return;
		}

		WP_CLI::success( 'All checks passed.' );
	}
}
