<?php
/**
 * Firebase Realtime Database catalogue importer.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Import;

defined( 'ABSPATH' ) || exit;

use WC_Product_Attribute;
use WC_Product_Simple;
use WC_Product_Variable;
use WC_Product_Variation;
use WP_Error;
use WP_Term;

/**
 * Imports a catalogue exported from a Firebase Realtime Database.
 *
 * The export is the one a Next.js storefront wrote: a `product` map keyed by
 * numeric id, each entry carrying its own category strings, a Quill-authored
 * description and an array of Firebase Storage image URLs.
 *
 * Three things about that shape drive the design here.
 *
 * 1. **Price is polymorphic.** `productPrice` is either a scalar — a simple
 *    product — or an array of `{price, type}` rows, where `type` is a size or
 *    dimension string. The latter is a variable product with one variation per
 *    row, and roughly half the catalogue is that shape.
 * 2. **Descriptions carry editor scaffolding.** Every record was written by
 *    Quill and includes its `ql-clipboard` and `ql-tooltip` sibling divs, which
 *    are editor chrome rather than content. Imported raw they render as stray
 *    empty blocks and a hidden link on every product page.
 * 3. **Images live on a third-party host.** They are sideloaded into the media
 *    library rather than hot-linked, so the store keeps working if the Firebase
 *    bucket is ever taken down and so WordPress can generate its own sizes.
 *
 * The importer is idempotent: products are matched on SKU, which is the source
 * `productId`, so a second run updates rather than duplicates. Images already
 * attached to a product are left alone, because sideloading is the slow part.
 */
final class FirebaseImporter {

	/**
	 * Meta key marking a product as imported, and recording its source id.
	 */
	private const SOURCE_META = '_bhc_import_source_id';

	/**
	 * Attribute taxonomy used for the size/dimension axis of variable products.
	 */
	private const SIZE_TAXONOMY = 'pa_size';

	/**
	 * Attribute taxonomy used for colour.
	 */
	private const COLOUR_TAXONOMY = 'pa_colour';

	/**
	 * Counters describing what the run did.
	 *
	 * @var array<string, int>
	 */
	private array $stats = [
		'created'    => 0,
		'updated'    => 0,
		'variations' => 0,
		'images'     => 0,
		'categories' => 0,
		'skipped'    => 0,
		'failed'     => 0,
	];

	/**
	 * Messages describing anything that could not be imported cleanly.
	 *
	 * @var string[]
	 */
	private array $problems = [];

	/**
	 * Runs the import.
	 *
	 * @param string               $path    Path to the export JSON.
	 * @param array<string, mixed> $options dry_run, skip_images, limit, progress.
	 *
	 * @return array<string, mixed> Stats and problems.
	 */
	public function import( string $path, array $options = [] ): array {
		$dry_run     = (bool) ( $options['dry_run'] ?? false );
		$skip_images = (bool) ( $options['skip_images'] ?? false );
		$limit       = (int) ( $options['limit'] ?? 0 );
		$progress    = $options['progress'] ?? null;

		$products = $this->read_products( $path );

		if ( $products instanceof WP_Error ) {
			return [ 'error' => $products->get_error_message() ];
		}

		if ( $limit > 0 ) {
			$products = array_slice( $products, 0, $limit, true );
		}

		$this->register_attribute_taxonomies( $dry_run );

		$index = 0;
		$total = count( $products );

		foreach ( $products as $source_id => $record ) {
			++$index;

			if ( is_callable( $progress ) ) {
				$progress( $index, $total, (string) ( $record['productName'] ?? $source_id ) );
			}

			$this->import_one( (string) $source_id, $record, $dry_run, $skip_images );
		}

		return [
			'stats'    => $this->stats,
			'problems' => $this->problems,
		];
	}

