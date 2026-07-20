<?php
// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;
$table = $wpdb->prefix . 'leadguard_said_submissions';
$wpdb->query("DROP TABLE IF EXISTS {$table}");

delete_option('leadguard_said_settings');
delete_option('leadguard_said_hmac_secret');
delete_option('leadguard_said_db_version');
delete_option('leadguard_said_last_sheets_result');
