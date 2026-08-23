<?php
/**
 * Integration test suite.
 *
 * Runs inside a real WordPress + WooCommerce install:
 *
 *     wp eval-file bin/integration-tests.php
 *
 * Why not the WordPress PHPUnit test library? It requires a MySQL server and a
 * separate scaffolded install. This suite exercises the same code against the
 * *actual* store — the same plugins, the same data, the same WooCommerce
 * version — which is what catches the integration bugs that matter (hook
 * ordering, template overrides, REST permissions, custom-table SQL).
 *
 * The unit suite (`composer test`) covers pure logic; this one covers wiring.
 * Exits non-zero on the first failing assertion group, so CI can gate on it.
 *
 * @package BoneHornCrafts\Core
 */

// Note: no `declare( strict_types = 1 )` here — `wp eval-file` evaluates this
// file inside an existing script, where a strict-types declaration is illegal.

use BoneHornCrafts\Core\Analytics\ProductStatsRepository;
use BoneHornCrafts\Core\Cache\CacheManager;
use BoneHornCrafts\Core\Checkout\DeliveryEstimator;
use BoneHornCrafts\Core\Checkout\PostcodeValidator;
use BoneHornCrafts\Core\Database\Schema;
use BoneHornCrafts\Core\Plugin;
use BoneHornCrafts\Core\Pricing\DiscountCalculator;
use BoneHornCrafts\Core\Product\Badges\BadgeResolver;
use BoneHornCrafts\Core\Product\ProductMeta;
use BoneHornCrafts\Core\Product\ProductRepository;
use BoneHornCrafts\Core\Recommendations\RecommendationService;
use BoneHornCrafts\Core\Search\FilterRequest;
use BoneHornCrafts\Core\Search\SearchService;
use BoneHornCrafts\Core\Wishlist\WishlistRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

/**
 * Tiny assertion harness.
 */
final class BHC_Test_Runner {

	/**
	 * Passed assertions.
	 */
	private int $passed = 0;

	/**
	 * Failure messages.
	 *
	 * @var string[]
	 */
	private array $failures = [];

	/**
	 * Current group name.
	 */
	private string $group = '';

	/**
	 * Starts a new group.
	 *
	 * @param string $name Group name.
	 */
	public function group( string $name ): void {
		$this->group = $name;

		echo PHP_EOL . '# ' . $name . PHP_EOL;
	}

	/**
	 * Asserts a condition.
	 *
	 * @param string $description What is being asserted.
	 * @param bool   $condition   Result.
	 * @param string $detail      Extra context printed on failure.
	 */
	public function assert( string $description, bool $condition, string $detail = '' ): void {
		if ( $condition ) {
			++$this->passed;

			echo '  ok   ' . $description . PHP_EOL;

			return;
		}

		$this->failures[] = $this->group . ' → ' . $description . ( '' !== $detail ? ' (' . $detail . ')' : '' );

		echo '  FAIL ' . $description . ( '' !== $detail ? ' — ' . $detail : '' ) . PHP_EOL;
	}

	/**
	 * Asserts equality.
	 *
	 * @param string $description What is being asserted.
	 * @param mixed  $expected    Expected value.
	 * @param mixed  $actual      Actual value.
	 */
	public function equals( string $description, $expected, $actual ): void {
		$this->assert(
			$description,
			$expected === $actual,
			sprintf( 'expected %s, got %s', var_export( $expected, true ), var_export( $actual, true ) )
		);
	}

	/**
	 * Prints the summary and exits with the right status code.
	 */
	public function finish(): void {
		echo PHP_EOL . str_repeat( '-', 60 ) . PHP_EOL;

		if ( [] === $this->failures ) {
			echo sprintf( 'PASS — %d assertions', $this->passed ) . PHP_EOL;

			exit( 0 );
		}

		echo sprintf( 'FAIL — %d passed, %d failed', $this->passed, count( $this->failures ) ) . PHP_EOL;

		foreach ( $this->failures as $failure ) {
			echo '  - ' . $failure . PHP_EOL;
		}

		exit( 1 );
	}
}