	/**
	 * Reads and validates the export file.
	 *
	 * @param string $path Path to the export JSON.
	 *
	 * @return array<string, array<string, mixed>>|WP_Error
	 */
	private function read_products( string $path ) {
		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'bhc_import_unreadable', sprintf( 'Cannot read %s', $path ) );
		}

		$raw = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file, not a remote request.

		if ( false === $raw ) {
			return new WP_Error( 'bhc_import_unreadable', sprintf( 'Cannot read %s', $path ) );
		}

		$data = json_decode( $raw, true );

		if ( ! is_array( $data ) ) {
			return new WP_Error( 'bhc_import_invalid', 'The file is not valid JSON.' );
		}

		// The export may be the whole database or just the product branch.
		$products = $data['product'] ?? $data;

		if ( ! is_array( $products ) || [] === $products ) {
			return new WP_Error( 'bhc_import_empty', 'No `product` branch found in the export.' );
		}

		return $products;
	}

	/**
	 * Imports one product record.
	 *
	 * @param string               $source_id   Source product id, used as the SKU.
	 * @param array<string, mixed> $record      Source record.
	 * @param bool                 $dry_run     Report only.
	 * @param bool                 $skip_images Do not sideload imagery.
	 */
	private function import_one( string $source_id, array $record, bool $dry_run, bool $skip_images ): void {
		$name = trim( (string) ( $record['productName'] ?? '' ) );

		if ( '' === $name ) {
			++$this->stats['skipped'];

			$this->problems[] = sprintf( '%s: no productName, skipped.', $source_id );

			return;
		}

		$tiers       = $this->price_tiers( $record );
		$is_variable = count( $tiers ) > 1;

		if ( $dry_run ) {
			++$this->stats[ $this->find_by_sku( $source_id ) > 0 ? 'updated' : 'created' ];

			if ( $is_variable ) {
				$this->stats['variations'] += count( $tiers );
			}

			return;
		}

		$existing_id = $this->find_by_sku( $source_id );
		$product     = $existing_id > 0 ? wc_get_product( $existing_id ) : null;

		// A product that changes shape between runs — simple becoming variable —
		// cannot be reused, because WooCommerce keys the class off the term.
		$wrong_class = $product && ( $is_variable xor $product instanceof WC_Product_Variable );

		if ( ! $product || $wrong_class ) {
			if ( $wrong_class && $product ) {
				$product->delete( true );
			}

			$product = $is_variable ? new WC_Product_Variable() : new WC_Product_Simple();

			++$this->stats['created'];
		} else {
			++$this->stats['updated'];
		}

		$product->set_name( $name );
		$product->set_sku( $source_id );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'visible' );
		$product->set_description( $this->clean_description( (string) ( $record['productDescription'] ?? '' ) ) );
		$product->set_short_description( '' );
		$product->set_featured( ! empty( $record['featured'] ) );

		$this->apply_stock( $product, $record );

		if ( ! $is_variable ) {
			$this->apply_simple_price( $product, $record, $tiers );
		}

		$product->set_category_ids( $this->category_ids( $record ) );
		$product->set_attributes( $this->build_attributes( $record, $tiers, $is_variable ) );

		$product_id = $product->save();

		if ( ! $product_id ) {
			++$this->stats['failed'];

			$this->problems[] = sprintf( '%s (%s): save failed.', $source_id, $name );

			return;
		}

		update_post_meta( $product_id, self::SOURCE_META, $source_id );

		if ( $is_variable ) {
			$this->sync_variations( $product_id, $tiers, $record );
		}

		if ( ! $skip_images ) {
			$this->attach_images( $product_id, $record, $name );
		}
	}

	/**
	 * Normalises the price field into a list of tiers.
	 *
	 * A scalar `productPrice` yields one tier with an empty label, which is the
	 * simple-product case. An array yields one tier per row, each labelled by
	 * its `type` — the size or dimension string the shop sells by.
	 *
	 * @param array<string, mixed> $record Source record.
	 *
	 * @return array<int, array{label: string, price: float}>
	 */
	private function price_tiers( array $record ): array {
		$price = $record['productPrice'] ?? '';

		if ( ! is_array( $price ) ) {
			return [
				[
					'label' => '',
					'price' => $this->to_price( $price ),
				],
			];
		}

		$tiers = [];
		$seen  = [];

		foreach ( $price as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$label = trim( (string) ( $row['type'] ?? '' ) );
			$value = $this->to_price( $row['price'] ?? '' );

			if ( '' === $label || $value <= 0 ) {
				continue;
			}

			// Source data repeats a dimension with and without a trailing space.
			$key = strtolower( preg_replace( '/\s+/', ' ', $label ) ?? $label );

			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;

			$tiers[] = [
				'label' => $label,
				'price' => $value,
			];
		}

		if ( [] === $tiers ) {
			return [
				[
					'label' => '',
					'price' => 0.0,
				],
			];
		}

		return $tiers;
	}

	/**
	 * Parses a price, tolerating currency symbols and stray whitespace.
	 *
	 * @param mixed $value Raw value.
	 */
	private function to_price( mixed $value ): float {
		$clean = preg_replace( '/[^0-9.]/', '', (string) $value );

		return is_string( $clean ) && '' !== $clean ? (float) $clean : 0.0;
	}

	/**
	 * Strips Quill's editor scaffolding, keeping the authored content.
	 *
	 * Quill serialises its own UI alongside the content: a `ql-clipboard` div, a
	 * `ql-tooltip` block containing a hidden link and a formula input. Imported
	 * raw they render as empty blocks and a stray "about:blank" anchor on every
	 * product page. Only the `ql-editor` contents are real.
	 *
	 * @param string $html Source description.
	 */
	private function clean_description( string $html ): string {
		if ( '' === trim( $html ) ) {
			return '';
		}

		if ( preg_match( '#<div[^>]*class="[^"]*\bql-editor\b[^"]*"[^>]*>(.*?)</div>\s*(?:<div[^>]*class="[^"]*\bql-(?:clipboard|tooltip)\b|$)#is', $html, $m ) ) {
			$html = $m[1];
		}

		// Whatever survived, drop any remaining editor-only nodes.
		$html = preg_replace( '#<div[^>]*class="[^"]*\bql-(?:clipboard|tooltip)\b[^"]*".*?</div>#is', '', $html ) ?? $html;
		$html = preg_replace( '#\s*(?:contenteditable|data-gramm|tabindex|spellcheck)="[^"]*"#i', '', $html ) ?? $html;

		$html = wp_kses_post( $html );

		return trim( $html );
	}

	/**
	 * Sets stock level and status from the source quantity.
	 *
	 * @param \WC_Product          $product Product object.
	 * @param array<string, mixed> $record  Source record.
	 */
	private function apply_stock( \WC_Product $product, array $record ): void {
		$qty = (int) preg_replace( '/[^0-9]/', '', (string) ( $record['productQty'] ?? '' ) );

		$product->set_manage_stock( true );
		$product->set_stock_quantity( $qty );
		$product->set_stock_status( $qty > 0 ? 'instock' : 'outofstock' );
		$product->set_backorders( 'no' );
	}

	/**
	 * Sets regular and sale price on a simple product.
	 *
	 * The source keeps the pre-discount figure in `productOldPrice`. Where it is
	 * higher than `productPrice` the difference is a genuine sale; where it is
	 * absent, equal or lower it is noise and only the regular price is set.
	 *
	 * @param \WC_Product                                    $product Product object.
	 * @param array<string, mixed>                           $record  Source record.
	 * @param array<int, array{label: string, price: float}> $tiers  Price tiers.
	 */
	private function apply_simple_price( \WC_Product $product, array $record, array $tiers ): void {
		$price = $tiers[0]['price'] ?? 0.0;
		$was   = $this->to_price( $record['productOldPrice'] ?? '' );

		if ( $was > $price && $price > 0 ) {
			$product->set_regular_price( (string) $was );
			$product->set_sale_price( (string) $price );

			return;
		}

		$product->set_regular_price( (string) $price );
		$product->set_sale_price( '' );
	}

	/**
	 * Resolves the product's categories, creating them where needed.
	 *
	 * The source carries a category and a sub-category as free text. They are
	 * mapped to a two-level `product_cat` hierarchy so the storefront's category
	 * navigation and the plugin's facets have something real to work with.
	 *
	 * @param array<string, mixed> $record Source record.
	 *
	 * @return int[]
	 */
	private function category_ids( array $record ): array {
		$ids    = [];
		$parent = 0;

		$top = $this->tidy_label( (string) ( $record['productCategory'] ?? '' ) );

		if ( '' !== $top ) {
			$parent = $this->ensure_term( $top, 0 );

			if ( $parent > 0 ) {
				$ids[] = $parent;
			}
		}

		$sub = $this->tidy_label( (string) ( $record['productSubCategory'] ?? '' ) );

		if ( '' !== $sub && 0 !== strcasecmp( $sub, $top ) ) {
			$child = $this->ensure_term( $sub, $parent );

			if ( $child > 0 ) {
				$ids[] = $child;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Finds or creates a product category.
	 *
	 * @param string $name   Category name.
	 * @param int    $parent Parent term id.
	 */
	private function ensure_term( string $name, int $parent ): int {
		$slug = sanitize_title( $name );

		$existing = get_term_by( 'slug', $slug, 'product_cat' );

		if ( $existing instanceof WP_Term ) {
			return (int) $existing->term_id;
		}

		$created = wp_insert_term(
			$name,
			'product_cat',
			[
				'slug'   => $slug,
				'parent' => $parent,
			]
		);

		if ( is_wp_error( $created ) ) {
			// A slug collision against another taxonomy still returns the id.
			$data = $created->get_error_data();

			return is_array( $data ) && isset( $data['term_id'] ) ? (int) $data['term_id'] : 0;
		}

		++$this->stats['categories'];

		return (int) $created['term_id'];
	}

	/**
	 * Title-cases a shouted source label without mangling short words.
	 *
	 * The export is almost entirely upper case ("BULL HORN CUTLERY"), which
	 * reads as shouting in a storefront. Words of two characters or fewer, and
	 * anything containing a digit, are left alone so "3D" and "MM" survive.
	 *
	 * @param string $label Source label.
	 */
	private function tidy_label( string $label ): string {
		$label = trim( preg_replace( '/\s+/', ' ', $label ) ?? $label );

		if ( '' === $label ) {
			return '';
		}

		if ( strtoupper( $label ) !== $label ) {
			return $label;
		}

		$words = array_map(
			static function ( string $word ): string {
				if ( strlen( $word ) <= 2 || preg_match( '/\d/', $word ) ) {
					return $word;
				}

				return ucfirst( strtolower( $word ) );
			},
			explode( ' ', $label )
		);

		return implode( ' ', $words );
	}

	/**
	 * Makes sure the size and colour attribute taxonomies exist.
	 *
	 * @param bool $dry_run Report only.
	 */
	private function register_attribute_taxonomies( bool $dry_run ): void {
		if ( $dry_run || ! function_exists( 'wc_create_attribute' ) ) {
			return;
		}

		$wanted = [
			'size'   => 'Size',
			'colour' => 'Colour',
		];

		$existing = wc_get_attribute_taxonomy_names();

		foreach ( $wanted as $slug => $label ) {
			if ( in_array( 'pa_' . $slug, $existing, true ) ) {
				continue;
			}

			wc_create_attribute(
				[
					'name'         => $label,
					'slug'         => $slug,
					'type'         => 'select',
					'order_by'     => 'menu_order',
					'has_archives' => false,
				]
			);
		}

		delete_transient( 'wc_attribute_taxonomies' );

		if ( function_exists( 'wc_get_attribute_taxonomies' ) ) {
			wc_get_attribute_taxonomies();
		}
	}

	/**
	 * Builds the product's attributes.
	 *
	 * @param array<string, mixed>                           $record      Source record.
	 * @param array<int, array{label: string, price: float}> $tiers       Price tiers.
	 * @param bool                                           $is_variable Whether the product varies.
	 *
	 * @return WC_Product_Attribute[]
	 */
	private function build_attributes( array $record, array $tiers, bool $is_variable ): array {
		$attributes = [];
		$position   = 0;

		if ( $is_variable ) {
			$labels = array_column( $tiers, 'label' );
			$ids    = $this->ensure_attribute_terms( self::SIZE_TAXONOMY, $labels );

			if ( [] !== $ids ) {
				$attributes[] = $this->attribute( self::SIZE_TAXONOMY, $ids, $position++, true );
			}
		}

		$colour = $this->tidy_label( (string) ( $record['productColor'] ?? '' ) );

		// "Same", "Any" and "None" are placeholders in the source, not colours.
		if ( '' !== $colour && ! in_array( strtolower( $colour ), [ 'same', 'any', 'none', 'n/a' ], true ) ) {
			$ids = $this->ensure_attribute_terms( self::COLOUR_TAXONOMY, [ $colour ] );

			if ( [] !== $ids ) {
				$attributes[] = $this->attribute( self::COLOUR_TAXONOMY, $ids, $position++, false );
			}
		}

		return $attributes;
	}

	/**
	 * Builds one attribute object.
	 *
	 * @param string $taxonomy  Attribute taxonomy.
	 * @param int[]  $term_ids  Term ids.
	 * @param int    $position  Display order.
	 * @param bool   $variation Whether it drives variations.
	 */
	private function attribute( string $taxonomy, array $term_ids, int $position, bool $variation ): WC_Product_Attribute {
		$attribute = new WC_Product_Attribute();

		$attribute->set_id( (int) wc_attribute_taxonomy_id_by_name( $taxonomy ) );
		$attribute->set_name( $taxonomy );
		$attribute->set_options( $term_ids );
		$attribute->set_position( $position );
		$attribute->set_visible( true );
		$attribute->set_variation( $variation );

		return $attribute;
	}

	/**
	 * Finds or creates attribute terms and returns their ids.
	 *
	 * @param string   $taxonomy Attribute taxonomy.
	 * @param string[] $labels   Term labels.
	 *
	 * @return int[]
	 */
	private function ensure_attribute_terms( string $taxonomy, array $labels ): array {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			register_taxonomy(
				$taxonomy,
				'product',
				[
					'hierarchical' => false,
					'public'       => false,
				]
			);
		}

		$ids = [];

		foreach ( $labels as $label ) {
			$label = trim( (string) $label );

			if ( '' === $label ) {
				continue;
			}

			$slug = sanitize_title( $label );
			$term = get_term_by( 'slug', $slug, $taxonomy );

			if ( ! $term instanceof WP_Term ) {
				$created = wp_insert_term( $label, $taxonomy, [ 'slug' => $slug ] );

				if ( is_wp_error( $created ) ) {
					continue;
				}

				$ids[] = (int) $created['term_id'];

				continue;
			}

			$ids[] = (int) $term->term_id;
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Creates or updates the variations of a variable product.
	 *
	 * @param int                                            $product_id Parent id.
	 * @param array<int, array{label: string, price: float}> $tiers      Price tiers.
	 * @param array<string, mixed>                           $record     Source record.
	 */
	private function sync_variations( int $product_id, array $tiers, array $record ): void {
		$parent = wc_get_product( $product_id );

		if ( ! $parent instanceof WC_Product_Variable ) {
			return;
		}

		$existing = [];

		foreach ( $parent->get_children() as $child_id ) {
			$variation = wc_get_product( $child_id );

			if ( $variation instanceof WC_Product_Variation ) {
				$slug = (string) ( $variation->get_attributes()[ self::SIZE_TAXONOMY ] ?? '' );

				$existing[ $slug ] = $variation;
			}
		}

		$qty  = (int) preg_replace( '/[^0-9]/', '', (string) ( $record['productQty'] ?? '' ) );
		$keep = [];

		foreach ( $tiers as $tier ) {
			$slug          = sanitize_title( $tier['label'] );
			$keep[ $slug ] = true;

			$variation = $existing[ $slug ] ?? new WC_Product_Variation();

			$variation->set_parent_id( $product_id );
			$variation->set_attributes( [ self::SIZE_TAXONOMY => $slug ] );
			$variation->set_regular_price( (string) $tier['price'] );
			$variation->set_status( 'publish' );
			$variation->set_manage_stock( false );
			$variation->set_stock_status( $qty > 0 ? 'instock' : 'outofstock' );
			$variation->save();

			++$this->stats['variations'];
		}

		// A tier removed at source should not linger as a buyable variation.
		foreach ( $existing as $slug => $variation ) {
			if ( ! isset( $keep[ $slug ] ) ) {
				$variation->delete( true );
			}
		}

		WC_Product_Variable::sync( $product_id );
	}

	/**
	 * Sideloads the product's imagery into the media library.
	 *
	 * Skipped entirely when the product already has a featured image, because
	 * downloading is far and away the slowest part of an import and a re-run is
	 * usually about copy or pricing rather than photography.
	 *
	 * @param int                  $product_id Product id.
	 * @param array<string, mixed> $record     Source record.
	 * @param string               $name       Product name, used for alt text.
	 */
	private function attach_images( int $product_id, array $record, string $name ): void {
		$urls = $record['productImage'] ?? [];

		if ( ! is_array( $urls ) || [] === $urls ) {
			return;
		}

		if ( has_post_thumbnail( $product_id ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_ids = [];

		foreach ( array_values( $urls ) as $position => $url ) {
			$url = trim( (string) $url );

			if ( '' === $url ) {
				continue;
			}

			$description = 0 === $position
				? $name
				: sprintf( '%s, view %d', $name, $position + 1 );

			$attachment_id = $this->sideload( $url, $product_id, $description );

			if ( $attachment_id > 0 ) {
				$attachment_ids[] = $attachment_id;

				++$this->stats['images'];
			}
		}

		if ( [] === $attachment_ids ) {
			return;
		}

		set_post_thumbnail( $product_id, (int) array_shift( $attachment_ids ) );

		if ( [] !== $attachment_ids ) {
			update_post_meta( $product_id, '_product_image_gallery', implode( ',', $attachment_ids ) );
		}
	}

	/**
	 * Downloads one image and attaches it to the product.
	 *
	 * Firebase Storage URLs carry the real filename inside a percent-encoded
	 * path and append a query string, neither of which `media_sideload_image()`
	 * handles well, so the download is done explicitly with a filename derived
	 * from the path.
	 *
	 * @param string $url         Remote URL.
	 * @param int    $product_id  Product to attach to.
	 * @param string $description Alt text and title.
	 */
	private function sideload( string $url, int $product_id, string $description ): int {
		$tmp = download_url( $url, 60 );

		if ( is_wp_error( $tmp ) ) {
			$this->problems[] = sprintf( 'image download failed (%s): %s', $description, $tmp->get_error_message() );

			return 0;
		}

		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$name = sanitize_file_name( urldecode( basename( $path ) ) );

		if ( '' === $name || ! preg_match( '/\.(jpe?g|png|gif|webp|avif)$/i', $name ) ) {
			$name = sanitize_title( $description ) . '.jpg';
		}

		$file = [
			'name'     => $name,
			'tmp_name' => $tmp,
		];

		$attachment_id = media_handle_sideload( $file, $product_id, $description );

		if ( is_wp_error( $attachment_id ) ) {
			// media_handle_sideload only unlinks the temp file on success.
			wp_delete_file( $tmp );

			$this->problems[] = sprintf( 'image import failed (%s): %s', $description, $attachment_id->get_error_message() );

			return 0;
		}

		update_post_meta( (int) $attachment_id, '_wp_attachment_image_alt', $description );

		return (int) $attachment_id;
	}

	/**
	 * Finds an existing product by SKU.
	 *
	 * @param string $sku SKU to look for.
	 */
	private function find_by_sku( string $sku ): int {
		$id = wc_get_product_id_by_sku( $sku );

		return is_numeric( $id ) ? (int) $id : 0;
	}
}
