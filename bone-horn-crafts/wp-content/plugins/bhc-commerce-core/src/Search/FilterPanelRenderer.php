<?php
/**
 * Storefront filter panel.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Search;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Contracts\HookableInterface;
use BoneHornCrafts\Core\Support\Template;

/**
 * Renders the shop filter panel.
 *
 * The panel is a plain `<form method="get">`: it filters without JavaScript,
 * and the front-end module upgrades it to a fetch-driven update when it loads.
 * That progressive-enhancement order matters for both accessibility and for
 * the "no layout shift while JS boots" requirement.
 */
final class FilterPanelRenderer implements HookableInterface {

	/**
	 * Constructor.
	 *
	 * @param SearchService $search   Search service.
	 * @param Template      $template Template renderer.
	 */
	public function __construct(
		private SearchService $search,
		private Template $template,
		private RequestParser $request_parser
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_shortcode( 'bhc_filter_panel', [ $this, 'shortcode' ] );
	}

	/**
	 * Renders the panel markup.
	 *
	 * @param FilterRequest|null $request Active filter selection.
	 */
	public function render( ?FilterRequest $request = null ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter state.
		$request = $request ?? $this->request_parser->current();

		return $this->template->render(
			'search/filter-panel.php',
			[
				'facets'      => $this->search->facets(),
				'price_range' => $this->search->price_range(),
				'request'     => $request,
				'action_url'  => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' ),
			]
		);
	}

	/**
	 * `[bhc_filter_panel]` shortcode.
	 */
	public function shortcode(): string {
		return $this->render();
	}
}
