<?php
if (!defined('ABSPATH')) {
    exit;
}

if (isset($_POST['leadguard_wcc_test_webhook']) && check_admin_referer('leadguard_wcc_test_webhook')) {
    $test_result = leadguard_wcc_push_to_google_sheets(
        [
            'name' => 'Lead Guard Test',
            'email' => 'test@example.com',
            'phone' => '0600000000',
            'page_url' => admin_url('admin.php?page=leadguard-wcc-settings'),
            'test_submission' => 'TRUE',
        ],
        leadguard_wcc_get_client_ip(),
        sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
        5000,
        0,
        'clean',
        ['manual_webhook_test'],
        'direct',
        ''
    );

    if (!empty($test_result['success'])) {
        echo '<div class="notice notice-success"><p><strong>Apps Script test succeeded.</strong> HTTP ' . esc_html((string) $test_result['code']) . '. Check the Web Creative Clicks sheet for the test row.</p></div>';
    } else {
        echo '<div class="notice notice-error"><p><strong>Apps Script test failed:</strong> ' . esc_html((string) ($test_result['error'] ?? 'unknown_error')) . ' â€” HTTP ' . esc_html((string) ($test_result['code'] ?? 0)) . '</p><p><code style="white-space:pre-wrap">' . esc_html((string) ($test_result['body'] ?? '')) . '</code></p></div>';
    }
}
if (isset($_POST['leadguard_wcc_settings_save']) && check_admin_referer('leadguard_wcc_settings')) {
    update_option('leadguard_wcc_settings', [
        'block_threshold' => (int) $_POST['block_threshold'],
        'reject_threshold' => (int) $_POST['reject_threshold'],
        'max_per_ip_10min' => (int) $_POST['max_per_ip_10min'],
        'max_global_per_min' => (int) $_POST['max_global_per_min'],
        'min_fill_time_ms' => (int) $_POST['min_fill_time_ms'],
        'require_turnstile' => isset($_POST['require_turnstile']) ? 1 : 0,
        'require_math_captcha' => isset($_POST['require_math_captcha']) ? 1 : 0,
        'turnstile_site_key' => sanitize_text_field($_POST['turnstile_site_key']),
        'turnstile_secret_key' => sanitize_text_field($_POST['turnstile_secret_key']),
        'meta_pixel_id' => sanitize_text_field($_POST['meta_pixel_id']),
        'meta_capi_token' => sanitize_text_field($_POST['meta_capi_token']),
        'notify_email' => sanitize_email($_POST['notify_email']),
        'blocked_ips' => sanitize_textarea_field($_POST['blocked_ips']),
        'blocked_emails' => sanitize_textarea_field($_POST['blocked_emails']),
        'phone_country' => sanitize_text_field($_POST['phone_country']),
        'google_sheets_webhook' => esc_url_raw(trim($_POST['google_sheets_webhook'])),
        'token_max_age_min' => max(1, (int) $_POST['token_max_age_min']),
        'email_verify_api_key' => sanitize_text_field($_POST['email_verify_api_key']),
        'selector_rotation_mode' => sanitize_text_field($_POST['selector_rotation_mode']),
        'selector_interval_min' => max(1, (int) $_POST['selector_interval_min']),
        'thankyou_url' => esc_url_raw(trim($_POST['thankyou_url'] ?? '')),
        'trust_cf_ip_only' => isset($_POST['trust_cf_ip_only']) ? 1 : 0,
        'rate_limit_init_per_10min' => max(0, (int) $_POST['rate_limit_init_per_10min']),
        'rate_limit_checkemail_per_10min' => max(0, (int) $_POST['rate_limit_checkemail_per_10min']),
        'device_repeat_threshold' => max(1, (int) $_POST['device_repeat_threshold']),
        'device_autoblock_threshold' => max(1, (int) $_POST['device_autoblock_threshold']),
        'google_sheets_secret' => sanitize_text_field($_POST['google_sheets_secret'] ?? ''),
    ]);
    echo '<div class="updated"><p>Settings saved.</p></div>';
}
$s = leadguard_wcc_get_settings();
?>
<div class="wrap">
    <h1>Lead Guard â€” Settings</h1>
    <p>Init endpoint: <code><?php echo esc_url(rest_url('leadguard-wcc/v1/init')); ?></code></p>
    <p>Submit endpoint: <code><?php echo esc_url(rest_url('leadguard-wcc/v1/submit')); ?></code></p>

    <?php $last_sheet_result = get_option('leadguard_wcc_last_sheets_result', []); ?>
    <?php if (!empty($last_sheet_result)): ?>
        <div class="notice inline <?php echo !empty($last_sheet_result['success']) ? 'notice-success' : 'notice-error'; ?>">
            <p><strong>Last Apps Script delivery:</strong>
                <?php echo !empty($last_sheet_result['success']) ? 'SUCCESS' : 'FAILED'; ?>
                â€” HTTP
                <?php echo esc_html((string) ($last_sheet_result['code'] ?? 0)); ?>
                â€”
                <?php echo esc_html((string) ($last_sheet_result['checked_at'] ?? '')); ?>
            </p>
            <?php if (!empty($last_sheet_result['error'])): ?>
                <p>Error: <code><?php echo esc_html((string) $last_sheet_result['error']); ?></code></p>
            <?php endif; ?>
            <?php if (!empty($last_sheet_result['body'])): ?>
                <p>Response: <code
                        style="white-space:pre-wrap"><?php echo esc_html((string) $last_sheet_result['body']); ?></code></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <form method="post">
        <?php wp_nonce_field('leadguard_wcc_settings'); ?>
        <table class="form-table">

            <tr>
                <th colspan="2">
                    <h2>Scoring Thresholds</h2>
                </th>
            </tr>
            <tr>
                <th>Suspicious threshold (hold for review)</th>
                <td><input type="number" name="block_threshold" value="<?php echo esc_attr($s['block_threshold']); ?>">
                </td>
            </tr>
            <tr>
                <th>Reject threshold (auto-block)</th>
                <td><input type="number" name="reject_threshold"
                        value="<?php echo esc_attr($s['reject_threshold']); ?>"></td>
            </tr>

            <tr>
                <th colspan="2">
                    <h2>Rate Limits</h2>
                </th>
            </tr>
            <tr>
                <th>Max submissions per IP / 10 min</th>
                <td><input type="number" name="max_per_ip_10min"
                        value="<?php echo esc_attr($s['max_per_ip_10min']); ?>"></td>
            </tr>
            <tr>
                <th>Max site-wide submissions / minute</th>
                <td><input type="number" name="max_global_per_min"
                        value="<?php echo esc_attr($s['max_global_per_min']); ?>"></td>
            </tr>
            <tr>
                <th>Minimum fill time (ms)</th>
                <td>
                    <input type="number" name="min_fill_time_ms"
                        value="<?php echo esc_attr($s['min_fill_time_ms']); ?>">
                    <p class="description">Submissions faster than this are flagged as bots.</p>
                </td>
            </tr>

            <tr>
                <th colspan="2">
                    <h2>CAPTCHA / Verification</h2>
                </th>
            </tr>
            <tr>
                <th>Require math captcha</th>
                <td>
                    <input type="checkbox" name="require_math_captcha" <?php checked($s['require_math_captcha'], 1); ?>>
                    <p class="description">Server-verified signed token â€” cannot be bypassed by always answering "10".
                    </p>
                </td>
            </tr>
            <tr>
                <th>Require Cloudflare Turnstile</th>
                <td><input type="checkbox" name="require_turnstile" <?php checked($s['require_turnstile'], 1); ?>></td>
            </tr>
            <tr>
                <th>Turnstile Site Key</th>
                <td><input type="text" class="regular-text" name="turnstile_site_key"
                        value="<?php echo esc_attr($s['turnstile_site_key']); ?>"></td>
            </tr>
            <tr>
                <th>Turnstile Secret Key</th>
                <td><input type="text" class="regular-text" name="turnstile_secret_key"
                        value="<?php echo esc_attr($s['turnstile_secret_key']); ?>"></td>
            </tr>

            <tr>
                <th colspan="2">
                    <h2>Selector Rotation Mode</h2>
                </th>
            </tr>
            <tr>
                <th>Rotation mode</th>
                <td>
                    <select name="selector_rotation_mode">
                        <option value="pageload" <?php selected($s['selector_rotation_mode'], 'pageload'); ?>>Every
                            page
                            load (strongest)</option>
                        <option value="session" <?php selected($s['selector_rotation_mode'], 'session'); ?>>Per visitor
                            session</option>
                        <option value="interval" <?php selected($s['selector_rotation_mode'], 'interval'); ?>>Every N
                            minutes</option>
                    </select>
                    <p class="description">Controls how often CSS class names and HTML IDs are regenerated.</p>
                </td>
            </tr>
            <tr>
                <th>Rotation interval (minutes)</th>
                <td>
                    <input type="number" name="selector_interval_min"
                        value="<?php echo esc_attr($s['selector_interval_min']); ?>" min="1">
                    <p class="description">Only used when mode = "Every N minutes".</p>
                </td>
            </tr>

            <tr>
                <th colspan="2">
                    <h2>Email Existence Verification</h2>
                </th>
            </tr>
            <tr>
                <th>AbstractAPI Email Validation Key</th>
                <td>
                    <input type="text" class="regular-text" name="email_verify_api_key"
                        value="<?php echo esc_attr($s['email_verify_api_key']); ?>"
                        placeholder="your-abstractapi-key-here">
                    <p class="description">
                        Free key at <a href="https://app.abstractapi.com/api/email-validation/documentation"
                            target="_blank">abstractapi.com</a>.
                        API key is never sent to the browser â€” all checks are server-side proxied.
                    </p>
                </td>
            </tr>

            <tr>
                <th colspan="2">
                    <h2>Notifications &amp; Integrations</h2>
                </th>
            </tr>
            <tr>
                <th>Meta Pixel ID</th>
                <td><input type="text" class="regular-text" name="meta_pixel_id"
                        value="<?php echo esc_attr($s['meta_pixel_id']); ?>"></td>
            </tr>
            <tr>
                <th>Meta CAPI Token</th>
                <td><input type="text" class="regular-text" name="meta_capi_token"
                        value="<?php echo esc_attr($s['meta_capi_token']); ?>"></td>
            </tr>
            <tr>
                <th>Notify email</th>
                <td><input type="email" class="regular-text" name="notify_email"
                        value="<?php echo esc_attr($s['notify_email']); ?>"></td>
            </tr>
            <tr>
                <th>Phone country format</th>
                <td>
                    <select name="phone_country">
                        <option value="MA" <?php selected($s['phone_country'], 'MA'); ?>>Morocco</option>
                        <option value="OTHER" <?php selected($s['phone_country'], 'OTHER'); ?>>Generic (8-15 digits)
                        </option>
                    </select>
                </td>
            </tr>

            <tr>
                <th colspan="2">
                    <h2>Manual Blocklists</h2>
                </th>
            </tr>
            <tr>
                <th>Blocked IPs (one per line)</th>
                <td><textarea name="blocked_ips" rows="5"
                        class="large-text"><?php echo esc_textarea($s['blocked_ips']); ?></textarea></td>
            </tr>
            <tr>
                <th>Blocked emails (one per line)</th>
                <td><textarea name="blocked_emails" rows="5"
                        class="large-text"><?php echo esc_textarea($s['blocked_emails']); ?></textarea></td>
            </tr>

            <tr>
                <th colspan="2">
                    <h2>Google Sheets Logging</h2>
                </th>
            </tr>
            <tr>
                <th>Apps Script Web App URL</th>
                <td>
                    <input type="url" class="large-text" name="google_sheets_webhook"
                        value="<?php echo esc_attr($s['google_sheets_webhook']); ?>"
                        placeholder="https://script.google.com/macros/s/AKfycb.../exec">
                    <p class="description">URL stays server-side â€” never exposed in page HTML.</p>
                </td>
            </tr>

            <tr>
                <th>Google Apps Script Shared Secret</th>
                <td>
                    <input type="text" name="google_sheets_secret"
                        value="<?php echo esc_attr($s['google_sheets_secret']); ?>"
                        style="width:400px;font-family:monospace;">
                    <p class="description">
                        Must exactly match <code>SHARED_SECRET</code> in your Apps Script code.
                        Generate a strong one (e.g. run <code>openssl rand -hex 32</code> in a terminal,
                        or use <a href="https://www.uuidgenerator.net/" target="_blank" rel="noopener">a UUID
                            generator</a>).
                        Without this, anyone who ever obtains your Apps Script URL can post fake leads
                        directly to your sheet, bypassing every fraud check below.
                    </p>
                </td>
            </tr>

            <tr>
                <th colspan="2">
                    <h2>Thank You / Redirect Page</h2>
                </th>
            </tr>
            <tr>
                <th>Thank You Page URL</th>
                <td>
                    <input type="url" class="large-text" name="thankyou_url"
                        value="<?php echo esc_attr($s['thankyou_url']); ?>"
                        placeholder="https://yoursite.com/thank-you/">
                    <p class="description">
                        After a successful form submission, visitors are redirected here.<br>
                        Leave empty to show an inline success message instead.<br>
                        <strong>Note:</strong> the shortcode <code>redirect</code> attribute overrides this setting per
                        form.
                    </p>
                </td>
            </tr>

            <tr>
                <th colspan="2">
                    <h2>Anti-bypass Token</h2>
                </th>
            </tr>
            <tr>
                <th>Token max age (minutes)</th>
                <td>
                    <input type="number" name="token_max_age_min"
                        value="<?php echo esc_attr($s['token_max_age_min']); ?>">
                    <p class="description">Single-use HMAC-signed tokens â€” cannot be reused or forged. Tokens are
                        also bound to the requesting IP+User-Agent; a mismatch at submit time is scored as
                        <code>token_device_mismatch</code>.
                    </p>
                </td>
            </tr>

            <tr>
                <th colspan="2">
                    <h2>Endpoint Rate Limits (anti-automation)</h2>
                </th>
            </tr>
            <tr>
                <th>Trust Cloudflare IP header only</th>
                <td>
                    <input type="checkbox" name="trust_cf_ip_only" <?php checked($s['trust_cf_ip_only'], 1); ?>>
                    <p class="description">If your origin server can be reached directly (not only through
                        Cloudflare), enable this so <code>CF-Connecting-IP</code> is only trusted when the
                        connection actually terminated at a real Cloudflare edge IP. Otherwise that header is
                        just an attacker-supplied string and the per-IP rate limit can be dodged by sending a
                        different fake IP on every request.</p>
                </td>
            </tr>
            <tr>
                <th>Max /init token requests per IP / 10 min</th>
                <td><input type="number" name="rate_limit_init_per_10min"
                        value="<?php echo esc_attr($s['rate_limit_init_per_10min']); ?>">
                    <p class="description">Previously unlimited â€” a script could mint unlimited signed tokens
                        per second.</p>
                </td>
            </tr>
            <tr>
                <th>Max /check-email requests per IP / 10 min</th>
                <td><input type="number" name="rate_limit_checkemail_per_10min"
                        value="<?php echo esc_attr($s['rate_limit_checkemail_per_10min']); ?>">
                    <p class="description">Protects your AbstractAPI quota from being drained by repeated
                        calls.</p>
                </td>
            </tr>

            <tr>
                <th colspan="2">
                    <h2>Device / Person Fingerprinting</h2>
                </th>
            </tr>
            <tr>
                <th>Repeat-offender threshold</th>
                <td><input type="number" name="device_repeat_threshold"
                        value="<?php echo esc_attr($s['device_repeat_threshold']); ?>">
                    <p class="description">Number of prior rejected/suspicious submissions from the same
                        device (signed cookie or browser fingerprint) within 7 days before future submissions
                        get a heavy +70 score penalty (<code>repeat_offender_device_N</code>).</p>
                </td>
            </tr>
            <tr>
                <th>Auto-block threshold</th>
                <td><input type="number" name="device_autoblock_threshold"
                        value="<?php echo esc_attr($s['device_autoblock_threshold']); ?>">
                    <p class="description">Beyond this many prior bad submissions from the same device, all
                        future submissions from it are effectively auto-blocked (+150 score,
                        <code>device_autoblocked_after_N</code>).
                    </p>
                </td>
            </tr>

        </table>
        <?php submit_button('Save Settings', 'primary', 'leadguard_wcc_settings_save'); ?>
    </form>

    <hr>
    <h2>Apps Script connection test</h2>
    <p>This sends one test row through WordPress and shows the real Google response instead of silently pretending it
        worked.</p>
    <form method="post">
        <?php wp_nonce_field('leadguard_wcc_test_webhook'); ?>
        <?php submit_button('Test Apps Script Now', 'secondary', 'leadguard_wcc_test_webhook'); ?>
    </form>
</div>
