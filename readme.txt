=== Lead Guard - Fake Lead & Bot Blocker ===
Contributors: webcreativeclicks
Tags: anti-bot, fake-lead, security, turnstile, spam-protection
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Ultimate anti-bot and fake lead blocker with rotating CSS classes, device fingerprinting, interaction timing, Turnstile, and Google Sheets webhook.

== Description ==

Lead Guard is an advanced, professional WordPress plugin developed by Web Creative Clicks designed to deliver ultimate protection against fake leads, bot submissions, and spam. 

It implements cutting-edge behavioral analysis, server-side cryptographic checks, and advanced fingerprinting to ensure that every lead entering your system is legitimate.

= Features =

* **Cryptographic Selector Rotation**: CSS classes and HTML IDs are dynamically generated per page-load or session, rendering automated scraping and generic bots completely useless.
* **Honeypots & Decoys**: Invisible fields and entire decoy forms trick bots into revealing themselves instantly.
* **Device Fingerprinting**: Persistent device cookies and browser fingerprinting tracks repeat offenders even if they change IP addresses.
* **Behavioral Analysis**: Tracks mouse movements, key presses, touch events, scroll behavior, and time-to-first-fill to distinguish human interaction from automation.
* **Server-Side Math Captcha**: Signed HMAC tokens ensure that the math challenge cannot be bypassed programmatically.
* **Cloudflare Turnstile**: Native support for privacy-preserving, friction-free bot challenges.
* **AbstractAPI Email Validation**: Real-time deliverability checks and disposable email blocking.
* **Google Sheets Webhooks**: Direct, server-to-server lead delivery to Google Sheets (bypassing any frontend exposure).
* **Meta CAPI & Pixel**: Server-side tracking integration for clean leads.

= Advanced Rate Limiting & Blocking =
* Endpoint Rate Limits
* Burst Detection
* Manual Blocklists (IPs and Emails)
* Cloudflare IP Trust validation

== External Services ==

This plugin integrates with external third-party services to deliver fraud detection, challenge verification, and webhook delivery:

* **Cloudflare Turnstile**: Provides privacy-friendly bot challenge verification.
  - Service URL: https://www.cloudflare.com/products/turnstile/
  - Terms of Service: https://www.cloudflare.com/website-terms/
  - Privacy Policy: https://www.cloudflare.com/privacypolicy/

* **AbstractAPI**: Validates email deliverability and blocks temporary disposable domains.
  - Service URL: https://www.abstractapi.com/api/email-validation
  - Terms of Service: https://www.abstractapi.com/legal/terms-of-service
  - Privacy Policy: https://www.abstractapi.com/legal/privacy-policy

* **Google Sheets (Google Apps Script)**: Server-to-server webhook destination for verified lead data.
  - Service URL: https://workspace.google.com/products/sheets/
  - Terms of Service: https://policies.google.com/terms
  - Privacy Policy: https://policies.google.com/privacy

* **Meta Conversions API (CAPI)**: Transmits clean lead conversion events directly to Meta.
  - Service URL: https://developers.facebook.com/docs/marketing-api/conversions-api/
  - Terms of Service: https://www.facebook.com/legal/terms
  - Data Policy: https://www.facebook.com/privacy/policy/

== Installation ==

1. Download the plugin as a `.zip` file.
2. Navigate to your WordPress Admin dashboard -> **Plugins** -> **Add New**.
3. Upload the `.zip` file and click **Install Now**.
4. Click **Activate Plugin**.
5. Go to **Lead Guard -> Settings** in your WordPress dashboard to configure your API keys.
6. Use the provided shortcode anywhere on your site to display the secure lead form: `[leadguard_wcc_form title="Register Now" button_text="Submit" redirect="https://yoursite.com/thank-you"]`

== Frequently Asked Questions ==

= Does this plugin work behind Cloudflare? =
Yes! We have an optional "Cloudflare IP Trust" setting that ensures the plugin correctly reads the `CF-Connecting-IP` header.

= How do I send leads to Google Sheets? =
In the Settings page, provide a Google Apps Script Webhook URL. The plugin securely POSTs verified leads directly from the server to your sheet.

== Changelog ==

= 2.2.0 =
* Complete architecture refactor to a modular, OOP structure.
* Rebranded to Web Creative Clicks.
* Added standard WordPress REST API integration.
* Added cryptographically rotating CSS classes.

= 1.0.0 =
* Initial release.
