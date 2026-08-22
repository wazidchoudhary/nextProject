<?php
/**
 * Value proposition tiles.
 *
 * @package BoneHornCrafts\Core
 *
 * @var array<int, array{title:string, body:string}> $items Tiles.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

if ( empty( $items ) ) {
	return;
}
?>
<ul class="bhc-value-props">
	<?php foreach ( $items as $item ) : ?>
		<li class="bhc-value-prop">
			<h3 class="bhc-value-prop__title"><?php echo esc_html( (string) $item['title'] ); ?></h3>
			<p class="bhc-value-prop__body"><?php echo esc_html( (string) $item['body'] ); ?></p>
		</li>
	<?php endforeach; ?>
</ul>
