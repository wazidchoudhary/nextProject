<?php
/**
 * Pure pricing arithmetic.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Pricing;

defined( 'ABSPATH' ) || exit;

/**
 * Side-effect free discount and tier maths.
 *
 * Nothing in this class touches WordPress, WooCommerce or the database, which
 * is exactly why it is a separate class: the money rules are the part that must
 * be provably correct, so they are unit tested in isolation
 * (`tests/Unit/DiscountCalculatorTest.php`).
 */
final class DiscountCalculator {

	/**
	 * Percentage saved, rounded to the nearest whole percent.
	 *
	 * @param float $regular Regular price.
	 * @param float $active  Current price.
	 */
	public function percentage_off( float $regular, float $active ): int {
		if ( $regular <= 0.0 || $active < 0.0 || $active >= $regular ) {
			return 0;
		}

		return (int) round( ( ( $regular - $active ) / $regular ) * 100 );
	}

	/**
	 * Absolute amount saved per unit.
	 *
	 * @param float $regular Regular price.
	 * @param float $active  Current price.
	 */
	public function savings( float $regular, float $active ): float {
		if ( $regular <= 0.0 || $active >= $regular ) {
			return 0.0;
		}

		return round( $regular - $active, 2 );
	}

	/**
	 * Resolves the unit price for a quantity against a tier table.
	 *
	 * Tiers are "minimum quantity" thresholds: the highest threshold that the
	 * quantity reaches wins. A tier is only ever applied when it is cheaper
	 * than the price the customer would otherwise pay, so a badly configured
	 * tier can never increase the price of a sale item.
	 *
	 * @param array<int, array{min_qty:int, price:float}> $tiers      Tier table.
	 * @param int                                         $quantity   Quantity.
	 * @param float                                       $base_price Price without tiers.
	 */
	public function tier_price( array $tiers, int $quantity, float $base_price ): float {
		if ( [] === $tiers || $quantity < 1 || $base_price <= 0.0 ) {
			return round( max( 0.0, $base_price ), 2 );
		}

		$price = $base_price;

		foreach ( $this->normalise_tiers( $tiers ) as $tier ) {
			if ( $quantity >= $tier['min_qty'] && $tier['price'] < $price ) {
				$price = $tier['price'];
			}
		}

		return round( $price, 2 );
	}

	/**
	 * Returns the next tier a customer has not reached yet, if any.
	 *
	 * @param array<int, array{min_qty:int, price:float}> $tiers    Tier table.
	 * @param int                                         $quantity Current quantity.
	 *
	 * @return array{min_qty:int, price:float}|null
	 */
	public function next_tier( array $tiers, int $quantity ): ?array {
		foreach ( $this->normalise_tiers( $tiers ) as $tier ) {
			if ( $quantity < $tier['min_qty'] ) {
				return $tier;
			}
		}

		return null;
	}

	/**
	 * Total for a line, using the tier price where one applies.
	 *
	 * @param array<int, array{min_qty:int, price:float}> $tiers      Tier table.
	 * @param int                                         $quantity   Quantity.
	 * @param float                                       $base_price Unit price without tiers.
	 */
	public function line_total( array $tiers, int $quantity, float $base_price ): float {
		if ( $quantity < 1 ) {
			return 0.0;
		}

		return round( $this->tier_price( $tiers, $quantity, $base_price ) * $quantity, 2 );
	}

	/**
	 * Sorts and cleans a tier table.
	 *
	 * @param array<int, array{min_qty:int, price:float}> $tiers Tier table.
	 *
	 * @return array<int, array{min_qty:int, price:float}>
	 */
	public function normalise_tiers( array $tiers ): array {
		$clean = [];

		foreach ( $tiers as $tier ) {
			if ( ! isset( $tier['min_qty'], $tier['price'] ) ) {
				continue;
			}

			$min_qty = (int) $tier['min_qty'];
			$price   = round( (float) $tier['price'], 2 );

			if ( $min_qty < 2 || $price <= 0.0 ) {
				continue;
			}

			// A later tier with the same threshold overwrites the earlier one.
			$clean[ $min_qty ] = [
				'min_qty' => $min_qty,
				'price'   => $price,
			];
		}

		ksort( $clean, SORT_NUMERIC );

		return array_values( $clean );
	}

	/**
	 * Builds the display rows for a tier table, including the base price row.
	 *
	 * @param array<int, array{min_qty:int, price:float}> $tiers      Tier table.
	 * @param float                                       $base_price Unit price without tiers.
	 *
	 * @return array<int, array{min_qty:int, price:float, saving_percent:int}>
	 */
	public function tier_rows( array $tiers, float $base_price ): array {
		$rows = [];

		foreach ( $this->normalise_tiers( $tiers ) as $tier ) {
			$rows[] = [
				'min_qty'        => $tier['min_qty'],
				'price'          => $tier['price'],
				'saving_percent' => $this->percentage_off( $base_price, $tier['price'] ),
			];
		}

		return $rows;
	}
}
