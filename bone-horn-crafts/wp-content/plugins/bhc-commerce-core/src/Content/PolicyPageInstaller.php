<?php
/**
 * Publishes the store's policy pages.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Content;

defined( 'ABSPATH' ) || exit;

use WP_Post;

/**
 * Creates the contact and legal pages, and points WooCommerce and WordPress at
 * them.
 *
 * These used to be produced only by the demo seeder, which was the wrong home
 * for them for the same reason customer registration was: a store that imported
 * a real catalogue and never ran the seeder had a footer legal menu that
 * rendered nothing, no privacy policy for WordPress to link from the login
 * screen, and no terms page for the checkout consent line to point at.
 *
 * Publishing is deliberately one-way. A page is created if it is missing and
 * then left alone forever — a merchant who rewrites the returns policy must not
 * have it overwritten on the next deploy. `refresh()` exists for the case where
 * that is genuinely wanted, and it is only ever reached from an explicit
 * WP-CLI flag.
 */
final class PolicyPageInstaller {

	/**
	 * Option recording that the pages have been published once.
	 */
	private const APPLIED_OPTION = 'bhc_policy_pages_installed';

	/**
	 * Marks a page as one of ours, so it can be found again after a rename.
	 */
	private const MARKER_META = '_bhc_policy_page';

	/**
	 * Cached slug => id map, so a caller that only needs the ids does not pay
	 * for a lookup per page.
	 */
	private const IDS_OPTION = 'bhc_policy_page_ids';

	/**
	 * WooCommerce options that must point at these pages, as slug => option.
	 */
	private const WC_OPTIONS = [
		'terms-conditions' => 'woocommerce_terms_page_id',
		'returns-refunds'  => 'woocommerce_refund_returns_page_id',
	];

	/**
	 * Constructor.
	 *
	 * @param PolicyPageContent $content Page copy.
	 */
	public function __construct( private PolicyPageContent $content ) {}

	/**
	 * Publishes the pages unless that has already been done.
	 *
	 * @return string[] Slugs of the pages created.
	 */
	public function install_once(): array {
		if ( '' !== (string) get_option( self::APPLIED_OPTION, '' ) ) {
			return [];
		}

		$created = $this->install();

		update_option( self::APPLIED_OPTION, gmdate( 'c' ), false );

		return $created;
	}

	/**
	 * Creates any missing page and wires the options.
	 *
	 * Safe to run repeatedly: an existing page is found and left untouched.
	 *
	 * @return string[] Slugs of the pages created.
	 */
	public function install(): array {
		$created = [];

		foreach ( $this->content->all() as $slug => $page ) {
			if ( $this->find( $slug ) instanceof WP_Post ) {
				continue;
			}

			$id = $this->create( $slug, $page );

			if ( $id > 0 ) {
				$created[] = $slug;
			}
		}

		$this->wire_options();
		$this->refresh_ids();

		return $created;
	}

	/**
	 * Replaces the body of every page with the current copy.
	 *
	 * Destructive by design and never called automatically.
	 *
	 * @return string[] Slugs of the pages rewritten.
	 */
	public function refresh(): array {
		$updated = [];

		foreach ( $this->content->all() as $slug => $page ) {
			$existing = $this->find( $slug );

			if ( ! $existing instanceof WP_Post ) {
				continue;
			}

			wp_update_post(
				[
					'ID'           => $existing->ID,
					'post_content' => $page['content'],
					'post_excerpt' => $page['excerpt'],
				]
			);

			$updated[] = $slug;
		}

		return $updated;
	}

	/**
	 * The page ids, read from a cached map.
	 *
	 * `status()` resolves each page properly and costs a query apiece. That is
	 * the right behaviour for a status report and the wrong behaviour for
	 * anything on a request path — feeding these ids to the page primer through
	 * `status()` added five queries per page load to save twenty, which is not
	 * the trade it looks like.
	 *
	 * The map is written whenever pages are installed and refreshed lazily if
	 * it is missing.
	 *
	 * @return int[]
	 */
	public function page_ids(): array {
		$cached = get_option( self::IDS_OPTION, null );

		if ( ! is_array( $cached ) ) {
			$cached = $this->refresh_ids();
		}

		return array_values( array_filter( array_map( 'intval', $cached ) ) );
	}

