<?php
/**
 * Action Scheduler registration.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Jobs;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Contracts\HookableInterface;
use BoneHornCrafts\Core\Contracts\LoggerInterface;

/**
 * Registers recurring schedules and binds job callbacks.
 *
 * Schedules are only created when they are missing, so this can run on every
 * request without duplicating actions. Callbacks are bound unconditionally
 * because Action Scheduler dispatches them from a cron/queue-runner request
 * where the front-end context checks do not apply.
 */
final class Scheduler implements HookableInterface {

	public const GROUP = AbstractBatchJob::GROUP;

	/**
	 * Constructor.
	 *
	 * @param array<string, AbstractBatchJob> $jobs   Jobs keyed by hook.
	 * @param LoggerInterface                 $logger Logger.
	 */
	public function __construct( private array $jobs, private LoggerInterface $logger ) {}

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		foreach ( $this->jobs as $job ) {
			add_action( $job->hook(), [ $job, 'run' ], 10, 1 );
		}

		add_action( 'init', [ $this, 'ensure_schedules' ], 20 );
		add_action( 'bhc_schema_installed', [ $this, 'ensure_schedules' ] );
	}

	/**
	 * Recurring schedule definitions: hook => interval in seconds.
	 *
	 * @return array<string, int>
	 */
	public function schedules(): array {
		$schedules = [
			MerchandisingIndexJob::HOOK => DAY_IN_SECONDS,
			ViewBufferFlushJob::HOOK    => 15 * MINUTE_IN_SECONDS,
			WishlistPruneJob::HOOK      => WEEK_IN_SECONDS,
		];

		/**
		 * Filters the recurring job schedule.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, int> $schedules hook => interval in seconds.
		 */
		return (array) apply_filters( 'bhc_job_schedules', $schedules );
	}

	/**
	 * Creates any missing recurring actions.
	 */
	public function ensure_schedules(): void {
		if ( ! function_exists( 'as_has_scheduled_action' ) || ! function_exists( 'as_schedule_recurring_action' ) ) {
			return;
		}

		foreach ( $this->schedules() as $hook => $interval ) {
			if ( as_has_scheduled_action( $hook, null, self::GROUP ) ) {
				continue;
			}

			// Stagger the first run so a fresh install does not fire every job
			// in the same minute.
			$first_run = time() + ( 5 * MINUTE_IN_SECONDS ) + ( crc32( $hook ) % 600 );

			as_schedule_recurring_action( $first_run, (int) $interval, $hook, [], self::GROUP );

			$this->logger->info(
				'job.scheduled',
				[
					'job'      => $hook,
					'interval' => (int) $interval,
				]
			);
		}
	}

	/**
	 * Returns a job by hook.
	 *
	 * @param string $hook Hook name.
	 */
	public function job( string $hook ): ?AbstractBatchJob {
		return $this->jobs[ $hook ] ?? null;
	}

	/**
	 * All registered jobs.
	 *
	 * @return array<string, AbstractBatchJob>
	 */
	public function jobs(): array {
		return $this->jobs;
	}

	/**
	 * Number of failed actions for a hook.
	 *
	 * A recurring action that fails is not rescheduled by Action Scheduler, so
	 * `ensure_schedules()` recreates it on the next request and the site
	 * recovers on its own. That self-healing is why a failure is easy to miss:
	 * by the time anyone looks, the job is running again and only the failed
	 * row is left behind. Surfacing the count means a deploy that briefly took
	 * the callbacks away — the plugin folder mid-upload, WooCommerce
	 * deactivated — leaves a visible trace instead of a silent one.
	 *
	 * @param string $hook Hook name.
	 */
	private function failed_count( string $hook ): int {
		if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
			return 0;
		}

		return count(
			(array) as_get_scheduled_actions(
				[
					'hook'     => $hook,
					'status'   => 'failed',
					'group'    => self::GROUP,
					'per_page' => 100,
				],
				'ids'
			)
		);
	}

	/**
	 * Total failed actions across every job in the group.
	 */
	public function failed(): int {
		$total = 0;

		foreach ( array_keys( $this->jobs ) as $hook ) {
			$total += $this->failed_count( (string) $hook );
		}

		return $total;
	}

	/**
	 * Status summary for the admin health screen.
	 *
	 * @return array<int, array{hook:string, next_run:string, pending:int, failed:int, last_completed:string}>
	 */
	public function status(): array {
		$status = [];

		foreach ( $this->jobs as $hook => $job ) {
			$next    = 0;
			$pending = 0;

			if ( function_exists( 'as_next_scheduled_action' ) ) {
				$timestamp = as_next_scheduled_action( $hook, null, self::GROUP );
				$next      = is_numeric( $timestamp ) ? (int) $timestamp : 0;
			}

			if ( function_exists( 'as_get_scheduled_actions' ) ) {
				$pending = count(
					(array) as_get_scheduled_actions(
						[
							'hook'     => $hook,
							'status'   => 'pending',
							'group'    => self::GROUP,
							'per_page' => 20,
						],
						'ids'
					)
				);
			}

			$last = $job->last_completed_at();

			$status[] = [
				'hook'           => $hook,
				'next_run'       => $next > 0 ? wp_date( 'Y-m-d H:i', $next ) : __( 'not scheduled', 'bhc-commerce-core' ),
				'pending'        => $pending,
				'failed'         => $this->failed_count( (string) $hook ),
				'last_completed' => $last > 0 ? wp_date( 'Y-m-d H:i', $last ) : __( 'never', 'bhc-commerce-core' ),
			];
		}

		return $status;
	}
}
