<?php
/**
 * Recommendation rail.
 *
 * @package BoneHornCrafts\Core
 *
 * @var \WC_Product[] $products Products.
 * @var string        $title    Heading.
 * @var string        $subtitle Sub heading.
 * @var string        $modifier CSS modifier.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Plugin;
use BoneHornCrafts\Core\Support\Template;

if ( empty( $products ) ) {
	return;
}

$template = Plugin::resolve( Template::class );
$modifier = isset( $modifier ) ? sanitize_html_class( (string) $modifier ) : 'rail';
?>
<section class="bhc-rail bhc-rail--<?php echo esc_attr( $modifier ); ?>" aria-labelledby="bhc-rail-<?php echo esc_attr( $modifier ); ?>">
	<header class="bhc-rail__header">
		<h2 class="bhc-section__title" id="bhc-rail-<?php echo esc_attr( $modifier ); ?>"><?php echo esc_html( (string) $title ); ?></h2>
		<?php if ( ! empty( $subtitle ) ) : ?>
			<p class="bhc-section__lede"><?php echo esc_html( (string) $subtitle ); ?></p>
		<?php endif; ?>
	</header>

	<div class="bhc-rail__track">
		<?php
		foreach ( $products as $rail_product ) {
			$template->output(
				'product/card.php',
				[
					'product' => $rail_product,
					'eager'   => false,
				]
			);
		}
		?>
	</div>
</section>
