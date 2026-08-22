<?php
/**
 * Product specification table.
 *
 * @package BHC_Theme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Product\Attributes\AttributeCatalog;
use BoneHornCrafts\Core\Product\ProductMeta;

$product = $args['product'] ?? null;

if ( ! $product instanceof WC_Product ) {
	return;
}

$rows = [];

foreach ( [ AttributeCatalog::MATERIAL, AttributeCatalog::FINISH, AttributeCatalog::COLOUR, AttributeCatalog::SIZE, AttributeCatalog::APPLICATION, AttributeCatalog::PRODUCT_TYPE ] as $attribute ) {
	$value = $product->get_attribute( AttributeCatalog::taxonomy( $attribute ) );

	if ( is_string( $value ) && '' !== $value ) {
		$rows[ AttributeCatalog::label( $attribute ) ] = $value;
	}
}

$dimensions = $product->get_dimensions( false );

if ( is_array( $dimensions ) && '' !== (string) ( $dimensions['length'] ?? '' ) ) {
	$rows[ __( 'Nominal dimensions', 'bhc-theme' ) ] = sprintf(
		'%s × %s × %s %s',
		$dimensions['length'],
		$dimensions['width'],
		$dimensions['height'],
		get_option( 'woocommerce_dimension_unit', 'in' )
	);
}

if ( '' !== (string) $product->get_weight() ) {
	$rows[ __( 'Weight', 'bhc-theme' ) ] = $product->get_weight() . ' ' . get_option( 'woocommerce_weight_unit', 'kg' );
}

$unit = ProductMeta::unit_of_sale( $product );

if ( '' !== $unit ) {
	$rows[ __( 'Sold as', 'bhc-theme' ) ] = $unit;
}

if ( '' !== $product->get_sku() ) {
	$rows[ __( 'SKU', 'bhc-theme' ) ] = $product->get_sku();
}

$lot = ProductMeta::batch_reference( $product );

if ( '' !== $lot ) {
	$rows[ __( 'Material lot', 'bhc-theme' ) ] = $lot;
}

$rows[ __( 'Country of manufacture', 'bhc-theme' ) ] = ProductMeta::origin_country( $product );

if ( [] === $rows ) {
	return;
}
?>
<table class="product-specs">
	<caption class="screen-reader-text"><?php esc_html_e( 'Product specification', 'bhc-theme' ); ?></caption>
	<tbody>
		<?php foreach ( $rows as $label => $value ) : ?>
			<tr>
				<th scope="row"><?php echo esc_html( (string) $label ); ?></th>
				<td><?php echo esc_html( wp_specialchars_decode( (string) $value ) ); ?></td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>
