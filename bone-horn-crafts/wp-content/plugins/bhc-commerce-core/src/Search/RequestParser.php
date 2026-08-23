<?php
/**
 * Reads the current request into a validated filter object.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Search;

defined( 'ABSPATH' ) || exit;

/**
 * The single place the storefront reads the query string.
 *
 * SearchService and FilterPanelRenderer both need to know what the visitor
 * filtered by, and both used to reach into `$_GET` themselves. That put request
 * parsing — a WordPress-integration concern — inside a service and a renderer,
 * and it meant neither class could be exercised without faking a superglobal.
 *
 * Parsing happens once per request and is memoised, so the two callers cannot
 * disagree about what was asked for, and a test swaps this object rather than
 * mutating global state.
 */
final class RequestParser {

	/**
	 * Memoised request.
	 *
	 * @var FilterRequest|null
	 */
	private ?FilterRequest $current = null;

	/**
	 * Returns the filter request for the current page view.
	 */
	public function current(): FilterRequest {
		if ( null !== $this->current ) {
			return $this->current;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filtering of a public archive; every value is validated by FilterRequest.
		$this->current = FilterRequest::from_array( wp_unslash( $_GET ) );

		return $this->current;
	}

	/**
	 * Returns the raw `orderby` value, which WooCommerce owns rather than us.
	 *
	 * WooCommerce's own sort dropdown posts values this plugin does not define,
	 * so this is deliberately not routed through FilterRequest's allow-list.
	 */
	public function orderby(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only ordering of a public archive.
		return isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( (string) $_GET['orderby'] ) ) : '';
	}

	/**
	 * Replaces the parsed request. For tests and for the REST controllers,
	 * which receive their parameters from WP_REST_Request instead.
	 *
	 * @param FilterRequest $request Request to use.
	 */
	public function set( FilterRequest $request ): void {
		$this->current = $request;
	}
}
