<?php
/**
 * Delivery estimator.
 *
 * @package BoneHornCrafts\Core
 *
 * @var \WC_Product          $product   Product.
 * @var array<string,string> $countries Shipping countries.
 * @var string               $selected  Selected country code.
 * @var array<string,mixed>  $estimate  Current estimate.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

if ( ! isset( $product ) || ! $product instanceof WC_Product ) {
	return;
}

$field_id = 'bhc-estimator-' . (int) $product->get_id();
?>
<section class="bhc-estimator" data-bhc-estimator data-product-id="<?php echo esc_attr( (string) $product->get_id() ); ?>">
	<h2 class="bhc-estimator__title"><?php esc_html_e( 'Delivery estimate', 'bhc-commerce-core' ); ?></h2>

	<div class="bhc-estimator__controls">
		<label class="bhc-estimator__label" for="<?php echo esc_attr( $field_id ); ?>">
			<?php esc_html_e( 'Ship to', 'bhc-commerce-core' ); ?>
		</label>

		<select class="bhc-estimator__select" id="<?php echo esc_attr( $field_id ); ?>" data-bhc-estimator-country>
			<?php foreach ( $countries as $code => $label ) : ?>
				<option value="<?php echo esc_attr( (string) $code ); ?>" <?php selected( (string) $selected, (string) $code ); ?>>
					<?php echo esc_html( wp_specialchars_decode( (string) $label ) ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</div>

	<p class="bhc-estimator__result" data-bhc-estimator-result aria-live="polite">
		<?php echo esc_html( (string) ( $estimate['label'] ?? '' ) ); ?>
	</p>

	<p class="bhc-estimator__note">
		<?php esc_html_e( 'Estimates exclude customs clearance. Duties and import taxes are payable on arrival.', 'bhc-commerce-core' ); ?>
	</p>
</section>