$t         = new BHC_Test_Runner();
$container = Plugin::instance()->container();

// ---------------------------------------------------------------------------
$t->group( 'Environment' );

$t->assert( 'WooCommerce is active', defined( 'WC_VERSION' ), 'WC_VERSION missing' );
$t->assert( 'plugin booted and container is populated', count( $container->ids() ) > 20, count( $container->ids() ) . ' services' );
$t->assert( 'custom tables are installed', Schema::is_installed() );

// ---------------------------------------------------------------------------
$t->group( 'Catalogue read model' );

/** @var ProductRepository $products */
$products = $container->get( ProductRepository::class );

$new_arrivals = $products->new_arrival_ids( 8 );
$bestsellers  = $products->bestseller_ids( 8 );

$t->assert( 'new arrivals return ids', count( $new_arrivals ) > 0, count( $new_arrivals ) . ' ids' );
$t->assert( 'bestsellers return ids', count( $bestsellers ) > 0, count( $bestsellers ) . ' ids' );
$t->assert( 'bestseller ids all hydrate to live products', count( $products->hydrate( $bestsellers ) ) === count( $bestsellers ), 'orphaned index rows would fail here' );
$t->assert( 'queries are bounded', count( $products->query( [ 'limit' => 500 ] ) ) <= 60, 'limit clamp' );

$price_band = $products->price_band_ids( 10.0, 30.0, 5 );

$t->assert( 'price band query uses the lookup table', count( $price_band ) > 0, count( $price_band ) . ' ids' );

$in_band = true;

foreach ( $products->hydrate( $price_band ) as $banded ) {
	$price = (float) $banded->get_price();

	if ( $price < 9.99 || $price > 30.01 ) {
		$in_band = false;
	}
}

$t->assert( 'price band results respect the band', $in_band );

// ---------------------------------------------------------------------------
$t->group( 'Query efficiency' );

/**
 * Renders the same field set a product card touches.
 *
 * @param int[] $ids Product ids.
 */
$render_cards = static function ( array $ids ): void {
	foreach ( $ids as $id ) {
		$card_product = wc_get_product( $id );

		if ( ! $card_product ) {
			continue;
		}

		$card_product->get_name();
		$card_product->get_price_html();

		// Through the facade, which is the path the card template takes.
		// Calling $card_product->get_meta() directly would measure
		// WooCommerce's uncached per-object meta read instead of the
		// primed one, i.e. the bug rather than the fix.
		ProductMeta::unit_of_sale( $card_product );

		get_the_terms( $id, 'product_cat' );
		get_permalink( $id );
		$card_product->get_image( 'woocommerce_thumbnail' );
	}
};

$card_ids = array_slice( $new_arrivals, 0, 12 );

// Cold caches, no priming: the naive path most themes take.
wp_cache_flush();
$queries_before = get_num_queries();
$render_cards( $card_ids );
$unprimed = get_num_queries() - $queries_before;

// Cold caches, primed through the repository.
wp_cache_flush();
$queries_before = get_num_queries();
$products->prime( $card_ids );
$render_cards( $card_ids );
$primed = get_num_queries() - $queries_before;

$t->assert(
	'batch priming removes most per-card queries',
	$primed < ( $unprimed * 0.6 ),
	sprintf( '%d primed vs %d unprimed for %d cards', $primed, $unprimed, count( $card_ids ) )
);

// Warm caches, primed again: the second visitor's cost. An absolute per-card
// budget would be meaningless here because wp_cache_flush() costs far more to
// recover from under a persistent object cache (it wipes alloptions and every
// WooCommerce cache too) than under the non-persistent default. What must hold
// in every environment is that a render behind warm caches adds no queries at
// all — which is the actual claim being made about the N+1 work.
$queries_before = get_num_queries();
$products->prime( $card_ids );
$render_cards( $card_ids );
$warm = get_num_queries() - $queries_before;

