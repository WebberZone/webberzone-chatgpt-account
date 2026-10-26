<?php
/**
 * Uninstall routine: revokes the sign-in and removes the stored tokens and caches on every site.
 *
 * @package WebberZone\ChatGPT_Account
 */

// If uninstall is not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

foreach ( array( 'config', 'token-store', 'oauth-client', 'oauth' ) as $wzcga_class ) {
	require_once __DIR__ . '/includes/class-' . $wzcga_class . '.php';
}

$wzcga_uninstall_site = static function () {
	global $wpdb;

	try {
		\WebberZone\ChatGPT_Account\OAuth::disconnect();
	} catch ( \Exception $e ) {
		// The data below is deleted even if the lock times out.
		unset( $e );
	}

	delete_option( 'wzcga_tokens' );
	delete_option( 'wzcga_refresh_lock' );
	delete_transient( 'wzcga_models' );
	delete_transient( 'wzcga_models_failed' );
	// Pending sign-ins and notices; with an external object cache they expire on their own within 15 minutes.
	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_wzcga_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_wzcga_' ) . '%'
		)
	);
};

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $wzcga_site_id ) {
		switch_to_blog( (int) $wzcga_site_id );
		$wzcga_uninstall_site();
		restore_current_blog();
	}
} else {
	$wzcga_uninstall_site();
}
