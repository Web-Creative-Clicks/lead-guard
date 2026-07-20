<?php
if (!defined('ABSPATH')) {
    exit;
}

class Lead_Guard_Public
{
    public function register_shortcodes(): void
    {
        add_shortcode('leadguard_said_form', [$this, 'render_shortcode']);
    }

    public function render_shortcode($atts): string
    {
        static $instance = 0;
        static $turnstile_js = false;
        $instance++;

        $atts = shortcode_atts([
            'title' => 'سجل معلوماتك',
            'button_text' => 'سجل دابا',
            'redirect' => '',
        ], $atts, 'leadguard_said_form');

        $settings = leadguard_said_get_settings();
        $uid = 'u' . $instance . bin2hex(random_bytes(4));
        $init_url = esc_url(rest_url('leadguard-said/v1/init'));
        $submit_url = esc_url(rest_url('leadguard-said/v1/submit'));
        $check_email_url = esc_url(rest_url('leadguard-said/v1/check-email'));
        $site_key = esc_attr($settings['turnstile_site_key']);
        $require_turnstile = !empty($settings['require_turnstile']);
        $require_math_captcha = !empty($settings['require_math_captcha']);
        $email_verify_enabled = !empty($settings['email_verify_api_key']);
        // Priority: shortcode attribute → plugin global setting → '' (show inline message)
        $redirect = esc_url_raw(
            !empty($atts['redirect'])
            ? $atts['redirect']
            : ($settings['thankyou_url'] ?? '')
        );

        /* ------------------------------------------------------------------
         * SELECTOR MAP — new cryptographically random names every page load
         * ------------------------------------------------------------------ */
        $mode = $settings['selector_rotation_mode'] ?? 'pageload';

        if ($mode === 'session') {
            // Same names for the full visitor PHP session
            if (!session_id()) {
                @session_start();
            }
            $map_key = 'leadguard_said_smap_' . $uid;
            if (empty($_SESSION[$map_key])) {
                $_SESSION[$map_key] = leadguard_said_generate_selector_map($uid);
            }
            $m = $_SESSION[$map_key];

        } elseif ($mode === 'interval') {
            // New names every N minutes (stored in WordPress transient)
            $interval = max(1, (int) ($settings['selector_interval_min'] ?? 30));
            $tk = 'leadguard_said_imap_' . $uid;
            $m = get_transient($tk);
            if (!$m) {
                $m = leadguard_said_generate_selector_map($uid);
                set_transient($tk, $m, $interval * MINUTE_IN_SECONDS);
            }

        } else {
            // 'pageload' — fresh map every render (default, strongest)
            $m = leadguard_said_generate_selector_map($uid);
        }

        // Shorthand aliases
        [
            'id_wrap' => $id_wrap,
            'id_form' => $id_form,
            'id_decoy' => $id_decoy,
            'cls_wrap' => $cls_wrap,
            'cls_form' => $cls_form,
            'cls_title' => $cls_title,
            'cls_field' => $cls_field,
            'cls_label' => $cls_label,
            'cls_input' => $cls_input,
            'cls_input_err' => $cls_input_err,
            'cls_btn' => $cls_btn,
            'cls_error_box' => $cls_error_box,
            'cls_success' => $cls_success,
            'cls_captcha_row' => $cls_captcha_row,
            'cls_captcha_q' => $cls_captcha_q,
            'cls_captcha_a' => $cls_captcha_a,
        ] = $m;

        ob_start();
        /* ================================================================== */
        ?>
        <div class="<?php echo esc_attr($cls_wrap); ?>" id="<?php echo esc_attr($id_wrap); ?>">

            <!-- ============================================================
                 SCOPED CSS  — every selector is the random class for this render
                 ============================================================ -->
            <style>
                /* Reset */
                /* Google Font — Tajawal (Bold) */
                @import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700;800;900&display=swap');

                #<?php echo esc_attr($id_wrap); ?>* {
                    box-sizing: border-box;
                }

                /* Wrapper — keep the entire shortcode area black without changing form logic */
                .<?php echo esc_attr($cls_wrap); ?> {
                    position: relative;
                    width: 100%;
                    background: #000;

                    /* Paint black across the full viewport width while keeping
                                                   the shortcode's original layout and dimensions unchanged. */
                    box-shadow: 0 0 0 100vmax #000;
                    clip-path: inset(0 -100vmax);
                }

