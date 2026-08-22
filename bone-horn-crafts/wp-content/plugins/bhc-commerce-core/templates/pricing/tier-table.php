<?php
/**
 * Quantity price table.
 *
 * @package BoneHornCrafts\Core
 *
 * @var \WC_Product                                                     $product Product.
 * @var array<int, array{min_qty:int, price:float, saving_percent:int}>  $rows    Tier rows.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

if ( empty( $rows ) ) {
	return;
}
?>
<section class="bhc-tiers" aria-labelledby="bhc-tiers-title">
	<h2 class="bhc-tiers__title" id="bhc-tiers-title"><?php esc_html_e( 'Quantity pricing', 'bhc-commerce-core' ); ?></h2>

	<table class="bhc-tiers__table">
		<caption class="screen-reader-text"><?php esc_html_e( 'Unit price by quantity ordered', 'bhc-commerce-core' ); ?></caption>
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Quantity', 'bhc-commerce-core' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Unit price', 'bhc-commerce-core' ); ?></th>
				<th scope="col"><?php esc_html_e( 'You save', 'bhc-commerce-core' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $rows as $row ) : ?>
				<tr>
					<th scope="row">
						<?php
						printf(
							/* translators: %d: minimum quantity. */
							esc_html__( '%d or more', 'bhc-commerce-core' ),
							(int) $row['min_qty']
						);
						?>
					</th>
					<td><?php echo wp_kses_post( wc_price( (float) $row['price'] ) ); ?></td>
					<td><?php echo esc_html( sprintf( '%d%%', (int) $row['saving_percent'] ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<p class="bhc-tiers__note"><?php esc_html_e( 'Quantity pricing is applied automatically in the cart — no code needed.', 'bhc-commerce-core' ); ?></p>
</section>
