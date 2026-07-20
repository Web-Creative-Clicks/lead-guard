<?php
// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;
$table = $wpdb->prefix . 'leadguard_wcc_submissions';
$wpdb->query("DROP TABLE IF EXISTS {$table}");

delete_option('leadguard_wcc_settings');
delete_option('leadguard_wcc_hmac_secret');
delete_option('leadguard_wcc_db_version');
delete_option('leadguard_wcc_last_sheets_result');
