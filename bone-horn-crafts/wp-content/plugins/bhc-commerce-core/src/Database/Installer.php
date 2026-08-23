<?php
/**
 * Schema installer and migration runner.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Database;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Contracts\HookableInterface;

/**
 * Runs versioned schema migrations.
 *
 * Activation only sets a flag: WooCommerce may not be loaded during an
 * activation request (plugin order is not guaranteed), so the real work happens
 * on the next `admin_init`/CLI run when the full stack is available. That also
 * covers deployments that never "activate" the plugin because it was already
 * active in the release before.
 */
final class Installer implements HookableInterface {

	public const DB_VERSION_OPTION = 'bhc_db_version';
	public const PENDING_OPTION    = 'bhc_install_pending';
	public const DB_VERSION        = 4;

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'admin_init', [ $this, 'maybe_upgrade' ], 5 );
		add_action( 'bhc_run_migrations', [ $this, 'run' ] );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			add_action( 'init', [ $this, 'maybe_upgrade' ], 5 );
		}
	}

	/**
	 * Activation hook: flag the install and schedule the migration.
	 */
	public static function activate(): void {
		update_option( self::PENDING_OPTION, 1, false );

		if ( ! wp_next_scheduled( 'bhc_run_migrations' ) ) {
			wp_schedule_single_event( time() + 5, 'bhc_run_migrations' );
		}
	}

	/**
	 * Deactivation hook: clear scheduled work but keep all data.
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'bhc_run_migrations' );

		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( '', [], 'bhc-core' );
		}
	}

	/**
	 * Runs migrations when the stored version is behind the code version.
	 */
	public function maybe_upgrade(): void {
		$installed = (int) get_option( self::DB_VERSION_OPTION, 0 );

		if ( $installed >= self::DB_VERSION && ! get_option( self::PENDING_OPTION ) ) {
			return;
		}

		$this->run();
	}

	/**
	 * Executes the schema install and any post-install bootstrapping.
	 *
	 * Safe to run repeatedly: `dbDelta()` is idempotent and every step below
	 * checks before it writes.
	 */
	public function run(): void {
		Schema::install();

		delete_option( self::PENDING_OPTION );
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );

		/**
		 * Fires after the custom schema has been created or upgraded.
		 *
		 * Modules use this to register roles, seed attributes and schedule
		 * background jobs without adding their own activation hooks.
		 *
		 * @since 1.0.0
		 *
		 * @param int $db_version Installed schema version.
		 */
		do_action( 'bhc_schema_installed', self::DB_VERSION );
	}

	/**
	 * Current installed schema version.
	 */
	public function installed_version(): int {
		return (int) get_option( self::DB_VERSION_OPTION, 0 );
	}
}