	/**
	 * Rebuilds and stores the slug => id map.
	 *
	 * @return array<string, int>
	 */
	private function refresh_ids(): array {
		$map = $this->status();

		update_option( self::IDS_OPTION, $map, true );

		return $map;
	}

	/**
	 * Reports which pages exist, as slug => page id (0 when missing).
	 *
	 * @return array<string, int>
	 */
	public function status(): array {
		$status = [];

		foreach ( array_keys( $this->content->all() ) as $slug ) {
			$page = $this->find( (string) $slug );

			$status[ (string) $slug ] = $page instanceof WP_Post ? (int) $page->ID : 0;
		}

		return $status;
	}

	/**
	 * Finds a policy page by slug, then by our marker.
	 *
	 * The marker lookup is what makes a renamed page survive: an owner who
	 * retitles "Returns & Refunds" to "Our Returns Promise" changes the slug,
	 * and a slug-only lookup would decide the page was missing and publish a
	 * second copy.
	 *
	 * @param string $slug Page slug.
	 */
	private function find( string $slug ): ?WP_Post {
		$page = get_page_by_path( $slug );

		if ( $page instanceof WP_Post && 'trash' !== $page->post_status ) {
			return $page;
		}

		$found = get_posts(
			[
				'post_type'        => 'page',
				'post_status'      => [ 'publish', 'draft', 'private', 'pending' ],
				'numberposts'      => 1,
				'meta_key'         => self::MARKER_META,
				'meta_value'       => $slug,
				'suppress_filters' => false,
			]
		);

		$first = $found[0] ?? null;

		return $first instanceof WP_Post ? $first : null;
	}

	/**
	 * Creates one page.
	 *
	 * @param string                                              $slug Page slug.
	 * @param array{title:string, excerpt:string, content:string} $page Page copy.
	 */
	private function create( string $slug, array $page ): int {
		$id = wp_insert_post(
			[
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'post_title'     => wp_specialchars_decode( $page['title'] ),
				'post_name'      => $slug,
				'post_excerpt'   => $page['excerpt'],
				'post_content'   => $page['content'],
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			],
			true
		);

		if ( is_wp_error( $id ) || 0 === (int) $id ) {
			return 0;
		}

		update_post_meta( (int) $id, self::MARKER_META, $slug );

		return (int) $id;
	}

	/**
	 * Points WordPress and WooCommerce at the pages.
	 *
	 * Each option is only set when it is empty or points at a page that no
	 * longer exists, so a store that has chosen its own terms page keeps it.
	 */
	private function wire_options(): void {
		foreach ( self::WC_OPTIONS as $slug => $option ) {
			$page = $this->find( (string) $slug );

			if ( ! $page instanceof WP_Post ) {
				continue;
			}

			if ( $this->page_exists( (int) get_option( $option, 0 ) ) ) {
				continue;
			}

			update_option( $option, (int) $page->ID );
		}

		// WordPress links this from the login and registration screens, and
		// WooCommerce's privacy tools reference it.
		$privacy = $this->find( 'privacy-policy' );

		if ( $privacy instanceof WP_Post && ! $this->page_exists( (int) get_option( 'wp_page_for_privacy_policy', 0 ) ) ) {
			update_option( 'wp_page_for_privacy_policy', (int) $privacy->ID );
		}
	}

	/**
	 * Whether an id refers to a page that is still there.
	 *
	 * @param int $page_id Candidate page id.
	 */
	private function page_exists( int $page_id ): bool {
		if ( $page_id <= 0 ) {
			return false;
		}

		$page = get_post( $page_id );

		return $page instanceof WP_Post && 'trash' !== $page->post_status;
	}
}
