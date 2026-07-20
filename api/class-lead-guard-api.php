<?php
if (!defined('ABSPATH')) {
    exit;
}

class Lead_Guard_API
{
    public function register_routes(): void
    {
        add_action('rest_api_init', function () {
            register_rest_route('leadguard-wcc/v1', '/submit', [
                'methods' => 'POST',
                'callback' => [$this, 'handle_submission'],
                'permission_callback' => '__return_true'
            ]);
            register_rest_route('leadguard-wcc/v1', '/init', [
                'methods' => 'GET',
                'callback' => [$this, 'handle_init'],
                'permission_callback' => '__return_true'
            ]);
            register_rest_route('leadguard-wcc/v1', '/check-email', [
                'methods' => 'POST',
                'callback' => [$this, 'check_email_endpoint'],
                'permission_callback' => '__return_true'
            ]);
        });

        add_action('rest_api_init', function () {
            remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
            add_filter('rest_pre_serve_request', function ($value) {
                header('Access-Control-Allow-Origin: ' . esc_url_raw(home_url()));
                header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
                header('Access-Control-Allow-Headers: Content-Type');
                return $value;
            });
        }, 15);
    }

    public function handle_init(WP_REST_Request $req): WP_REST_Response
    {
        $ip = leadguard_wcc_get_client_ip();
        $settings = leadguard_wcc_get_settings();

        if (leadguard_wcc_rate_limited('init', $ip, (int) $settings['rate_limit_init_per_10min'], 10 * MINUTE_IN_SECONDS)) {
            return new WP_REST_Response(['error' => 'rate_limited'], 429);
        }

        leadguard_wcc_ensure_device_cookie();

        $secret = get_option('leadguard_wcc_hmac_secret');
        $issued_at = time();
        $nonce = bin2hex(random_bytes(16));

        $ops = ['add', 'sub', 'mul'];
        $op = $ops[array_rand($ops)];

        if ($op === 'mul') {
            $a = wp_rand(2, 9);
            $b = wp_rand(2, 9);
        } elseif ($op === 'sub') {
            $a = wp_rand(5, 20);
            $b = wp_rand(1, $a - 1);
        } else {
            $a = wp_rand(3, 25);
            $b = wp_rand(3, 20);
        }

        $answer = match ($op) {
            'mul' => $a * $b,
            'sub' => $a - $b,
            default => $a + $b,
        };

        $ua = sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? '');
        $fp_bind = substr(hash('sha256', $ip . '|' . $ua), 0, 32);

        $sig = hash_hmac('sha256', "{$nonce}|{$issued_at}|{$answer}|{$fp_bind}", $secret);
        $token = base64_encode("{$nonce}|{$issued_at}|{$answer}|{$fp_bind}|{$sig}");

