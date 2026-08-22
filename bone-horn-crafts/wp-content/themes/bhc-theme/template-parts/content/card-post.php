<?php
/**
 * Journal card.
 *
 * @package BHC_Theme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;
?>
<article <?php post_class( 'bhc-post-card' ); ?>>
	<?php if ( has_post_thumbnail() ) : ?>
		<a class="bhc-post-card__media" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
			<?php
			the_post_thumbnail(
				'bhc-wide',
				[
					'loading'  => 'lazy',
					'decoding' => 'async',
					'sizes'    => '(min-width: 64em) 30vw, 100vw',
				]
			);
			?>
		</a>
	<?php endif; ?>

	<?php bhc_posted_on(); ?>

	<h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>

	<p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 22 ) ); ?></p>
</article>
