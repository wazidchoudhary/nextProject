<?php
/**
 * Document head and site header.
 *
 * @package BHC_Theme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="skip-link" href="#main"><?php esc_html_e( 'Skip to content', 'bhc-theme' ); ?></a>

<header class="site-header" role="banner">
	<p class="site-header__announcement">
		<?php esc_html_e( 'Worldwide export from the workshop · Free shipping on orders over $150', 'bhc-theme' ); ?>
	</p>

	<div class="site-header__inner">
		<a class="site-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
			<svg class="site-brand__mark" width="34" height="34" viewBox="0 0 34 34" fill="none" aria-hidden="true" focusable="false">
				<rect x="1" y="1" width="32" height="32" rx="2" stroke="currentColor" stroke-width="1.2" />
				<path d="M10 24c2.5-4 3.2-8.5 2-13.5 3.6 1.6 6.6 1.6 10 0-1.2 5-0.5 9.5 2 13.5-4.6-1.8-9.4-1.8-14 0Z" fill="currentColor" opacity=".85" />
			</svg>
			<span>
				<span class="site-brand__name"><?php bloginfo( 'name' ); ?></span>
				<span class="site-brand__tagline"><?php bloginfo( 'description' ); ?></span>
			</span>
		</a>

		<nav class="site-header__nav primary-nav" aria-label="<?php esc_attr_e( 'Primary', 'bhc-theme' ); ?>">
			<?php
			wp_nav_menu(
				[
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'primary-nav__list',
					'depth'          => 2,
					'fallback_cb'    => false,
				]
			);
			?>
		</nav>

		<div class="site-header__actions">
			<div class="header-search">
				<?php get_search_form( [ 'aria_label' => __( 'Search the catalogue', 'bhc-theme' ) ] ); ?>
			</div>

			<?php
			// Mobile search. The brief's mobile bar is Logo | Search | Cart |
			// Menu, and search behind the menu button is not a search
			// affordance. This reveals a full-width search row under the header
			// rather than reusing the drawer, so looking for something is not
			// the same gesture as browsing the menu.
			?>
			<button
				type="button"
				class="bhc-icon-button mobile-search-toggle"
				data-bhc-search-toggle
				aria-expanded="false"
				aria-controls="bhc-mobile-search"
			>
				<?php bhc_icon( 'search' ); ?>
				<span class="screen-reader-text"><?php esc_html_e( 'Search', 'bhc-theme' ); ?></span>
			</button>

			<a class="bhc-icon-button bhc-icon-button--desktop" href="<?php echo esc_url( bhc_wc_page_url( 'myaccount' ) ); ?>">
				<?php bhc_icon( 'account' ); ?>
				<span class="screen-reader-text"><?php esc_html_e( 'Your account', 'bhc-theme' ); ?></span>
			</a>

			<a class="bhc-icon-button bhc-icon-button--desktop" href="<?php echo esc_url( bhc_wishlist_url() ); ?>">
				<?php bhc_icon( 'wishlist' ); ?>
				<span class="screen-reader-text"><?php esc_html_e( 'Wishlist', 'bhc-theme' ); ?></span>
				<span class="bhc-icon-button__count<?php echo bhc_wishlist_count() > 0 ? '' : ' is-empty'; ?>" data-bhc-wishlist-count><?php echo (int) bhc_wishlist_count(); ?></span>
			</a>

			<a class="bhc-icon-button" href="<?php echo esc_url( bhc_wc_page_url( 'cart' ) ); ?>">
				<?php bhc_icon( 'cart' ); ?>
				<span class="screen-reader-text"><?php esc_html_e( 'Cart', 'bhc-theme' ); ?></span>
				<span class="bhc-icon-button__count<?php echo bhc_cart_count() > 0 ? '' : ' is-empty'; ?>" data-bhc-cart-count><?php echo (int) bhc_cart_count(); ?></span>
			</a>

			<button
				type="button"
				class="bhc-icon-button nav-toggle"
				data-bhc-nav-toggle
				aria-expanded="false"
				aria-controls="bhc-mobile-drawer"
			>
				<?php bhc_icon( 'menu' ); ?>
				<span class="screen-reader-text"><?php esc_html_e( 'Menu', 'bhc-theme' ); ?></span>
			</button>
		</div>
	</div>

	<div class="mobile-search" id="bhc-mobile-search" data-bhc-search-panel hidden>
		<div class="container">
			<?php get_search_form( [ 'aria_label' => __( 'Search the catalogue', 'bhc-theme' ) ] ); ?>
		</div>
	</div>
</header>

<div class="mobile-drawer" id="bhc-mobile-drawer" data-bhc-drawer data-open="false" aria-hidden="true">
	<div class="mobile-drawer__panel" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Site menu', 'bhc-theme' ); ?>">
		<div class="mobile-drawer__header">
			<span class="site-brand__name"><?php bloginfo( 'name' ); ?></span>
			<button type="button" class="bhc-icon-button" data-bhc-drawer-close>
				<?php bhc_icon( 'close' ); ?>
				<span class="screen-reader-text"><?php esc_html_e( 'Close menu', 'bhc-theme' ); ?></span>
			</button>
		</div>

		<?php get_search_form( [ 'aria_label' => __( 'Search the catalogue (menu)', 'bhc-theme' ) ] ); ?>

		<nav aria-label="<?php esc_attr_e( 'Mobile', 'bhc-theme' ); ?>">
			<?php
			wp_nav_menu(
				[
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'mobile-nav__list',
					'depth'          => 2,
					'fallback_cb'    => false,
				]
			);
			?>
		</nav>

		<ul class="mobile-drawer__account">
			<li>
				<a href="<?php echo esc_url( bhc_wc_page_url( 'myaccount' ) ); ?>">
					<?php bhc_icon( 'account', 20 ); ?>
					<?php esc_html_e( 'Your account', 'bhc-theme' ); ?>
				</a>
			</li>
			<li>
				<a href="<?php echo esc_url( bhc_wishlist_url() ); ?>">
					<?php bhc_icon( 'wishlist', 20 ); ?>
					<?php esc_html_e( 'Wishlist', 'bhc-theme' ); ?>
					<span class="mobile-drawer__count" data-bhc-wishlist-count><?php echo (int) bhc_wishlist_count(); ?></span>
				</a>
			</li>
		</ul>
	</div>

	<button type="button" class="mobile-drawer__backdrop" data-bhc-drawer-close tabindex="-1">
		<span class="screen-reader-text"><?php esc_html_e( 'Close menu', 'bhc-theme' ); ?></span>
	</button>
</div>
