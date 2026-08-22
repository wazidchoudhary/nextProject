<?php
/**
 * Why buy here.
 *
 * @package BHC_Theme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$copy  = isset( $args['copy'] ) && is_array( $args['copy'] ) ? $args['copy'] : [];
$items = class_exists( \BoneHornCrafts\Core\Demo\ContentLibrary::class )
	? \BoneHornCrafts\Core\Demo\ContentLibrary::value_props()
	: [];

if ( [] === $items ) {
	return;
}
?>
<section class="section section--surface" aria-labelledby="home-why">
	<div class="container">
		<?php
		bhc_section_header(
			(string) ( $copy['title'] ?? __( 'Why Bone Horn Crafts', 'bhc-theme' ) ),
			(string) ( $copy['body'] ?? __( 'Four things decide whether material is worth buying twice.', 'bhc-theme' ) )
		);
		?>

		<ul class="bhc-value-props">
			<?php foreach ( $items as $item ) : ?>
				<li class="bhc-value-prop">
					<h3 class="bhc-value-prop__title"><?php echo esc_html( (string) $item['title'] ); ?></h3>
					<p class="bhc-value-prop__body"><?php echo esc_html( (string) $item['body'] ); ?></p>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
