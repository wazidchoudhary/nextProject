<?php
/**
 * Static page template.
 *
 * The seeder stores a layout hint in `_bhc_page_template`, which selects the
 * container width and whether the page header is shown. That keeps a single
 * template file serving fifteen editorial pages without a template-per-page
 * explosion.
 *
 * @package BHC_Theme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

get_header();

$layout = (string) get_post_meta( (int) get_the_ID(), '_bhc_page_template', true );
$narrow = in_array( $layout, [ 'page-narrow', 'page-contact', 'page-faq' ], true );
?>

<main id="main" class="site-main" role="main">
	<div class="container<?php echo $narrow ? ' container--narrow' : ''; ?>">
		<?php
		while ( have_posts() ) :
			the_post();

			get_template_part(
				'template-parts/content/page-header',
				null,
				[
					'title'       => get_the_title(),
					'description' => (string) get_post_field( 'post_excerpt', (int) get_the_ID() ),
				]
			);
			?>

			<div class="entry-content<?php echo $narrow ? ' entry-content--narrow' : ''; ?>">
				<?php the_content(); ?>
			</div>

			<?php
			if ( 'page-contact' === $layout ) {
				get_template_part( 'template-parts/content/contact-details' );
			}

		endwhile;
		?>
	</div>
</main>

<?php
get_footer();
