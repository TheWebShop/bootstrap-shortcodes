<?php
/**
 * Runs when the plugin is deleted from Plugins > Installed Plugins.
 *
 * Removes the plugin's own options: settings and the dependency-scan state and
 * results. Post content and post meta are left alone.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'bs_options' );
delete_option( 'bs_scan_state' );
delete_option( 'bs_scan_results' );
