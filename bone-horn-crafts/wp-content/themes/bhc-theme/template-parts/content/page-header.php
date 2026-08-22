<?php
/**
 * Page header block.
 *
 * @package BHC_Theme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$header_title = isset( $args['title'] ) ? (string) $args['title'] : get_the_title();
$description  = isset( $args['description'] ) ? (string) $args['description'] : '';
?>
<header class="page-header">
	<?php bhc_breadcrumbs(); ?>

	<h1 class="page-header__title"><?php echo esc_html( $header_title ); ?></h1>

	<?php if ( '' !== $description ) : ?>
		<p class="page-header__description"><?php echo esc_html( $description ); ?></p>
	<?php endif; ?>
</header>
