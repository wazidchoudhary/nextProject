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
use BoneHornCrafts\Core\Import\ImageImporter;
use WP_CLI;

/**
 * `wp bhc import firebase` — imports a real catalogue from a Firebase export.
 */
final class ImportCommand {

	/**
	 * Constructor.
	 *
	 * @param FirebaseImporter $importer Catalogue importer.
	 * @param ImageImporter    $images   Local image importer.
	 * @param CacheManager     $cache    Cache manager.
	 */
	public function __construct(
		private FirebaseImporter $importer,
		private ImageImporter $images,
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

	/**
	 * Attaches product imagery from a local folder.
	 *
	 * Runs in two passes on purpose. Files that can be matched exactly — through
	 * the export manifest, a SKU folder or filename, or an identical product
	 * name — are attached directly. Anything else is only ever *proposed*,
	 * because measuring similarity matching against a real catalogue showed it
	 * confidently wrong: two different blanks both scored over 70% against the
	 * same third product. A wrong product photograph is worse than a missing
	 * one.
	 *
	 * So: run with `--plan` to write a review file, correct it, then `--apply`
	 * it.
	 *
	 * ## OPTIONS
	 *
	 * <directory>
	 * : Folder holding the images. Searched recursively.
	 *
	 * [--manifest=<file>]
	 * : Export JSON, used to match camera filenames to products exactly.
	 *
	 * [--plan=<file>]
	 * : Write a review CSV here and change nothing.
	 *
	 * [--apply=<file>]
	 * : Attach images according to a reviewed CSV.
	 *
	 * [--replace]
	 * : Replace existing imagery. Without this, products that already have a
	 * featured image are left alone.
	 *
	 * [--dry-run]
	 * : Report what would happen without writing.
	 *
	 * ## EXAMPLES
	 *
	 *     wp bhc import images ./images --plan=mapping.csv
	 *     wp bhc import images ./images --apply=mapping.csv
	 *     wp bhc import images ./images --manifest=export.json
	 *
	 * @param string[]              $args       Positional arguments.
	 * @param array<string, string> $assoc_args Options.
	 */
	public function images( array $args, array $assoc_args ): void {
		$directory = (string) ( $args[0] ?? '' );

		if ( '' === $directory ) {
			WP_CLI::error( 'Pass the folder holding the images.' );
		}

		$manifest = (string) WP_CLI\Utils\get_flag_value( $assoc_args, 'manifest', '' );
		$plan     = (string) WP_CLI\Utils\get_flag_value( $assoc_args, 'plan', '' );
		$apply    = (string) WP_CLI\Utils\get_flag_value( $assoc_args, 'apply', '' );
		$replace  = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'replace', false );
		$dry_run  = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );

		if ( '' !== $plan ) {
			$this->write_plan( $directory, $manifest, $plan );

			return;
		}

		$progress = static function ( int $index, int $total, string $name, int $count ): void {
			WP_CLI::log( sprintf( '  %d/%d — %s (%d image(s))', $index, $total, $name, $count ) );
		};

		$result = '' !== $apply
			? $this->images->apply(
				$directory,
				$apply,
				[
					'replace'  => $replace,
					'dry_run'  => $dry_run,
					'progress' => $progress,
				]
			)
			: $this->images->import(
				$directory,
				[
					'manifest' => $manifest,
					'replace'  => $replace,
					'dry_run'  => $dry_run,
					'progress' => $progress,
				]
			);

		if ( isset( $result['error'] ) ) {
			WP_CLI::error( (string) $result['error'] );
		}

		$stats = (array) ( $result['stats'] ?? [] );

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

		WP_CLI::success( 'Imagery imported.' );
	}

	/**
	 * Writes the review CSV.
	 *
	 * @param string $directory Image folder.
	 * @param string $manifest  Optional export JSON.
	 * @param string $target    Where to write the CSV.
	 */
	private function write_plan( string $directory, string $manifest, string $target ): void {
		$rows = $this->images->plan( $directory, $manifest );

		if ( $rows instanceof \WP_Error ) {
			WP_CLI::error( $rows->get_error_message() );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Writing a local review file.
		$handle = fopen( $target, 'w' );

		if ( false === $handle ) {
			WP_CLI::error( sprintf( 'Could not write %s', $target ) );
		}

		$columns = [ 'file', 'sku', 'product', 'score', 'method', 'alternates' ];

		fputcsv( $handle, $columns, ',', '"', '' );

		$exact = 0;
		$guess = 0;

		foreach ( $rows as $row ) {
			fputcsv( $handle, array_map( static fn ( string $c ): string => (string) ( $row[ $c ] ?? '' ), $columns ), ',', '"', '' );

			if ( 'exact' === ( $row['method'] ?? '' ) ) {
				++$exact;

				continue;
			}

			++$guess;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing the handle opened above.
		fclose( $handle );

		WP_CLI::log( sprintf( '%d file(s) matched exactly and carry a sku.', $exact ) );
		WP_CLI::log(
			sprintf(
				'%d could not be matched safely. Their sku column is blank, so --apply will skip them.',
				$guess
			)
		);
		WP_CLI::log( 'Fill in a sku to attach one; leave it blank to leave the product without that image.' );
		WP_CLI::success(
			sprintf( 'Wrote %s. Review it, then: wp bhc import images %s --apply=%s', $target, $directory, $target )
		);
	}
}
