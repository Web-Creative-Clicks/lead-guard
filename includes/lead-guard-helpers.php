<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Generate one random token: letter prefix + N hex chars.
 */
function leadguard_wcc_rand_token(string $prefix = 'a', int $length = 8): string
{
    return $prefix . bin2hex(random_bytes((int) ceil($length / 2)));
}

/**
 * Build a complete selector map for one form instance.
 */
function leadguard_wcc_generate_selector_map(string $uid): array
{
    $map['id_wrap'] = 'w_' . leadguard_wcc_rand_token('x', 8) . '_' . $uid;
    $map['id_form'] = 'f_' . leadguard_wcc_rand_token('y', 8) . '_' . $uid;
    $map['id_decoy'] = 'd_' . leadguard_wcc_rand_token('z', 8) . '_' . $uid;

    $map['cls_wrap'] = leadguard_wcc_rand_token('w', 9);
    $map['cls_form'] = leadguard_wcc_rand_token('f', 9);
    $map['cls_title'] = leadguard_wcc_rand_token('t', 9);
    $map['cls_field'] = leadguard_wcc_rand_token('d', 9);
    $map['cls_label'] = leadguard_wcc_rand_token('l', 9);
    $map['cls_input'] = leadguard_wcc_rand_token('i', 9);
    $map['cls_input_err'] = leadguard_wcc_rand_token('e', 9);
    $map['cls_btn'] = leadguard_wcc_rand_token('b', 9);
    $map['cls_error_box'] = leadguard_wcc_rand_token('r', 9);
    $map['cls_success'] = leadguard_wcc_rand_token('s', 9);
    $map['cls_captcha_row'] = leadguard_wcc_rand_token('c', 9);
    $map['cls_captcha_q'] = leadguard_wcc_rand_token('q', 9);
    $map['cls_captcha_a'] = leadguard_wcc_rand_token('a', 9);

    return $map;
}

function leadguard_wcc_default_settings(): array
{
    return [
        'block_threshold' => 50,
        'reject_threshold' => 80,
        'max_per_ip_10min' => 3,
        'max_global_per_min' => 15,
        'min_fill_time_ms' => 3000,
        'require_turnstile' => 0,
        'require_math_captcha' => 1,
        'turnstile_site_key' => '',
        'turnstile_secret_key' => '',
        'meta_pixel_id' => '',
        'meta_capi_token' => '',
        'notify_email' => get_option('admin_email'),
        'blocked_ips' => '',
        'blocked_emails' => '',
        'phone_country' => 'MA',
        'google_sheets_webhook' => '',
        'token_max_age_min' => 30,
        'email_verify_api_key' => '',
        'selector_rotation_mode' => 'pageload',
        'selector_interval_min' => 30,
        'thankyou_url' => '',
        'google_sheets_secret' => '',
        'trust_cf_ip_only' => 0,
        'rate_limit_init_per_10min' => 20,
        'rate_limit_checkemail_per_10min' => 20,
        'device_repeat_threshold' => 2,
        'device_autoblock_threshold' => 5,
    ];
}

function leadguard_wcc_get_settings(): array
{
    return wp_parse_args(
        get_option('leadguard_wcc_settings', []),
        leadguard_wcc_default_settings()
    );
}

