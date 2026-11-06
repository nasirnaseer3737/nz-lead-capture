<?php
/**
 * Uninstall: remove the leads table and all plugin options.
 * Runs only when the plugin is deleted from WordPress, not on deactivation.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;
$table = $wpdb->prefix . 'nzlc_leads';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

delete_option( 'nzlc_notify_email' );
delete_option( 'nzlc_success_message' );
delete_option( 'nzlc_seo_enabled' );
delete_option( 'nzlc_business_name' );