$t->assert(
	'a repeat render behind warm caches costs no queries',
	0 === $warm,
	$warm . ' queries for ' . count( $card_ids ) . ' cards'
);

// ---------------------------------------------------------------------------
$t->group( 'Pricing' );

$calculator = $container->get( DiscountCalculator::class );
$tier_sku   = 'BHC-BS-1090';
$tier_id    = (int) wc_get_product_id_by_sku( $tier_sku );
$tier_prod  = $tier_id > 0 ? wc_get_product( $tier_id ) : null;

$t->assert( 'wholesale product exists', null !== $tier_prod, $tier_sku );

if ( null !== $tier_prod ) {
	$tiers = ProductMeta::price_tiers( $tier_prod );
	$base  = (float) $tier_prod->get_price();

	$t->assert( 'price tiers are stored', count( $tiers ) >= 2, count( $tiers ) . ' tiers' );
	$t->assert( 'tier pricing reduces the unit price at volume', $calculator->tier_price( $tiers, 50, $base ) < $base );
	$t->assert( 'tier pricing leaves single units alone', abs( $calculator->tier_price( $tiers, 1, $base ) - $base ) < 0.001 );
}

// ---------------------------------------------------------------------------
$t->group( 'Badges' );

/** @var BadgeResolver $badges */
$badges     = $container->get( BadgeResolver::class );
$badged     = 0;
$sale_badge = false;

foreach ( $products->hydrate( array_slice( $new_arrivals, 0, 8 ) ) as $badge_product ) {
	$resolved = $badges->for_product( $badge_product, 3 );

	$badged += count( $resolved ) > 0 ? 1 : 0;

	foreach ( $resolved as $badge ) {
		if ( 'sale' === $badge->slug ) {
			$sale_badge = true;
		}
	}
}

$t->assert( 'badges resolve for recent products', $badged > 0, $badged . ' of 8 products badged' );
$t->assert( 'discount percentage is computed', $badges->discount_percentage( $products->hydrate( $products->on_sale_ids( 1 ) )[0] ?? wc_get_product( $tier_id ) ) >= 0 );

// ---------------------------------------------------------------------------
$t->group( 'Recommendations' );

/** @var RecommendationService $recommendations */
$recommendations = $container->get( RecommendationService::class );
$seed            = $products->hydrate( array_slice( $bestsellers, 0, 1 ) )[0] ?? null;

if ( null !== $seed ) {
	$suggested = $recommendations->products_for( $seed, 4, 'complete_your_build' );

	$t->assert( 'recommendations return products', count( $suggested ) > 0, count( $suggested ) . ' products' );

	$includes_seed = false;

	foreach ( $suggested as $suggestion ) {
		if ( $suggestion->get_id() === $seed->get_id() ) {
			$includes_seed = true;
		}
	}

	$t->assert( 'recommendations never include the seed product', ! $includes_seed );

	$queries_before = get_num_queries();
	$recommendations->products_for( $seed, 4, 'complete_your_build' );
	$t->assert( 'a repeated recommendation request is served from cache', ( get_num_queries() - $queries_before ) <= 6, ( get_num_queries() - $queries_before ) . ' queries' );
}

// ---------------------------------------------------------------------------
$t->group( 'Search and filters' );

/** @var SearchService $search_service */
$search_service = $container->get( SearchService::class );
$request        = FilterRequest::from_array(
	[
		'material' => 'water-buffalo-horn',
		'in_stock' => '1',
		'per_page' => 6,
	]
);

$results = $search_service->results( $request );

$t->assert( 'filtered search returns results', $results['total'] > 0, $results['total'] . ' matches' );
$t->assert( 'filtered search respects per_page', count( $results['ids'] ) <= 6, count( $results['ids'] ) . ' ids' );

