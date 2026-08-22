<?php
/**
 * Badge registry.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Product\Badges;

defined( 'ABSPATH' ) || exit;

/**
 * Knows every badge the store can display.
 *
 * Manual badges are selectable in the product editor; automatic badges are
 * derived from store data and are never editable, which stops the catalogue
 * drifting away from reality (a "Bestseller" tag left on a product that has not
 * sold in a year is worse than no tag at all).
 */
final class BadgeRegistry {

	public const BESTSELLER    = 'bestseller';
	public const NEW_ARRIVAL   = 'new-arrival';
	public const WHOLESALE     = 'wholesale';
	public const LIMITED_BATCH = 'limited-batch';
	public const LOW_STOCK     = 'low-stock';
	public const SALE          = 'sale';
	public const WORKSHOP_PICK = 'workshop-pick';

	/**
	 * Memoised badge map.
	 *
	 * @var array<string, Badge>|null
	 */
	private ?array $badges = null;

	/**
	 * Returns every registered badge keyed by slug.
	 *
	 * @return array<string, Badge>
	 */
	public function all(): array {
		if ( null !== $this->badges ) {
			return $this->badges;
		}

		$badges = [
			self::SALE          => new Badge(
				self::SALE,
				__( 'Sale', 'bhc-commerce-core' ),
				'sale',
				true,
				10,
				__( 'Shown automatically while a sale price is active.', 'bhc-commerce-core' )
			),
			self::NEW_ARRIVAL   => new Badge(
				self::NEW_ARRIVAL,
				__( 'New Arrival', 'bhc-commerce-core' ),
				'accent',
				true,
				20,
				__( 'Shown automatically for recently published products.', 'bhc-commerce-core' )
			),
			self::BESTSELLER    => new Badge(
				self::BESTSELLER,
				__( 'Bestseller', 'bhc-commerce-core' ),
				'warm',
				true,
				30,
				__( 'Shown automatically for products in the current bestseller ranking.', 'bhc-commerce-core' )
			),
			self::LIMITED_BATCH => new Badge(
				self::LIMITED_BATCH,
				__( 'Limited Batch', 'bhc-commerce-core' ),
				'warm',
				false,
				40,
				__( 'Use for one-off lots where the material cannot be matched again.', 'bhc-commerce-core' )
			),
			self::WHOLESALE     => new Badge(
				self::WHOLESALE,
				__( 'Bulk / Wholesale', 'bhc-commerce-core' ),
				'neutral',
				true,
				50,
				__( 'Shown automatically when quantity price breaks are configured.', 'bhc-commerce-core' )
			),
			self::WORKSHOP_PICK => new Badge(
				self::WORKSHOP_PICK,
				__( 'Workshop Pick', 'bhc-commerce-core' ),
				'accent',
				false,
				60,
				__( 'Hand-picked by the workshop team for the homepage rail.', 'bhc-commerce-core' )
			),
			self::LOW_STOCK     => new Badge(
				self::LOW_STOCK,
				__( 'Low Stock', 'bhc-commerce-core' ),
				'stock',
				true,
				70,
				__( 'Shown automatically when remaining stock hits the WooCommerce low-stock threshold.', 'bhc-commerce-core' )
			),
		];

		/**
		 * Filters the registered badges.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, Badge> $badges Badges keyed by slug.
		 */
		$badges = (array) apply_filters( 'bhc_registered_badges', $badges );

		$this->badges = array_filter( $badges, static fn ( $badge ): bool => $badge instanceof Badge );

		return $this->badges;
	}

	/**
	 * Returns a single badge, or null when unknown.
	 *
	 * @param string $slug Badge slug.
	 */
	public function get( string $slug ): ?Badge {
		return $this->all()[ $slug ] ?? null;
	}

	/**
	 * Returns only the badges a shop manager may assign by hand.
	 *
	 * @return array<string, Badge>
	 */
	public function manual(): array {
		return array_filter( $this->all(), static fn ( Badge $badge ): bool => ! $badge->automatic );
	}
}
