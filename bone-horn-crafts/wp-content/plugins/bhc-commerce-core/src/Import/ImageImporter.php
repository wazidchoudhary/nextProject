<?php
/**
 * Local product image importer.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Import;

defined( 'ABSPATH' ) || exit;

use WC_Product;
use WP_Error;

/**
 * Attaches product imagery from a local folder.
 *
 * The companion to {@see FirebaseImporter}, for the common case where the
 * source images cannot be fetched over the network — the bucket is private, the
 * host blocks outbound requests, or somebody simply has the folder on a laptop.
 *
 * The matching problem is the whole job, and it is why this runs in two passes
 * rather than one.
 *
 * Four strategies are tried, in descending order of how much they can be
 * trusted:
 *
 * 1. **Manifest basename.** Exact, and preserves the source ordering, so the
 *    first image in the export stays the featured one.
 * 2. **A subfolder named after the SKU**, or the SKU as a filename prefix.
 * 3. **An exact product-name match** once both sides are normalised —
 *    `Bone Dice.jpg` against `BONE DICE`.
 * 4. **A similarity score** against every product name.
 *
 * Only the first three write anything. The fourth is reported and never
 * applied, because measuring it against a real catalogue showed it confidently
 * wrong: `Buffalo Horn Button Blank.jpg` and `Buffalo Horn Pen Blank.jpg` both
 * scored above 70% against `BUFFALO HORN NUT BLANK` — the same product, and
 * neither correct. A wrong product photograph is worse than a missing one; it
 * is a customer receiving something other than what they were shown.
 *
 * So an uncertain run writes a review file instead: image, best guess, score
 * and runners-up. Someone reads it, fixes the wrong rows, deletes the ones with
 * no product, and feeds it back with `--apply`. Minutes of work, and the
 * catalogue is right.
 *
 * A filename can legitimately belong to more than one product; the manifest has
 * three such cases. Those attach to every product that references them.
 */
final class ImageImporter {

	/**
	 * Extensions treated as product imagery.
	 */
	private const EXTENSIONS = [ 'jpg', 'jpeg', 'png', 'webp', 'avif', 'gif' ];

	/**
	 * Counters describing the run.
	 *
	 * @var array<string, int>
	 */
	private array $stats = [
		'products_matched' => 0,
		'images_attached'  => 0,
		'products_skipped' => 0,
		'files_unmatched'  => 0,
		'failed'           => 0,
	];

	/**
	 * Things worth telling the operator about.
	 *
	 * @var string[]
	 */
	private array $problems = [];

	/**
	 * Runs the import.
	 *
	 * @param string               $directory Folder holding the images.
	 * @param array<string, mixed> $options   manifest, dry_run, replace, progress.
	 *
	 * @return array<string, mixed>
	 */
	public function import( string $directory, array $options = [] ): array {
		$directory = rtrim( $directory, '/\\' );

		if ( ! is_dir( $directory ) || ! is_readable( $directory ) ) {
			return [ 'error' => sprintf( 'Cannot read the directory %s', $directory ) ];
		}

		$files = $this->scan( $directory );

		if ( [] === $files ) {
			return [ 'error' => sprintf( 'No images found under %s', $directory ) ];
		}

		$plan = $this->build_plan( $files, (string) ( $options['manifest'] ?? '' ) );

		if ( $plan instanceof WP_Error ) {
			return [ 'error' => $plan->get_error_message() ];
		}

		$claimed = 0;

		foreach ( $plan as $paths ) {
			$claimed += count( $paths );
		}

		$this->stats['files_unmatched'] = max( 0, count( $files ) - $claimed );

		return $this->run_plan( $plan, $options );
	}

	/**
	 * Executes a resolved SKU => paths plan.
	 *
	 * Shared by the automatic path and by `apply()`, so a reviewed mapping and
	 * an exact one go through exactly the same attach, skip and replace rules.
	 *
	 * @param array<string, string[]> $plan    SKU => ordered file paths.
	 * @param array<string, mixed>    $options dry_run, replace, progress.
	 *
	 * @return array<string, mixed>
	 */
	private function run_plan( array $plan, array $options ): array {
		$dry_run  = (bool) ( $options['dry_run'] ?? false );
		$replace  = (bool) ( $options['replace'] ?? false );
		$progress = $options['progress'] ?? null;

		$index = 0;
		$total = count( $plan );

		foreach ( $plan as $sku => $paths ) {
			++$index;

			$product = $this->product_for( (string) $sku );

			if ( ! $product instanceof WC_Product ) {
				$this->problems[] = sprintf( 'No product with SKU %s; %d file(s) left alone.', $sku, count( $paths ) );

				continue;
			}

			++$this->stats['products_matched'];

			if ( is_callable( $progress ) ) {
				$progress( $index, $total, $product->get_name(), count( $paths ) );
			}

			if ( ! $replace && $product->get_image_id() ) {
				++$this->stats['products_skipped'];

				continue;
			}

			if ( $dry_run ) {
				$this->stats['images_attached'] += count( $paths );

				continue;
			}

			$this->attach( $product, $paths, $replace );
		}

		return [
			'stats'    => $this->stats,
			'problems' => $this->problems,
		];
	}