$all_horn = true;

foreach ( $products->hydrate( $results['ids'] ) as $filtered ) {
	if ( ! has_term( 'water-buffalo-horn', 'pa_material', $filtered->get_id() ) ) {
		$all_horn = false;
	}
}

$t->assert( 'every filtered result carries the selected attribute', $all_horn );
$t->assert( 'facets expose counts', count( $search_service->facets() ) >= 4, count( $search_service->facets() ) . ' facets' );

$range = $search_service->price_range();

$t->assert( 'price range is derived from the catalogue', $range['max'] > $range['min'] && $range['min'] >= 0 );

$rejected = FilterRequest::from_array(
	[
		'material' => 'unobtainium',
		'orderby'  => 'drop-table',
	]
);

$t->equals( 'unknown attribute terms are dropped', [], $rejected->attributes );
$t->equals( 'unknown ordering falls back to a safe default', 'date', $rejected->orderby );

// ---------------------------------------------------------------------------
$t->group( 'Wishlist storage' );

/** @var WishlistRepository $wishlist */
$wishlist   = $container->get( WishlistRepository::class );
$user_id    = 999001;
$product_id = $new_arrivals[0];

$wishlist->clear( $user_id );

$t->assert( 'adding to a wishlist succeeds', $wishlist->add( $user_id, $product_id ) );
$t->assert( 'adding the same product twice is a no-op', ! $wishlist->add( $user_id, $product_id ) );
$t->equals( 'the product is on the list', true, $wishlist->has( $user_id, $product_id ) );
$t->equals( 'the list has one item', 1, $wishlist->count_for_user( $user_id ) );

$wishlist->bulk_add( $user_id, array_slice( $new_arrivals, 0, 4 ) );

$t->assert( 'bulk add merges without duplicates', $wishlist->count_for_user( $user_id ) === count( array_unique( array_slice( $new_arrivals, 0, 4 ) ) ) );
$t->assert( 'removing works', $wishlist->remove( $user_id, $product_id ) );

$wishlist->clear( $user_id );

$t->equals( 'clearing empties the list', 0, $wishlist->count_for_user( $user_id ) );

// ---------------------------------------------------------------------------
$t->group( 'Caching' );

/** @var CacheManager $cache */
$cache = $container->get( CacheManager::class );
$group = $cache->for_group( 'integration' );

$group->set( 'probe', [ 'value' => 42 ], 60 );

$t->equals( 'values round-trip through the real cache backend', 42, ( $group->get( 'probe' ) )['value'] ?? null );

$group->flush_group( 'integration' );

$t->assert( 'group flush invalidates the key', null === $group->get( 'probe' ) );

// ---------------------------------------------------------------------------
$t->group( 'REST API' );

$routes = rest_get_server()->get_routes();

foreach ( [ '/bhc/v1/wishlist', '/bhc/v1/wishlist/toggle', '/bhc/v1/catalog', '/bhc/v1/delivery-estimate', '/bhc/v1/health' ] as $route ) {
	$t->assert( 'route registered: ' . $route, isset( $routes[ $route ] ) );
}

$permission_callbacks_present = true;

foreach ( $routes as $route => $handlers ) {
	// `/bhc/v1` itself is the namespace index route that WordPress registers
	// for every namespace; the plugin does not define it.
	if ( ! str_starts_with( $route, '/bhc/v1/' ) ) {
		continue;
	}

	foreach ( $handlers as $handler ) {
		if ( empty( $handler['permission_callback'] ) ) {
			$permission_callbacks_present = false;
		}
	}
}

$t->assert( 'every bhc route declares a permission callback', $permission_callbacks_present );

wp_set_current_user( 0 );

$request  = new WP_REST_Request( 'GET', '/bhc/v1/health' );
$response = rest_get_server()->dispatch( $request );

