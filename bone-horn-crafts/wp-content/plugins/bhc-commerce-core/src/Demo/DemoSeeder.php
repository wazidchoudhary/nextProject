<?php
/**
 * Demo dataset seeder.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Demo;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Contracts\LoggerInterface;
use BoneHornCrafts\Core\Support\Options;
use BoneHornCrafts\Core\Product\Attributes\AttributeCatalog;
use BoneHornCrafts\Core\Product\Attributes\AttributeRegistrar;
use BoneHornCrafts\Core\Product\ProductMeta;
use WC_Product_Attribute;
use WC_Product_Simple;
use WC_Product_Variable;
use WC_Product_Variation;
use WP_Term;

/**
 * Builds the entire demo store from the deterministic dataset.
 *
 * Design rules:
 *
 * * **Idempotent.** Products are matched by SKU, pages by slug, terms by slug.
 *   Running the seeder twice updates in place and creates nothing new, so it is
 *   safe in a CI pipeline or after a partial failure.
 * * **Tracked.** Every id it creates is recorded in {@see DemoState}, which is
 *   what allows a reset to remove exactly the demo content and nothing else.
 * * **Bounded.** Work happens in steps that report progress, so a CLI run shows
 *   where it is rather than appearing to hang while it renders imagery.
 */
final class DemoSeeder {

	/**
	 * Transient key guarding against concurrent seeding runs.
	 */
	private const LOCK_KEY = 'bhc_demo_seeding';

	/**
	 * Progress callback.
	 *
	 * @var callable(string):void
	 */
	private $progress;

	/**
	 * Constructor.
	 *
	 * @param DemoState          $state      Demo bookkeeping.
	 * @param ImageFactory       $images     Image renderer.
	 * @param AttributeRegistrar $attributes Attribute installer.
	 * @param LoggerInterface    $logger     Logger.
	 * @param Options            $options    Plugin settings.
	 */
	public function __construct(
		private DemoState $state,
		private ImageFactory $images,
		private AttributeRegistrar $attributes,
		private LoggerInterface $logger,
		private Options $options
	) {
		$this->progress = static function ( string $message ): void {};
	}

	/**
	 * Registers a progress reporter.
	 *
	 * @param callable(string):void $callback Reporter.
	 */
	public function on_progress( callable $callback ): void {
		$this->progress = $callback;
	}

	/**
	 * Runs the whole seed.
	 *
	 * @param array<string, mixed> $options Seeding options.
	 *
	 * @return array<string, int>
	 */
	public function seed( array $options = [] ): array {
		$options = array_merge(
			[
				'products' => 0,
				'orders'   => 24,
				'images'   => true,
				'content'  => true,
			],
			$options
		);

		$counts = [];

		// A second seeder running concurrently (a stray CLI process, a CI job
		// that overlapped) would race the SKU lookup and create duplicate
		// products. One short-lived lock removes that whole class of problem.
		if ( ! $this->acquire_lock() ) {
			$this->report( 'Another seeding run is already in progress — aborting.' );

			return [];
		}

		// Seeding creates customers and orders, each of which would otherwise
		// fire a transactional email. Nobody wants 24 order confirmations from
		// a demo build, and on a slow mail transport it dominates the runtime.
		$silence_mail = static fn (): bool => true;

		add_filter( 'pre_wp_mail', $silence_mail, 9999 );

		$this->report( 'Configuring store settings' );
		$this->configure_store();

		$this->report( 'Installing craft attributes' );
		$attribute_result          = $this->attributes->install();
		$counts['attributes']      = (int) $attribute_result['created_attributes'];
		$counts['attribute_terms'] = (int) $attribute_result['created_terms'];

		$this->report( 'Creating categories and tags' );
		$counts['categories'] = $this->seed_categories();
		$counts['tags']       = $this->seed_tags();

		$this->report( 'Creating products' );
		$counts['products'] = $this->seed_products( (int) $options['products'], (bool) $options['images'] );

		$this->report( 'Configuring shipping zones' );
		$counts['shipping_zones']   = $this->seed_shipping();
		$counts['payment_gateways'] = $this->seed_payment_gateways();
		$this->seed_social_image();

		$this->report( 'Creating demo customers' );
		$counts['customers'] = $this->seed_customers();

		$this->report( 'Creating demo reviews' );
		$counts['reviews'] = $this->seed_reviews();

		$this->report( 'Creating demo orders' );
		$counts['orders'] = $this->seed_orders( (int) $options['orders'] );

		if ( $options['content'] ) {
			$this->report( 'Creating pages and journal articles' );
			$counts['pages']    = $this->seed_pages();
			$counts['articles'] = $this->seed_articles( (bool) $options['images'] );

			$this->report( 'Building navigation menus' );
			$counts['menus'] = $this->seed_menus();
		}

		remove_filter( 'pre_wp_mail', $silence_mail, 9999 );

		$this->release_lock();

		$this->logger->info( 'demo.seeded', $counts );

		return $counts;
	}

	/**
	 * Applies the store settings the demo expects.
	 */
	public function configure_store(): void {
		update_option( 'woocommerce_currency', 'USD' );
		update_option( 'woocommerce_currency_pos', 'left' );
		update_option( 'woocommerce_price_thousand_sep', ',' );
		update_option( 'woocommerce_price_decimal_sep', '.' );
		update_option( 'woocommerce_price_num_decimals', 2 );
		update_option( 'woocommerce_weight_unit', 'kg' );
		update_option( 'woocommerce_dimension_unit', 'in' );
		update_option( 'woocommerce_enable_reviews', 'yes' );
		update_option( 'woocommerce_enable_review_rating', 'yes' );
		update_option( 'woocommerce_review_rating_required', 'yes' );
		update_option( 'woocommerce_manage_stock', 'yes' );
		update_option( 'woocommerce_notify_low_stock_amount', 6 );
		update_option( 'woocommerce_catalog_columns', 4 );
		update_option( 'woocommerce_catalog_rows', 3 );
		update_option( 'woocommerce_allowed_countries', 'all_except' );
		update_option( 'woocommerce_all_except_countries', [] );
		update_option( 'woocommerce_ship_to_countries', '' );
		update_option( 'woocommerce_default_country', 'IN:UP' );
		update_option( 'woocommerce_calc_taxes', 'no' );

		$this->configure_accounts();

		// New WooCommerce installs start in "coming soon" mode, which returns a
		// placeholder for every storefront URL. A seeded demo must be live.
		update_option( 'woocommerce_coming_soon', 'no' );
		update_option( 'woocommerce_store_pages_only', 'no' );

		// WordPress fills a newly registered sidebar with default widgets
		// (Archives, Categories, Meta). None of them belong in this footer.
		$sidebars = get_option( 'sidebars_widgets', [] );

		if ( is_array( $sidebars ) && ! empty( $sidebars['footer-workshop'] ) ) {
			$sidebars['footer-workshop'] = [];

			update_option( 'sidebars_widgets', $sidebars );
		}
		update_option( 'blogname', 'Bone Horn Crafts' );
		update_option( 'blogdescription', 'Natural Materials, Handcrafted for Makers' );
		update_option( 'timezone_string', 'Asia/Kolkata' );

		if ( '' === (string) get_option( 'permalink_structure' ) ) {
			update_option( 'permalink_structure', '/%postname%/' );

			flush_rewrite_rules( false );
		}

		$this->purge_default_content();
	}

	/**
	 * Turns on customer accounts.
	 *
	 * Delegates to {@see \BoneHornCrafts\Core\Customer\AccountSetup}, which
	 * owns these settings. They used to live here, which meant a store that
	 * imported a real catalogue and never ran the seeder never got them —
	 * registration is store policy, not demo content.
	 */
	private function configure_accounts(): void {
		( new \BoneHornCrafts\Core\Customer\AccountSetup() )->apply();
	}

	/**
	 * Removes the placeholder content WordPress and WooCommerce ship with.
	 *
	 * A fresh install leaves behind "Hello world!", "Sample Page", the
	 * "A WordPress Commenter" comment and WooCommerce's draft refund policy.
	 * They are the clearest tell that a site is an untouched install, so a
	 * store that is meant to read as a real business has to clear them.
	 *
	 * Each is matched on its default slug *and* left alone once edited, so an
	 * operator who repurposed one of these pages does not lose the work.
	 */
	private function purge_default_content(): void {
		$defaults = [
			'post' => [ 'hello-world' ],
			'page' => [ 'sample-page', 'refund_returns', 'privacy-policy-2' ],
		];

		foreach ( $defaults as $post_type => $slugs ) {
			foreach ( $slugs as $slug ) {
				$posts = get_posts(
					[
						'name'             => $slug,
						'post_type'        => $post_type,
						'post_status'      => [ 'publish', 'draft', 'auto-draft', 'pending' ],
						'numberposts'      => 1,
						'suppress_filters' => false,
					]
				);

				if ( [] === $posts ) {
					continue;
				}

				$post = $posts[0];

				// An edited placeholder is somebody's content now: the modified
				// timestamp only stays equal to the created one while untouched.
				if ( $post->post_modified_gmt !== $post->post_date_gmt ) {
					continue;
				}

				wp_delete_post( $post->ID, true );
			}
		}

		$default_comments = get_comments(
			[
				'author_email' => 'wapuu@wordpress.example',
				'number'       => 5,
			]
		);

		foreach ( $default_comments as $comment ) {
			wp_delete_comment( (int) $comment->comment_ID, true );
		}
	}

