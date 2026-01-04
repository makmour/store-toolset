<?php
/**
 * Uninstall routine for Store Toolset for WooCommerce.
 *
 * @package Store_Toolset_WooCommerce
 * @version 1.1.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

// 1. Delete the transient that stores the last log content.
delete_transient( 'store_toolset_last_log' );

// 2. Delete the user meta for screen options from all users.
delete_metadata( 'user', 0, 'store_toolset_columns', '', true );
delete_metadata( 'user', 0, 'store_toolset_per_page', '', true );

// 3. Recursively remove the log directory.
$store_toolset_upload_dir = wp_upload_dir();
$store_toolset_log_dir    = trailingslashit( $store_toolset_upload_dir['basedir'] ) . 'store-toolset-logs';

global $wp_filesystem;
if ( empty( $wp_filesystem ) ) {
	require_once ABSPATH . '/wp-admin/includes/file.php';
	WP_Filesystem();
}

if ( $wp_filesystem->is_dir( $store_toolset_log_dir ) ) {
	$wp_filesystem->delete( $store_toolset_log_dir, true );
}