$t->assert( 'health endpoint rejects anonymous callers', in_array( $response->get_status(), [ 401, 403 ], true ), 'status ' . $response->get_status() );

$request = new WP_REST_Request( 'POST', '/bhc/v1/wishlist/toggle' );
$request->set_param( 'product_id', $product_id );
$response = rest_get_server()->dispatch( $request );

$t->assert( 'wishlist writes reject a missing nonce', 403 === $response->get_status(), 'status ' . $response->get_status() );

$request = new WP_REST_Request( 'GET', '/bhc/v1/delivery-estimate' );
$request->set_param( 'country', 'DE' );
$response = rest_get_server()->dispatch( $request );
$data     = $response->get_data();

$t->assert( 'delivery estimate is public and returns a window', 200 === $response->get_status() && ! empty( $data['estimate']['label'] ) );

$request = new WP_REST_Request( 'GET', '/bhc/v1/delivery-estimate' );
$request->set_param( 'country', 'not-a-country' );
$response = rest_get_server()->dispatch( $request );

$t->assert( 'delivery estimate validates its input', 400 === $response->get_status(), 'status ' . $response->get_status() );

$request = new WP_REST_Request( 'GET', '/bhc/v1/catalog' );
$request->set_param( 'material', 'water-buffalo-horn' );
$request->set_param( 'per_page', 4 );
$response = rest_get_server()->dispatch( $request );
$payload  = $response->get_data();

$t->assert( 'catalog endpoint returns presented products', 200 === $response->get_status() && count( $payload['products'] ) > 0 );
$t->assert( 'presented products carry image dimensions', isset( $payload['products'][0]['image']['width'] ) && $payload['products'][0]['image']['width'] > 0 );

// ---------------------------------------------------------------------------
$t->group( 'Checkout services' );

$postcodes = $container->get( PostcodeValidator::class );

$t->assert( 'valid US ZIP accepted', $postcodes->is_valid( '97205', 'US' ) );
$t->assert( 'UK postcode rejected for the US', ! $postcodes->is_valid( 'SW1A 1AA', 'US' ) );

$estimator = $container->get( DeliveryEstimator::class );
$estimate  = $estimator->estimate( $products->hydrate( [ $product_id ] )[0] ?? null, 'AU' );

$t->assert( 'estimate produces a date window', '' !== $estimate['min_date'] && '' !== $estimate['max_date'] );
$t->assert( 'estimate includes workshop lead time', $estimate['dispatch_days'] >= 1 );

// ---------------------------------------------------------------------------
$t->group( 'Merchandising index' );

/** @var ProductStatsRepository $stats */
$stats = $container->get( ProductStatsRepository::class );

$t->assert( 'stats rows exist', $stats->count() > 0, $stats->count() . ' rows' );
$t->assert( 'bestseller ranking is populated', count( $stats->bestseller_ids( 5 ) ) > 0 );

// ---------------------------------------------------------------------------
$t->group( 'SEO output' );

$product_for_schema = $products->hydrate( array_slice( $bestsellers, 0, 1 ) )[0] ?? null;

