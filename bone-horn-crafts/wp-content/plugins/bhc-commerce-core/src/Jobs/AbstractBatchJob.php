<?php
/**
 * Base class for Action Scheduler batch jobs.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Jobs;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Contracts\LoggerInterface;
use Throwable;

/**
 * Batch processing with retries, structured logging and idempotency.
 *
 * The contract every job here follows:
 *
 * * **Bounded work per run.** A job never processes the whole catalogue in one
 *   request. It handles `batch_size()` items, then schedules itself again with
 *   the next offset. Nothing runs long enough to hit a PHP timeout or a
 *   gateway limit.
 * * **Idempotent.** Re-running a batch recomputes the same rows (every write
 *   is an upsert keyed by product id), so a retry after a partial failure is
 *   always safe.
 * * **Retries with backoff.** A thrown exception is caught, logged with
 *   context, and the batch is rescheduled with an increasing delay up to
 *   `MAX_ATTEMPTS`; after that the failure is logged at error level and the
 *   chain stops rather than looping forever.
 * * **Observable.** Start, each batch and completion are logged with the same
 *   structured shape, so a stuck job is visible in the WooCommerce logs and on
 *   the plugin's health screen.
 */
abstract class AbstractBatchJob {

	public const GROUP = 'bhc-core';

	protected const MAX_ATTEMPTS = 3;

	/**
	 * Constructor.
	 *
	 * @param LoggerInterface $logger Logger.
	 */
	public function __construct( protected LoggerInterface $logger ) {}

	/**
	 * Action Scheduler hook name.
	 */
	abstract public function hook(): string;

	/**
	 * Items handled per batch.
	 */
	abstract public function batch_size(): int;

	/**
	 * Processes one batch.
	 *
	 * @param array<string, mixed> $args Batch arguments (offset, page, ...).
	 *
	 * @return array{processed:int, next:?array<string, mixed>} Next args, or null when finished.
	 */
	abstract protected function handle_batch( array $args ): array;

	/**
	 * Entry point invoked by Action Scheduler.
	 *
	 * @param array<string, mixed>|string $args Batch arguments.
	 */
	public function run( $args = [] ): void {
		$args = $this->normalise_args( $args );

		$started = microtime( true );

		try {
			$result = $this->handle_batch( $args );

			$this->logger->info(
				'job.batch_complete',
				[
					'job'       => $this->hook(),
					'page'      => (int) ( $args['page'] ?? 1 ),
					'processed' => (int) ( $result['processed'] ?? 0 ),
					'duration'  => round( microtime( true ) - $started, 3 ),
				]
			);

			if ( is_array( $result['next'] ?? null ) ) {
				$this->schedule_next( $result['next'] );

				return;
			}

			$this->on_complete( $args );
		} catch ( Throwable $exception ) {
			$this->handle_failure( $args, $exception );
		}
	}

	/**
	 * Called once the final batch finishes.
	 *
	 * @param array<string, mixed> $args Final batch arguments.
	 */
	protected function on_complete( array $args ): void {
		update_option( $this->state_option(), time(), false );

		$this->logger->info( 'job.complete', [ 'job' => $this->hook() ] );

		/**
		 * Fires when a batch job chain completes.
		 *
		 * @since 1.0.0
		 *
		 * @param string               $hook Job hook name.
		 * @param array<string, mixed> $args Final batch arguments.
		 */
		do_action( 'bhc_job_completed', $this->hook(), $args );
	}

	/**
	 * Schedules the next batch.
	 *
	 * @param array<string, mixed> $args  Next batch arguments.
	 * @param int                  $delay Delay in seconds.
	 */
	protected function schedule_next( array $args, int $delay = 5 ): void {
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			// Without Action Scheduler the chain runs inline; still bounded by
			// batch size, and only ever hit in a WP-CLI context.
			$this->run( $args );

			return;
		}

		as_schedule_single_action( time() + max( 1, $delay ), $this->hook(), [ $args ], self::GROUP );
	}

	/**
	 * Logs a failure and retries with backoff.
	 *
	 * @param array<string, mixed> $args      Batch arguments.
	 * @param Throwable            $exception Thrown error.
	 */
	protected function handle_failure( array $args, Throwable $exception ): void {
		$attempt = (int) ( $args['attempt'] ?? 1 );

		$context = [
			'job'     => $this->hook(),
			'page'    => (int) ( $args['page'] ?? 1 ),
			'attempt' => $attempt,
			'error'   => $exception->getMessage(),
		];

		if ( $attempt >= self::MAX_ATTEMPTS ) {
			$this->logger->error( 'job.failed', $context );

			/**
			 * Fires when a batch job gives up after its final retry.
			 *
			 * @since 1.0.0
			 *
			 * @param string               $hook      Job hook name.
			 * @param array<string, mixed> $args      Batch arguments.
			 * @param Throwable            $exception Thrown error.
			 */
			do_action( 'bhc_job_failed', $this->hook(), $args, $exception );

			return;
		}

		$this->logger->warning( 'job.retry', $context );

		$args['attempt'] = $attempt + 1;

		$this->schedule_next( $args, 30 * $attempt );
	}

	/**
	 * Starts a job chain from the first batch.
	 *
	 * @param array<string, mixed> $args Initial arguments.
	 */
	public function start( array $args = [] ): void {
		$args = $this->normalise_args( $args );

		$this->logger->info( 'job.start', [ 'job' => $this->hook() ] );

		$this->schedule_next( $args, 1 );
	}

	/**
	 * Runs the whole chain synchronously. Used by WP-CLI.
	 *
	 * @param array<string, mixed> $args      Initial arguments.
	 * @param int                  $max_loops Safety valve.
	 *
	 * @return int Total items processed.
	 */
	public function run_sync( array $args = [], int $max_loops = 200 ): int {
		$args      = $this->normalise_args( $args );
		$processed = 0;
		$loops     = 0;

		while ( $loops < $max_loops ) {
			++$loops;

			$result     = $this->handle_batch( $args );
			$processed += (int) ( $result['processed'] ?? 0 );

			if ( ! is_array( $result['next'] ?? null ) ) {
				$this->on_complete( $args );

				break;
			}

			$args = $this->normalise_args( $result['next'] );
		}

		return $processed;
	}

	/**
	 * Timestamp of the last successful completion.
	 */
	public function last_completed_at(): int {
		return (int) get_option( $this->state_option(), 0 );
	}

	/**
	 * Option name storing the last completion timestamp.
	 */
	protected function state_option(): string {
		return 'bhc_job_' . sanitize_key( str_replace( 'bhc_job_', '', $this->hook() ) ) . '_last_run';
	}

	/**
	 * Normalises Action Scheduler arguments into an array.
	 *
	 * @param mixed $args Raw arguments.
	 *
	 * @return array<string, mixed>
	 */
	protected function normalise_args( mixed $args ): array {
		if ( is_string( $args ) ) {
			$decoded = json_decode( $args, true );

			$args = is_array( $decoded ) ? $decoded : [];
		}

		if ( ! is_array( $args ) ) {
			$args = [];
		}

		// Action Scheduler passes the argument list, so a single array argument
		// can arrive wrapped in an outer array.
		if ( isset( $args[0] ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}

		return [
			'page'    => max( 1, absint( $args['page'] ?? 1 ) ),
			'attempt' => max( 1, absint( $args['attempt'] ?? 1 ) ),
		] + $args;
	}
}
