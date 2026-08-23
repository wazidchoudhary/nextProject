<?php
/**
 * Template helpers.
 *
 * Small, focused functions the templates call. Anything that would need a
 * database query of its own belongs in the plugin's repositories instead.
 *
 * @package BHC_Theme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Resolves a service from the commerce plugin container.
 *
 * Returns null when the plugin is inactive so templates can degrade instead of
 * fataling — a theme should never white-screen because a plugin is switched off.
 *
 * @param class-string $id Service id.
 *
 * @return mixed|null
 */
function bhc_service( string $id ) {
	if ( ! class_exists( \BoneHornCrafts\Core\Plugin::class ) ) {
		return null;
	}

	try {
		return \BoneHornCrafts\Core\Plugin::resolve( $id );
	} catch ( \Throwable $exception ) {
		return null;
	}
}

/**
 * Renders a product card through the plugin template.
 *
 * @param WC_Product $product Product.
 * @param bool       $eager   Whether this is the LCP candidate.
 */
function bhc_product_card( $product, bool $eager = false ): void {
	$template = bhc_service( \BoneHornCrafts\Core\Support\Template::class );

	if ( null === $template || ! $product instanceof WC_Product ) {
		return;
	}

	$template->output(
		'product/card.php',
		[
			'product' => $product,
			'eager'   => $eager,
		]
	);
}

/**
 * Renders a list of products as a grid.
 *
 * @param WC_Product[] $products Products.
 * @param int          $columns  Column count.
 */
function bhc_product_cards( array $products, int $columns = 4 ): void {
	if ( [] === $products ) {
		return;
	}

	printf( '<div class="bhc-grid bhc-grid--%d" data-bhc-product-grid>', (int) $columns );

	foreach ( array_values( $products ) as $index => $product ) {
		bhc_product_card( $product, 0 === $index );
	}

	echo '</div>';
}

/**
 * Returns the product ids behind a merchandising source.
 *
 * Every branch is a cached, bounded repository call, so resolving a rail's ids
 * without rendering it is cheap. That is what lets a page prime all its rails
 * in one pass — see bhc_prime_product_rails().
 *
 * @param string $source One of new|bestsellers|sale|category|tag.
 * @param int    $limit  Maximum products.
 * @param string $value  Category or tag slug where relevant.
 *
 * @return int[]
 */
function bhc_product_ids_for( string $source, int $limit = 8, string $value = '' ): array {
	$repository = bhc_service( \BoneHornCrafts\Core\Product\ProductRepository::class );

	if ( null === $repository ) {
		return [];
	}

	return match ( $source ) {
		'bestsellers' => $repository->bestseller_ids( $limit ),
		'sale'        => $repository->on_sale_ids( $limit ),
		'category'    => $repository->category_ids( $value, $limit ),
		'tag'         => $repository->tag_ids( $value, $limit ),
		default       => $repository->new_arrival_ids( $limit ),
	};
}

/**
 * Returns hydrated products for a merchandising source.
 *
 * @param string $source One of new|bestsellers|sale|category|tag.
 * @param int    $limit  Maximum products.
 * @param string $value  Category or tag slug where relevant.
 *
 * @return WC_Product[]
 */
function bhc_products_for( string $source, int $limit = 8, string $value = '' ): array {
	$repository = bhc_service( \BoneHornCrafts\Core\Product\ProductRepository::class );

	if ( null === $repository ) {
		return [];
	}

	return $repository->hydrate( bhc_product_ids_for( $source, $limit, $value ) );
}

/**
 * The configured home page banner.
 *
 * A media-library attachment rather than a file bundled with the theme: the
 * front of the store is the thing a shop owner most wants to change, and it
 * should not need a deploy. Set it under Bone Horn Crafts → Settings, or with
 * `wp bhc setup hero <file>`.
 *
 * @return int Attachment id, or 0 when none is set.
 */