                /* Form card — pure black like the reference */
                #<?php echo esc_attr($id_form); ?> {
                    max-width: 500px;
                    margin: 0 auto;
                    background: #000;
                    border-radius: 0;
                    padding: 36px 28px 32px;
                    font-family: 'Tajawal', system-ui, sans-serif;
                    direction: rtl;
                }

                /* Title */
                #<?php echo esc_attr($id_form); ?>.<?php echo esc_attr($cls_title); ?> {
                    color: #fff;
                    font-size: 32px;
                    font-weight: 900;
                    text-align: center;
                    margin-bottom: 28px;
                    letter-spacing: -0.5px;
                }

                /* Field wrapper */
                #<?php echo esc_attr($id_form); ?>.<?php echo esc_attr($cls_field); ?> {
                    margin-bottom: 18px;
                }

                /* Label */
                #<?php echo esc_attr($id_form); ?>.<?php echo esc_attr($cls_field); ?>.<?php echo esc_attr($cls_label); ?> {
                    display: block;
                    color: #fff;
                    font-size: 15px;
                    margin-bottom: 7px;
                    font-weight: 700;
                    text-align: right;
                }

                /* Input — white background, black text, like the reference */
                #<?php echo esc_attr($id_form); ?>.<?php echo esc_attr($cls_input); ?> {
                    width: 100%;
                    padding: 15px 16px;
                    border-radius: 10px;
                    border: 2px solid #fff;
                    background: #fff;
                    color: #111;
                    font-size: 15px;
                    font-family: 'Tajawal', inherit;
                    font-weight: 500;
                    transition: border-color .2s, box-shadow .2s;
                    text-align: right;
                }

                #<?php echo esc_attr($id_form); ?>.<?php echo esc_attr($cls_input); ?>:focus {
                    outline: none;
                    border-color: #FFD400;
                    box-shadow: 0 0 0 3px rgba(255, 212, 0, .25);
                }

                #<?php echo esc_attr($id_form); ?>.<?php echo esc_attr($cls_input); ?>::placeholder {
                    color: #999;
                    font-weight: 400;
                }

                #<?php echo esc_attr($id_form); ?>.<?php echo esc_attr($cls_input_err); ?> {
                    border-color: #ff4444 !important;
                    box-shadow: 0 0 0 3px rgba(255, 68, 68, .2) !important;
                }

                /* Button — yellow (#FFD400) with black bold text + glow */
                #<?php echo esc_attr($id_form); ?>.<?php echo esc_attr($cls_btn); ?> {
                    width: 100%;
                    padding: 16px;
                    margin-top: 14px;
                    border: none;
                    border-radius: 10px;
                    background: #FFD400;
                    color: #000;
                    font-size: 18px;
                    font-weight: 900;
                    cursor: pointer;
                    font-family: 'Tajawal', inherit;
                    letter-spacing: 0.3px;
                    transition: transform .18s, box-shadow .18s, background .18s;
                    box-shadow: 0 4px 24px rgba(255, 212, 0, .35);
                }

                #<?php echo esc_attr($id_form); ?>.<?php echo esc_attr($cls_btn); ?>:hover {
                    transform: translateY(-2px);
                    background: #ffe033;
                    box-shadow: 0 8px 32px rgba(255, 212, 0, .55);
                }

                #<?php echo esc_attr($id_form); ?>.<?php echo esc_attr($cls_btn); ?>:disabled {
                    opacity: .55;
                    cursor: not-allowed;
                    transform: none;
                    box-shadow: none;
                }

                /* Error / success text */
                #<?php echo esc_attr($id_form); ?>.<?php echo esc_attr($cls_error_box); ?> {
                    color: #ff4444;
                    font-size: 13px;
                    font-weight: 700;
                    text-align: center;
                    margin-top: 10px;
                    min-height: 18px;
                }

                .<?php echo esc_attr($cls_success); ?> {
                    color: #FFD400;
                    font-size: 18px;
                    text-align: center;
                    padding: 24px 0;
                    font-weight: 800;
                    font-family: 'Tajawal', system-ui, sans-serif;
                    direction: rtl;
                }

                /* Math captcha row */
                #<?php echo esc_attr($id_form); ?>.<?php echo esc_attr($cls_captcha_row); ?> {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    margin-bottom: 6px;
                }

                #<?php echo esc_attr($id_form); ?>.<?php echo esc_attr($cls_captcha_q); ?> {
                    color: #fff;
                    font-size: 15px;
                    font-weight: 700;
                    white-space: nowrap;
                }

                #<?php echo esc_attr($id_form); ?>.<?php echo esc_attr($cls_captcha_a); ?> {
                    width: 80px;
                    padding: 10px 12px;
                    border-radius: 10px;
                    border: 2px solid #fff;
                    background: #fff;
                    color: #111;
                    font-size: 15px;
                    text-align: center;
                    font-family: 'Tajawal', inherit;
                }
            </style>

            <div id="<?php echo esc_attr($id_decoy); ?>"
                style="position:fixed;left:-99999px;top:-99999px;width:1px;height:1px;overflow:hidden;opacity:0;pointer-events:none;"
                aria-hidden="true">
                <form class="contact-form lead-form wpcf7-form elementor-form registration-form" method="post" action="#">
                    <input type="text" name="name" class="form-control input-field name-field" tabindex="-1" autocomplete="off">
                    <input type="email" name="email" class="form-control input-field email-field" tabindex="-1"
                        autocomplete="off">
                    <input type="tel" name="phone" class="form-control input-field phone-field" tabindex="-1"
                        autocomplete="off">
                    <input type="text" name="first_name" class="form-control input-field first-name" tabindex="-1"
                        autocomplete="off">
                    <input type="text" name="last_name" class="form-control input-field last-name" tabindex="-1"
                        autocomplete="off">
                    <input type="text" name="message" class="form-control input-field" tabindex="-1" autocomplete="off">
                    <input type="text" name="website_url" class="form-control" tabindex="-1" autocomplete="off">
                    <button type="submit" class="btn btn-primary submit-button wpcf7-submit">Submit</button>
                </form>
            </div>

            <form id="<?php echo esc_attr($id_form); ?>" class="<?php echo esc_attr($cls_form); ?>" novalidate>

                <h2 class="<?php echo esc_attr($cls_title); ?>">
                    <?php echo esc_html($atts['title']); ?>
                </h2>

                <!-- Name -->
                <div class="<?php echo esc_attr($cls_field); ?>">
                    <label class="<?php echo esc_attr($cls_label); ?>">الاسم الكامل</label>
                    <input class="<?php echo esc_attr($cls_input); ?>" data-field="name" type="text" placeholder="الاسم الكامل"
                        autocomplete="name" required>
                </div>

                <!-- Email -->
                <div class="<?php echo esc_attr($cls_field); ?>">
                    <label class="<?php echo esc_attr($cls_label); ?>">البريد الإلكتروني</label>
                    <input class="<?php echo esc_attr($cls_input); ?>" data-field="email" type="email"
                        placeholder="example@email.com" autocomplete="email" required>
                </div>

                <!-- Phone -->
                <div class="<?php echo esc_attr($cls_field); ?>">
                    <label class="<?php echo esc_attr($cls_label); ?>">رقم الهاتف</label>
                    <input class="<?php echo esc_attr($cls_input); ?>" data-field="phone" type="tel" inputmode="numeric"
                        pattern="[0-9]+" placeholder="0612345678" autocomplete="tel" required>
                </div>

                <div style="position:absolute;left:-99999px;top:-99999px;height:1px;width:1px;overflow:hidden;opacity:0;pointer-events:none;"
                    aria-hidden="true">
                    <input type="text" data-field="hp" autocomplete="off" tabindex="-1">
                </div>

                <!-- Math captcha -->
                <?php if ($require_math_captcha): ?>
                    <div class="<?php echo esc_attr($cls_captcha_row); ?>">
                        <span class="<?php echo esc_attr($cls_captcha_q); ?>" data-field="captcha-question">...</span>
                        <input class="<?php echo esc_attr($cls_captcha_a); ?>" data-field="captcha-answer" type="text"
                            inputmode="numeric" placeholder="؟" autocomplete="off">
                    </div>
                <?php endif; ?>

                <!-- Turnstile widget -->
                <?php if ($require_turnstile && $site_key): ?>
                    <div class="cf-turnstile" data-sitekey="<?php echo $site_key; ?>" data-theme="dark"
                        style="display:flex;justify-content:center;margin-top:8px;"></div>
                <?php endif; ?>

                <p class="<?php echo esc_attr($cls_error_box); ?>" aria-live="polite"></p>
                <button type="submit" class="<?php echo esc_attr($cls_btn); ?>">
                    <?php echo esc_html($atts['button_text']); ?>
                </button>
            </form>
        </div><!-- /#<?php echo esc_attr($id_wrap); ?> -->

        <?php if ($require_turnstile && $site_key && !$turnstile_js): ?>
            <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
            <?php $turnstile_js = true; ?>
        <?php endif; ?>

        <script>
            (function () {
                'use strict';

                const WRAP_ID = '<?php echo esc_js($id_wrap); ?>';
                const FORM_ID = '<?php echo esc_js($id_form); ?>';
                const DECOY_ID = '<?php echo esc_js($id_decoy); ?>';
                const ERROR_CLS = '<?php echo esc_js($cls_error_box); ?>';
                const BTN_CLS = '<?php echo esc_js($cls_btn); ?>';
                const INPUT_CLS = '<?php echo esc_js($cls_input); ?>';
                const INPUT_ERR_CLS = '<?php echo esc_js($cls_input_err); ?>';
                const SUCCESS_CLS = '<?php echo esc_js($cls_success); ?>';
                const CAPTCHA_Q_CLS = '<?php echo esc_js($cls_captcha_q); ?>';

                const INIT_URL = '<?php echo esc_js($init_url); ?>';
                const SUBMIT_URL = '<?php echo esc_js($submit_url); ?>';
                const CHECK_EMAIL_URL = '<?php echo esc_js($check_email_url); ?>';
                const REDIRECT_URL = '<?php echo esc_js($redirect); ?>';
                const BTN_LABEL = '<?php echo esc_js($atts['button_text']); ?>';
                const REQUIRE_TURNSTILE = <?php echo $require_turnstile ? 'true' : 'false'; ?>;
                const REQUIRE_MATH_CAPTCHA = <?php echo $require_math_captcha ? 'true' : 'false'; ?>;
                const EMAIL_VERIFY = <?php echo $email_verify_enabled ? 'true' : 'false'; ?>;

                const wrap = document.getElementById(WRAP_ID);
                const form = document.getElementById(FORM_ID);
                const decoy = document.getElementById(DECOY_ID);
                const errorBox = form.querySelector('.' + ERROR_CLS);
                const btn = form.querySelector('.' + BTN_CLS);

                const $ = (sel) => form.querySelector(sel);

                const nameEl = $('[data-field="name"]');
                const emailEl = $('[data-field="email"]');
                const phoneEl = $('[data-field="phone"]');
                const hpEl = $('[data-field="hp"]');
                const captchaQ = $('[data-field="captcha-question"]');
                const captchaA = $('[data-field="captcha-answer"]');

                const PAGE_LOAD_TIME = Date.now();
                let serverToken = '';
                let mouseMoves = 0, clicks = 0, keyPresses = 0, touches = 0, scrolls = 0;
                let firstInteractionAt = null;
                let tName = null, tEmail = null, tPhone = null;
                let hpTriggered = false, hpEvents = 0;
                let decoyHit = false;
                let emailCache = {};

                async function fetchNewToken() {
                    try {
                        const d = await fetch(INIT_URL, { credentials: 'same-origin' }).then(r => r.json());
                        serverToken = d.token || '';
                        if (REQUIRE_MATH_CAPTCHA && captchaQ) {
                            const opSymbol = d.op === 'mul' ? '×' : d.op === 'sub' ? '−' : '+';
                            captchaQ.innerHTML = 'كم يساوي <bdi>' + d.a + ' ' + opSymbol + ' ' + d.b + '</bdi> ؟';
                        }
                    } catch (e) { serverToken = ''; }
                }
                fetchNewToken();

                if (decoy) {
                    decoy.querySelectorAll('input, select, textarea, button').forEach(el => {
                        ['focus', 'input', 'change', 'click', 'mousedown'].forEach(ev => {
                            el.addEventListener(ev, () => { decoyHit = true; }, { passive: true });
                        });
                    });
                }

                function markFirst() {
                    if (firstInteractionAt === null) firstInteractionAt = Date.now() - PAGE_LOAD_TIME;
                }
                document.addEventListener('mousemove', () => { mouseMoves++; markFirst(); }, { passive: true });
                document.addEventListener('click', () => { clicks++; markFirst(); }, { passive: true });
                document.addEventListener('keydown', () => { keyPresses++; markFirst(); }, { passive: true });
                document.addEventListener('touchstart', () => { touches++; markFirst(); }, { passive: true });
                document.addEventListener('scroll', () => { scrolls++; }, { passive: true });

                nameEl?.addEventListener('focus', () => { if (tName === null) tName = Date.now() - PAGE_LOAD_TIME; markFirst(); });
                emailEl?.addEventListener('focus', () => { if (tEmail === null) tEmail = Date.now() - PAGE_LOAD_TIME; markFirst(); });
                phoneEl?.addEventListener('focus', () => { if (tPhone === null) tPhone = Date.now() - PAGE_LOAD_TIME; markFirst(); });
                if (hpEl) {
                    ['focus', 'input'].forEach(ev => hpEl.addEventListener(ev, () => { hpTriggered = true; hpEvents++; }));
                }

                let clientIp = '';
                fetch('https://api.ipify.org?format=json')
                    .then(r => r.json()).then(d => { clientIp = d.ip || ''; }).catch(() => { });

                let webglVendor = '', webglRenderer = '';
                try {
                    const canvas = document.createElement('canvas');
                    const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
                    if (gl) {
                        const ext = gl.getExtension('WEBGL_debug_renderer_info');
                        if (ext) {
                            webglVendor = gl.getParameter(ext.UNMASKED_VENDOR_WEBGL) || '';
                            webglRenderer = gl.getParameter(ext.UNMASKED_RENDERER_WEBGL) || '';
                        }
                    }
                } catch (_) { }
                const gpuDeviceType = /Mobi|Android/i.test(navigator.userAgent) ? 'mobile' : 'desktop';

                function fnv1a(str) {
                    let h = 0x811c9dc5;
                    for (let i = 0; i < str.length; i++) {
                        h ^= str.charCodeAt(i);
                        h = Math.imul(h, 0x01000193);
                    }
                    return (h >>> 0).toString(16);
                }

                function computeDeviceFp() {
                    let canvasSig = '';
                    try {
                        const c = document.createElement('canvas');
                        c.width = 220; c.height = 30;
                        const cx = c.getContext('2d');
                        cx.textBaseline = 'top';
                        cx.font = '14px Arial';
                        cx.fillStyle = '#f60';
                        cx.fillRect(0, 0, 200, 20);
                        cx.fillStyle = '#069';
                        cx.fillText('lgs-fp-\u062a\u062d\u0642\u0642', 2, 2);
                        canvasSig = c.toDataURL();
                    } catch (_) { }

                    let fonts = '';
                    try {
                        const testFonts = ['Arial', 'Tahoma', 'Times New Roman', 'Courier New', 'Tajawal', 'Georgia'];
                        const base = document.createElement('span');
                        base.style.cssText = 'position:absolute;left:-9999px;font-size:72px;';
                        base.textContent = 'mmmmmmmmmmlli';
                        document.body.appendChild(base);
                        const widths = testFonts.map(f => {
                            base.style.fontFamily = f + ', monospace';
                            return base.offsetWidth;
                        });
                        document.body.removeChild(base);
                        fonts = widths.join(',');
                    } catch (_) { }

                    const parts = [
                        navigator.userAgent, navigator.language, navigator.hardwareConcurrency || '',
                        navigator.deviceMemory || '', navigator.maxTouchPoints || 0,
                        screen.width + 'x' + screen.height, window.devicePixelRatio,
                        Intl.DateTimeFormat().resolvedOptions().timeZone,
                        webglVendor, webglRenderer, fonts, canvasSig,
                    ];
                    return fnv1a(parts.join('|'));
                }
                const deviceFp = computeDeviceFp();

                const _qs = new URLSearchParams(window.location.search);
                const _ref = document.referrer || '';

                // Collect all UTM params present in the URL
                const _utm = {};
                ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id'].forEach(k => {
                    const v = _qs.get(k);
                    if (v) _utm[k] = v;
                });
                const _utmStr = JSON.stringify(_utm);

                // Determine primary traffic source (first match wins)
                let _trafficSource = 'direct';
                if (_qs.get('fbclid')) _trafficSource = 'meta_ads';
                else if (_qs.get('gclid')) _trafficSource = 'google_ads';
                else if (_qs.get('ttclid')) _trafficSource = 'tiktok_ads';
                else if (_qs.get('msclkid')) _trafficSource = 'bing_ads';
                else if (_qs.get('twclid')) _trafficSource = 'twitter_ads';
                else if (_qs.get('li_fat_id')) _trafficSource = 'linkedin_ads';
                else if (_qs.get('sc_cid') || _qs.get('snapcid')) _trafficSource = 'snapchat_ads';
                else if (_utm.utm_medium === 'cpc' || _utm.utm_medium === 'paid')
                    _trafficSource = 'paid_utm';
                else if (Object.keys(_utm).length > 0) _trafficSource = 'utm_organic';
                else if (_ref) {
                    try {
                        const refHost = new URL(_ref).hostname.replace(/^www\./, '');
                        const searchEngines = ['google.', 'bing.', 'yahoo.', 'duckduckgo.', 'baidu.', 'yandex.'];
                        const socialNets = ['facebook.com', 'instagram.com', 'twitter.com', 'x.com',
                            'linkedin.com', 'tiktok.com', 'snapchat.com', 'pinterest.com',
                            't.co', 'lnkd.in'];
                        if (searchEngines.some(s => refHost.includes(s))) _trafficSource = 'organic_search';
                        else if (socialNets.some(s => refHost.includes(s))) _trafficSource = 'organic_social';
                        else _trafficSource = 'referral';
                    } catch (e) { _trafficSource = 'referral'; }
                }
                async function checkEmail(email) {
                    if (!EMAIL_VERIFY) return { valid: true };
                    if (emailCache[email] !== undefined) return emailCache[email];
                    try {
                        const r = await fetch(CHECK_EMAIL_URL, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ email })
                        });
                        const d = await r.json();
                        emailCache[email] = d;
                        return d;
                    } catch (e) { return { valid: true }; }
                }

                emailEl?.addEventListener('blur', async () => {
                    const val = emailEl.value.trim();
                    if (!val || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) return;
                    const result = await checkEmail(val);
                    if (!result.valid) {
                        emailEl.classList.add(INPUT_ERR_CLS);
                        errorBox.textContent = 'أدخل بريداً إلكترونياً صحيحاً.';
                    } else {
                        emailEl.classList.remove(INPUT_ERR_CLS);
                        if (errorBox.textContent === 'أدخل بريداً إلكترونياً صحيحاً.') errorBox.textContent = '';
                    }
                });

                form.addEventListener('submit', async function (e) {
                    e.preventDefault();

                    const name = nameEl?.value.trim() || '';
                    const email = emailEl?.value.trim() || '';
                    const phone = phoneEl?.value.trim() || '';
                    const hp = hpEl?.value || '';
                    const tsRes = $('[name="cf-turnstile-response"]')?.value || '';

                    if (!name || !email || !phone) {
                        errorBox.textContent = 'عمر جميع المعلومات من فضلك.';
                        return;
                    }
                    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                        errorBox.textContent = 'أدخل بريداً إلكترونياً صحيحاً.';
                        return;
                    }
                    if (REQUIRE_TURNSTILE && !tsRes) {
                        errorBox.textContent = 'المرجو تأكيد أنك لست روبوت.';
                        return;
                    }
                    if (REQUIRE_MATH_CAPTCHA && captchaA && captchaA.value.trim() === '') {
                        errorBox.textContent = 'جاوب على السؤال من فضلك.';
                        return;
                    }

                    if (EMAIL_VERIFY) {
                        errorBox.textContent = '';
                        const emailResult = await checkEmail(email);
                        if (!emailResult.valid) {
                            emailEl.classList.add(INPUT_ERR_CLS);
                            errorBox.textContent = 'أدخل بريداً إلكترونياً صحيحاً.';
                            return;
                        }
                    }

                    errorBox.textContent = '';
                    btn.disabled = true;
                    btn.textContent = 'جاري التسجيل...';

                    const _pixelEventId = 'lead_' + Date.now() + '_' + Math.random().toString(36).slice(2);

                    const honeypotValue = (hpTriggered || decoyHit) ? (hp || 'decoy_triggered') : hp;

                    const payload = {
                        name, email, phone,
                        device_fp: deviceFp,
                        website_url: honeypotValue,
                        decoy_hit: decoyHit ? 'TRUE' : 'FALSE',
                        form_token: serverToken,
                        captcha_answer: captchaA ? captchaA.value.trim() : '',
                        cf_turnstile_response: tsRes,
                        page_url: window.location.href,
                        fbclid: new URLSearchParams(window.location.search).get('fbclid') || '',
                        form_rendered_at: PAGE_LOAD_TIME,
                        ttff: firstInteractionAt,
                        t_n: tName,
                        t_e: tEmail,
                        t_p: tPhone,
                        mouse_moves: mouseMoves,
                        mouse_clicks: clicks,
                        key_presses: keyPresses,
                        touch_events: touches,
                        scroll_events: scrolls,
                        honeypot_triggered: (hpTriggered || decoyHit) ? 'TRUE' : 'FALSE',
                        honeypot_value: hp,
                        honeypot_events: hpEvents,
                        webdriver: navigator.webdriver ? 'TRUE' : 'FALSE',
                        ip_address: clientIp,
                        referrer: document.referrer || '',
                        language: navigator.language,
                        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
                        screen_width: screen.width,
                        screen_height: screen.height,
                        viewport_width: window.innerWidth,
                        viewport_height: window.innerHeight,
                        device_pixel_ratio: window.devicePixelRatio,
                        max_touch_points: navigator.maxTouchPoints || 0,
                        hardware_concurrency: navigator.hardwareConcurrency || '',
                        device_memory: navigator.deviceMemory || '',
                        cookies_enabled: navigator.cookieEnabled ? 'TRUE' : 'FALSE',
                        online: navigator.onLine ? 'TRUE' : 'FALSE',
                        webgl_vendor: webglVendor,
                        webgl_renderer: webglRenderer,
                        gpu_device_type: gpuDeviceType,
                        traffic_source: _trafficSource,
                        utm_params: _utmStr,
                        pixel_event_id: _pixelEventId,  // server CAPI uses same ID
                    };

                    try {
                        const resp = await fetch(SUBMIT_URL, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(payload),
                        });
                        const result = await resp.json().catch(() => ({}));

                        if (!resp.ok || result.success === false) {
                            if (result.error === 'captcha_mismatch') {
                                errorBox.textContent = 'الجواب خاطئ، جاوب على السؤال الجديد.';
                                if (captchaA) captchaA.value = '';
                                await fetchNewToken();
                                btn.disabled = false;
                                btn.textContent = BTN_LABEL;
                                return;
                            }
                            throw new Error('submit_failed');
                        }

                        if (typeof fbq === 'function') {
                            fbq('track', 'Lead', {}, { eventID: _pixelEventId });
                        }

                        if (REDIRECT_URL) {
                            setTimeout(() => { window.location.href = REDIRECT_URL; }, 350);
                        } else {
                            wrap.innerHTML = '<div class="' + SUCCESS_CLS + '">تم التسجيل بنجاح، شكرا ليك!</div>';
                        }
                    } catch (err) {
                        btn.disabled = false;
                        btn.textContent = BTN_LABEL;
                        if (typeof turnstile !== 'undefined') turnstile.reset();
                        errorBox.textContent = 'وقع مشكل، عاود حاول.';
                    }
                });
            })();
        </script>
        <?php
        return ob_get_clean();
    }
}