	/**
	 * Maps SKU => ordered list of file paths.
	 *
	 * @param array<string, string> $files    Lower-case basename => full path.
	 * @param string                $manifest Path to the export JSON, or ''.
	 *
	 * @return array<string, string[]>|WP_Error
	 */
	private function build_plan( array $files, string $manifest ) {
		$plan = [];

		if ( '' !== $manifest ) {
			$mapped = $this->plan_from_manifest( $files, $manifest );

			if ( $mapped instanceof WP_Error ) {
				return $mapped;
			}

			$plan = $mapped;
		}

		// Whatever the manifest did not claim can still be matched structurally.
		foreach ( $files as $key => $path ) {
			if ( $this->already_planned( $plan, $path ) ) {
				continue;
			}

			$sku = $this->sku_from_path( $path, $key );

			if ( '' === $sku ) {
				continue;
			}

			$plan[ $sku ][] = $path;
		}

		// Then exact product-name matches, which are safe: normalisation only
		// lower-cases, drops punctuation and strips a trailing "(2)".
		$catalogue = $this->catalogue_by_name();

		foreach ( $files as $path ) {
			if ( $this->already_planned( $plan, $path ) ) {
				continue;
			}

			$key = $this->normalise_name( basename( $path ) );

			if ( '' !== $key && isset( $catalogue[ $key ] ) && 1 === count( $catalogue[ $key ] ) ) {
				$plan[ $catalogue[ $key ][0] ][] = $path;
			}
		}

		return $plan;
	}

	/**
	 * Proposes a match for every file, without writing anything.
	 *
	 * The output is meant to be read by a person and corrected. Each row
	 * carries the score and the runners-up precisely so a wrong guess is
	 * obvious: two files proposing the same product, or a plausible-looking
	 * score against a product whose name differs in the one word that matters,
	 * both stand out in a list in a way they never do in a progress log.
	 *
	 * @param string $directory Folder holding the images.
	 * @param string $manifest  Optional export JSON.
	 *
	 * @return array<int, array<string, string>>|WP_Error Rows, or an error.
	 */
	public function plan( string $directory, string $manifest = '' ) {
		$directory = rtrim( $directory, '/\\' );

		if ( ! is_dir( $directory ) || ! is_readable( $directory ) ) {
			return new WP_Error( 'bhc_images_unreadable', sprintf( 'Cannot read the directory %s', $directory ) );
		}

		$files = $this->scan( $directory );

		if ( [] === $files ) {
			return new WP_Error( 'bhc_images_empty', sprintf( 'No images found under %s', $directory ) );
		}

		$confident = $this->build_plan( $files, $manifest );

		if ( $confident instanceof WP_Error ) {
			return $confident;
		}

		$claimed = [];

		foreach ( $confident as $sku => $paths ) {
			foreach ( $paths as $path ) {
				$claimed[ $path ] = (string) $sku;
			}
		}

		$products = $this->catalogue();
		$rows     = [];

		foreach ( $files as $path ) {
			if ( isset( $claimed[ $path ] ) ) {
				$product = $this->product_for( $claimed[ $path ] );

				$rows[] = [
					'file'       => basename( $path ),
					'sku'        => $claimed[ $path ],
					'product'    => $product instanceof WC_Product ? $product->get_name() : '',
					'score'      => '100',
					'method'     => 'exact',
					'alternates' => '',
				];

				continue;
			}

			$ranked = $this->rank( basename( $path ), $products );
			$best   = $ranked[0] ?? null;

			// The sku column is left blank for every guess, whatever it scored.
			// Populating it above some threshold is what makes a review file
			// dangerous: the rows a person most needs to check are exactly the
			// ones that look already decided. Measured against this catalogue,
			// "Bone Pen Blank" scored 63 against BONE PICK BLANK and "Buffalo
			// Horn Striker" scored 59 against Buffalo Horn Rolls — both wrong,
			// both plausible, and both would have been applied silently.
			//
			// The proposal still travels in the product column, so filling the
			// sku in is a copy from the row itself when the guess is right.
			$rows[] = [
				'file'       => basename( $path ),
				'sku'        => '',
				'product'    => null === $best ? '' : $best['name'],
				'score'      => null === $best ? '0' : (string) $best['score'],
				'method'     => 'guess',
				'alternates' => implode(
					' | ',
					array_map(
						static fn ( array $r ): string => sprintf( '%s (%s)', $r['name'], $r['score'] ),
						array_slice( $ranked, 1, 2 )
					)
				),
			];
		}

		return $rows;
	}

