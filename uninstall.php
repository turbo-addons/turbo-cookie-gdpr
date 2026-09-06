<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package TurboCookie
 * @since   1.0.0
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Load constants needed for uninstall.
define( 'TURBO_COOKIE_CONSENT_LOG_TABLE', 'turbo_cookie_consent_log' );
define( 'TURBO_COOKIE_SETTINGS_OPTION', 'turbo_cookie_settings' );
define( 'TURBO_COOKIE_BANNER_OPTION', 'turbo_cookie_banner' );
define( 'TURBO_COOKIE_CATEGORIES_OPTION', 'turbo_cookie_categories' );
define( 'TURBO_COOKIE_GCM_OPTION', 'turbo_cookie_gcm' );
define( 'TURBO_COOKIE_ONBOARDING_OPTION', 'turbo_cookie_onboarding_completed' );

global $wpdb;

// Remove all plugin options.
delete_option( TURBO_COOKIE_SETTINGS_OPTION );
delete_option( TURBO_COOKIE_BANNER_OPTION );
delete_option( TURBO_COOKIE_CATEGORIES_OPTION );
delete_option( TURBO_COOKIE_GCM_OPTION );
delete_option( TURBO_COOKIE_ONBOARDING_OPTION );
delete_option( 'turbo_cookie_db_version' );

// Drop the consent log table.
$turbo_cookie_table_name = $wpdb->prefix . TURBO_COOKIE_CONSENT_LOG_TABLE;
$wpdb->query( "DROP TABLE IF EXISTS {$turbo_cookie_table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange

// Clear scheduled hooks.
wp_clear_scheduled_hook( 'turbo_cookie_cleanup_logs' );