function bhc_hero_banner_id(): int {
	$options = bhc_service( \BoneHornCrafts\Core\Support\Options::class );

	$id = null !== $options ? (int) $options->get( 'hero_image_id', 0 ) : 0;

	/**
	 * Filters the home page banner attachment.
	 *
	 * @param int $id Attachment id, or 0 for none.
	 */
	$id = (int) apply_filters( 'bhc_hero_banner_id', $id );

	// A deleted attachment must not leave the hero pointing at a 404.
	return $id > 0 && wp_attachment_is_image( $id ) ? $id : 0;
}

/**
 * Returns the newest product that actually has a photograph.
 *
 * The hero used to take `bhc_products_for( 'new', 1 )` and render its image.
 * On an imported catalogue the newest product is frequently the one whose
 * photography has not been attached yet, and the hero's whole media column is
 * behind an `if` — so the section silently collapsed to a column of text with
 * a large empty space beside it, which reads as a broken page rather than a
 * design choice.
 *
 * Looking a little further down the same rail costs one already-cached call
 * and means the hero only falls back when the store has no product imagery at
 * all.
 *
 * @param int $depth How many of the newest products to consider.
 */
function bhc_hero_product( int $depth = 12 ): ?WC_Product {
	foreach ( bhc_products_for( 'new', max( 1, $depth ) ) as $product ) {
		if ( $product instanceof WC_Product && (int) $product->get_image_id() > 0 ) {
			return $product;
		}
	}

	return null;
}

/**
 * Warms the caches for every product rail a page is about to render.
 *
 * ProductRepository::prime() batches its lookups, but it can only batch what it
 * is given. Called once per rail it issues a term-relationship query per rail;
 * called once with every rail's ids it issues one, and each rail's own prime()
 * then finds the caches already warm. On the homepage that is the difference
 * between ten term queries and two.
 *
 * @param array<int, array{0:string, 1?:int, 2?:string}> $rails Rail definitions,
 *                                                              each [ source, limit, value ].
 */
function bhc_prime_product_rails( array $rails ): void {
	$repository = bhc_service( \BoneHornCrafts\Core\Product\ProductRepository::class );

	if ( null === $repository ) {
		return;
	}

	$ids = [];

	foreach ( $rails as $rail ) {
		$ids = array_merge(
			$ids,
			bhc_product_ids_for( (string) $rail[0], (int) ( $rail[1] ?? 8 ), (string) ( $rail[2] ?? '' ) )
		);
	}

	$repository->prime( array_values( array_unique( $ids ) ) );
}

/**
 * Prints a section heading block.
 *
 * @param string $title    Heading text.
 * @param string $lede     Optional supporting line.
 * @param string $link_url Optional link URL.
 * @param string $link_text Optional link text.
 */
function bhc_section_header( string $title, string $lede = '', string $link_url = '', string $link_text = '' ): void {
	echo '<header class="section__header">';
	echo '<div>';
	printf( '<h2 class="section__title">%s</h2>', esc_html( $title ) );

	if ( '' !== $lede ) {
		printf( '<p class="section__lede">%s</p>', esc_html( $lede ) );
	}

	echo '</div>';

	if ( '' !== $link_url && '' !== $link_text ) {
		printf(
			'<a class="section__link" href="%s">%s</a>',
			esc_url( $link_url ),
			esc_html( $link_text )
		);
	}

	echo '</header>';
}

/**
 * Returns the URL of a shop page by WooCommerce page id.
 *
 * @param string $page Page key: shop|cart|checkout|myaccount.
 */
function bhc_wc_page_url( string $page ): string {
	if ( ! function_exists( 'wc_get_page_permalink' ) ) {
		return home_url( '/' );
	}

	$url = wc_get_page_permalink( $page );

	return is_string( $url ) ? $url : home_url( '/' );
}

/**
 * Returns the wishlist page URL, falling back to the account endpoint.
 */
function bhc_wishlist_url(): string {
	$page_id = (int) get_option( 'bhc_wishlist_page_id', 0 );

	if ( $page_id > 0 ) {
		$permalink = get_permalink( $page_id );

		if ( is_string( $permalink ) ) {
			return $permalink;
		}
	}

	return bhc_wc_page_url( 'myaccount' );
}

