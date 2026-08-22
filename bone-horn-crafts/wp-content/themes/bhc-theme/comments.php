<?php
/**
 * Comments template for journal articles.
 *
 * @package BHC_Theme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

if ( post_password_required() ) {
	return;
}
?>
<section id="comments" class="comments-area">
	<?php if ( have_comments() ) : ?>
		<h2 class="section__title">
			<?php
			printf(
				/* translators: %d: comment count. */
				esc_html( _n( '%d comment', '%d comments', (int) get_comments_number(), 'bhc-theme' ) ),
				(int) get_comments_number()
			);
			?>
		</h2>

		<ol class="commentlist">
			<?php
			wp_list_comments(
				[
					'style'       => 'ol',
					'short_ping'  => true,
					'avatar_size' => 48,
				]
			);
			?>
		</ol>

		<?php the_comments_pagination(); ?>
	<?php endif; ?>

	<?php
	comment_form(
		[
			'title_reply'          => __( 'Leave a note', 'bhc-theme' ),
			'label_submit'         => __( 'Post comment', 'bhc-theme' ),
			'class_submit'         => 'bhc-button',
			'comment_notes_before' => '<p class="bhc-field__hint">' . esc_html__( 'Your email address is not published.', 'bhc-theme' ) . '</p>',
		]
	);
	?>
</section>