function leadguard_wcc_verify_token(string $token, string $current_ip = '', string $current_ua = ''): array|false
{
    $settings = leadguard_wcc_get_settings();
    $secret = get_option('leadguard_wcc_hmac_secret');
    $decoded = base64_decode($token, true);
    if (!$decoded) return false;

    $parts = explode('|', $decoded);
    $fp_bind = null;

    if (count($parts) === 5 && strlen($parts[2]) > 6) {
        [$nonce, $issued_at, $answer, $fp_bind, $sig] = $parts;
        $expected = hash_hmac('sha256', "{$nonce}|{$issued_at}|{$answer}|{$fp_bind}", $secret);
    } elseif (count($parts) === 5) {
        [$nonce, $issued_at, $a, $b, $sig] = $parts;
        $answer = (int) $a + (int) $b;
        $expected = hash_hmac('sha256', "{$nonce}|{$issued_at}|{$a}|{$b}", $secret);
    } elseif (count($parts) === 4) {
        [$nonce, $issued_at, $answer, $sig] = $parts;
        $expected = hash_hmac('sha256', "{$nonce}|{$issued_at}|{$answer}", $secret);
    } else {
        return false;
    }

    if (!hash_equals($expected, $sig)) return false;

    $issued_at = (int) $issued_at;
    $max_age = (int) $settings['token_max_age_min'] * MINUTE_IN_SECONDS;
    if ((time() - $issued_at) > $max_age) return false;

    $used_key = 'leadguard_wcc_used_' . md5($nonce);
    if (get_transient($used_key)) return false;
    set_transient($used_key, 1, $max_age + 60);

    $fp_mismatch = false;
    if ($fp_bind !== null && $current_ip !== '') {
        $expected_fp = substr(hash('sha256', $current_ip . '|' . $current_ua), 0, 32);
        $fp_mismatch = !hash_equals($expected_fp, $fp_bind);
    }

    return ['issued_at' => $issued_at, 'answer' => (int) $answer, 'fp_mismatch' => $fp_mismatch];
}

function leadguard_wcc_is_truthy($v): bool
{
    if (is_bool($v)) return $v;
    return in_array(strtolower(trim((string) $v)), ['true', '1', 'yes', 'on'], true);
}

function leadguard_wcc_rate_limited(string $bucket, string $key, int $max, int $window_seconds): bool
{
    if ($max <= 0) return false;
    $tkey = 'lgs_rl_' . $bucket . '_' . md5($key);
    $count = (int) get_transient($tkey);
    if ($count >= $max) {
        return true;
    }
    if ($count === 0) {
        set_transient($tkey, 1, $window_seconds);
    } else {
        set_transient($tkey, $count + 1, $window_seconds);
    }
    return false;
}

function leadguard_wcc_cloudflare_ranges(): array
{
    $ranges = [
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
        '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
        '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
        '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22', '2400:cb00::/32',
        '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32',
        '2a06:98c0::/29', '2c0f:f248::/32',
    ];
    return apply_filters('leadguard_wcc_cloudflare_ranges', $ranges);
}

function leadguard_wcc_ip_in_cidr(string $ip, string $cidr): bool
{
    if (strpos($cidr, '/') === false) return $ip === $cidr;
    [$subnet, $bits] = explode('/', $cidr);
    $bits = (int) $bits;

    if (strpos($ip, ':') !== false || strpos($subnet, ':') !== false) {
        $ip_bin = @inet_pton($ip);
        $subnet_bin = @inet_pton($subnet);
        if ($ip_bin === false || $subnet_bin === false) return false;
        $bytes = intdiv($bits, 8);
        $remainder = $bits % 8;
        if ($bytes > 0 && substr($ip_bin, 0, $bytes) !== substr($subnet_bin, 0, $bytes)) return false;
        if ($remainder === 0) return true;
        $mask = chr((0xFF << (8 - $remainder)) & 0xFF);
        return (substr($ip_bin, $bytes, 1) & $mask) === (substr($subnet_bin, $bytes, 1) & $mask);
    }

    $ip_long = ip2long($ip);
    $subnet_long = ip2long($subnet);
    if ($ip_long === false || $subnet_long === false) return false;
    $mask = -1 << (32 - $bits);
    return ($ip_long & $mask) === ($subnet_long & $mask);
}

function leadguard_wcc_request_is_from_cloudflare(): bool
{
    $remote = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
    if (!$remote) return false;
    foreach (leadguard_wcc_cloudflare_ranges() as $cidr) {
        if (leadguard_wcc_ip_in_cidr($remote, $cidr)) return true;
    }
    return false;
}