/**
 * Prints an inline SVG icon from the theme sprite.
 *
 * @param string $name  Icon name.
 * @param int    $size  Pixel size.
 * @param string $class Extra CSS classes.
 */
function bhc_icon( string $name, int $size = 22, string $class = '' ): void {
	$paths = [
		'search'   => '<circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/>',
		'account'  => '<circle cx="12" cy="8.5" r="3.75"/><path d="M4.5 20a7.5 7.5 0 0 1 15 0"/>',
		'wishlist' => '<path d="M12 20.5 4.4 12.9a4.7 4.7 0 0 1 6.6-6.6l1 1 1-1a4.7 4.7 0 1 1 6.6 6.6Z"/>',
		'cart'     => '<path d="M3.5 4.5h2.2l2.1 10.2h9.8l1.9-7.3H7"/><circle cx="9.5" cy="19" r="1.3"/><circle cx="17" cy="19" r="1.3"/>',
		'menu'     => '<path d="M4 7h16M4 12h16M4 17h16"/>',
		'close'    => '<path d="m6 6 12 12M18 6 6 18"/>',
		'filter'   => '<path d="M4 6h16M7 12h10M10 18h4"/>',
		'arrow'    => '<path d="M5 12h13m-5-5 5 5-5 5"/>',
	];

	if ( ! isset( $paths[ $name ] ) ) {
		return;
	}

	printf(
		'<svg class="bhc-icon %1$s" width="%2$d" height="%2$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%3$s</svg>',
		esc_attr( $class ),
		(int) $size,
		wp_kses(
			$paths[ $name ],
			[
				'path'   => [ 'd' => true ],
				'circle' => [
					'cx' => true,
					'cy' => true,
					'r'  => true,
				],
			]
		)
	);
}

/**
 * Returns the number of items currently in the cart.
 */
function bhc_cart_count(): int {
	if ( ! function_exists( 'WC' ) || null === WC()->cart ) {
		return 0;
	}

	return (int) WC()->cart->get_cart_contents_count();
}

/**
 * Returns the number of saved wishlist items.
 */
function bhc_wishlist_count(): int {
	$wishlist = bhc_service( \BoneHornCrafts\Core\Wishlist\WishlistService::class );

	return null === $wishlist ? 0 : $wishlist->count();
}

/**
 * Prints a formatted post date.
 *
 * @param int $post_id Post id.
 */
function bhc_posted_on( int $post_id = 0 ): void {
	$post_id = $post_id > 0 ? $post_id : (int) get_the_ID();

	printf(
		'<time datetime="%s">%s</time>',
		esc_attr( (string) get_the_date( 'c', $post_id ) ),
		esc_html( (string) get_the_date( '', $post_id ) )
	);
}

/**
 * Estimated reading time for an article.
 *
 * @param int $post_id Post id.
 */
function bhc_reading_time( int $post_id = 0 ): string {
	$post_id = $post_id > 0 ? $post_id : (int) get_the_ID();
	$words   = str_word_count( wp_strip_all_tags( (string) get_post_field( 'post_content', $post_id ) ) );
	$minutes = max( 1, (int) ceil( $words / 220 ) );

	/* translators: %d: minutes. */
	return sprintf( _n( '%d minute read', '%d minute read', $minutes, 'bhc-theme' ), $minutes );
}

/**
 * Returns one piece of the workshop's contact detail.
 *
 * Held in one place because the same values appear in the footer, on the
 * contact page and in the Organization schema, and three copies of an address
 * is three chances for one of them to be a year out of date. Values come from
 * the commerce plugin's settings when it is active, so an operator can change
 * them without editing a template, and fall back to the registered address
 * otherwise.
 *
 * @param string $part street, locality, region, postcode, phone, phone_href, email or country.
 */
