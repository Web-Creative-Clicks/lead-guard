<?php
if (!defined('ABSPATH')) {
    exit;
}

class Lead_Guard_Activator
{
    public static function activate(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . LEADGUARD_SAID_TABLE;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at          DATETIME        NOT NULL,
            name                VARCHAR(191)    NULL,
            email               VARCHAR(191)    NULL,
            phone               VARCHAR(64)     NULL,
            ip_address          VARCHAR(64)     NULL,
            user_agent          TEXT            NULL,
            page_url            TEXT            NULL,
            fbclid              VARCHAR(255)    NULL,
            fill_time_ms        INT             NULL,
            mouse_moves         INT             NULL,
            key_presses         INT             NULL,
            honeypot_triggered  TINYINT(1)      DEFAULT 0,
            webdriver_detected  TINYINT(1)      DEFAULT 0,
            fraud_score         INT             NOT NULL DEFAULT 0,
            status              VARCHAR(20)     NOT NULL DEFAULT 'clean',
            reasons             TEXT            NULL,
            raw_payload         LONGTEXT        NULL,
            traffic_source      VARCHAR(50)     NULL,
            utm_params          TEXT            NULL,
            device_id           VARCHAR(64)     NULL,
            device_fingerprint  VARCHAR(64)     NULL,
            token_fp_mismatch   TINYINT(1)      DEFAULT 0,
            PRIMARY KEY (id),
            KEY ip_address     (ip_address),
            KEY email          (email),
            KEY status         (status),
            KEY created_at     (created_at),
            KEY traffic_source (traffic_source),
            KEY device_id          (device_id),
            KEY device_fingerprint (device_fingerprint)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        if (!get_option('leadguard_said_settings')) {
            add_option('leadguard_said_settings', leadguard_said_default_settings());
        }
        if (!get_option('leadguard_said_hmac_secret')) {
            add_option('leadguard_said_hmac_secret', wp_generate_password(64, true, true));
        }
    }

    public static function maybe_migrate_db(): void
    {
        $installed = get_option('leadguard_said_db_version', '0');
        if (version_compare($installed, '2.2.0', '>=')) return;

        global $wpdb;
        $table = $wpdb->prefix . LEADGUARD_SAID_TABLE;

        $cols = $wpdb->get_col("DESCRIBE {$table}", 0);
        if (!in_array('traffic_source', $cols, true)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN traffic_source VARCHAR(50) NULL AFTER raw_payload");
            $wpdb->query("ALTER TABLE {$table} ADD INDEX traffic_source (traffic_source)");
        }
        if (!in_array('utm_params', $cols, true)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN utm_params TEXT NULL AFTER traffic_source");
        }

        if (!in_array('device_id', $cols, true)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN device_id VARCHAR(64) NULL AFTER utm_params");
            $wpdb->query("ALTER TABLE {$table} ADD INDEX device_id (device_id)");
        }
        if (!in_array('device_fingerprint', $cols, true)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN device_fingerprint VARCHAR(64) NULL AFTER device_id");
            $wpdb->query("ALTER TABLE {$table} ADD INDEX device_fingerprint (device_fingerprint)");
        }
        if (!in_array('token_fp_mismatch', $cols, true)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN token_fp_mismatch TINYINT(1) DEFAULT 0 AFTER device_fingerprint");
        }

        update_option('leadguard_said_db_version', '2.2.0');
    }
}
