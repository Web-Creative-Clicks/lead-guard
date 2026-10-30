<?php
/**
 * Plugin Name: Lead Guard - Fake Lead & Bot Blocker
 * Plugin URI:  https://github.com/Web-Creative-Clicks
 * Description: Ultimate anti-bot and fake lead protection. Uses cryptographically rotating CSS classes, persistent device fingerprinting, interaction timing, Turnstile, and Apps Script Webhook delivery.
 * Version:     2.2.0
 * Author:      Web Creative Clicks
 * License:     GPL v2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define('leadguard_wcc_VERSION', '2.2.0');
define('leadguard_wcc_TABLE', 'leadguard_wcc_submissions');
define('leadguard_wcc_DEVICE_COOKIE', 'lgsdid');
define('leadguard_wcc_DIR', plugin_dir_path(__FILE__));

// Load dependencies
require_once leadguard_wcc_DIR . 'includes/lead-guard-helpers.php';
require_once leadguard_wcc_DIR . 'includes/class-lead-guard-activator.php';
require_once leadguard_wcc_DIR . 'includes/class-lead-guard.php';

// Register activation hook
register_activation_hook(__FILE__, ['Leadguard_WCC_Activator', 'activate']);

// Boot the plugin
function leadguard_wcc_run() {
    $plugin = new Leadguard_WCC();
    $plugin->run();
}
leadguard_wcc_run();
