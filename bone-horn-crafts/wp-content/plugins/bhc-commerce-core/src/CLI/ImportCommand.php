<?php
/**
 * Catalogue import command.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\CLI;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Cache\CacheManager;
use BoneHornCrafts\Core\Cache\Invalidator;
use BoneHornCrafts\Core\Import\FirebaseImporter;
use WP_CLI;

/**
 * `wp bhc import firebase` — imports a real catalogue from a Firebase export.
 */
final class ImportCommand {

	/**
	 * Constructor.
	 *
	 * @param FirebaseImporter $importer Importer service.
	 * @param CacheManager     $cache    Cache manager.
	 */
	public function __construct(
		private FirebaseImporter $importer,
		private CacheManager $cache
	) {}

	/**
	 * Imports products from a Firebase Realtime Database export.
	 *
	 * Products are matched on SKU — the source `productId` — so re-running
	 * updates rather than duplicates. A record whose `productPrice` is a list of
	 * `{price, type}` rows becomes a variable product with one variation per
	 * row; a scalar price becomes a simple product.
	 *
	 * Imagery is sideloaded into the media library rather than hot-linked, and
	 * is skipped for any product that already has a featured image, because
	 * downloading is the slow part of a re-run.
	 *
	 * Only the `product` branch is read. Any `orders` or `messages` branch in
	 * the same export is ignored: it holds real customer names, addresses and
	 * email, which do not belong in a catalogue import.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to the exported JSON.
	 *
	 * [--dry-run]
	 * : Report what would be imported without writing anything.
	 *
	 * [--skip-images]
	 * : Do not download imagery. Much faster; products import without photos.
	 *
	 * [--limit=<n>]
	 * : Import at most this many products. Useful for a first look.
	 *
	 * ## EXAMPLES
	 *
	 *     wp bhc import firebase export.json --dry-run
	 *     wp bhc import firebase export.json --limit=5
	 *     wp bhc import firebase export.json
	 *
	 * @param string[]              $args       Positional arguments.
	 * @param array<string, string> $assoc_args Options.
	 */
	public function firebase( array $args, array $assoc_args ): void {
		$path = (string) ( $args[0] ?? '' );

		if ( '' === $path ) {
			WP_CLI::error( 'Pass the path to the export JSON.' );
		}

		$dry_run     = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$skip_images = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'skip-images', false );
		$limit       = (int) WP_CLI\Utils\get_flag_value( $assoc_args, 'limit', 0 );

		if ( $dry_run ) {
			WP_CLI::log( 'Dry run — nothing will be written.' );
		}

		$started = microtime( true );

		$result = $this->importer->import(
			$path,
			[
				'dry_run'     => $dry_run,
				'skip_images' => $skip_images,
				'limit'       => $limit,
				'progress'    => static function ( int $index, int $total, string $name ): void {
					WP_CLI::log( sprintf( '  %d/%d — %s', $index, $total, $name ) );
				},
			]
		);

		if ( isset( $result['error'] ) ) {
			WP_CLI::error( (string) $result['error'] );
		}

		$stats = $result['stats'] ?? [];

		WP_CLI\Utils\format_items(
			'table',
			array_map(
				static fn ( string $k, int $v ): array => [
					'item'  => $k,
					'count' => $v,
				],
				array_keys( $stats ),
				array_values( $stats )
			),
			[ 'item', 'count' ]
		);

		foreach ( (array) ( $result['problems'] ?? [] ) as $problem ) {
			WP_CLI::warning( (string) $problem );
		}

		if ( $dry_run ) {
			WP_CLI::success( 'Dry run complete. Nothing was written.' );

			return;
		}

		foreach ( Invalidator::ALL_GROUPS as $group ) {
			$this->cache->flush_group( $group );
		}

		WP_CLI::success(
			sprintf(
				'Imported in %.1fs. Run `wp bhc products sync` to rebuild the merchandising index.',
				microtime( true ) - $started
			)
		);
	}
}
