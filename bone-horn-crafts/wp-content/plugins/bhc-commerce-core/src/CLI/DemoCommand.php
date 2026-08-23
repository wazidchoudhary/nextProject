<?php
/**
 * Demo data commands.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\CLI;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Cache\CacheManager;
use BoneHornCrafts\Core\Cache\Invalidator;
use BoneHornCrafts\Core\Demo\DemoSeeder;
use BoneHornCrafts\Core\Demo\DemoState;
use BoneHornCrafts\Core\Jobs\MerchandisingIndexJob;
use WP_CLI;

/**
 * `wp bhc demo` — generate and remove the fictional demo catalogue.
 */
final class DemoCommand {

	/**
	 * Constructor.
	 *
	 * @param DemoSeeder            $seeder Seeder.
	 * @param DemoState             $state  Demo bookkeeping.
	 * @param MerchandisingIndexJob $index  Index job.
	 * @param CacheManager          $cache  Cache manager.
	 */
	public function __construct(
		private DemoSeeder $seeder,
		private DemoState $state,
		private MerchandisingIndexJob $index,
		private CacheManager $cache
	) {}

	/**
	 * Generates the demo catalogue, customers, orders and content.
	 *
	 * Safe to run repeatedly: products are matched by SKU, pages by slug and
	 * terms by slug, so a second run updates rather than duplicates.
	 *
	 * ## OPTIONS
	 *
	 * [--products=<count>]
	 * : Limit how many catalogue rows are created. Default: all 60.
	 *
	 * [--orders=<count>]
	 * : How many demo orders to create. Default 24.
	 *
	 * [--skip-images]
	 * : Skip image rendering. Much faster, but products ship without imagery.
	 *
	 * [--skip-content]
	 * : Skip pages, journal articles and menus.
	 *
	 * [--skip-index]
	 * : Skip the merchandising index rebuild at the end.
	 *
	 * ## EXAMPLES
	 *
	 *     wp bhc demo seed
	 *     wp bhc demo seed --products=12 --orders=6 --skip-images
	 *
	 * @param string[]              $args       Positional arguments.
	 * @param array<string, string> $assoc_args Options.
	 */
	public function seed( array $args, array $assoc_args ): void {
		$started = microtime( true );

		$this->seeder->on_progress(
			static function ( string $message ): void {
				WP_CLI::log( $message );
			}
		);

		$counts = $this->seeder->seed(
			[
				'products' => (int) ( $assoc_args['products'] ?? 0 ),
				'orders'   => (int) ( $assoc_args['orders'] ?? 24 ),
				'images'   => ! isset( $assoc_args['skip-images'] ),
				'content'  => ! isset( $assoc_args['skip-content'] ),
			]
		);

		if ( ! isset( $assoc_args['skip-index'] ) ) {
			WP_CLI::log( 'Rebuilding merchandising index' );

			$this->index->run_sync();
		}

		foreach ( Invalidator::ALL_GROUPS as $group ) {
			$this->cache->flush_group( $group );
		}

		$rows = [];

		foreach ( $counts as $key => $value ) {
			$rows[] = [
				'item'    => (string) $key,
				'created' => (int) $value,
			];
		}

		WP_CLI\Utils\format_items( 'table', $rows, [ 'item', 'created' ] );

		WP_CLI::success(
			sprintf(
				'Demo data ready in %ss. Visit %s',
				number_format( microtime( true ) - $started, 1 ),
				home_url( '/' )
			)
		);
	}

	/**
	 * Removes everything the seeder created.
	 *
	 * Only objects recorded in the demo state option are deleted, and each one
	 * is re-checked for its `_bhc_demo` marker before removal, so content added
	 * by hand is never touched.
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Skip the confirmation prompt.
	 *
	 * [--orphans]
	 * : Also remove demo-marked objects that are no longer tracked, which can
	 * : happen when a seeding run was interrupted.
	 *
	 * ## EXAMPLES
	 *
	 *     wp bhc demo reset
	 *     wp bhc demo reset --yes
	 *
	 * @param string[]              $args       Positional arguments.
	 * @param array<string, string> $assoc_args Options.
	 */
	public function reset( array $args, array $assoc_args ): void {
		$sweep_orphans = isset( $assoc_args['orphans'] );

		if ( ! $this->state->is_seeded() && ! $sweep_orphans ) {
			WP_CLI::warning( 'No demo data is recorded for this site. Nothing to remove. Pass --orphans to sweep demo-marked leftovers.' );

			return;
		}

		$only = (string) WP_CLI\Utils\get_flag_value( $assoc_args, 'only', '' );

		$buckets = '' === $only
			? []
			: array_values( array_filter( array_map( 'trim', explode( ',', $only ) ) ) );

		$unknown = array_diff( $buckets, DemoState::BUCKETS );

		if ( [] !== $unknown ) {
			WP_CLI::error(
				sprintf(
					'Unknown bucket(s): %s. Valid buckets: %s.',
					implode( ', ', $unknown ),
					implode( ', ', DemoState::BUCKETS )
				)
			);
		}

		$summary = $this->state->summary();

		if ( [] !== $buckets ) {
			$summary = array_intersect_key( $summary, array_flip( $buckets ) );
		}

		WP_CLI::log( 'This will permanently delete the recorded demo objects:' );

		foreach ( $summary as $bucket => $count ) {
			WP_CLI::log( sprintf( '  %-12s %d', $bucket, $count ) );
		}

		WP_CLI::confirm( 'Delete the demo dataset? Content added by hand is not affected.', $assoc_args );

		$removed = $this->seeder->reset( $sweep_orphans, $buckets );

		foreach ( Invalidator::ALL_GROUPS as $group ) {
			$this->cache->flush_group( $group );
		}

		$rows = [];

		foreach ( $removed as $key => $value ) {
			$rows[] = [
				'item'    => (string) $key,
				'removed' => (int) $value,
			];
		}

		WP_CLI\Utils\format_items( 'table', $rows, [ 'item', 'removed' ] );

		WP_CLI::success( 'Demo data removed.' );
	}

	/**
	 * Shows what the seeder currently owns.
	 *
	 * ## EXAMPLES
	 *
	 *     wp bhc demo status
	 *
	 * @param string[]              $args       Positional arguments.
	 * @param array<string, string> $assoc_args Options.
	 */
	public function status( array $args, array $assoc_args ): void {
		if ( ! $this->state->is_seeded() ) {
			WP_CLI::log( 'No demo data recorded.' );

			return;
		}

		$rows = [];

		foreach ( $this->state->summary() as $bucket => $count ) {
			$rows[] = [
				'item'  => (string) $bucket,
				'count' => (int) $count,
			];
		}

		WP_CLI\Utils\format_items( 'table', $rows, [ 'item', 'count' ] );
	}
}
