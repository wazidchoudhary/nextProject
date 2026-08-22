<?php
/**
 * Catalogue filter panel.
 *
 * @package BoneHornCrafts\Core
 *
 * @var array<int, array<string, mixed>>                    $facets      Facet model.
 * @var array{min:float, max:float}                         $price_range Catalogue price bounds.
 * @var \BoneHornCrafts\Core\Search\FilterRequest            $request     Active selection.
 * @var string                                              $action_url  Form action.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

if ( empty( $facets ) ) {
	return;
}

$selected = $request->attributes;
$min      = (float) ( $price_range['min'] ?? 0 );
$max      = (float) ( $price_range['max'] ?? 0 );
?>
<form class="bhc-filters" method="get" action="<?php echo esc_url( (string) $action_url ); ?>" data-bhc-filters>
	<h2 class="bhc-filters__title"><?php esc_html_e( 'Filter material', 'bhc-commerce-core' ); ?></h2>

	<?php if ( '' !== $request->search ) : ?>
		<input type="hidden" name="s" value="<?php echo esc_attr( $request->search ); ?>" />
		<input type="hidden" name="post_type" value="product" />
	<?php endif; ?>

	<?php foreach ( $facets as $facet ) : ?>
		<?php $facet_slug = (string) $facet['slug']; ?>
		<fieldset class="bhc-filters__group" data-bhc-filter-group="<?php echo esc_attr( $facet_slug ); ?>">
			<legend class="bhc-filters__legend"><?php echo esc_html( wp_specialchars_decode( (string) $facet['label'] ) ); ?></legend>

			<ul class="bhc-filters__options">
				<?php foreach ( (array) $facet['options'] as $option ) : ?>
					<?php
					$option_slug = (string) $option['slug'];
					$checked     = in_array( $option_slug, (array) ( $selected[ $facet_slug ] ?? [] ), true );
					$input_id    = 'bhc-filter-' . $facet_slug . '-' . $option_slug;
					?>
					<li class="bhc-filters__option">
						<input
							type="checkbox"
							id="<?php echo esc_attr( $input_id ); ?>"
							name="<?php echo esc_attr( $facet_slug ); ?>[]"
							value="<?php echo esc_attr( $option_slug ); ?>"
							<?php checked( $checked ); ?>
						/>
						<label for="<?php echo esc_attr( $input_id ); ?>">
							<span class="bhc-filters__option-label"><?php echo esc_html( (string) $option['label'] ); ?></span>
							<span class="bhc-filters__option-count">(<?php echo (int) $option['count']; ?>)</span>
						</label>
					</li>
				<?php endforeach; ?>
			</ul>
		</fieldset>
	<?php endforeach; ?>

	<?php if ( $max > 0 ) : ?>
		<fieldset class="bhc-filters__group bhc-filters__group--price">
			<legend class="bhc-filters__legend"><?php esc_html_e( 'Price', 'bhc-commerce-core' ); ?></legend>

			<div class="bhc-filters__price">
				<label class="screen-reader-text" for="bhc-filter-min-price"><?php esc_html_e( 'Minimum price', 'bhc-commerce-core' ); ?></label>
				<input
					type="number"
					id="bhc-filter-min-price"
					name="min_price"
					inputmode="decimal"
					min="<?php echo esc_attr( (string) floor( $min ) ); ?>"
					max="<?php echo esc_attr( (string) ceil( $max ) ); ?>"
					step="1"
					placeholder="<?php echo esc_attr( (string) floor( $min ) ); ?>"
					value="<?php echo esc_attr( null === $request->min_price ? '' : (string) $request->min_price ); ?>"
				/>

				<span aria-hidden="true">—</span>

				<label class="screen-reader-text" for="bhc-filter-max-price"><?php esc_html_e( 'Maximum price', 'bhc-commerce-core' ); ?></label>
				<input
					type="number"
					id="bhc-filter-max-price"
					name="max_price"
					inputmode="decimal"
					min="<?php echo esc_attr( (string) floor( $min ) ); ?>"
					max="<?php echo esc_attr( (string) ceil( $max ) ); ?>"
					step="1"
					placeholder="<?php echo esc_attr( (string) ceil( $max ) ); ?>"
					value="<?php echo esc_attr( null === $request->max_price ? '' : (string) $request->max_price ); ?>"
				/>
			</div>
		</fieldset>
	<?php endif; ?>

	<fieldset class="bhc-filters__group">
		<legend class="bhc-filters__legend"><?php esc_html_e( 'Availability', 'bhc-commerce-core' ); ?></legend>

		<ul class="bhc-filters__options">
			<li class="bhc-filters__option">
				<input type="checkbox" id="bhc-filter-in-stock" name="in_stock" value="1" <?php checked( $request->in_stock ); ?> />
				<label for="bhc-filter-in-stock"><?php esc_html_e( 'In stock now', 'bhc-commerce-core' ); ?></label>
			</li>
			<li class="bhc-filters__option">
				<input type="checkbox" id="bhc-filter-on-sale" name="on_sale" value="1" <?php checked( $request->on_sale ); ?> />
				<label for="bhc-filter-on-sale"><?php esc_html_e( 'On sale', 'bhc-commerce-core' ); ?></label>
			</li>
		</ul>
	</fieldset>

	<div class="bhc-filters__actions">
		<button type="submit" class="bhc-button bhc-button--primary"><?php esc_html_e( 'Apply filters', 'bhc-commerce-core' ); ?></button>
		<a class="bhc-filters__reset" href="<?php echo esc_url( (string) $action_url ); ?>"><?php esc_html_e( 'Clear all', 'bhc-commerce-core' ); ?></a>
	</div>

	<p class="bhc-filters__status" data-bhc-filter-status role="status" aria-live="polite"></p>
</form>
