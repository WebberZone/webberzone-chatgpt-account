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