	/**
	 * Creates the product categories.
	 */
	public function seed_categories(): int {
		$created = 0;

		foreach ( ProductCatalog::categories() as $slug => $definition ) {
			$parent_id = 0;

			if ( '' !== $definition['parent'] ) {
				$parent = get_term_by( 'slug', $definition['parent'], 'product_cat' );

				$parent_id = $parent instanceof WP_Term ? (int) $parent->term_id : 0;
			}

			$existing = get_term_by( 'slug', $slug, 'product_cat' );

			if ( $existing instanceof WP_Term ) {
				wp_update_term(
					(int) $existing->term_id,
					'product_cat',
					[
						'name'        => wp_specialchars_decode( $definition['name'] ),
						'description' => $definition['description'],
						'parent'      => $parent_id,
					]
				);

				continue;
			}

			$term = wp_insert_term(
				wp_specialchars_decode( $definition['name'] ),
				'product_cat',
				[
					'slug'        => $slug,
					'description' => $definition['description'],
					'parent'      => $parent_id,
				]
			);

			if ( ! is_wp_error( $term ) ) {
				++$created;

				$this->state->track( 'terms', (int) $term['term_id'] );
			}
		}

		return $created;
	}

	/**
	 * Creates the merchandising tags.
	 */
	public function seed_tags(): int {
		$created = 0;

		foreach ( ProductCatalog::tags() as $slug => $name ) {
			if ( get_term_by( 'slug', $slug, 'product_tag' ) instanceof WP_Term ) {
				continue;
			}

			$term = wp_insert_term( wp_specialchars_decode( $name ), 'product_tag', [ 'slug' => $slug ] );

			if ( ! is_wp_error( $term ) ) {
				++$created;

				$this->state->track( 'terms', (int) $term['term_id'] );
			}
		}

		return $created;
	}

	/**
	 * Creates or updates the products.
	 *
	 * @param int  $limit       Maximum products (0 = all).
	 * @param bool $with_images Whether to render imagery.
	 */
	public function seed_products( int $limit = 0, bool $with_images = true ): int {
		$catalog = ProductCatalog::products();

		if ( $limit > 0 ) {
			$catalog = array_slice( $catalog, 0, $limit );
		}

		$care  = ProductCatalog::care_notes();
		$total = count( $catalog );
		$index = 0;

		foreach ( $catalog as $definition ) {
			++$index;

			$this->report( sprintf( '  product %d/%d — %s', $index, $total, $definition['sku'] ) );

			$this->upsert_product( $definition, $care, $with_images, $index );
		}

		return $total;
	}

	/**
	 * Creates or updates a single product.
	 *
	 * @param array<string, mixed>  $definition  Catalogue row.
	 * @param array<string, string> $care        Care copy per family.
	 * @param bool                  $with_images Whether to render imagery.
	 * @param int                   $position    Menu order.
	 */
	private function upsert_product( array $definition, array $care, bool $with_images, int $position ): int {
		$sku        = (string) $definition['sku'];
		$product_id = $this->find_product_id( $sku );
		$variable   = $this->should_be_variable( $definition );

		$product = $product_id > 0 ? wc_get_product( $product_id ) : null;

		if ( null === $product || ( $variable && ! $product->is_type( 'variable' ) ) || ( ! $variable && $product->is_type( 'variable' ) ) ) {
			$product = $variable ? new WC_Product_Variable() : new WC_Product_Simple();

			if ( $product_id > 0 ) {
				$product->set_id( $product_id );
			}
		}

		$product->set_name( wp_specialchars_decode( (string) $definition['name'] ) );
		$product->set_sku( $sku );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'visible' );
		$product->set_menu_order( $position );
		$product->set_short_description( $this->short_description( $definition ) );
		$product->set_description( $this->long_description( $definition, $care ) );
		$product->set_reviews_allowed( true );
		$product->set_weight( (string) $definition['weight'] );
		$product->set_length( (string) $definition['dimensions'][0] );
		$product->set_width( (string) $definition['dimensions'][1] );
		$product->set_height( (string) $definition['dimensions'][2] );
		$product->set_date_created( $this->created_date( $definition, $position ) );

		if ( ! $variable ) {
			$product->set_regular_price( (string) $definition['price'] );
			$product->set_sale_price( null === $definition['sale_price'] ? '' : (string) $definition['sale_price'] );
			$product->set_manage_stock( true );
			$product->set_stock_quantity( (int) $definition['stock'] );
			$product->set_stock_status( (int) $definition['stock'] > 0 ? 'instock' : 'outofstock' );
			$product->set_backorders( 'no' );
		} else {
			$product->set_manage_stock( false );
			$product->set_stock_status( 'instock' );
		}

		$product->set_category_ids( $this->term_ids( (array) $definition['categories'], 'product_cat' ) );
		$product->set_tag_ids( $this->term_ids( (array) $definition['tags'], 'product_tag' ) );
		$product->set_attributes( $this->build_attributes( $definition, $variable ) );

		ProductMeta::set( $product, ProductMeta::HSN_CODE, (string) $definition['hsn'] );
		ProductMeta::set( $product, ProductMeta::GST_RATE, (float) $definition['gst'] );
		ProductMeta::set( $product, ProductMeta::BATCH_REFERENCE, (string) $definition['lot'] );
		ProductMeta::set( $product, ProductMeta::LEAD_TIME_DAYS, (int) $definition['lead_time'] );
		ProductMeta::set( $product, ProductMeta::ORIGIN_COUNTRY, 'IN' );
		ProductMeta::set( $product, ProductMeta::UNIT_OF_SALE, (string) $definition['unit'] );
		ProductMeta::set( $product, ProductMeta::PAIR_MATCHED, ! empty( $definition['pair_matched'] ) ? 'yes' : 'no' );
		ProductMeta::set( $product, ProductMeta::LIMITED_BATCH, ! empty( $definition['limited'] ) ? 'yes' : 'no' );
		ProductMeta::set( $product, ProductMeta::CARE_INSTRUCTIONS, $care[ (string) $definition['family'] ] ?? '' );
		ProductMeta::set( $product, ProductMeta::WHOLESALE_ENABLED, ! empty( $definition['wholesale'] ) ? 'yes' : 'no' );
		ProductMeta::set( $product, ProductMeta::PRICE_TIERS, (array) ( $definition['tiers'] ?? [] ) );

		$badges = (array) ( $definition['badges'] ?? [] );

		if ( ! empty( $definition['limited'] ) ) {
			$badges[] = 'limited-batch';
		}

		ProductMeta::set( $product, ProductMeta::BADGES, array_values( array_unique( $badges ) ) );

		$product->update_meta_data( '_bhc_demo', 'yes' );

		$product_id = (int) $product->save();

		if ( $product_id <= 0 ) {
			return 0;
		}

		$this->state->track( 'products', $product_id );

		if ( $with_images ) {
			$this->attach_images( $product_id, $definition );
		}

		if ( $variable ) {
			$this->sync_variations( $product_id, $definition );
		}

