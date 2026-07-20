<?php
/**
 * Plugin Name: Lead Guard - Fake Lead & Bot Blocker
 * Plugin URI:  https://github.com/said
 * Description: Ultimate anti-bot and fake lead protection. Uses cryptographically rotating CSS classes, persistent device fingerprinting, interaction timing, Turnstile, and Apps Script Webhook delivery.
 * Version:     2.2.0
 * Author:      Said
 * License:     GPL v2 or later
 */

if (!defined('ABSPATH')) {
    exit;
}

define('LEADGUARD_SAID_VERSION', '2.2.0');
define('LEADGUARD_SAID_TABLE', 'leadguard_said_submissions');
define('LEADGUARD_SAID_DEVICE_COOKIE', 'lgsdid');
define('LEADGUARD_SAID_DIR', plugin_dir_path(__FILE__));

// Load dependencies
require_once LEADGUARD_SAID_DIR . 'includes/lead-guard-helpers.php';
require_once LEADGUARD_SAID_DIR . 'includes/class-lead-guard-activator.php';
require_once LEADGUARD_SAID_DIR . 'includes/class-lead-guard.php';

// Register activation hook
register_activation_hook(__FILE__, ['Lead_Guard_Activator', 'activate']);

// Boot the plugin
function run_lead_guard() {
    $plugin = new Lead_Guard();
    $plugin->run();
}
run_lead_guard();