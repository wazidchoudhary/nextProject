<?php
/**
 * Subscriber table installer.
 *
 * @package BoneHornCrafts\Newsletter
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Newsletter\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and upgrades the subscriber table.
 *
 * A custom table rather than a post type or user meta, for three reasons that
 * all point the same way: a subscriber is not editorial content and does not
 * want a permalink, an address must be unique and only a table can enforce
 * that, and the common queries — "everyone confirmed", "how many this month" —
 * are index scans here and joins against `postmeta` otherwise.
 */
final class SchemaInstaller {

	/**
	 * Option holding the installed schema version.
	 */
	public const VERSION_OPTION = 'bhc_newsletter_db_version';

	/**
	 * Current schema version. Bump to trigger an upgrade on the next load.
	 */
	public const VERSION = 1;

	/**
	 * Unqualified table name.
	 */
	private const TABLE = 'bhc_subscribers';

	/**
	 * Fully qualified table name.
	 */
	public static function table(): string {
		global $wpdb;

		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Creates or upgrades the table if the stored version is behind.
	 *
	 * Cheap enough to call on every load: it is an option read until the
	 * version actually changes.
	 */
	public function maybe_install(): void {
		if ( (int) get_option( self::VERSION_OPTION, 0 ) >= self::VERSION ) {
			return;
		}

		$this->install();
	}

	/**
	 * Runs `dbDelta()` against the schema.
	 *
	 * Idempotent: dbDelta compares the existing table to the statement and
	 * issues only the differences, so running it twice is a no-op.
	 */
	public function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$collate = $wpdb->get_charset_collate();

		// The email column is 190 rather than 255 so it fits a unique index
		// under utf8mb4, where MySQL's 767-byte legacy index limit allows 191
		// four-byte characters. 255 fails to create the index on older servers
		// and the uniqueness guarantee disappears silently.
		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			email VARCHAR(190) NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			token CHAR(64) NOT NULL,
			source VARCHAR(60) NOT NULL DEFAULT '',
			ip_hash CHAR(64) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL,
			confirmed_at DATETIME NULL DEFAULT NULL,
			ended_at DATETIME NULL DEFAULT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY email (email),
			KEY status_created (status, created_at),
			KEY token (token)
		) {$collate};";

		dbDelta( $sql );

		update_option( self::VERSION_OPTION, self::VERSION, false );
	}
}
