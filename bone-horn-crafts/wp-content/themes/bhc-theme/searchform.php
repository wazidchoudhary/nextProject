<?php
/**
 * Search form.
 *
 * @package BHC_Theme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$bhc_search_id = 'bhc-search-' . wp_unique_id();

// Several search forms can appear on one page — the header, the mobile drawer,
// and the one on the search results and 404 pages. Two unnamed role="search"
// landmarks are indistinguishable to a screen reader moving by landmark, so
// each caller names its own. `aria_label` is core's own argument for this.
$bhc_search_label = isset( $args['aria_label'] ) && '' !== (string) $args['aria_label']
	? (string) $args['aria_label']
	: __( 'Search materials', 'bhc-theme' );
?>
<form class="bhc-search" role="search" aria-label="<?php echo esc_attr( $bhc_search_label ); ?>" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="screen-reader-text" for="<?php echo esc_attr( $bhc_search_id ); ?>">
		<?php esc_html_e( 'Search materials', 'bhc-theme' ); ?>
	</label>

	<input
		type="search"
		id="<?php echo esc_attr( $bhc_search_id ); ?>"
		name="s"
		value="<?php echo esc_attr( get_search_query() ); ?>"
		placeholder="<?php esc_attr_e( 'Search material or SKU', 'bhc-theme' ); ?>"
		autocomplete="off"
	/>

	<input type="hidden" name="post_type" value="product" />

	<button type="submit">
		<?php bhc_icon( 'search', 20 ); ?>
		<span class="screen-reader-text"><?php esc_html_e( 'Search', 'bhc-theme' ); ?></span>
	</button>
</form>
