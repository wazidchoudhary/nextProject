<?php
/**
 * Product grid.
 *
 * @package BoneHornCrafts\Core
 *
 * @var \WC_Product[] $products Products to render.
 * @var int           $columns  Column count.
 * @var string        $title    Optional heading.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Plugin;
use BoneHornCrafts\Core\Support\Template;

if ( empty( $products ) ) {
	return;
}

$template = Plugin::resolve( Template::class );
$columns  = isset( $columns ) ? (int) $columns : 4;
?>
<section class="bhc-grid-section">
	<?php if ( ! empty( $title ) ) : ?>
		<h2 class="bhc-section__title"><?php echo esc_html( (string) $title ); ?></h2>
	<?php endif; ?>

	<div class="bhc-grid bhc-grid--<?php echo esc_attr( (string) $columns ); ?>">
		<?php
		foreach ( array_values( $products ) as $index => $grid_product ) {
			$template->output(
				'product/card.php',
				[
					'product' => $grid_product,
					'eager'   => 0 === $index,
				]
			);
		}
		?>
	</div>
</section>