function leadguard_wcc_get_client_ip(): string
{
    $settings = leadguard_wcc_get_settings();
    if (!empty($settings['trust_cf_ip_only']) && !leadguard_wcc_request_is_from_cloudflare()) {
        $remote = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '0.0.0.0';
        return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '0.0.0.0';
    }

    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $k) {
        if (!empty($_SERVER[$k])) {
            $raw_server_val = sanitize_text_field(wp_unslash($_SERVER[$k]));
            $ip = trim(explode(',', $raw_server_val)[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return '0.0.0.0';
}

function leadguard_wcc_sign_device_id(string $id, string $secret): string
{
    return $id . '.' . substr(hash_hmac('sha256', $id, $secret), 0, 32);
}

function leadguard_wcc_verify_device_cookie(): ?string
{
    $secret = get_option('leadguard_wcc_hmac_secret');
    $raw = isset($_COOKIE[leadguard_wcc_DEVICE_COOKIE]) ? sanitize_text_field(wp_unslash($_COOKIE[leadguard_wcc_DEVICE_COOKIE])) : '';
    if (!$raw || strpos($raw, '.') === false) return null;
    [$id, $sig] = array_pad(explode('.', $raw, 2), 2, '');
    if (!$id || !$sig) return null;
    $expected = substr(hash_hmac('sha256', $id, $secret), 0, 32);
    if (!hash_equals($expected, $sig)) return null;
    return $id;
}

function leadguard_wcc_ensure_device_cookie(): string
{
    $existing = leadguard_wcc_verify_device_cookie();
    if ($existing !== null) return $existing;

    $secret = get_option('leadguard_wcc_hmac_secret');
    $new_id = bin2hex(random_bytes(16));
    $cookie_value = leadguard_wcc_sign_device_id($new_id, $secret);

    // 1-year lifetime
    setcookie(leadguard_wcc_DEVICE_COOKIE, $cookie_value, [
        'expires' => time() + 31536000,
        'path' => COOKIEPATH ?: '/',
        'domain' => COOKIE_DOMAIN ?: '',
        'secure' => is_ssl(),
        'httponly' => false,
        'samesite' => 'Lax',
    ]);

    return $new_id;
}

function leadguard_wcc_verify_turnstile(string $token, string $secret): bool
{
    if (empty($token) || empty($secret)) return false;
    // phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent
    $resp = wp_remote_post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
        'body' => ['secret' => $secret, 'response' => $token, 'remoteip' => leadguard_wcc_get_client_ip()],
        'timeout' => 8,
    ]);
    if (is_wp_error($resp)) return false;
    $body = json_decode(wp_remote_retrieve_body($resp), true);
    return !empty($body['success']);
}

function leadguard_wcc_is_disposable_email(string $email): bool
{
    $domain = strtolower(substr(strrchr($email, '@'), 1));
    $disposable = [
        'mailinator.com', 'tempmail.com', 'guerrillamail.com', '10minutemail.com',
        'throwawaymail.com', 'yopmail.com', 'trashmail.com', 'fakeinbox.com',
        'getnada.com', 'sharklasers.com', 'temp-mail.org', 'discard.email',
    ];
    return in_array($domain, $disposable, true);
}

function leadguard_wcc_valid_phone(string $phone, string $country = 'MA'): bool
{
    $digits = preg_replace('/\D/', '', $phone);
    if ($country === 'MA') {
        return (bool) preg_match('/^(0[5-7][0-9]{8}|212[5-7][0-9]{8})$/', $digits);
    }
    return strlen($digits) >= 8 && strlen($digits) <= 15;
}

function leadguard_wcc_notify_admin_suspicious($name, $email, $phone, $score, $reasons): void
{
    $settings = leadguard_wcc_get_settings();
    if (empty($settings['notify_email'])) return;
    wp_mail(
        $settings['notify_email'],
        "Lead Guard: suspicious submission flagged (score {$score})",
        "A form submission was flagged as suspicious.\n\nName: {$name}\nEmail: {$email}\nPhone: {$phone}\nScore: {$score}\nReasons: " . implode(', ', $reasons)
    );
}

function leadguard_wcc_push_to_google_sheets($params, $ip, $ua, $fill_time_ms, $score, $status, $reasons, $traffic_source = 'direct', $utm_params = ''): array
{
    $settings = leadguard_wcc_get_settings();
    $url = trim((string) ($settings['google_sheets_webhook'] ?? ''));

    if ($url === '' || !wp_http_validate_url($url)) {
        $result = [
            'success' => false,
            'error' => $url === '' ? 'missing_webhook_url' : 'invalid_webhook_url',
            'code' => 0,
            'body' => '',
            'checked_at' => current_time('mysql'),
        ];
        update_option('leadguard_wcc_last_sheets_result', $result, false);
        return $result;
    }

    $payload = array_merge($params, [
        'secret' => $settings['google_sheets_secret'],
        'submitted_at' => current_time('mysql'),
        'ip_address' => $ip,
        'user_agent' => $ua,
        'fill_time_ms' => $fill_time_ms,
        'fraud_score' => $score,
        'fraud_status' => $status,
        'fraud_reasons' => implode(',', $reasons),
        'traffic_source' => $traffic_source,
        'utm_params' => $utm_params,
    ]);

    $response = wp_remote_post($url, [
        'method' => 'POST',
        'headers' => [
            'Content-Type' => 'application/json; charset=utf-8',
            'Accept' => 'application/json, text/plain, */*',
            'User-Agent' => 'WordPress/Lead-Guard-WCC-' . leadguard_wcc_VERSION,
        ],
        'body' => wp_json_encode($payload),
        'timeout' => 8,
        'redirection' => 10,
        'blocking' => true,
        'data_format' => 'body',
        'sslverify' => true,
    ]);

    if (is_wp_error($response)) {
        $result = [
            'success' => false,
            'error' => $response->get_error_code() . ': ' . $response->get_error_message(),
            'code' => 0,
            'body' => '',
            'checked_at' => current_time('mysql'),
        ];
    } else {
        $code = (int) wp_remote_retrieve_response_code($response);
        $body = trim((string) wp_remote_retrieve_body($response));
        $result = [
            'success' => $code >= 200 && $code < 300,
            'error' => ($code >= 200 && $code < 300) ? '' : 'HTTP_' . $code,
            'code' => $code,
            'body' => substr(wp_strip_all_tags($body), 0, 1000),
            'checked_at' => current_time('mysql'),
        ];
    }

    update_option('leadguard_wcc_last_sheets_result', $result, false);

    if (!$result['success']) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('[Lead Guard] Google Sheets delivery failed: ' . wp_json_encode($result));
        }
    }

    return $result;
}

function leadguard_wcc_maybe_send_meta_lead_event($email, $phone, $ip, $ua): void
{
    $settings = leadguard_wcc_get_settings();
    if (empty($settings['meta_pixel_id']) || empty($settings['meta_capi_token'])) return;

    $user_data = ['client_ip_address' => $ip, 'client_user_agent' => $ua];
    if (!empty($email)) $user_data['em'] = [hash('sha256', strtolower(trim($email)))];
    if (!empty($phone)) $user_data['ph'] = [hash('sha256', preg_replace('/\D/', '', $phone))];

    wp_remote_post("https://graph.facebook.com/v19.0/{$settings['meta_pixel_id']}/events", [
        'body' => wp_json_encode(['data' => [['event_name' => 'Lead', 'event_time' => time(), 'action_source' => 'website', 'user_data' => $user_data]], 'access_token' => $settings['meta_capi_token']]),
        'headers' => ['Content-Type' => 'application/json'],
        'timeout' => 10,
    ]);
}
