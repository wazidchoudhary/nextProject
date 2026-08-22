<?php
/**
 * Uninstall routine.
 *
 * Runs only when an administrator deletes the plugin — never on deactivation,
 * which is how plugins destroy data people expected to keep.
 *
 * Even here the destructive path is opt-in: the custom tables and the plugin's
 * product and order meta survive unless "Delete data on uninstall" was switched
 * on in the settings screen first. Nothing in a deactivate-reactivate cycle, a
 * failed update, or a migration between hosts can lose a customer's wishlists.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$bhc_settings = get_option( 'bhc_settings', [] );

if ( ! is_array( $bhc_settings ) || empty( $bhc_settings['delete_data_on_uninstall'] ) ) {
	return;
}

require_once __DIR__ . '/src/Support/Autoloader.php';

BoneHornCrafts\Core\Support\Autoloader::register( 'BoneHornCrafts\\Core', __DIR__ . '/src' );

BoneHornCrafts\Core\Database\Schema::drop();

foreach ( [ 'bhc_settings', 'bhc_db_version', 'bhc_install_pending', 'bhc_account_endpoints_version' ] as $bhc_option ) {
	delete_option( $bhc_option );
}

unset( $bhc_settings, $bhc_option );
