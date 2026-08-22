<?php
/**
 * Custom table definitions.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Owns the DDL for the three custom tables the plugin creates.
 *
 * Why custom tables at all? WordPress meta is the right default and is used for
 * everything that belongs to a single product (badges, HSN code, price tiers).
 * These three cases are the ones where meta is the wrong tool:
 *
 * 1. `bhc_wishlist` — a many-to-many relation between customers and products
 *    that is queried in both directions ("this customer's list", "how many
 *    customers saved this product"). As user meta it would be one serialised
 *    blob per customer: unqueryable, race-prone under concurrent writes and
 *    unbounded in size.
 *
 * 2. `bhc_product_affinity` — a pre-computed recommendation index. Computing
 *    "frequently bought together" from order data at request time is a
 *    multi-join scan across order items; the nightly Action Scheduler job
 *    writes the result here so the front end does one indexed read.
 *
 * 3. `bhc_product_stats` — merchandising counters (views, 30 day units, rank).
 *    These are written far more often than they are read and would otherwise
 *    add autoloaded postmeta churn plus `meta_value` sorts, which cannot use an
 *    index because `meta_value` is a longtext.
 *
 * Everything else deliberately stays in core/WooCommerce tables so standard
 * exports, backups and the WooCommerce lookup tables keep working.
 */
final class Schema {

	public const TABLE_WISHLIST = 'bhc_wishlist';
	public const TABLE_AFFINITY = 'bhc_product_affinity';
	public const TABLE_STATS    = 'bhc_product_stats';

	/**
	 * Returns the prefixed table name.
	 *
	 * @param string $table One of the TABLE_* constants.
	 */
	public static function table( string $table ): string {
		global $wpdb;

		return $wpdb->prefix . $table;
	}

	/**
	 * Returns every `CREATE TABLE` statement, keyed by table name.
	 *
	 * @return array<string, string>
	 */
	public static function definitions(): array {
		global $wpdb;

		$collate = $wpdb->has_cap( 'collation' ) ? $wpdb->get_charset_collate() : '';

		$wishlist = self::table( self::TABLE_WISHLIST );
		$affinity = self::table( self::TABLE_AFFINITY );
		$stats    = self::table( self::TABLE_STATS );

		return [
			$wishlist => "CREATE TABLE {$wishlist} (
	id bigint(20) unsigned NOT NULL auto_increment,
	user_id bigint(20) unsigned NOT NULL default 0,
	list_token char(64) NOT NULL default '',
	product_id bigint(20) unsigned NOT NULL,
	date_added datetime NOT NULL default '0000-00-00 00:00:00',
	PRIMARY KEY  (id),
	UNIQUE KEY user_product (user_id,product_id),
	KEY token_product (list_token,product_id),
	KEY product_id (product_id),
	KEY date_added (date_added)
) {$collate};",

			$affinity => "CREATE TABLE {$affinity} (
	product_id bigint(20) unsigned NOT NULL,
	related_id bigint(20) unsigned NOT NULL,
	strategy varchar(32) NOT NULL default 'bought_together',
	score decimal(8,5) NOT NULL default 0.00000,
	updated_at datetime NOT NULL default '0000-00-00 00:00:00',
	PRIMARY KEY  (product_id,strategy,related_id),
	KEY product_score (product_id,score),
	KEY related_id (related_id),
	KEY updated_at (updated_at)
) {$collate};",

			$stats    => "CREATE TABLE {$stats} (
	product_id bigint(20) unsigned NOT NULL,
	views_30d int(10) unsigned NOT NULL default 0,
	units_30d int(10) unsigned NOT NULL default 0,
	revenue_30d decimal(12,2) NOT NULL default 0.00,
	bestseller_rank int(10) unsigned NOT NULL default 0,
	trending_score decimal(10,5) NOT NULL default 0.00000,
	updated_at datetime NOT NULL default '0000-00-00 00:00:00',
	PRIMARY KEY  (product_id),
	KEY bestseller_rank (bestseller_rank),
	KEY trending_score (trending_score),
	KEY units_30d (units_30d)
) {$collate};",
		];
	}

	/**
	 * Creates or updates every table via `dbDelta()`.
	 *
	 * @return string[] dbDelta result lines.
	 */
	public static function install(): array {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$results = [];

		foreach ( self::definitions() as $sql ) {
			$results[] = dbDelta( $sql );
		}

		return array_merge( ...array_map( 'array_values', $results ) );
	}

	/**
	 * Whether every custom table exists.
	 */
	public static function is_installed(): bool {
		global $wpdb;

		foreach ( array_keys( self::definitions() ) as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Schema check, table name is generated.
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

			if ( $found !== $table ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Drops the custom tables. Only ever called by an explicit uninstall.
	 */
	public static function drop(): void {
		global $wpdb;

		foreach ( array_keys( self::definitions() ) as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table identifiers cannot be bound as placeholders; these come from Schema::definitions(), never from user input.
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}
	}
}
