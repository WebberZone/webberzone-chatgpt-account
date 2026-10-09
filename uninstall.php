<?php
/**
 * Uninstall routine: removes the stored tokens and caches.
 *
 * @package WebberZone\ChatGPT_Account
 */

// If uninstall is not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wzcga_tokens' );
delete_option( 'wzcga_refresh_lock' );
delete_transient( 'wzcga_models' );
delete_transient( 'wzcga_models_failed' );

global $wpdb;
$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_wzcga_flow_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_wzcga_flow_' ) . '%'
	)
);