if ( null !== $product_for_schema ) {
	$response = wp_remote_get( (string) $product_for_schema->get_permalink(), [ 'timeout' => 20 ] );

	if ( ! is_wp_error( $response ) ) {
		$body = (string) wp_remote_retrieve_body( $response );

		$t->assert( 'product page emits JSON-LD', str_contains( $body, 'application/ld+json' ) );
		$t->assert( 'graph includes a Product node', str_contains( $body, '"@type":"Product"' ) );
		$t->assert( 'graph includes BreadcrumbList', str_contains( $body, 'BreadcrumbList' ) );
		$t->assert( 'canonical URL is emitted once', 1 === substr_count( $body, 'rel="canonical"' ) );
		$t->assert( 'Open Graph product price present', str_contains( $body, 'product:price:amount' ) );

		// WooCommerce prints its own Product/Review/BreadcrumbList JSON-LD in
		// the footer. Two graphs describing the same page under different @id
		// values is worse than one, so SchemaGraph opts WooCommerce out.
		$blocks = substr_count( $body, 'application/ld+json' );

		$t->assert( 'exactly one JSON-LD block on the page', 1 === $blocks, $blocks . ' block(s)' );

		// The graph must reference the canonical host, not whatever hostname
		// the request happened to arrive on.
		// BrandProfile decides the host: the configured canonical host outside
		// production, home_url() on production, where the site already runs on
		// the real host. Asserting the configured value directly would fail on
		// a correctly configured production install.
		$brand_host = $container->get( BoneHornCrafts\Core\SEO\BrandProfile::class )->canonical_host();

		if ( '' !== $brand_host ) {
			$t->assert(
				'schema @id uses the canonical host',
				str_contains( $body, '"@id":"' . $brand_host . '/#organization"' ),
				$brand_host
			);
		}

		$t->assert(
			'wishlist page is excluded from the index',
			str_contains(
				(string) wp_remote_retrieve_body( wp_remote_get( home_url( '/wishlist/' ), [ 'timeout' => 20 ] ) ),
				'noindex'
			)
		);
	} else {
		$t->assert( 'product page fetched for SEO assertions', false, $response->get_error_message() );
	}
}

// ---------------------------------------------------------------------------
$t->group( 'Product search' );

// Regression: `add_sku_search()` injected `ID IN (SELECT ... LIMIT 50)`, and
// both MySQL and MariaDB reject a LIMIT inside an IN subquery outright. The
// query errored, WP_Query swallowed the error, and every product search
// returned nothing on the primary stack. Two smaller bugs rode along: the
// clause was spliced in at the last `)` in the string, which belongs to the
// password clause core appends after the search group, and the separately
// prepared fragment left placeholder hashes in the SQL.
//
// Asserted over HTTP rather than through WP_Query: SearchService only hooks
// when `Context::is_frontend()`, so under WP-CLI the filter never registers and
// an in-process query would pass while the storefront stayed broken.

/**
 * Counts product cards on a storefront URL.
 *
 * @param string $url URL to fetch.
 *
 * @return array{status:int, cards:int, body:string}
 */
$bhc_fetch_cards = static function ( string $url ): array {
	$response = wp_remote_get(
		$url,
		[
			'timeout'     => 25,
			'redirection' => 0,
		]
	);

	if ( is_wp_error( $response ) ) {
		return [
			'status' => 0,
			'cards'  => 0,
			'body'   => $response->get_error_message(),
		];
	}

	$body = (string) wp_remote_retrieve_body( $response );

	return [
		'status' => (int) wp_remote_retrieve_response_code( $response ),
		'cards'  => substr_count( $body, 'bhc-card__title' ),
		'body'   => $body,
	];
};

$search_text = $bhc_fetch_cards( home_url( '/?s=horn&post_type=product' ) );

$t->assert(
	'a text search renders product cards',
	$search_text['cards'] > 0,
	$search_text['cards'] . ' card(s), HTTP ' . $search_text['status']
);

$t->assert(
	'the search page is not an error page',
	200 === $search_text['status'],
	'HTTP ' . $search_text['status']
);

// A SKU prefix matches nothing in any title, excerpt or body, so a hit can only
// come from the lookup-table clause.
$sku_prefix = '';

foreach ( wc_get_products( [ 'limit' => 20 ] ) as $sku_product ) {
	$candidate = (string) $sku_product->get_sku();

	if ( str_starts_with( $candidate, 'BHC-' ) ) {
		$sku_prefix = substr( $candidate, 0, 6 );

		break;
	}
}

