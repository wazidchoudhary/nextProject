<?php
/**
 * Uninstall handler.
 *
 * @package BoneHornCrafts\Newsletter
 */

declare( strict_types = 1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// The subscriber table is deliberately left in place.
//
// Deactivating a plugin to troubleshoot something, then deleting it because the
// troubleshooting did not work, must not destroy a mailing list that took two
// years to build and exists nowhere else. The table is small, inert without the
// plugin, and trivially dropped by hand for anyone who genuinely wants it gone.
//
// Only the schema-version option is removed, so a reinstall re-runs dbDelta
// against whatever is already there.
delete_option( 'bhc_newsletter_db_version' );
