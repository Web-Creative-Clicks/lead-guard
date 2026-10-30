# WCC Lead & Bot Guard

**WCC Lead & Bot Guard** is an advanced, professional WordPress plugin developed by Web Creative Clicks designed to deliver ultimate protection against fake leads, bot submissions, and spam. 

It implements cutting-edge behavioral analysis, server-side cryptographic checks, and advanced fingerprinting to ensure that every lead entering your system is legitimate.

## Features

### 🛡️ Advanced Bot & Spam Protection
*   **Cryptographic Selector Rotation**: CSS classes and HTML IDs are dynamically generated per page-load or session, rendering automated scraping and generic bots completely useless.
*   **Honeypots & Decoys**: Invisible fields and entire decoy forms trick bots into revealing themselves instantly.
*   **Device Fingerprinting**: Persistent device cookies and browser fingerprinting tracks repeat offenders even if they change IP addresses.
*   **Behavioral Analysis**: Tracks mouse movements, key presses, touch events, scroll behavior, and time-to-first-fill to distinguish human interaction from automation.
*   **Server-Side Math Captcha**: Signed HMAC tokens ensure that the math challenge cannot be bypassed programmatically.

### 🔌 Third-Party Integrations
*   **Cloudflare Turnstile**: Native support for privacy-preserving, friction-free bot challenges.
*   **AbstractAPI Email Validation**: Real-time deliverability checks and disposable email blocking.
*   **Google Sheets Webhooks**: Direct, server-to-server lead delivery to Google Sheets (bypassing any frontend exposure).
*   **Meta CAPI & Pixel**: Server-side tracking integration for clean leads.

### 🛑 Advanced Rate Limiting & Blocking
*   **Endpoint Rate Limits**: Blocks rapid API polling and prevents quota drain.
*   **Burst Detection**: Monitors and restricts global site-wide burst attacks.
*   **Manual Blocklists**: Explicit IP and Email blocking directly from the dashboard.
*   **Cloudflare IP Trust**: Optional strict IP validation to prevent IP-spoofing when behind Cloudflare.

## Installation

1. Download the plugin as a `.zip` file.
2. Navigate to your WordPress Admin dashboard -> **Plugins** -> **Add New**.
3. Upload the `.zip` file and click **Install Now**.
4. Click **Activate Plugin**.

## Usage

### 1. Configuration
Go to **WCC Lead Guard -> Settings** in your WordPress dashboard to configure thresholds, add your API keys (Turnstile, AbstractAPI), and set up your Google Sheets webhook URL.

### 2. Displaying the Form
Use the provided shortcode anywhere on your site to display the secure lead form:
```text
[leadguard_wcc_form title="Register Now" button_text="Submit" redirect="https://yoursite.com/thank-you"]
```

### 3. Reviewing Submissions
Navigate to **WCC Lead Guard -> Submissions** to view all captured leads. You can review their fraud scores, traffic sources, device fingerprints, and manually mark submissions as clean or block them completely.

## Architecture

* `wcc-lead-guard.php`: Core plugin bootstrap.
* `admin/`: Admin UI, settings management, submissions table.
* `api/`: REST API endpoints for secure token init, email verification, and submission handling.
* `includes/`: Cryptographic hashing, validation logic, external service callers (Apps Script, Meta CAPI).
* `public/`: Frontend shortcode rendering and interactive form handler with bot decoy mechanisms.