if ( '' !== $sku_prefix ) {
	$search_sku = $bhc_fetch_cards( home_url( '/?s=' . rawurlencode( $sku_prefix ) . '&post_type=product' ) );

	$t->assert(
		'a SKU prefix matches through the lookup table',
		$search_sku['cards'] > 0,
		$sku_prefix . ' => ' . $search_sku['cards'] . ' card(s)'
	);
}

$search_none = $bhc_fetch_cards( home_url( '/?s=zzzznotathinginthecatalogue&post_type=product' ) );

$t->assert(
	'a term matching nothing renders no cards',
	0 === $search_none['cards'],
	$search_none['cards'] . ' card(s)'
);

$t->assert(
	'the empty state is shown rather than a blank page',
	str_contains( $search_none['body'], 'bhc-empty' ) || str_contains( $search_none['body'], 'No matches' ),
	'no empty-state markup found'
);

// ---------------------------------------------------------------------------
$t->group( 'Customer accounts' );

// Regression: the store shipped with registration off, so My Account showed a
// login form and no way to create the account it was asking you to sign in to.
// Three switches have to agree — WordPress's own, WooCommerce's My Account one,
// and WooCommerce's checkout one — and setting any single one of them makes
// that page look correct while the others stay broken.
foreach (
	[
		'users_can_register'                         => '1',
		'woocommerce_enable_myaccount_registration'  => 'yes',
		'woocommerce_enable_signup_and_login_from_checkout' => 'yes',
		'woocommerce_registration_generate_password' => 'yes',
	] as $account_option => $expected
) {
	$t->assert(
		$account_option . ' is enabled',
		(string) get_option( $account_option ) === $expected,
		'is ' . var_export( get_option( $account_option ), true )
	);
}

$t->equals( 'new registrations become customers', 'customer', (string) get_option( 'default_role' ) );

$account_page = wp_remote_get( wc_get_page_permalink( 'myaccount' ), [ 'timeout' => 20 ] );

if ( ! is_wp_error( $account_page ) ) {
	$account_html = (string) wp_remote_retrieve_body( $account_page );

	$t->assert(
		'the account page renders a registration form',
		str_contains( $account_html, 'woocommerce-form-register' ),
		'no register form in the markup'
	);

	$t->assert(
		'login and registration are laid out as a pair',
		str_contains( $account_html, 'id="customer_login"' ),
		'the two-column wrapper is absent'
	);
}

// ---------------------------------------------------------------------------
$t->group( 'Store pages' );

// Regression: `write_store_pages()` used to mark WooCommerce's Shop page as
// demo content and track it, so `wp bhc demo reset` deleted a core store page
// outright. Re-seeding could not repair it either — `wc_get_page_id()` still
// returned the dangling id, so `wp_update_post()` silently no-opped and the
// archive rendered an empty <h1>: no title, no breadcrumb root, nothing for a
// screen reader or a crawler to read.
foreach ( [ 'shop', 'cart', 'checkout', 'myaccount' ] as $store_page ) {
	$store_page_id = (int) wc_get_page_id( $store_page );

	$t->assert(
		sprintf( '%s page id resolves to a real page', $store_page ),
		$store_page_id > 0 && get_post( $store_page_id ) instanceof WP_Post,
		'id ' . $store_page_id
	);

	$t->assert(
		sprintf( '%s page is not owned by the demo dataset', $store_page ),
		'yes' !== (string) get_post_meta( $store_page_id, '_bhc_demo', true ),
		'a reset would delete it'
	);
}

$t->assert(
	'shop page has a non-empty title',
	'' !== trim( (string) woocommerce_page_title( false ) ),
	'the archive <h1> renders this'
);

foreach ( [ 'woocommerce_terms_page_id', 'woocommerce_refund_returns_page_id' ] as $policy_option ) {
	$policy_id = (int) get_option( $policy_option );

	$t->assert(
		$policy_option . ' points at a published page',
		$policy_id > 0 && 'publish' === get_post_status( $policy_id ),
		'id ' . $policy_id
	);
}

$t->finish();
