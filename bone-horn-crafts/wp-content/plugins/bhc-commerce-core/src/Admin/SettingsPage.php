<?php
/**
 * Settings screen.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Admin;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Cache\CacheManager;
use BoneHornCrafts\Core\Cache\Invalidator;
use BoneHornCrafts\Core\Security\Capabilities;
use BoneHornCrafts\Core\Support\Options;

/**
 * Settings form for the merchandising and storefront toggles.
 *
 * Submission is a plain POST with a nonce and a capability check; values are
 * sanitised against the defaults schema in {@see Options::sanitize()}, so an
 * unexpected field cannot reach the database.
 */
final class SettingsPage {

	private const NONCE_ACTION = 'bhc_save_settings';
	private const NONCE_FIELD  = 'bhc_settings_nonce';

	/**
	 * Constructor.
	 *
	 * @param Options      $options Settings.
	 * @param CacheManager $cache   Cache manager.
	 */
	public function __construct( private Options $options, private CacheManager $cache ) {}

	/**
	 * Handles the settings POST.
	 */
	public function handle_submission(): void {
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) {
			return;
		}

		if ( ! Capabilities::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to change these settings.', 'bhc-commerce-core' ), 403 );
		}

		$nonce = sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE_FIELD ] ) );

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Your session expired. Please try again.', 'bhc-commerce-core' ), 403 );
		}

		$input = [];

		foreach ( array_keys( $this->options->defaults() ) as $key ) {
			if ( isset( $_POST[ 'bhc_' . $key ] ) ) {
				// Sanitisation happens in Options::sanitize() against the schema.
				$input[ $key ] = wp_unslash( $_POST[ 'bhc_' . $key ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			} elseif ( is_bool( $this->options->defaults()[ $key ] ) ) {
				$input[ $key ] = false;
			}
		}

		$this->options->save( $input );

		if ( isset( $_POST['bhc_flush_cache'] ) ) {
			foreach ( Invalidator::ALL_GROUPS as $group ) {
				$this->cache->flush_group( $group );
			}
		}

		wp_safe_redirect(
			add_query_arg(
				[
					'page'    => AdminMenu::SETTINGS_SLUG,
					'updated' => 'true',
				],
				admin_url( 'admin.php' )
			)
		);

		exit;
	}

	/**
	 * Renders the screen.
	 */
	public function render(): void {
		if ( ! Capabilities::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'bhc-commerce-core' ), 403 );
		}

		$settings = $this->options->all();

		echo '<div class="wrap bhc-admin">';
		printf( '<h1>%s</h1>', esc_html__( 'Commerce settings', 'bhc-commerce-core' ) );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success flag.
		if ( isset( $_GET['updated'] ) ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html__( 'Settings saved.', 'bhc-commerce-core' )
			);
		}

		echo '<form method="post" action="">';

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );

		echo '<table class="form-table" role="presentation"><tbody>';

		$this->checkbox_row( 'badges_enabled', __( 'Merchandising badges', 'bhc-commerce-core' ), __( 'Show badges on product cards and product pages.', 'bhc-commerce-core' ), (bool) $settings['badges_enabled'] );
		$this->number_row( 'new_arrival_days', __( 'New arrival window (days)', 'bhc-commerce-core' ), (int) $settings['new_arrival_days'], 1, 180 );
		$this->number_row( 'bestseller_limit', __( 'Bestseller ranking size', 'bhc-commerce-core' ), (int) $settings['bestseller_limit'], 1, 100 );
		$this->number_row( 'recommendations_limit', __( 'Recommendations per rail', 'bhc-commerce-core' ), (int) $settings['recommendations_limit'], 1, 24 );
		$this->number_row( 'recommendations_ttl', __( 'Recommendation cache TTL (seconds)', 'bhc-commerce-core' ), (int) $settings['recommendations_ttl'], 60, 604800 );
		$this->checkbox_row( 'wishlist_enabled', __( 'Wishlist', 'bhc-commerce-core' ), __( 'Enable the wishlist across the storefront.', 'bhc-commerce-core' ), (bool) $settings['wishlist_enabled'] );
		$this->checkbox_row( 'wishlist_guest_enabled', __( 'Guest wishlist', 'bhc-commerce-core' ), __( 'Let signed-out visitors save products to a signed cookie.', 'bhc-commerce-core' ), (bool) $settings['wishlist_guest_enabled'] );
		$this->number_row( 'wishlist_max_items', __( 'Wishlist item limit', 'bhc-commerce-core' ), (int) $settings['wishlist_max_items'], 1, 200 );
		$this->number_row( 'recently_viewed_limit', __( 'Recently viewed items', 'bhc-commerce-core' ), (int) $settings['recently_viewed_limit'], 2, 20 );
		$this->checkbox_row( 'tiered_pricing_enabled', __( 'Quantity pricing', 'bhc-commerce-core' ), __( 'Apply wholesale price breaks in the cart.', 'bhc-commerce-core' ), (bool) $settings['tiered_pricing_enabled'] );
		$this->checkbox_row( 'delivery_estimator_enabled', __( 'Delivery estimator', 'bhc-commerce-core' ), __( 'Show the destination delivery estimate on product pages.', 'bhc-commerce-core' ), (bool) $settings['delivery_estimator_enabled'] );
		$this->checkbox_row( 'schema_enabled', __( 'Structured data', 'bhc-commerce-core' ), __( 'Output the JSON-LD schema graph.', 'bhc-commerce-core' ), (bool) $settings['schema_enabled'] );
		$this->text_row( 'canonical_host', __( 'Canonical host', 'bhc-commerce-core' ), (string) $settings['canonical_host'], __( 'Used to normalise absolute URLs in metadata when the site is served from a staging domain.', 'bhc-commerce-core' ) );
		$this->text_row( 'twitter_handle', __( 'Social handle', 'bhc-commerce-core' ), (string) $settings['twitter_handle'], '' );
		$this->text_row( 'organization_email', __( 'Public contact email', 'bhc-commerce-core' ), (string) $settings['organization_email'], '' );
		$this->text_row( 'organization_phone', __( 'Public contact phone', 'bhc-commerce-core' ), (string) $settings['organization_phone'], '' );
		$this->text_row( 'legal_entity', __( 'Manufacturing entity', 'bhc-commerce-core' ), (string) $settings['legal_entity'], __( 'Shown in the footer credit and as the manufacturer in structured data.', 'bhc-commerce-core' ) );
		$this->number_row( 'index_batch_size', __( 'Index batch size', 'bhc-commerce-core' ), (int) $settings['index_batch_size'], 10, 200 );

		echo '</tbody></table>';

		printf(
			'<p><label><input type="checkbox" name="bhc_flush_cache" value="1" /> %s</label></p>',
			esc_html__( 'Also flush the plugin caches when saving.', 'bhc-commerce-core' )
		);

		submit_button( __( 'Save settings', 'bhc-commerce-core' ) );

		echo '</form></div>';
	}

	/**
	 * Renders a checkbox row.
	 *
	 * @param string $key         Setting key.
	 * @param string $label       Field label.
	 * @param string $description Field description.
	 * @param bool   $value       Current value.
	 */
	private function checkbox_row( string $key, string $label, string $description, bool $value ): void {
		printf(
			'<tr><th scope="row"><label for="bhc_%1$s">%2$s</label></th><td><label><input type="checkbox" id="bhc_%1$s" name="bhc_%1$s" value="1" %3$s /> %4$s</label></td></tr>',
			esc_attr( $key ),
			esc_html( $label ),
			checked( $value, true, false ),
			esc_html( $description )
		);
	}

	/**
	 * Renders a number row.
	 *
	 * @param string $key   Setting key.
	 * @param string $label Field label.
	 * @param int    $value Current value.
	 * @param int    $min   Minimum.
	 * @param int    $max   Maximum.
	 */
	private function number_row( string $key, string $label, int $value, int $min, int $max ): void {
		printf(
			'<tr><th scope="row"><label for="bhc_%1$s">%2$s</label></th><td><input type="number" id="bhc_%1$s" name="bhc_%1$s" value="%3$d" min="%4$d" max="%5$d" class="small-text" /></td></tr>',
			esc_attr( $key ),
			esc_html( $label ),
			(int) $value,
			(int) $min,
			(int) $max
		);
	}

	/**
	 * Renders a text row.
	 *
	 * @param string $key         Setting key.
	 * @param string $label       Field label.
	 * @param string $value       Current value.
	 * @param string $description Field description.
	 */
	private function text_row( string $key, string $label, string $value, string $description ): void {
		printf(
			'<tr><th scope="row"><label for="bhc_%1$s">%2$s</label></th><td><input type="text" id="bhc_%1$s" name="bhc_%1$s" value="%3$s" class="regular-text" />%4$s</td></tr>',
			esc_attr( $key ),
			esc_html( $label ),
			esc_attr( $value ),
			'' === $description ? '' : '<p class="description">' . esc_html( $description ) . '</p>'
		);
	}
}