function bhc_contact( string $part ): string {
	// The plugin owns the address, because the same details also have to appear
	// in the Organization JSON-LD and in the policy pages, and three copies of
	// a postal address drift. The theme keeps a fallback so it still renders a
	// sensible footer if the plugin is deactivated.
	$business = bhc_service( \BoneHornCrafts\Core\Store\BusinessDetails::class );

	if ( null !== $business ) {
		$value = match ( $part ) {
			'street'     => $business->street(),
			'locality'   => $business->locality(),
			'region'     => trim( $business->region() . ' ' . $business->postcode() . ', ' . $business->country() ),
			'postcode'   => $business->postcode(),
			'country'    => $business->country_code(),
			'phone'      => $business->phone(),
			'phone_href' => $business->phone_href(),
			'email'      => $business->email(),
			default      => '',
		};

		/** This filter is documented in inc/template-tags.php. */
		return (string) apply_filters( 'bhc_contact_detail', $value, $part );
	}

	static $defaults = [
		'street'     => 'Khasra No. 535-536, Garima Garden',
		'locality'   => 'Sahibabad, Ghaziabad',
		'region'     => 'Uttar Pradesh 201005, India',
		'postcode'   => '201005',
		'country'    => 'IN',
		'phone'      => '+91 87007 53517',
		'phone_href' => '+918700753517',
		'email'      => 'info@bonehorncrafts.com',
	];

	$value = $defaults[ $part ] ?? '';

	/**
	 * Filters a contact detail.
	 *
	 * @param string $value Resolved value.
	 * @param string $part  Which part was requested.
	 */
	return (string) apply_filters( 'bhc_contact_detail', $value, $part );
}

/**
 * Renders the legal links when no menu is assigned to that location.
 *
 * A store must be able to reach its privacy policy and terms from every page
 * whether or not somebody remembered to build a menu, so the fallback is the
 * pages themselves rather than an empty element.
 */
function bhc_legal_menu_fallback(): void {
	$slugs = [
		'contact'           => __( 'Contact Us', 'bhc-theme' ),
		'privacy-policy'    => __( 'Privacy Policy', 'bhc-theme' ),
		'terms-conditions'  => __( 'Terms & Conditions', 'bhc-theme' ),
		'shipping-delivery' => __( 'Shipping & Delivery', 'bhc-theme' ),
		'returns-refunds'   => __( 'Returns & Refunds', 'bhc-theme' ),
	];

	$items = [];

	foreach ( $slugs as $slug => $label ) {
		$page = get_page_by_path( $slug );

		if ( $page instanceof WP_Post ) {
			$items[] = sprintf(
				'<li><a href="%s">%s</a></li>',
				esc_url( (string) get_permalink( $page ) ),
				esc_html( $label )
			);
		}
	}

	if ( [] === $items ) {
		return;
	}

	printf( '<ul class="footer-legal-menu">%s</ul>', implode( '', $items ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each item is escaped as it is built above.
}

/**
 * Whether visitors can create an account.
 *
 * Both switches have to agree: WordPress gates registration at the platform
 * level and WooCommerce controls the My Account form independently. Offering a
 * "Create account" link that leads to a page with no registration form is worse
 * than not offering one.
 */
function bhc_registration_open(): bool {
	$setup = bhc_service( \BoneHornCrafts\Core\Customer\AccountSetup::class );

	if ( null !== $setup ) {
		return $setup->registration_open();
	}

	return (bool) get_option( 'users_can_register' )
		&& 'yes' === (string) get_option( 'woocommerce_enable_myaccount_registration' );
}

/**
 * URL of the registration form.
 *
 * WooCommerce renders login and registration on one page, so there is no
 * separate URL to link to. The fragment targets the register column, which the
 * browser scrolls to — a link that lands someone on a login form and leaves
 * them to find the other half of the page is the reason people assume a store
 * has no signup.
 */
function bhc_register_url(): string {
	$url = bhc_wc_page_url( 'myaccount' );

	/**
	 * Filters the registration URL.
	 *
	 * Point this at a dedicated page if the store grows one.
	 *
	 * @param string $url Registration URL.
	 */
	return (string) apply_filters( 'bhc_register_url', $url . '#customer_login' );
}
