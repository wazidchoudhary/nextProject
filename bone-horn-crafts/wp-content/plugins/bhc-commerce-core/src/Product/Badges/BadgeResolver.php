<?php
/**
 * Resolves which badges apply to a product.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Product\Badges;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Analytics\ProductStatsRepository;
use BoneHornCrafts\Core\Cache\CacheManager;
use BoneHornCrafts\Core\Product\ProductMeta;
use BoneHornCrafts\Core\Support\Options;
use WC_Product;

/**
 * Combines manually assigned badges with rule-derived ones.
 *
 * Performance shape: every rule reads data that is already on the loaded
 * `WC_Product` object, so resolving a badge set costs no queries. The one
 * external input — the bestseller ranking — is fetched once per request and
 * cached in the object cache, not once per product. Results are memoised per
 * product id because archive templates ask for badges twice (card + a11y
 * label).
 */
final class BadgeResolver {

	/**
	 * Per-request memo of resolved badges.
	 *
	 * @var array<int, Badge[]>
	 */
	private array $memo = [];

	/**
	 * Memoised bestseller id lookup.
	 *
	 * @var array<int, bool>|null
	 */
	private ?array $bestsellers = null;

	/**
	 * Constructor.
	 *
	 * @param BadgeRegistry          $registry Badge registry.
	 * @param Options                $options  Plugin settings.
	 * @param ProductStatsRepository $stats    Stats repository.
	 * @param CacheManager           $cache    Cache manager (products group).
	 */
	public function __construct(
		private BadgeRegistry $registry,
		private Options $options,
		private ProductStatsRepository $stats,
		private CacheManager $cache
	) {}

	/**
	 * Returns the badges that apply to a product, sorted and capped.
	 *
	 * @param WC_Product $product Product.
	 * @param int        $limit   Maximum badges returned (0 = no cap).
	 *
	 * @return Badge[]
	 */
	public function for_product( WC_Product $product, int $limit = 2 ): array {
		if ( ! $this->options->bool( 'badges_enabled' ) ) {
			return [];
		}

		$product_id = $product->get_id();

		if ( ! isset( $this->memo[ $product_id ] ) ) {
			$this->memo[ $product_id ] = $this->resolve( $product );
		}

		$badges = $this->memo[ $product_id ];

		return $limit > 0 ? array_slice( $badges, 0, $limit ) : $badges;
	}

	/**
	 * Runs the badge rules for a product.
	 *
	 * @param WC_Product $product Product.
	 *
	 * @return Badge[]
	 */
	private function resolve( WC_Product $product ): array {
		$slugs = ProductMeta::badges( $product );

		// Manual entries may only reference badges a manager is allowed to set.
		$slugs = array_values( array_intersect( $slugs, array_keys( $this->registry->manual() ) ) );

		if ( ProductMeta::is_limited_batch( $product ) ) {
			$slugs[] = BadgeRegistry::LIMITED_BATCH;
		}

		if ( $this->is_new_arrival( $product ) ) {
			$slugs[] = BadgeRegistry::NEW_ARRIVAL;
		}

		if ( $this->is_bestseller( $product ) ) {
			$slugs[] = BadgeRegistry::BESTSELLER;
		}

		if ( ProductMeta::wholesale_enabled( $product ) && [] !== ProductMeta::price_tiers( $product ) ) {
			$slugs[] = BadgeRegistry::WHOLESALE;
		}

		if ( $this->is_low_stock( $product ) ) {
			$slugs[] = BadgeRegistry::LOW_STOCK;
		}

		$badges = [];

		foreach ( array_unique( $slugs ) as $slug ) {
			$badge = $this->registry->get( $slug );

			if ( null !== $badge ) {
				$badges[ $slug ] = $badge;
			}
		}

		if ( $product->is_on_sale() ) {
			$sale = $this->registry->get( BadgeRegistry::SALE );

			if ( null !== $sale ) {
				$percentage = $this->discount_percentage( $product );

				$badges[ BadgeRegistry::SALE ] = $percentage > 0
					/* translators: %d: discount percentage. */
					? $sale->with_label( sprintf( __( '%d%% Off', 'bhc-commerce-core' ), $percentage ) )
					: $sale;
			}
		}

		uasort( $badges, static fn ( Badge $a, Badge $b ): int => $a->priority <=> $b->priority );

		/**
		 * Filters the badges resolved for a product.
		 *
		 * @since 1.0.0
		 *
		 * @param Badge[]    $badges  Resolved badges keyed by slug.
		 * @param WC_Product $product Product.
		 */
		return array_values( (array) apply_filters( 'bhc_product_badges', $badges, $product ) );
	}

	/**
	 * Whether the product was published inside the "new arrival" window.
	 *
	 * @param WC_Product $product Product.
	 */
	private function is_new_arrival( WC_Product $product ): bool {
		$created = $product->get_date_created();

		if ( null === $created ) {
			return false;
		}

		$days = max( 1, $this->options->int( 'new_arrival_days' ) );

		return $created->getTimestamp() >= ( time() - ( $days * DAY_IN_SECONDS ) );
	}

	/**
	 * Whether the product appears in the current bestseller ranking.
	 *
	 * @param WC_Product $product Product.
	 */
	private function is_bestseller( WC_Product $product ): bool {
		if ( null === $this->bestsellers ) {
			$limit = max( 1, $this->options->int( 'bestseller_limit' ) );

			$ids = $this->cache->for_group( 'stats' )->remember(
				'bestseller_ids_' . $limit,
				fn (): array => $this->stats->bestseller_ids( $limit ),
				6 * HOUR_IN_SECONDS
			);

			$this->bestsellers = array_fill_keys( array_map( 'absint', (array) $ids ), true );
		}

		return isset( $this->bestsellers[ $product->get_id() ] );
	}

	/**
	 * Whether stock is managed and has fallen to the low-stock threshold.
	 *
	 * @param WC_Product $product Product.
	 */
	private function is_low_stock( WC_Product $product ): bool {
		if ( ! $product->managing_stock() || ! $product->is_in_stock() ) {
			return false;
		}

		$quantity = $product->get_stock_quantity();

		if ( null === $quantity ) {
			return false;
		}

		$threshold = function_exists( 'wc_get_low_stock_amount' ) ? (int) wc_get_low_stock_amount( $product ) : 2;

		return $quantity > 0 && $quantity <= max( 1, $threshold );
	}

	/**
	 * Percentage saved against the regular price.
	 *
	 * @param WC_Product $product Product.
	 */
	public function discount_percentage( WC_Product $product ): int {
		$regular = (float) $product->get_regular_price();
		$active  = (float) $product->get_price();

		if ( $product->is_type( 'variable' ) ) {
			$regular = (float) $product->get_variation_regular_price( 'min' );
			$active  = (float) $product->get_variation_price( 'min' );
		}

		if ( $regular <= 0 || $active <= 0 || $active >= $regular ) {
			return 0;
		}

		return (int) round( ( ( $regular - $active ) / $regular ) * 100 );
	}
}
