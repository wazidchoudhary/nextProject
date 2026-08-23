<?php
/**
 * Marks front-end options as autoloaded.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Performance;

defined( 'ABSPATH' ) || exit;

/**
 * Moves options that every front-end request reads into the autoload set.
 *
 * WordPress fetches all autoloaded options in one query at bootstrap. An option
 * outside that set costs an individual `SELECT` the first time it is read, and
 * a store's front end reads a surprising number of them: measuring a cold
 * home-page render found 21 separate option queries, each for a different
 * non-autoloaded scalar — WooCommerce's HPOS sync flags, its feature switches,
 * the cart and checkout page ids, the site logo.
 *
 * Twenty-one queries to read twenty-one short strings that could have arrived
 * with the ones already being fetched.
 *
 * The list is deliberately explicit rather than "anything WooCommerce". Every
 * autoloaded option is loaded on every request including admin and cron, so a
 * large or rarely-read value belongs outside the set — the point is to move the
 * small, hot ones in, not to grow the payload.
 */
final class OptionAutoloader {

	/**
	 * Option recording that the pass has run, and at which version.
	 */
	private const APPLIED_OPTION = 'bhc_autoload_optimised';

	/**
	 * Bumped when the list below changes, so an existing site re-runs.
	 */
	private const VERSION = 1;

	/**
	 * Options read on ordinary front-end requests.
	 *
	 * @var string[]
	 */
	private const HOT = [
		// HPOS asks whether the tables are in sync on essentially every
		// request that touches an order, and on plenty that do not.
		'woocommerce_custom_orders_table_data_sync_enabled',
		'woocommerce_custom_orders_table_background_sync_mode',

		// Feature switches, each read through wc_get_container() early.
		'woocommerce_feature_fulfillments_enabled',
		'woocommerce_feature_agentic_checkout_enabled',
		'woocommerce_feature_push_notifications_enabled',
		'wc_feature_woocommerce_brands_enabled',
		'woocommerce_address_autocomplete_provider',

		// Page ids, resolved by wc_get_page_id() on every template that links
		// to the cart, the checkout or the account area — which is the header.
		'woocommerce_cart_page_id',
		'woocommerce_checkout_page_id',
		'woocommerce_myaccount_page_id',
		'woocommerce_shop_page_id',
		'woocommerce_terms_page_id',

		// Storefront behaviour.
		'woocommerce_demo_store',
		'woocommerce_enable_myaccount_registration',
		'woocommerce_enable_delayed_account_creation',
		'woocommerce_notify_low_stock_amount',
		'woocommerce_brand_permalink',
		'woocommerce_pickup_location_settings',

		// WordPress itself.
		'site_logo',
		'WPLANG',

		// Ours: read by the account endpoint registrar on every request.
		'bhc_account_endpoints_version',
	];

	/**
	 * Options that should exist with a definite value.
	 *
	 * HPOS reads the sync flag constantly and it was never written on this
	 * store, so every request asked the database for a row that was not there.
	 * The store runs HPOS-only with no post-table mirror, so 'no' is both the
	 * correct answer and one that can be autoloaded.
	 *
	 * @var array<string, string>
	 */
	private const DEFAULTS = [
		'woocommerce_custom_orders_table_data_sync_enabled' => 'no',
	];

	/**
	 * Writes the definite values for options WooCommerce leaves unset.
	 *
	 * Only ever writes when the option is genuinely absent — a store that has
	 * deliberately turned sync on keeps it.
	 *
	 * @return string[] Options that were created.
	 */
	public function seed_defaults(): array {
		$created = [];

		foreach ( self::DEFAULTS as $option => $value ) {
			if ( false !== get_option( $option, false ) ) {
				continue;
			}

			add_option( $option, $value, '', true );

			$created[] = $option;
		}

		return $created;
	}

	/**
	 * Applies the change unless it has already run at this version.
	 *
	 * @return string[] Options that were changed.
	 */
	public function apply_once(): array {
		if ( (int) get_option( self::APPLIED_OPTION, 0 ) >= self::VERSION ) {
			return [];
		}

		$this->seed_defaults();

		$changed = $this->apply();

		update_option( self::APPLIED_OPTION, self::VERSION, true );

		return $changed;
	}

	/**
	 * Sets autoload on every option in the list that exists and is not already
	 * autoloaded.
	 *
	 * An option that does not exist is left alone: creating it here would
	 * invent a value WooCommerce has deliberately not set, and WordPress
	 * already caches a missing option as a "notoption" for the request.
	 *
	 * @return string[] Options that were changed.
	 */
	public function apply(): array {
		global $wpdb;

		$pending = $this->pending();

		if ( [] === $pending ) {
			return [];
		}

		$placeholders = implode( ', ', array_fill( 0, count( $pending ), '%s' ) );

		// One statement rather than one update_option() per name: this runs on
		// upgrade, and update_option() would fire a filter chain and invalidate
		// the alloptions cache once per option.
		//
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- The autoload column is not reachable through the options API.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching -- A schema-level write, run on upgrade; the caches it affects are cleared below.
		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- The placeholders are interpolated from a fixed-length list, which the sniff cannot follow.
		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Placeholders only; every value is bound.
				"UPDATE {$wpdb->options} SET autoload = 'yes' WHERE option_name IN ( {$placeholders} )",
				$pending
			)
		);
		// phpcs:enable

		// The autoload set has changed, so the cached copy of it is now wrong.
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'notoptions', 'options' );

		return $pending;
	}

	/**
	 * Options from the list that exist and are not yet autoloaded.
	 *
	 * @return string[]
	 */
	public function pending(): array {
		global $wpdb;

		$placeholders = implode( ', ', array_fill( 0, count( self::HOT ), '%s' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- The autoload column is not exposed by the options API.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching -- Read once on upgrade or from WP-CLI, never on a request path.
		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Placeholders interpolated from a fixed-length list.
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Placeholders only; every value is bound.
				"SELECT option_name FROM {$wpdb->options} WHERE option_name IN ( {$placeholders} ) AND autoload NOT IN ( 'yes', 'on', 'auto', 'auto-on' )",
				self::HOT
			)
		);
		// phpcs:enable

		return array_map( 'strval', (array) $rows );
	}
}