        return new WP_REST_Response([
            'token' => $token,
            'a' => $a,
            'b' => $b,
            'op' => $op,
        ], 200);
    }

    public function check_email_endpoint(WP_REST_Request $req): WP_REST_Response
    {
        $settings = leadguard_wcc_get_settings();
        $api_key = trim($settings['email_verify_api_key']);
        $email = sanitize_email((string) $req->get_param('email'));

        $ip = leadguard_wcc_get_client_ip();
        if (leadguard_wcc_rate_limited('check_email', $ip, (int) $settings['rate_limit_checkemail_per_10min'], 10 * MINUTE_IN_SECONDS)) {
            return new WP_REST_Response(['valid' => true, 'reason' => 'rate_limited_fail_open'], 200);
        }

        if (empty($email) || !is_email($email)) {
            return new WP_REST_Response(['valid' => false, 'reason' => 'invalid_format'], 200);
        }
        if (empty($api_key)) {
            return new WP_REST_Response(['valid' => true, 'reason' => 'no_api_key'], 200);
        }

        $url = add_query_arg(
            ['api_key' => $api_key, 'email' => rawurlencode($email)],
            'https://emailvalidation.abstractapi.com/v1/'
        );
        $resp = wp_remote_get($url, ['timeout' => 8]);

        if (is_wp_error($resp)) {
            return new WP_REST_Response(['valid' => true, 'reason' => 'api_error'], 200);
        }

        $body = json_decode(wp_remote_retrieve_body($resp), true);
        $deliverable = strtoupper($body['deliverability'] ?? '');
        $valid_format = (bool) ($body['is_valid_format']['value'] ?? false);
        $is_disposable = (bool) ($body['is_disposable_email']['value'] ?? false);

        if (!$valid_format || $is_disposable || $deliverable === 'UNDELIVERABLE') {
            return new WP_REST_Response(['valid' => false, 'reason' => 'undeliverable'], 200);
        }
        return new WP_REST_Response(['valid' => true, 'reason' => $deliverable], 200);
    }

    public function handle_submission(WP_REST_Request $req): WP_REST_Response
    {
        $settings = leadguard_wcc_get_settings();
        global $wpdb;
        $table = $wpdb->prefix . leadguard_wcc_TABLE;

        $params = $req->get_params();
        
        if (strlen(wp_json_encode($params)) > 15000) {
            return new WP_REST_Response(['success' => false, 'error' => 'payload_too_large'], 413);
        }
        
        $ip = leadguard_wcc_get_client_ip();
        $ua = sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? '');
        $name = sanitize_text_field($params['name'] ?? '');
        $email = sanitize_email($params['email'] ?? '');
        $phone = sanitize_text_field($params['phone'] ?? '');
        $page_url = esc_url_raw($params['page_url'] ?? '');
        $fbclid = sanitize_text_field($params['fbclid'] ?? '');
        $honeypot = $params['website_url'] ?? '';
        $mouse_moves = (int) ($params['mouse_moves'] ?? 0);
        $key_presses = (int) ($params['key_presses'] ?? 0);
        $webdriver = leadguard_wcc_is_truthy($params['webdriver'] ?? false);
        $turnstile_token = $params['cf_turnstile_response'] ?? '';
        $form_token = $params['form_token'] ?? '';
        $decoy_hit = leadguard_wcc_is_truthy($params['decoy_hit'] ?? false);

        $allowed_sources = [
            'meta_ads', 'google_ads', 'tiktok_ads', 'bing_ads', 'twitter_ads',
            'linkedin_ads', 'snapchat_ads', 'paid_utm', 'utm_organic',
            'organic_search', 'organic_social', 'referral', 'direct',
        ];
        $traffic_source_raw = sanitize_key($params['traffic_source'] ?? 'direct');
        $traffic_source = in_array($traffic_source_raw, $allowed_sources, true) ? $traffic_source_raw : 'direct';
        $utm_params = sanitize_text_field($params['utm_params'] ?? '');

        $now = time();
        $score = 0;
        $reasons = [];

        if (!empty($honeypot) || $decoy_hit) {
            $score += 100;
            $reasons[] = $decoy_hit ? 'decoy_form_triggered' : 'honeypot_filled';
        }

        if ($settings['require_turnstile']) {
            if (!leadguard_wcc_verify_turnstile($turnstile_token, $settings['turnstile_secret_key'])) {
                $score += 100;
                $reasons[] = 'turnstile_failed';
            }
        }

        $token_data = leadguard_wcc_verify_token($form_token, $ip, $ua);

        if ($settings['require_math_captcha']) {
            if ($token_data === false) {
                return new WP_REST_Response(['success' => false, 'error' => 'captcha_mismatch'], 200);
            }
            $expected = $token_data['answer'];
            $given = isset($params['captcha_answer']) ? (int) $params['captcha_answer'] : null;
            if ($given !== $expected) {
                return new WP_REST_Response(['success' => false, 'error' => 'captcha_mismatch'], 200);
            }
        }

        if ($token_data === false) {
            $score += 60;
            $reasons[] = 'invalid_or_missing_form_token';
            $form_ts = (int) ($params['form_rendered_at'] ?? 0);
            $fill_time_ms = $form_ts > 0 ? max(0, ($now * 1000) - $form_ts) : null;
        } else {
            $fill_time_ms = ($now - $token_data['issued_at']) * 1000;
            if (!empty($token_data['fp_mismatch'])) {
                $score += 35;
                $reasons[] = 'token_device_mismatch';
            }
        }
        $token_fp_mismatch = !empty($token_data['fp_mismatch']) ? 1 : 0;

        if ($fill_time_ms !== null && $fill_time_ms < (int) $settings['min_fill_time_ms']) {
            $score += 40;
            $reasons[] = "too_fast_{$fill_time_ms}ms";
        }

        if ($mouse_moves === 0 && $key_presses === 0) {
            $score += 30;
            $reasons[] = 'no_interaction_signals';
        }

        if ($webdriver) {
            $score += 60;
            $reasons[] = 'webdriver_detected';
        }

        $bot_agents = [
            'python-requests', 'curl/', 'HeadlessChrome', 'PhantomJS', 'Scrapy', 
            'axios/', 'node-fetch', 'Selenium', 'PuppeteerSharp', 'Playwright',
            'Webhook-Lead-Simulator', 'Moroccan-Webhook-Lead-Simulator'
        ];
        foreach ($bot_agents as $pat) {
            if (stripos($ua, $pat) !== false) {
                $score += 100;
                $reasons[] = 'bot_user_agent';
                break;
            }
        }

        $payload_str = wp_json_encode($params);
        if (stripos($payload_str, 'Adreno (TM) 640') !== false) {
            $score += 50;
            $reasons[] = 'suspicious_gpu_adreno_640';
        }

        if (empty($ua)) {
            $score += 20;
            $reasons[] = 'missing_user_agent';
        }

        if (!empty($email) && leadguard_wcc_is_disposable_email($email)) {
            $score += 25;
            $reasons[] = 'disposable_email';
        }

        if (!empty($phone) && !leadguard_wcc_valid_phone($phone, $settings['phone_country'])) {
            $score += 20;
            $reasons[] = 'invalid_phone_format';
        }

        $blocked_ips = array_unique(array_filter(
            array_map('trim', explode("\n", $settings['blocked_ips'])),
            fn($v) => (bool) filter_var($v, FILTER_VALIDATE_IP)
        ));
        $blocked_emails = array_filter(array_map('trim', explode("\n", $settings['blocked_emails'])));

        if (in_array($ip, $blocked_ips, true)) {
            $score += 100;
            $reasons[] = 'blocked_ip';
        }
        if (!empty($email) && in_array(strtolower($email), array_map('strtolower', $blocked_emails), true)) {
            $score += 100;
            $reasons[] = 'blocked_email';
        }

        $count_ip = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE ip_address=%s AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)",
            $ip
        ));
        if ($count_ip >= (int) $settings['max_per_ip_10min']) {
            $score += 50;
            $reasons[] = "ip_rate_limit_exceeded_{$count_ip}";
        }

        $count_global = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)"
        );
        if ($count_global >= (int) $settings['max_global_per_min']) {
            $score += 40;
            $reasons[] = "global_burst_detected_{$count_global}";
        }

        if (!empty($fbclid)) {
            $dupe = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE fbclid=%s AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
                $fbclid
            ));
            if ($dupe > 0) {
                $score += 40;
                $reasons[] = 'duplicate_fbclid';
            }
        }

        $device_cookie_id = leadguard_wcc_verify_device_cookie();
        $device_fp = sanitize_text_field($params['device_fp'] ?? '');
        $device_key = $device_cookie_id ?: ($device_fp ?: null);

        if ($device_cookie_id === null) {
            $score += 15;
            $reasons[] = 'no_persistent_device_cookie';
        }

        if ($device_key !== null) {
            $bad_count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table}
                 WHERE (device_id = %s OR device_fingerprint = %s)
                   AND status IN ('rejected','suspicious')
                   AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)",
                $device_key,
                $device_key
            ));
            if ($bad_count >= (int) $settings['device_autoblock_threshold']) {
                $score += 150;
                $reasons[] = "device_autoblocked_after_{$bad_count}";
            } elseif ($bad_count >= (int) $settings['device_repeat_threshold']) {
                $score += 70;
                $reasons[] = "repeat_offender_device_{$bad_count}";
            }
        }

        $status = $score >= (int) $settings['reject_threshold'] ? 'rejected'
            : ($score >= (int) $settings['block_threshold'] ? 'suspicious' : 'clean');

        $wpdb->insert($table, [
            'created_at' => current_time('mysql'),
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'ip_address' => $ip,
            'user_agent' => $ua,
            'page_url' => $page_url,
            'fbclid' => $fbclid,
            'fill_time_ms' => $fill_time_ms,
            'mouse_moves' => $mouse_moves,
            'key_presses' => $key_presses,
            'honeypot_triggered' => (!empty($honeypot) || $decoy_hit) ? 1 : 0,
            'webdriver_detected' => $webdriver ? 1 : 0,
            'fraud_score' => $score,
            'status' => $status,
            'reasons' => implode(',', $reasons),
            'raw_payload' => wp_json_encode($params),
            'traffic_source' => $traffic_source,
            'utm_params' => $utm_params,
            'device_id' => $device_cookie_id,
            'device_fingerprint' => $device_fp ?: null,
            'token_fp_mismatch' => $token_fp_mismatch,
        ]);

        leadguard_wcc_push_to_google_sheets($params, $ip, $ua, $fill_time_ms, $score, $status, $reasons, $traffic_source, $utm_params);

        if ($status === 'rejected') {
            return new WP_REST_Response(['success' => true], 200);
        }
        if ($status === 'clean') {
            do_action('leadguard_wcc_clean_lead', compact('name', 'email', 'phone', 'page_url', 'fbclid'));
            leadguard_wcc_maybe_send_meta_lead_event($email, $phone, $ip, $ua);
        } else {
            leadguard_wcc_notify_admin_suspicious($name, $email, $phone, $score, $reasons);
        }

        return new WP_REST_Response(['success' => true, 'status' => $status], 200);
    }
}
