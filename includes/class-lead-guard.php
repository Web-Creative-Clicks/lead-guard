<?php
if (!defined('ABSPATH')) {
    exit;
}

class Lead_Guard
{
    public function __construct()
    {
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_api_hooks();
    }

    private function load_dependencies(): void
    {
        require_once LEADGUARD_SAID_DIR . 'includes/lead-guard-helpers.php';
        require_once LEADGUARD_SAID_DIR . 'api/class-lead-guard-api.php';
        require_once LEADGUARD_SAID_DIR . 'admin/class-lead-guard-admin.php';
        require_once LEADGUARD_SAID_DIR . 'public/class-lead-guard-public.php';
    }

    private function define_admin_hooks(): void
    {
        $admin = new Lead_Guard_Admin();
        $admin->register_menus();
    }

    private function define_public_hooks(): void
    {
        $public = new Lead_Guard_Public();
        $public->register_shortcodes();
    }

    private function define_api_hooks(): void
    {
        $api = new Lead_Guard_API();
        $api->register_routes();
    }
    
    public function run(): void
    {
        // Trigger any global initialization if needed
        add_action('plugins_loaded', ['Lead_Guard_Activator', 'maybe_migrate_db']);
    }
}
