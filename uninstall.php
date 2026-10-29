<?php
/**
 * Plugin Uninstaller
 *
 * Triggered when Nativly is deleted via WordPress Admin.
 *
 * @package Nativly
 * @author  Arsalan Jabarzare
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Clean up all plugin options, transients, and generated assets for the current blog.
 *
 * @return void
 */
function ntv_uninstall_cleanup() {
	$settings = get_option( 'ntv_settings', array() );

	// Only delete persistent data if explicitly enabled in plugin settings.
	if ( empty( $settings['delete_data_on_uninstall'] ) ) {
		return;
	}

	// 1. Delete options.
	delete_option( 'ntv_settings' );
	delete_option( 'ntv_cache_version' );

	// 2. Delete transients.
	delete_transient( 'ntv_update_check' );
	delete_site_transient( 'ntv_update_check' );

	// 3. Remove generated PWA icons directory in uploads.
	$upload_dir = wp_upload_dir();
	$pwa_dir    = trailingslashit( $upload_dir['basedir'] ) . 'ntv-pwa';

	if ( is_dir( $pwa_dir ) ) {
		ntv_recursive_rmdir( $pwa_dir );
	}
}

/**
 * Safely delete a directory and all files inside it.
 *
 * @param string $dir Path to directory.
 * @return void
 */
function ntv_recursive_rmdir( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return;
	}

	$files = scandir( $dir );
	if ( false === $files ) {
		return;
	}

	foreach ( $files as $file ) {
		if ( '.' === $file || '..' === $file ) {
			continue;
		}

		$item_path = $dir . DIRECTORY_SEPARATOR . $file;
		if ( is_dir( $item_path ) ) {
			ntv_recursive_rmdir( $item_path );
		} else {
			if ( function_exists( 'wp_delete_file' ) ) {
				wp_delete_file( $item_path );
			} elseif ( file_exists( $item_path ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				@unlink( $item_path );
			}
		}
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions.rmdir_rmdir
	@rmdir( $dir );
}

// Execute cleanup across all sites in multisite or current single site.
if ( is_multisite() ) {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" );

	if ( ! empty( $blog_ids ) ) {
		$original_blog_id = get_current_blog_id();

		foreach ( $blog_ids as $blog_id ) {
			switch_to_blog( (int) $blog_id );
			ntv_uninstall_cleanup();
		}

		switch_to_blog( $original_blog_id );
	}
} else {
	ntv_uninstall_cleanup();
}