		return $product_id;
	}

	/**
	 * Resolves an existing product id for a SKU.
	 *
	 * `wc_get_product_id_by_sku()` reads `wc_product_meta_lookup`. That row is
	 * written by the CRUD layer, so an interrupted or partially rolled back
	 * write can leave a product whose SKU the lookup does not know about — and
	 * the next seed would then create a duplicate. Falling back to `postmeta`
	 * makes the seeder correct even against a damaged lookup table.
	 *
	 * @param string $sku Product SKU.
	 */
	private function find_product_id( string $sku ): int {
		$product_id = (int) wc_get_product_id_by_sku( $sku );

		if ( $product_id > 0 ) {
			return $product_id;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Seeder-only integrity check.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT pm.post_id FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key = '_sku' AND pm.meta_value = %s
				   AND p.post_type = 'product' AND p.post_status <> 'trash'
				 ORDER BY p.ID ASC LIMIT 1",
				$sku
			)
		);
	}

	/**
	 * Takes the seeding lock.
	 */
	private function acquire_lock(): bool {
		if ( false !== get_transient( self::LOCK_KEY ) ) {
			return false;
		}

		set_transient( self::LOCK_KEY, time(), 15 * MINUTE_IN_SECONDS );

		return true;
	}

	/**
	 * Releases the seeding lock.
	 */
	private function release_lock(): void {
		delete_transient( self::LOCK_KEY );
	}

	/**
	 * Whether a catalogue row is sold in several sizes.
	 *
	 * Deterministic so re-seeding never flips a product between simple and
	 * variable.
	 *
	 * @param array<string, mixed> $definition Catalogue row.
	 */
	private function should_be_variable( array $definition ): bool {
		if ( 'knife-scales' !== $definition['product_type'] ) {
			return false;
		}

		return 0 === ( crc32( (string) $definition['sku'] ) % 3 );
	}

	/**
	 * Size variations offered for a variable product.
	 *
	 * The middle entry is the catalogue row's own size, so a row whose size
	 * already matches one of the two offsets is offered in two sizes rather
	 * than three. Slugs are deduplicated here so callers never see the same
	 * size twice.
	 *
	 * @param array<string, mixed> $definition Catalogue row.
	 *
	 * @return array<int, array{slug:string, modifier:float, stock:int}>
	 */
	private function variation_sizes( array $definition ): array {
		$sizes = [
			[
				'slug'     => '4-5x1-25x0-25',
				'modifier' => -0.2,
				'stock'    => max( 4, (int) round( (int) $definition['stock'] * 0.4 ) ),
			],
			[
				'slug'     => (string) $definition['size'],
				'modifier' => 0.0,
				'stock'    => (int) $definition['stock'],
			],
			[
				'slug'     => '6x2x0-375',
				'modifier' => 0.36,
				'stock'    => max( 3, (int) round( (int) $definition['stock'] * 0.3 ) ),
			],
		];

		// The row's own size wins a collision: it carries the catalogue price,
		// where the other two only exist as offsets from it. Seed the map with
		// the base entry so neither offset can overwrite it, then re-order so
		// the sizes still read small to large on the product page.
		$base   = (string) $definition['size'];
		$unique = [ $base => $sizes[1] ];

		foreach ( [ $sizes[0], $sizes[2] ] as $size ) {
			$slug = (string) $size['slug'];

			if ( isset( $unique[ $slug ] ) ) {
				continue;
			}

			$unique[ $slug ] = $size;
		}

		$unique = array_values( $unique );

		usort( $unique, static fn ( array $a, array $b ): int => $a['modifier'] <=> $b['modifier'] );

		return $unique;
	}

	/**
	 * Creates or updates the size variations.
	 *
	 * @param int                  $product_id Parent product id.
	 * @param array<string, mixed> $definition Catalogue row.
	 */
	private function sync_variations( int $product_id, array $definition ): void {
		$parent = wc_get_product( $product_id );

		if ( ! $parent instanceof WC_Product_Variable ) {
			return;
		}

		$taxonomy = AttributeCatalog::taxonomy( AttributeCatalog::SIZE );
		$wanted   = array_column( $this->variation_sizes( $definition ), 'slug' );
		$existing = [];
		$stale    = [];

		foreach ( $parent->get_children() as $child_id ) {
			$variation = wc_get_product( (int) $child_id );

			if ( ! $variation instanceof WC_Product_Variation ) {
				continue;
			}

			// `get_attributes()` returns the stored term slug. `get_attribute()`
			// resolves it to the display name, which never matches the slugs in
			// variation_sizes() — keying on it made every reseed build a fresh
			// set of variations instead of updating the existing ones.
			$slug = (string) ( $variation->get_attributes()[ $taxonomy ] ?? '' );

			if ( '' === $slug || ! in_array( $slug, $wanted, true ) || isset( $existing[ $slug ] ) ) {
				$stale[] = $variation;

				continue;
			}

			$existing[ $slug ] = $variation;
		}

		// Sizes that were dropped from the catalogue, and any duplicates left by
		// an earlier run, go now: a variable product must offer each size once.
		foreach ( $stale as $variation ) {
			$variation->delete( true );
		}

		foreach ( $this->variation_sizes( $definition ) as $size ) {
			$slug = (string) $size['slug'];

			$variation = $existing[ $slug ] ?? new WC_Product_Variation();

			$variation->set_parent_id( $product_id );
			$variation->set_attributes( [ $taxonomy => $slug ] );
			$variation->set_status( 'publish' );
			$variation->set_manage_stock( true );
			$variation->set_stock_quantity( (int) $size['stock'] );
			$variation->set_stock_status( (int) $size['stock'] > 0 ? 'instock' : 'outofstock' );

			$price = round( (float) $definition['price'] * ( 1 + (float) $size['modifier'] ), 2 );

			$variation->set_regular_price( (string) $price );

			if ( null !== $definition['sale_price'] ) {
				$variation->set_sale_price( (string) round( (float) $definition['sale_price'] * ( 1 + (float) $size['modifier'] ), 2 ) );
			} else {
				$variation->set_sale_price( '' );
			}

			$variation_id = (int) $variation->save();

			if ( $variation_id > 0 ) {
				$this->state->track( 'products', $variation_id );
			}
		}

		if ( class_exists( \WC_Product_Variable::class ) ) {
			WC_Product_Variable::sync( $product_id );
		}
	}

	/**
	 * Builds the WooCommerce attribute objects for a product.
	 *
	 * @param array<string, mixed> $definition Catalogue row.
	 * @param bool                 $variable   Whether the product is variable.
	 *
	 * @return WC_Product_Attribute[]
	 */
	private function build_attributes( array $definition, bool $variable ): array {
		$map = [
			AttributeCatalog::MATERIAL     => [ (string) $definition['material'] ],
			AttributeCatalog::FINISH       => [ (string) $definition['finish'] ],
			AttributeCatalog::APPLICATION  => [ (string) $definition['application'] ],
			AttributeCatalog::COLOUR       => [ (string) $definition['colour'] ],
			AttributeCatalog::PRODUCT_TYPE => [ (string) $definition['product_type'] ],
			AttributeCatalog::SIZE         => $variable
				? array_column( $this->variation_sizes( $definition ), 'slug' )
				: [ (string) $definition['size'] ],
		];

		$attributes = [];
		$position   = 0;

		foreach ( $map as $slug => $slugs ) {
			$taxonomy = AttributeCatalog::taxonomy( $slug );
			$term_ids = [];

			foreach ( array_unique( $slugs ) as $term_slug ) {
				$term = get_term_by( 'slug', $term_slug, $taxonomy );

				if ( $term instanceof WP_Term ) {
					$term_ids[] = (int) $term->term_id;
				}
			}

			if ( [] === $term_ids ) {
				continue;
			}

			$attribute = new WC_Product_Attribute();

			$attribute->set_id( (int) wc_attribute_taxonomy_id_by_name( $taxonomy ) );
			$attribute->set_name( $taxonomy );
			$attribute->set_options( $term_ids );
			$attribute->set_position( $position++ );
			$attribute->set_visible( true );
			$attribute->set_variation( $variable && AttributeCatalog::SIZE === $slug );

			$attributes[] = $attribute;
		}

		return $attributes;
	}

	/**
	 * Renders and attaches product imagery.
	 *
	 * @param int                  $product_id Product id.
	 * @param array<string, mixed> $definition Catalogue row.
	 */
	private function attach_images( int $product_id, array $definition ): void {
		if ( ! $this->images->is_available() ) {
			return;
		}

		$name    = wp_specialchars_decode( (string) $definition['name'] );
		$gallery = [];

		for ( $view = 0; $view < 3; $view++ ) {
			$alt = 0 === $view
				? sprintf( '%s photographed on a workshop bench', $name )
				: sprintf( '%s, detail view %d', $name, $view );

			$attachment_id = $this->images->create(
				(string) $definition['sku'],
				(string) $definition['material'],
				(string) $definition['shape'],
				$name,
				$alt,
				$view,
				(string) ( $definition['colour'] ?? '' )
			);

			if ( $attachment_id <= 0 ) {
				continue;
			}

			$this->state->track( 'attachments', $attachment_id );

			wp_update_post(
				[
					'ID'          => $attachment_id,
					'post_parent' => $product_id,
				]
			);

			if ( 0 === $view ) {
				set_post_thumbnail( $product_id, $attachment_id );

				continue;
			}

			$gallery[] = $attachment_id;
		}

		if ( [] !== $gallery ) {
			update_post_meta( $product_id, '_product_image_gallery', implode( ',', $gallery ) );
		}
	}

	/**
	 * Builds the short description.
	 *
	 * @param array<string, mixed> $definition Catalogue row.
	 */
	private function short_description( array $definition ): string {
		return '<p>' . esc_html( (string) $definition['short'] ) . '</p>';
	}

	/**
	 * Builds the long description.
	 *
	 * @param array<string, mixed>  $definition Catalogue row.
	 * @param array<string, string> $care       Care copy per family.
	 */
	private function long_description( array $definition, array $care ): string {
		$bullets = '';

		foreach ( (array) $definition['bullets'] as $bullet ) {
			$bullets .= '<li>' . esc_html( (string) $bullet ) . '</li>';
		}

		$dimensions = sprintf(
			'%s x %s x %s in',
			$definition['dimensions'][0],
			$definition['dimensions'][1],
			$definition['dimensions'][2]
		);

		$specs = [
			'Material'     => AttributeCatalog::all()[ AttributeCatalog::MATERIAL ]['terms'][ $definition['material'] ] ?? $definition['material'],
			'Finish'       => AttributeCatalog::all()[ AttributeCatalog::FINISH ]['terms'][ $definition['finish'] ] ?? $definition['finish'],
			'Nominal size' => $dimensions,
			'Sold as'      => (string) $definition['unit'],
			'Lot'          => (string) $definition['lot'],
		];

		$spec_rows = '';

		foreach ( $specs as $label => $value ) {
			$spec_rows .= sprintf(
				'<tr><th scope="row">%s</th><td>%s</td></tr>',
				esc_html( (string) $label ),
				esc_html( wp_specialchars_decode( (string) $value ) )
			);
		}

		$lead_time = (int) $definition['lead_time'] > 0
			? sprintf(
				'<p><strong>Workshop lead time:</strong> this listing is cut to order and leaves the bench within %d working days.</p>',
				(int) $definition['lead_time']
			)
			: '<p><strong>In stock:</strong> ships from the shelf within one working day.</p>';

		return '<p>' . esc_html( (string) $definition['intro'] ) . '</p>'
			. '<h3>What you get</h3><ul>' . $bullets . '</ul>'
			. '<h3>Specification</h3><table class="bhc-spec-table"><tbody>' . $spec_rows . '</tbody></table>'
			. $lead_time
			. '<h3>Finishing</h3><p>' . esc_html( (string) ( $care[ (string) $definition['family'] ] ?? '' ) ) . '</p>';
	}

	/**
	 * Deterministic publish date so "new arrival" badges are stable.
	 *
	 * @param array<string, mixed> $definition Catalogue row.
	 * @param int                  $position   Catalogue position.
	 */
	private function created_date( array $definition, int $position ): string {
		$days = ( crc32( (string) $definition['sku'] ) % 210 ) + ( $position % 7 );

		return gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
	}

	/**
	 * Resolves term ids from slugs.
	 *
	 * @param string[] $slugs    Term slugs.
	 * @param string   $taxonomy Taxonomy.
	 *
	 * @return int[]
	 */
	private function term_ids( array $slugs, string $taxonomy ): array {
		$ids = [];

		foreach ( $slugs as $slug ) {
			$term = get_term_by( 'slug', $slug, $taxonomy );

			if ( $term instanceof WP_Term ) {
				$ids[] = (int) $term->term_id;
			}
		}

		return $ids;
	}

	/**
	 * Emits a progress message.
	 *
	 * @param string $message Message.
	 */
	private function report( string $message ): void {
		( $this->progress )( $message );
	}

	/**
	 * Points the social share image at a real photograph from the catalogue.
	 *
	 * Without one, the home page, the shop and every archive fall back to a
	 * text-only Twitter card and an Open Graph object with no image — the two
	 * highest-value share targets on the site being the worst-looking links.
	 * Only singular pages have a featured image to borrow.
	 *
	 * Set once and then left alone, so a store that has chosen its own share
	 * image keeps it.
	 */
	private function seed_social_image(): void {
		$options = $this->options->all();

		if ( ! empty( $options['social_image_id'] ) ) {
			return;
		}

		$products = $this->state->get( 'products' );

		foreach ( $products as $product_id ) {
			$image_id = (int) get_post_thumbnail_id( $product_id );

			if ( $image_id > 0 ) {
				$options['social_image_id'] = $image_id;

				$this->options->save( $options );

				return;
			}
		}
	}

	/**
	 * Enables the offline payment gateways so a demo store can take an order.
	 *
	 * A fresh WooCommerce install has every gateway disabled, which means a
	 * seeded demo looks complete and then fails at the last step of checkout
	 * with "Invalid payment method". Enabling the two offline gateways is what
	 * makes the purchase flow demonstrable end to end.
	 *
	 * Both are labelled "(demo)" on purpose: nothing here processes a payment,
	 * and nobody looking at the store should be left wondering whether it does.
	 *
	 * **Skipped entirely on production, and skipped whenever a real gateway is
	 * already live.** Relying on somebody remembering to turn these off after
	 * a seed is not a control — re-running the seeder on a configured store
	 * would otherwise quietly put two payment methods that take no money back
	 * on the checkout beside the one that does. `Payments\GatewayGuard` is the
	 * second half of that: it removes them from checkout on production even if
	 * they were enabled some other way.
	 *
	 * @return int Number of gateways enabled.
	 */
	public function seed_payment_gateways(): int {
		if ( 'production' === wp_get_environment_type() ) {
			$this->report( 'Production environment — demo payment gateways not enabled' );

			return 0;
		}

		if ( $this->has_real_gateway() ) {
			$this->report( 'A real payment gateway is configured — demo gateways not enabled' );

			return 0;
		}

		$gateways = [
			'cod'  => [
				'enabled'     => 'yes',
				'title'       => __( 'Pay on invoice (demo)', 'bhc-commerce-core' ),
				'description' => __( 'Demo gateway. No payment is taken and no payment details are stored in this build.', 'bhc-commerce-core' ),
			],
			'bacs' => [
				'enabled'     => 'yes',
				'title'       => __( 'Bank transfer (demo)', 'bhc-commerce-core' ),
				'description' => __( 'Demo gateway. No payment is taken and no bank details are stored in this build.', 'bhc-commerce-core' ),
			],
		];

		$enabled = 0;

		foreach ( $gateways as $id => $settings ) {
			$option   = 'woocommerce_' . $id . '_settings';
			$existing = get_option( $option, [] );
			$existing = is_array( $existing ) ? $existing : [];

			// Do not overwrite a gateway somebody has already configured.
			if ( isset( $existing['enabled'] ) && 'yes' === $existing['enabled'] ) {
				continue;
			}

			update_option( $option, array_merge( $existing, $settings ) );

			++$enabled;
		}

		if ( $enabled > 0 ) {
			$this->report( 'Enabling demo payment gateways' );
		}

		return $enabled;
	}

	/**
	 * Whether a gateway that actually takes money is enabled.
	 *
	 * Checks for any enabled gateway that is not one of the two offline ones
	 * the seeder itself manages. Cheques and bank transfer configured by hand
	 * with real account details are a merchant's decision, so those count only
	 * when their title is no longer the seeded demo copy.
	 */
	private function has_real_gateway(): bool {
		if ( ! function_exists( 'WC' ) || null === WC()->payment_gateways() ) {
			return false;
		}

		$demo_titles = [ 'Pay on invoice (demo)', 'Bank transfer (demo)' ];

		foreach ( WC()->payment_gateways()->payment_gateways() as $gateway ) {
			if ( 'yes' !== $gateway->enabled ) {
				continue;
			}

			if ( ! in_array( $gateway->get_title(), $demo_titles, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Creates the shipping zones and rates.
	 *
	 * @return int Number of zones created.
	 */
	public function seed_shipping(): int {
		if ( ! class_exists( \WC_Shipping_Zone::class ) ) {
			return 0;
		}

		$definitions = [
			'India (domestic)'        => [
				'countries' => [ 'IN' ],
				'rate'      => '6.00',
				'label'     => 'Courier, 2-5 working days',
			],
			'United States & Canada'  => [
				'countries' => [ 'US', 'CA' ],
				'rate'      => '14.00',
				'label'     => 'Tracked international parcel',
			],
			'United Kingdom & Europe' => [
				'countries' => [ 'GB', 'IE', 'DE', 'FR', 'IT', 'ES', 'NL', 'BE', 'SE', 'NO', 'DK', 'FI', 'PL', 'AT', 'CH' ],
				'rate'      => '16.00',
				'label'     => 'Tracked international parcel',
			],
			'Australia & New Zealand' => [
				'countries' => [ 'AU', 'NZ' ],
				'rate'      => '19.00',
				'label'     => 'Tracked international parcel',
			],
		];

		$existing = [];

		foreach ( \WC_Shipping_Zones::get_zones() as $zone_data ) {
			$existing[ (string) $zone_data['zone_name'] ] = (int) $zone_data['zone_id'];
		}

		$created = 0;

		foreach ( $definitions as $name => $definition ) {
			if ( isset( $existing[ $name ] ) ) {
				continue;
			}

			$zone = new \WC_Shipping_Zone();

			$zone->set_zone_name( $name );
			$zone->set_locations(
				array_map(
					static fn ( string $code ): array => [
						'code' => $code,
						'type' => 'country',
					],
					$definition['countries']
				)
			);

			$zone_id = (int) $zone->save();

			if ( $zone_id <= 0 ) {
				continue;
			}

			++$created;

			$this->state->track( 'zones', $zone_id );

			$this->add_flat_rate( $zone, (string) $definition['rate'], (string) $definition['label'] );
			$this->add_free_shipping( $zone );
		}

		// "Rest of the world" is zone 0 and always exists.
		$rest = new \WC_Shipping_Zone( 0 );

		if ( [] === $rest->get_shipping_methods() ) {
			$this->add_flat_rate( $rest, '24.00', 'Tracked international parcel' );
			$this->add_free_shipping( $rest );
		} else {
			foreach ( $rest->get_shipping_methods() as $method ) {
				if ( 'flat_rate' === $method->id && ( '' === (string) $method->get_option( 'cost' ) || '0' === (string) $method->get_option( 'cost' ) ) ) {
					$this->write_method_settings(
						$method->id,
						(int) $method->instance_id,
						[
							'title'      => 'Tracked international parcel',
							'cost'       => '24.00',
							'tax_status' => 'none',
						]
					);
				}
			}
		}

		return $created;
	}

	/**
	 * Adds a flat rate method to a zone.
	 *
	 * @param \WC_Shipping_Zone $zone  Zone.
	 * @param string            $cost  Cost.
	 * @param string            $label Method title.
	 */
	private function add_flat_rate( \WC_Shipping_Zone $zone, string $cost, string $label ): void {
		$instance_id = (int) $zone->add_shipping_method( 'flat_rate' );

		if ( $instance_id <= 0 ) {
			return;
		}

		$this->write_method_settings(
			'flat_rate',
			$instance_id,
			[
				'title'      => $label,
				'cost'       => $cost,
				'tax_status' => 'none',
			]
		);
	}

	/**
	 * Adds free shipping above the published threshold.
	 *
	 * @param \WC_Shipping_Zone $zone Zone.
	 */
	private function add_free_shipping( \WC_Shipping_Zone $zone ): void {
		$instance_id = (int) $zone->add_shipping_method( 'free_shipping' );

		if ( $instance_id <= 0 ) {
			return;
		}

		$this->write_method_settings(
			'free_shipping',
			$instance_id,
			[
				'title'      => 'Free shipping',
				'requires'   => 'min_amount',
				'min_amount' => '150',
			]
		);
	}

	/**
	 * Writes shipping method instance settings.
	 *
	 * Instance settings live in their own option row keyed by instance id;
	 * calling `update_option()` on the method object before it is initialised
	 * silently writes nothing, which is why this is done explicitly.
	 *
	 * @param string               $method_id   Method id.
	 * @param int                  $instance_id Instance id.
	 * @param array<string, mixed> $settings    Settings.
	 */
	private function write_method_settings( string $method_id, int $instance_id, array $settings ): void {
		$option_key = sprintf( 'woocommerce_%s_%d_settings', sanitize_key( $method_id ), $instance_id );
		$existing   = get_option( $option_key, [] );

		update_option( $option_key, array_merge( is_array( $existing ) ? $existing : [], $settings ) );
	}

	/**
	 * Creates the fictional customer accounts.
	 */
	public function seed_customers(): int {
		$created = 0;

		foreach ( ReviewLibrary::customers() as $customer ) {
			$email = (string) $customer['email'];
			$user  = get_user_by( 'email', $email );

			if ( false === $user ) {
				$user_id = wp_insert_user(
					[
						'user_login'   => sanitize_user( strtolower( $customer['first'] . '.' . $customer['last'] ), true ),
						'user_email'   => $email,
						'user_pass'    => wp_generate_password( 24, true, true ),
						'first_name'   => (string) $customer['first'],
						'last_name'    => (string) $customer['last'],
						'display_name' => $customer['first'] . ' ' . $customer['last'],
						'role'         => 'customer',
					]
				);

				if ( is_wp_error( $user_id ) ) {
					continue;
				}

				++$created;

				$this->state->track( 'customers', (int) $user_id );
			} else {
				$user_id = (int) $user->ID;
			}

			$fields = [
				'billing_first_name'  => (string) $customer['first'],
				'billing_last_name'   => (string) $customer['last'],
				'billing_email'       => $email,
				'billing_phone'       => (string) $customer['phone'],
				'billing_address_1'   => (string) $customer['address'],
				'billing_city'        => (string) $customer['city'],
				'billing_state'       => (string) $customer['state'],
				'billing_postcode'    => (string) $customer['postcode'],
				'billing_country'     => (string) $customer['country'],
				'shipping_first_name' => (string) $customer['first'],
				'shipping_last_name'  => (string) $customer['last'],
				'shipping_address_1'  => (string) $customer['address'],
				'shipping_city'       => (string) $customer['city'],
				'shipping_state'      => (string) $customer['state'],
				'shipping_postcode'   => (string) $customer['postcode'],
				'shipping_country'    => (string) $customer['country'],
			];

			foreach ( $fields as $key => $value ) {
				update_user_meta( (int) $user_id, $key, $value );
			}

			update_user_meta( (int) $user_id, '_bhc_demo', 'yes' );

			if ( ! empty( $customer['wholesale'] ) ) {
				update_user_meta( (int) $user_id, \BoneHornCrafts\Core\Customer\WholesaleService::APPROVED_META, 'yes' );

				$wp_user = get_user_by( 'id', (int) $user_id );

				if ( $wp_user instanceof \WP_User ) {
					$wp_user->add_role( \BoneHornCrafts\Core\Customer\Roles::WHOLESALE_ROLE );
				}
			}
		}

		return $created;
	}

	/**
	 * Creates the fictional product reviews.
	 *
	 * Reviews are attached to demo products only, are flagged with `_bhc_demo`,
	 * and are the sole source of the aggregate ratings the product schema
	 * publishes — which is why the schema builder refuses to emit a rating node
	 * when a product has none.
	 */
	public function seed_reviews(): int {
		$reviewers = ReviewLibrary::reviewers();
		$bodies    = ReviewLibrary::bodies();
		$created   = 0;

		foreach ( ProductCatalog::products() as $index => $definition ) {
			$product_id = $this->find_product_id( (string) $definition['sku'] );

			if ( $product_id <= 0 ) {
				continue;
			}

			$family = (string) $definition['family'];
			$pool   = $bodies[ $family ] ?? $bodies['bone'];
			$count  = 1 + ( crc32( (string) $definition['sku'] ) % 4 );

			for ( $i = 0; $i < $count; $i++ ) {
				$reviewer = $reviewers[ ( $index + $i * 3 ) % count( $reviewers ) ];
				$review   = $pool[ ( $index + $i ) % count( $pool ) ];

				$existing = get_comments(
					[
						'post_id'      => $product_id,
						'author_email' => (string) $reviewer['email'],
						'count'        => true,
						'status'       => 'all',
					]
				);

				if ( (int) $existing > 0 ) {
					continue;
				}

				$comment_id = wp_insert_comment(
					[
						'comment_post_ID'      => $product_id,
						'comment_author'       => (string) $reviewer['name'],
						'comment_author_email' => (string) $reviewer['email'],
						'comment_content'      => (string) $review['body'],
						'comment_type'         => 'review',
						'comment_approved'     => 1,
						'comment_date'         => gmdate( 'Y-m-d H:i:s', time() - ( ( 5 + ( $index + $i ) * 3 ) * DAY_IN_SECONDS ) ),
					]
				);

				if ( ! $comment_id ) {
					continue;
				}

				++$created;

				$this->state->track( 'comments', (int) $comment_id );

				update_comment_meta( (int) $comment_id, 'rating', (int) $review['rating'] );
				update_comment_meta( (int) $comment_id, 'verified', 1 );
				update_comment_meta( (int) $comment_id, '_bhc_demo', 'yes' );
			}

			$product = wc_get_product( $product_id );

			if ( $product instanceof \WC_Product ) {
				$this->refresh_ratings( $product );
			}
		}

		return $created;
	}

	/**
	 * Recomputes and stores a product's rating aggregates.
	 *
	 * `WC_Comments::get_*_for_product()` only *calculates*; the values are
	 * persisted through the CRUD object, which also refreshes the
	 * `wc_product_meta_lookup.average_rating` column that catalogue sorting
	 * reads. Doing it by hand here keeps the demo dataset consistent without
	 * waiting for a comment hook to fire.
	 *
	 * @param \WC_Product $product Product.
	 */
	private function refresh_ratings( \WC_Product $product ): void {
		if ( ! class_exists( \WC_Comments::class ) ) {
			return;
		}

		$counts = (array) \WC_Comments::get_rating_counts_for_product( $product );
		$total  = 0;
		$sum    = 0;

		foreach ( $counts as $rating => $count ) {
			$total += (int) $count;
			$sum   += (int) $rating * (int) $count;
		}

		$product->set_rating_counts( $counts );
		$product->set_review_count( (int) \WC_Comments::get_review_count_for_product( $product ) );
		$product->set_average_rating( $total > 0 ? (string) round( $sum / $total, 2 ) : '0' );
		$product->save();
	}

	/**
	 * Creates the fictional order history.
	 *
	 * Orders are what make the merchandising demo real: the bestseller ranking
	 * and the "bought together" index are both derived from them rather than
	 * hard-coded.
	 *
	 * @param int $count Number of orders.
	 */
	public function seed_orders( int $count = 24 ): int {
		if ( $count < 1 || ! function_exists( 'wc_create_order' ) ) {
			return 0;
		}

		$existing = $this->state->get( 'orders' );

		if ( count( $existing ) >= $count ) {
			return 0;
		}

		$catalog   = ProductCatalog::products();
		$customers = ReviewLibrary::customers();
		$notes     = ReviewLibrary::packing_notes();
		$statuses  = [ 'completed', 'completed', 'completed', 'processing', 'completed', 'on-hold' ];
		$created   = 0;

		for ( $i = count( $existing ); $i < $count; $i++ ) {
			$customer = $customers[ $i % count( $customers ) ];
			$user     = get_user_by( 'email', (string) $customer['email'] );

			$order = wc_create_order(
				[
					'customer_id' => $user ? (int) $user->ID : 0,
					'created_via' => 'bhc-demo',
				]
			);

			if ( is_wp_error( $order ) ) {
				continue;
			}

			// Two or three lines per order, chosen deterministically so the
			// co-occurrence index has a stable shape between seeds.
			$line_count = 2 + ( $i % 2 );

			for ( $line = 0; $line < $line_count; $line++ ) {
				$definition = $catalog[ ( $i * 5 + $line * 11 ) % count( $catalog ) ];
				$product_id = $this->find_product_id( (string) $definition['sku'] );
				$product    = $product_id > 0 ? wc_get_product( $product_id ) : null;

				if ( ! $product ) {
					continue;
				}

				if ( $product->is_type( 'variable' ) ) {
					$children = $product->get_children();

					if ( [] === $children ) {
						continue;
					}

					$product = wc_get_product( (int) $children[ $i % count( $children ) ] );

					if ( ! $product ) {
						continue;
					}
				}

				$order->add_product( $product, 1 + ( ( $i + $line ) % 3 ) );
			}

			$address = [
				'first_name' => (string) $customer['first'],
				'last_name'  => (string) $customer['last'],
				'address_1'  => (string) $customer['address'],
				'city'       => (string) $customer['city'],
				'state'      => (string) $customer['state'],
				'postcode'   => (string) $customer['postcode'],
				'country'    => (string) $customer['country'],
				'email'      => (string) $customer['email'],
				'phone'      => (string) $customer['phone'],
			];

			$order->set_address( $address, 'billing' );
			$order->set_address( $address, 'shipping' );

			$order->set_currency( 'USD' );
			$order->set_payment_method_title( 'Demo payment (test)' );
			$order->set_date_created( gmdate( 'Y-m-d H:i:s', time() - ( ( 3 + $i * 3 ) * DAY_IN_SECONDS ) ) );

			$order->update_meta_data( '_bhc_demo', 'yes' );
			$order->update_meta_data( \BoneHornCrafts\Core\Order\OrderMeta::PACKING_NOTES, $notes[ $i % count( $notes ) ] );
			$order->update_meta_data(
				\BoneHornCrafts\Core\Order\OrderMeta::EXPORT_TYPE,
				'IN' === (string) $customer['country']
					? \BoneHornCrafts\Core\Order\OrderMeta::DOMESTIC_GST
					: \BoneHornCrafts\Core\Order\OrderMeta::EXPORT_ZERO_RATED
			);
			$order->update_meta_data(
				\BoneHornCrafts\Core\Order\OrderMeta::IS_WHOLESALE,
				! empty( $customer['wholesale'] ) ? 'yes' : 'no'
			);

			$order->calculate_totals( false );
			$order->set_status( $statuses[ $i % count( $statuses ) ] );

			$order_id = (int) $order->save();

			if ( $order_id <= 0 ) {
				continue;
			}

			++$created;

			$this->state->track( 'orders', $order_id );

			$this->sync_order_analytics( $order_id );
		}

		return $created;
	}

	/**
	 * Pushes a demo order into WooCommerce's analytics lookup tables.
	 *
	 * Normally this happens asynchronously through Action Scheduler. During
	 * seeding it is forced so the merchandising index has data to read the
	 * moment the seed finishes.
	 *
	 * @param int $order_id Order id.
	 */
	private function sync_order_analytics( int $order_id ): void {
		$stats_store    = '\\Automattic\\WooCommerce\\Admin\\API\\Reports\\Orders\\Stats\\DataStore';
		$products_store = '\\Automattic\\WooCommerce\\Admin\\API\\Reports\\Products\\DataStore';

		if ( class_exists( $stats_store ) && method_exists( $stats_store, 'sync_order' ) ) {
			call_user_func( [ $stats_store, 'sync_order' ], $order_id );
		}

		if ( class_exists( $products_store ) && method_exists( $products_store, 'sync_order_products' ) ) {
			call_user_func( [ $products_store, 'sync_order_products' ], $order_id );
		}
	}

	/**
	 * Creates the static pages and assigns the front page.
	 */
	public function seed_pages(): int {
		$created = 0;

		foreach ( ContentLibrary::pages() as $slug => $page ) {
			$existing = get_page_by_path( $slug, OBJECT, 'page' );

			$postarr = [
				'post_title'   => wp_specialchars_decode( (string) $page['title'] ),
				'post_name'    => $slug,
				'post_content' => (string) $page['content'],
				'post_excerpt' => (string) $page['excerpt'],
				'post_status'  => 'publish',
				'post_type'    => 'page',
			];

			if ( $existing instanceof \WP_Post ) {
				$postarr['ID'] = $existing->ID;

				$page_id = (int) wp_update_post( $postarr );
			} else {
				$page_id = (int) wp_insert_post( $postarr );

				++$created;
			}

			if ( $page_id <= 0 ) {
				continue;
			}

			$this->state->track( 'pages', $page_id );

			update_post_meta( $page_id, '_bhc_demo', 'yes' );
			update_post_meta( $page_id, '_bhc_page_template', (string) $page['template'] );
			update_post_meta( $page_id, '_bhc_menu_group', (string) $page['menu'] );

			if ( 'home' === $slug ) {
				update_option( 'show_on_front', 'page' );
				update_option( 'page_on_front', $page_id );
			}

			if ( 'blog' === $slug ) {
				update_option( 'page_for_posts', $page_id );
			}

			if ( 'wishlist' === $slug ) {
				update_option( 'bhc_wishlist_page_id', $page_id );
			}
		}

		$this->write_store_pages();

		return $created;
	}

	/**
	 * Configures the WooCommerce store pages.
	 *
	 * Three decisions are made here.
	 *
	 * 1. The shop page gets real copy. WooCommerce's stock text ("This is where
	 *    you can browse products in this store") is the clearest tell that a
	 *    store was never finished.
	 * 2. Cart and checkout use the **classic shortcodes** rather than the cart
	 *    and checkout blocks. The blocks ship roughly 200KB of JavaScript and
	 *    render client side, which conflicts with this build's performance
	 *    budget and with server-rendered checkout validation. The trade-off is
	 *    explicit: a store that wants the block checkout swaps the page content
	 *    back and loses the server-rendered address validation UX.
	 * 3. These pages stay WooCommerce's. The seeder rewrites their copy but
	 *    never claims their lifecycle, so `reset()` cannot delete them. See
	 *    `store_page_id()` for what went wrong when it did.
	 */
	private function write_store_pages(): void {
		$shop_id = $this->store_page_id( 'shop', 'Shop All Materials' );

		$this->release_store_page( $shop_id );

		if ( $shop_id > 0 ) {
			wp_update_post(
				[
					'ID'           => $shop_id,
					'post_title'   => 'Shop All Materials',
					'post_excerpt' => 'Every material we cut, in one place: handle scales, guitar blanks, pen blanks, drinking horns and the small stock that finishes a build.',
					'post_content' => '',
				]
			);
		}

		$shortcodes = [
			'cart'      => [ 'Cart', '[woocommerce_cart]' ],
			'checkout'  => [ 'Checkout', '[woocommerce_checkout]' ],
			'myaccount' => [ 'My account', '[woocommerce_my_account]' ],
		];

		foreach ( $shortcodes as $page => list( $title, $shortcode ) ) {
			$page_id = $this->store_page_id( $page, $title, $shortcode );

			if ( $page_id <= 0 ) {
				continue;
			}

			$this->release_store_page( $page_id );

			$content = (string) get_post_field( 'post_content', $page_id );

			if ( str_contains( $content, $shortcode ) ) {
				continue;
			}

			wp_update_post(
				[
					'ID'           => $page_id,
					'post_content' => $shortcode,
				]
			);
		}

		$this->adopt_policy_pages();
	}

	/**
	 * Resolves a WooCommerce store page, recreating it if the option dangles.
	 *
	 * `wc_get_page_id()` reads an option and does not check that the post still
	 * exists. A store whose Shop page was deleted therefore keeps returning a
	 * live-looking id, `wp_update_post()` silently no-ops against it, and the
	 * archive renders with an empty `<h1>` — no page title, no breadcrumb root
	 * and nothing for a screen reader or a search engine to read.
	 *
	 * These pages belong to WooCommerce, not to the demo dataset. The seeder
	 * rewrites their copy but deliberately does not mark them `_bhc_demo` or
	 * track them, so `reset()` leaves them alone: destroying a core store page
	 * is not something un-seeding demo content should ever do.
	 *
	 * @param string $key     WooCommerce page key (shop, cart, checkout, myaccount).
	 * @param string $title   Title used only when the page has to be recreated.
	 * @param string $content Content used only when the page has to be recreated.
	 *
	 * @return int Page id, or 0 when WooCommerce is unavailable.
	 */
	private function store_page_id( string $key, string $title, string $content = '' ): int {
		if ( ! function_exists( 'wc_get_page_id' ) ) {
			return 0;
		}

		$page_id = (int) wc_get_page_id( $key );

		if ( $page_id > 0 && get_post( $page_id ) instanceof \WP_Post ) {
			return $page_id;
		}

		// wc_create_page() lives in the admin bundle, which is not loaded on a
		// WP-CLI or front-end request.
		if ( ! function_exists( 'wc_create_page' ) ) {
			$admin_functions = WC_ABSPATH . 'includes/admin/wc-admin-functions.php';

			if ( ! is_readable( $admin_functions ) ) {
				return 0;
			}

			require_once $admin_functions;
		}

		// Re-points the option as a side effect, which is the repair we want.
		return (int) wc_create_page( $key, 'woocommerce_' . $key . '_page_id', $title, $content );
	}

	/**
	 * Drops the demo marker an earlier build wrote onto a core store page.
	 *
	 * Without this the fix only protects fresh installs: a store seeded by the
	 * previous build still has `_bhc_demo` on its Shop page and still has that
	 * id in the tracked-pages bucket, so the next `reset()` would delete it
	 * exactly as before. Clearing the marker is enough — `reset()` checks it
	 * before deleting, so a stale entry in the bucket becomes inert.
	 *
	 * @param int $page_id Store page id.
	 */
	private function release_store_page( int $page_id ): void {
		if ( $page_id <= 0 ) {
			return;
		}

		delete_post_meta( $page_id, '_bhc_demo' );
	}

	/**
	 * Points WooCommerce's policy-page options at the store's own pages.
	 *
	 * WooCommerce ships a draft "Refund and Returns Policy" page which
	 * `purge_default_content()` removes, leaving the option pointing at a post
	 * that no longer exists; the terms option ships empty. Both surface in
	 * checkout copy and in the emailed order confirmation, so a store that
	 * reads as a real business has to wire them to the policies it actually
	 * publishes.
	 */
	private function adopt_policy_pages(): void {
		$policies = [
			'woocommerce_terms_page_id'          => 'terms-conditions',
			'woocommerce_refund_returns_page_id' => 'returns-refunds',
		];

		foreach ( $policies as $option => $slug ) {
			$page = get_page_by_path( $slug, OBJECT, 'page' );

			if ( ! $page instanceof \WP_Post ) {
				continue;
			}

			update_option( $option, $page->ID );
		}
	}

	/**
	 * Creates the journal articles.
	 *
	 * @param bool $with_images Whether to render header imagery.
	 */
	public function seed_articles( bool $with_images = true ): int {
		$created = 0;

		$category_id = 0;
		$category    = get_term_by( 'slug', 'workshop-notes', 'category' );

		if ( $category instanceof WP_Term ) {
			$category_id = (int) $category->term_id;
		} else {
			$term = wp_insert_term( 'Workshop Notes', 'category', [ 'slug' => 'workshop-notes' ] );

			if ( ! is_wp_error( $term ) ) {
				$category_id = (int) $term['term_id'];

				$this->state->track( 'terms', $category_id );
			}
		}

		foreach ( ContentLibrary::articles() as $index => $article ) {
			$existing = get_page_by_path( (string) $article['slug'], OBJECT, 'post' );

			$postarr = [
				'post_title'   => (string) $article['title'],
				'post_name'    => (string) $article['slug'],
				'post_content' => (string) $article['content'],
				'post_excerpt' => (string) $article['excerpt'],
				'post_status'  => 'publish',
				'post_type'    => 'post',
				'post_date'    => gmdate( 'Y-m-d H:i:s', time() - ( ( 9 + $index * 12 ) * DAY_IN_SECONDS ) ),
			];

			if ( $existing instanceof \WP_Post ) {
				$postarr['ID'] = $existing->ID;

				$post_id = (int) wp_update_post( $postarr );
			} else {
				$post_id = (int) wp_insert_post( $postarr );

				++$created;
			}

			if ( $post_id <= 0 ) {
				continue;
			}

			$this->state->track( 'posts', $post_id );

			update_post_meta( $post_id, '_bhc_demo', 'yes' );

			if ( $category_id > 0 ) {
				wp_set_post_categories( $post_id, [ $category_id ] );
			}

			if ( $with_images && ! has_post_thumbnail( $post_id ) ) {
				$attachment_id = $this->images->create(
					'journal-' . (string) $article['slug'],
					(string) $article['material'],
					(string) $article['shape'],
					(string) $article['title'],
					sprintf( 'Illustration for the journal article “%s”', (string) $article['title'] ),
					0
				);

				if ( $attachment_id > 0 ) {
					$this->state->track( 'attachments', $attachment_id );

					set_post_thumbnail( $post_id, $attachment_id );
				}
			}
		}

		return $created;
	}

	/**
	 * Builds the navigation menus and assigns them to theme locations.
	 */
	public function seed_menus(): int {
		$primary_categories = [ 'knife-handle-scales', 'guitar-parts', 'pen-blanks', 'drinking-horns-mugs', 'combs-beads-cutlery', 'workshop-essentials' ];

		$shop_page_id = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'shop' ) : 0;

		// Primary navigation: a single "Shop" parent with the category tree
		// beneath it. A flat bar of ten items wraps onto three rows on a laptop
		// and reads as a sitemap rather than a navigation.
		$primary = [];

		if ( $shop_page_id > 0 ) {
			$primary[] = [
				'key'    => 'shop',
				'type'   => 'post_type',
				'object' => 'page',
				'id'     => $shop_page_id,
				'title'  => __( 'Shop', 'bhc-commerce-core' ),
				'parent' => '',
			];
		}

		foreach ( $primary_categories as $slug ) {
			$term = get_term_by( 'slug', $slug, 'product_cat' );

			if ( $term instanceof WP_Term ) {
				$primary[] = [
					'key'    => 'cat-' . $slug,
					'type'   => 'taxonomy',
					'object' => 'product_cat',
					'id'     => (int) $term->term_id,
					'title'  => $term->name,
					'parent' => 'shop',
				];
			}
		}

		foreach ( [ 'new-arrivals', 'bestsellers', 'blog', 'about-us', 'contact' ] as $slug ) {
			$page = get_page_by_path( $slug, OBJECT, 'page' );

			if ( $page instanceof \WP_Post ) {
				$primary[] = [
					'key'    => $slug,
					'type'   => 'post_type',
					'object' => 'page',
					'id'     => (int) $page->ID,
					'title'  => wp_specialchars_decode( get_the_title( $page ) ),
					'parent' => '',
				];
			}
		}

		$footer = [];

		foreach ( ContentLibrary::pages() as $slug => $page_definition ) {
			if ( 'footer' !== $page_definition['menu'] ) {
				continue;
			}

			$page = get_page_by_path( $slug, OBJECT, 'page' );

			if ( $page instanceof \WP_Post ) {
				$footer[] = [
					'key'    => $slug,
					'type'   => 'post_type',
					'object' => 'page',
					'id'     => (int) $page->ID,
					'title'  => wp_specialchars_decode( (string) $page_definition['title'] ),
					'parent' => '',
				];
			}
		}

		$menus = [
			'primary' => [
				'name'  => 'Primary Navigation',
				'items' => $primary,
			],
			'footer'  => [
				'name'  => 'Footer Navigation',
				'items' => $footer,
			],
		];

		$locations = [];
		$created   = 0;

		foreach ( $menus as $location => $menu ) {
			$existing = wp_get_nav_menu_object( $menu['name'] );

			if ( $existing ) {
				$menu_id = (int) $existing->term_id;

				// Rebuilt from scratch so re-seeding cannot duplicate items.
				foreach ( (array) wp_get_nav_menu_items( $menu_id ) as $item ) {
					wp_delete_post( (int) $item->ID, true );
				}
			} else {
				$menu_id = (int) wp_create_nav_menu( $menu['name'] );

				if ( is_wp_error( $menu_id ) || $menu_id <= 0 ) {
					continue;
				}

				++$created;
			}

			$this->state->track( 'menus', $menu_id );

			$item_ids = [];

			foreach ( $menu['items'] as $position => $item ) {
				$item_id = wp_update_nav_menu_item(
					$menu_id,
					0,
					[
						'menu-item-title'     => (string) $item['title'],
						'menu-item-object'    => (string) $item['object'],
						'menu-item-object-id' => (int) $item['id'],
						'menu-item-type'      => (string) $item['type'],
						'menu-item-status'    => 'publish',
						'menu-item-position'  => $position + 1,
						'menu-item-parent-id' => '' !== (string) $item['parent'] ? (int) ( $item_ids[ $item['parent'] ] ?? 0 ) : 0,
					]
				);

				if ( ! is_wp_error( $item_id ) ) {
					$item_ids[ (string) $item['key'] ] = (int) $item_id;
				}
			}

			$locations[ $location ] = $menu_id;
		}

		if ( [] !== $locations ) {
			set_theme_mod( 'nav_menu_locations', array_merge( (array) get_theme_mod( 'nav_menu_locations', [] ), $locations ) );
		}

		return $created;
	}

	/**
	 * Removes the objects the seeder created.
	 *
	 * Only ids recorded in {@see DemoState} are touched. Anything a real
	 * merchandiser added by hand survives a reset, which is the entire reason
	 * the state option exists.
	 *
	 * `$buckets` narrows what is removed. A full reset takes the pages, journal
	 * posts, menus and shipping zones with it, which is right when the demo is
	 * being thrown away and wrong when a real catalogue is replacing the
	 * fictional one: there the site structure is worth keeping and only the
	 * products, their imagery, the invented orders, customers and reviews
	 * should go. Passing `[ 'products', 'attachments', 'orders', 'customers',
	 * 'comments' ]` does exactly that.
	 *
	 * @param bool     $include_orphans Also sweep objects that carry the demo
	 *                                  marker but are no longer tracked, which
	 *                                  can happen if a seeding run was
	 *                                  interrupted.
	 * @param string[] $buckets         Buckets to remove. Empty means all of
	 *                                  them.
	 *
	 * @return array<string, int>
	 */
	public function reset( bool $include_orphans = false, array $buckets = [] ): array {
		$removed = [];
		$wanted  = static fn ( string $bucket ): bool => [] === $buckets || in_array( $bucket, $buckets, true );

		foreach ( array_filter( [ 'orders', 'products', 'attachments', 'pages', 'posts' ], $wanted ) as $bucket ) {
			$count = 0;

			foreach ( $this->state->get( $bucket ) as $id ) {
				if ( 'orders' === $bucket ) {
					$order = wc_get_order( $id );

					if ( $order && 'yes' === $order->get_meta( '_bhc_demo', true ) ) {
						$order->delete( true );

						++$count;
					}

					continue;
				}

				if ( 'attachments' === $bucket ) {
					if ( wp_delete_attachment( $id, true ) ) {
						++$count;
					}

					continue;
				}

				$post = get_post( $id );

				if ( $post instanceof \WP_Post && 'yes' === (string) get_post_meta( $id, '_bhc_demo', true ) ) {
					wp_delete_post( $id, true );

					++$count;
				}
			}

			$removed[ $bucket ] = $count;
		}

		$removed['comments'] = 0;

		foreach ( $wanted( 'comments' ) ? $this->state->get( 'comments' ) : [] as $comment_id ) {
			if ( 'yes' === (string) get_comment_meta( $comment_id, '_bhc_demo', true ) && wp_delete_comment( $comment_id, true ) ) {
				++$removed['comments'];
			}
		}

		$removed['customers'] = 0;

		if ( ! function_exists( 'wp_delete_user' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}

		foreach ( $wanted( 'customers' ) ? $this->state->get( 'customers' ) : [] as $user_id ) {
			if ( 'yes' === (string) get_user_meta( $user_id, '_bhc_demo', true ) && wp_delete_user( $user_id ) ) {
				++$removed['customers'];
			}
		}

		$removed['menus'] = 0;

		foreach ( $wanted( 'menus' ) ? $this->state->get( 'menus' ) : [] as $menu_id ) {
			if ( wp_delete_nav_menu( $menu_id ) ) {
				++$removed['menus'];
			}
		}

		$removed['zones'] = 0;

		if ( class_exists( \WC_Shipping_Zones::class ) ) {
			foreach ( $wanted( 'zones' ) ? $this->state->get( 'zones' ) : [] as $zone_id ) {
				$zone = \WC_Shipping_Zones::get_zone( (int) $zone_id );

				if ( $zone instanceof \WC_Shipping_Zone ) {
					$zone->delete( true );

					++$removed['zones'];
				}
			}
		}

		$removed['terms'] = 0;

		foreach ( $wanted( 'terms' ) ? $this->state->get( 'terms' ) : [] as $term_id ) {
			$term = get_term( $term_id );

			if ( $term instanceof WP_Term && wp_delete_term( $term_id, $term->taxonomy ) ) {
				++$removed['terms'];
			}
		}

		if ( $include_orphans ) {
			$removed['orphans'] = $this->remove_orphans();
		}

		// Derived tables describe the catalogue that was just removed, so they
		// are emptied rather than left pointing at deleted products.
		if ( $wanted( 'products' ) ) {
			( new \BoneHornCrafts\Core\Analytics\ProductStatsRepository() )->truncate();
			( new \BoneHornCrafts\Core\Recommendations\AffinityRepository() )->truncate();
		}

		// Only a full reset has nothing left to track. After a partial one the
		// untracked buckets still exist, and forgetting them would strand the
		// pages and menus this run deliberately spared.
		if ( [] === $buckets ) {
			$this->state->forget();
		} else {
			$this->state->forget_buckets( $buckets );
		}

		delete_option( 'bhc_wishlist_page_id' );

		$this->logger->info( 'demo.reset', $removed );

		return $removed;
	}

	/**
	 * Deletes demo-marked posts that are no longer tracked.
	 *
	 * The `_bhc_demo` meta marker is written by the seeder and by nothing else,
	 * so sweeping on it cannot touch hand-made content. It is still opt-in,
	 * because "delete everything with this marker" deserves an explicit flag.
	 *
	 * @return int Number of objects removed.
	 */
	private function remove_orphans(): int {
		global $wpdb;

		$removed = 0;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded maintenance query.
		$post_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT pm.post_id FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key = '_bhc_demo' AND pm.meta_value = 'yes'
				 LIMIT %d",
				500
			)
		);

		foreach ( array_map( 'absint', (array) $post_ids ) as $post_id ) {
			$post = get_post( $post_id );

			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			$deleted = 'attachment' === $post->post_type
				? wp_delete_attachment( $post_id, true )
				: wp_delete_post( $post_id, true );

			if ( $deleted ) {
				++$removed;
			}
		}

		foreach ( wc_get_orders(
			[
				'limit'  => 200,
				'return' => 'objects',
			]
		) as $order ) {
			if ( $order instanceof \WC_Order && 'yes' === $order->get_meta( '_bhc_demo', true ) ) {
				$order->delete( true );

				++$removed;
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded maintenance query.
		$comment_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT comment_id FROM {$wpdb->commentmeta} WHERE meta_key = '_bhc_demo' AND meta_value = 'yes' LIMIT %d",
				500
			)
		);

		foreach ( array_map( 'absint', (array) $comment_ids ) as $comment_id ) {
			if ( wp_delete_comment( $comment_id, true ) ) {
				++$removed;
			}
		}

		if ( ! function_exists( 'wp_delete_user' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded maintenance query.
		$user_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = '_bhc_demo' AND meta_value = 'yes' LIMIT %d",
				200
			)
		);

		foreach ( array_map( 'absint', (array) $user_ids ) as $user_id ) {
			if ( wp_delete_user( $user_id ) ) {
				++$removed;
			}
		}

		return $removed;
	}
}
