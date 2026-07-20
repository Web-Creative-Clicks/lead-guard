<?php
if (!defined('ABSPATH')) {
    exit;
}

class Lead_Guard_Admin
{
    public function register_menus(): void
    {
        add_action('admin_menu', function () {
            add_menu_page('Lead Guard', 'Lead Guard', 'manage_options', 'leadguard-said', [$this, 'render_submissions_page'], 'dashicons-shield', 58);
            add_submenu_page('leadguard-said', 'Submissions', 'Submissions', 'manage_options', 'leadguard-said', [$this, 'render_submissions_page']);
            add_submenu_page('leadguard-said', 'Settings', 'Settings', 'manage_options', 'leadguard-said-settings', [$this, 'render_settings_page']);
        });
    }

    public function render_submissions_page(): void
    {
        if (!current_user_can('manage_options')) return;
        require_once plugin_dir_path(__FILE__) . 'partials/lead-guard-admin-submissions.php';
    }

    public function render_settings_page(): void
    {
        if (!current_user_can('manage_options')) return;
        require_once plugin_dir_path(__FILE__) . 'partials/lead-guard-admin-settings.php';
    }
}