	/**
	 * Applies a reviewed mapping file.
	 *
	 * @param string               $directory Folder holding the images.
	 * @param string               $csv       Reviewed CSV, as written by plan().
	 * @param array<string, mixed> $options   dry_run, replace, progress.
	 *
	 * @return array<string, mixed>
	 */
	public function apply( string $directory, string $csv, array $options = [] ) {
		if ( ! is_readable( $csv ) ) {
			return [ 'error' => sprintf( 'Cannot read the mapping file %s', $csv ) ];
		}

		$files  = $this->scan( rtrim( $directory, '/\\' ) );
		$handle = fopen( $csv, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Reading a local CSV line by line.

		if ( false === $handle ) {
			return [ 'error' => sprintf( 'Cannot open %s', $csv ) ];
		}

		$header = fgetcsv( $handle, 0, ',', '"', '' );
		$plan   = [];

		if ( is_array( $header ) ) {
			$columns = array_flip( array_map( 'strval', $header ) );

			// phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition -- The canonical fgetcsv() loop; the assignment is compared against false explicitly.
			while ( false !== ( $row = fgetcsv( $handle, 0, ',', '"', '' ) ) ) {
				$file = trim( (string) ( $row[ $columns['file'] ?? 0 ] ?? '' ) );
				$sku  = trim( (string) ( $row[ $columns['sku'] ?? 1 ] ?? '' ) );

				if ( '' === $file || '' === $sku ) {
					continue;
				}

				$key = strtolower( $file );

				if ( isset( $files[ $key ] ) ) {
					$plan[ $sku ][] = $files[ $key ];

					continue;
				}

				$this->problems[] = sprintf( '%s is listed in the mapping but not present in the folder.', $file );
			}
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing the handle opened above.

		if ( [] === $plan ) {
			return [ 'error' => 'The mapping file contained no usable rows. Every row needs a file and a sku.' ];
		}

		return $this->run_plan( $plan, $options );
	}

	/**
	 * Product id => name, for every published product.
	 *
	 * @return array<int, array{sku: string, name: string}>
	 */
	private function catalogue(): array {
		$ids = get_posts(
			[
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			]
		);

		$catalogue = [];

		foreach ( (array) $ids as $id ) {
			$product = wc_get_product( (int) $id );

			if ( ! $product instanceof WC_Product ) {
				continue;
			}

			$sku = (string) $product->get_sku();

			$catalogue[] = [
				'sku'  => '' !== $sku ? $sku : (string) $id,
				'name' => (string) $product->get_name(),
			];
		}

		return $catalogue;
	}

	/**
	 * Normalised product name => list of SKUs.
	 *
	 * @return array<string, string[]>
	 */
	private function catalogue_by_name(): array {
		$index = [];

		foreach ( $this->catalogue() as $entry ) {
			$index[ $this->normalise_name( $entry['name'] ) ][] = $entry['sku'];
		}

		return $index;
	}

	/**
	 * Ranks catalogue entries by similarity to a filename.
	 *
	 * @param string                                       $file      Filename.
	 * @param array<int, array{sku: string, name: string}> $catalogue Products.
	 *
	 * @return array<int, array{sku: string, name: string, score: float}>
	 */
	private function rank( string $file, array $catalogue ): array {
		$needle = $this->normalise_name( $file );
		$ranked = [];

		foreach ( $catalogue as $entry ) {
			$ranked[] = [
				'sku'   => $entry['sku'],
				'name'  => $entry['name'],
				'score' => $this->similarity( $needle, $this->normalise_name( $entry['name'] ) ),
			];
		}

		usort( $ranked, static fn ( array $a, array $b ): int => $b['score'] <=> $a['score'] );

		return $ranked;
	}

	/**
	 * Scores two normalised names from 0 to 100.
	 *
	 * Blends shared-word overlap with character similarity. Neither alone is
	 * enough: overlap alone rates "Bone Pen Blank" and "Bone Pick Blank" as a
	 * near match on two words out of three, and character similarity alone is
	 * fooled by names that differ only in a short but decisive word.
	 *
	 * @param string $a First normalised name.
	 * @param string $b Second normalised name.
	 */
	private function similarity( string $a, string $b ): float {
		if ( '' === $a || '' === $b ) {
			return 0.0;
		}

		if ( $a === $b ) {
			return 100.0;
		}

		$tokens_a = array_unique( explode( ' ', $a ) );
		$tokens_b = array_unique( explode( ' ', $b ) );

		$union   = count( array_unique( array_merge( $tokens_a, $tokens_b ) ) );
		$jaccard = $union > 0 ? count( array_intersect( $tokens_a, $tokens_b ) ) / $union : 0.0;

		similar_text( $a, $b, $percent );

		return round( 0.6 * ( $jaccard * 100 ) + 0.4 * $percent, 1 );
	}

	/**
	 * Normalises a filename or product name for comparison.
	 *
	 * @param string $value Filename or product name.
	 */
	private function normalise_name( string $value ): string {
		$value = pathinfo( $value, PATHINFO_FILENAME );
		$value = (string) preg_replace( '/\s*\(\d+\)$/', '', $value );
		$value = str_replace( '&', ' and ', $value );
		$value = (string) preg_replace( '/[^a-z0-9]+/', ' ', strtolower( $value ) );

		return trim( (string) preg_replace( '/\s+/', ' ', $value ) );
	}

	/**
	 * Builds the plan from the export manifest.
	 *
	 * @param array<string, string> $files    Lower-case basename => full path.
	 * @param string                $manifest Path to the export JSON.
	 *
	 * @return array<string, string[]>|WP_Error
	 */
	private function plan_from_manifest( array $files, string $manifest ) {
		if ( ! is_readable( $manifest ) ) {
			return new WP_Error( 'bhc_manifest_unreadable', sprintf( 'Cannot read the manifest %s', $manifest ) );
		}

		$raw = file_get_contents( $manifest ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.

		$data = is_string( $raw ) ? json_decode( $raw, true ) : null;

		if ( ! is_array( $data ) ) {
			return new WP_Error( 'bhc_manifest_invalid', 'The manifest is not valid JSON.' );
		}

		$products = $data['product'] ?? $data;

		if ( ! is_array( $products ) ) {
			return new WP_Error( 'bhc_manifest_empty', 'No `product` branch in the manifest.' );
		}

		$plan = [];

		foreach ( $products as $source_id => $record ) {
			if ( ! is_array( $record ) ) {
				continue;
			}

			foreach ( (array) ( $record['productImage'] ?? [] ) as $url ) {
				$key = $this->basename_key( (string) $url );

				if ( '' !== $key && isset( $files[ $key ] ) ) {
					$plan[ (string) $source_id ][] = $files[ $key ];
				}
			}
		}

		return $plan;
	}

	/**
	 * Normalises a URL or path to a comparable basename.
	 *
	 * Firebase percent-encodes the path, and the filenames contain spaces and
	 * parentheses that a browser may or may not have preserved on download, so
	 * comparison is on a decoded, lower-cased basename.
	 *
	 * @param string $url URL or path.
	 */
	private function basename_key( string $url ): string {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );

		if ( '' === $path ) {
			$path = $url;
		}

		return strtolower( basename( rawurldecode( $path ) ) );
	}

	/**
	 * Whether a path is already claimed by the plan.
	 *
	 * @param array<string, string[]> $plan Current plan.
	 * @param string                  $path File path.
	 */
	private function already_planned( array $plan, string $path ): bool {
		foreach ( $plan as $paths ) {
			if ( in_array( $path, $paths, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Derives a SKU from a folder name or filename prefix.
	 *
	 * @param string $path Full path.
	 * @param string $key  Lower-case basename.
	 */
	private function sku_from_path( string $path, string $key ): string {
		$folder = basename( dirname( $path ) );

		if ( preg_match( '/^[A-Za-z0-9_-]{3,}$/', $folder ) && wc_get_product_id_by_sku( $folder ) ) {
			return $folder;
		}

		// Filenames shaped like a SKU with an optional index: 7124857.jpg,
		// 7124857-2.jpg, BHC-BS-1042_1.png.
		if ( preg_match( '/^([A-Za-z0-9]+(?:-[A-Za-z0-9]+)*?)(?:[-_ ]?\d+)?\.[a-z0-9]+$/i', $key, $match ) ) {
			$candidate = $match[1];

			if ( wc_get_product_id_by_sku( $candidate ) ) {
				return $candidate;
			}
		}

		return '';
	}

	/**
	 * Finds a product by SKU, falling back to the import source id.
	 *
	 * @param string $sku SKU or source id.
	 */
	private function product_for( string $sku ): ?WC_Product {
		$id = wc_get_product_id_by_sku( $sku );

		if ( ! $id ) {
			$found = get_posts(
				[
					'post_type'      => 'product',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'meta_key'       => '_bhc_import_source_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- One indexed lookup per product during a manual import.
					'meta_value'     => $sku, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- As above.
				]
			);

			$id = (int) ( $found[0] ?? 0 );
		}

		$product = $id ? wc_get_product( $id ) : null;

		return $product instanceof WC_Product ? $product : null;
	}

	/**
	 * Copies files into the media library and attaches them.
	 *
	 * @param WC_Product $product Product.
	 * @param string[]   $paths   Ordered file paths; the first becomes featured.
	 * @param bool       $replace Whether to discard existing imagery first.
	 */
	private function attach( WC_Product $product, array $paths, bool $replace ): void {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$product_id = $product->get_id();

		if ( $replace ) {
			$existing = array_filter(
				array_merge( [ (int) $product->get_image_id() ], $product->get_gallery_image_ids() )
			);

			foreach ( $existing as $attachment_id ) {
				wp_delete_attachment( (int) $attachment_id, true );
			}
		}

		$attached = [];

		foreach ( $paths as $position => $path ) {
			$alt = 0 === $position
				? $product->get_name()
				: sprintf( '%s, view %d', $product->get_name(), $position + 1 );

			$attachment_id = $this->sideload_local( $path, $product_id, $alt );

			if ( $attachment_id > 0 ) {
				$attached[] = $attachment_id;

				++$this->stats['images_attached'];
			}
		}

		if ( [] === $attached ) {
			++$this->stats['failed'];

			return;
		}

		set_post_thumbnail( $product_id, (int) array_shift( $attached ) );

		if ( [] !== $attached ) {
			update_post_meta( $product_id, '_product_image_gallery', implode( ',', $attached ) );
		}
	}

	/**
	 * Copies one local file into the uploads directory as an attachment.
	 *
	 * The file is copied rather than moved, and `media_handle_sideload()` is
	 * given the copy: it deletes whatever temporary file it is handed, and
	 * handing it the operator's original would consume the source folder as the
	 * import ran — leaving nothing to retry with if something went wrong
	 * halfway.
	 *
	 * @param string $path        Source file.
	 * @param int    $product_id  Product to attach to.
	 * @param string $description Alt text and title.
	 */
	private function sideload_local( string $path, int $product_id, string $description ): int {
		$temp = wp_tempnam( basename( $path ) );

		if ( ! $temp || ! copy( $path, $temp ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy -- Copying into WordPress's own temp file before handing it to media_handle_sideload().
			$this->problems[] = sprintf( 'Could not stage %s for import.', basename( $path ) );

			++$this->stats['failed'];

			return 0;
		}

		$file = [
			'name'     => sanitize_file_name( basename( $path ) ),
			'tmp_name' => $temp,
		];

		$attachment_id = media_handle_sideload( $file, $product_id, $description );

		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( $temp );

			$this->problems[] = sprintf( '%s: %s', basename( $path ), $attachment_id->get_error_message() );

			++$this->stats['failed'];

			return 0;
		}

		update_post_meta( (int) $attachment_id, '_wp_attachment_image_alt', $description );

		return (int) $attachment_id;
	}

	/**
	 * Recursively lists image files.
	 *
	 * @param string $directory Folder to scan.
	 *
	 * @return array<string, string> Lower-case basename => full path.
	 */
	private function scan( string $directory ): array {
		$found = [];

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $directory, \FilesystemIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			if ( ! $file->isFile() ) {
				continue;
			}

			$extension = strtolower( $file->getExtension() );

			if ( ! in_array( $extension, self::EXTENSIONS, true ) ) {
				continue;
			}

			// Later duplicates lose: a folder holding both `img.jpg` and
			// `copy/img.jpg` should attach one of them, not two identical ones.
			$key = strtolower( $file->getFilename() );

			$found[ $key ] ??= $file->getPathname();
		}

		return $found;
	}
}
