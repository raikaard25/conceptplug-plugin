=== ConceptPlug ===
Contributors: conceptjames
Tags: woocommerce, ai, product, conwoo
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Modular WordPress enhancement platform. ConWoo module: AI-powered WooCommerce product publishing via ConceptPlug cloud credits.

== Description ==

ConceptPlug connects your WordPress site to the ConceptPlug API. ConWoo is the first module — create WooCommerce products with AI-generated SEO content and designed product images.

* Secure email verification with a single-use activation link
* Starter credits once per verified email address
* Additional credits are currently arranged by contacting hello@conceptplug.com
* All AI logic runs on ConceptPlug servers — your site is a thin client

== Installation ==

1. Upload the `conceptplug` folder to `/wp-content/plugins/`
2. Activate through the 'Plugins' menu
3. Open ConceptPlug → Dashboard, enter your email, then click the verification link
4. Enable the modules you want to use
5. ConWoo requires an active WooCommerce installation

== Configuration ==

Advanced: define `CONCEPTPLUG_API_URL` in wp-config.php to point at a custom API endpoint. Default is `https://api.conceptplug.com`.

== External Services ==

This plugin connects to the ConceptPlug API to provide cloud-powered features. The service is required for activation, credits, and AI features; the plugin's local module controls and settings remain available without it.

**What the service is used for**

* One-time email verification and installation-specific license activation
* Credit balance and billing for AI operations
* AI content generation, image design, and SEO analysis (ConWoo module)
* Optional anonymous usage statistics (only if you opt in)

**What data is sent and when**

* **On activation:** your email address, site URL, a randomly generated installation identifier, and marketing preference (if you opt in).
* **During AI operations:** product briefs, images, and brand settings you submit in the admin — sent only when you run generate, design, or publish actions.
* **Billing:** operation type and credit usage on each API call (required to run the service).
* **Optional telemetry (opt-in only):** feature names, counts, timings, success/error types, SEO scores (numbers only), plugin and WordPress versions. We do **not** collect product names, descriptions, images, or prompts in telemetry.

ConceptPlug uses these subprocessors to deliver the service:

* **OpenRouter** receives product briefs, submitted images, and brand settings only when you request an AI operation. Terms: https://openrouter.ai/terms — Privacy: https://openrouter.ai/privacy
* **Resend** receives your email address and activation email contents when ConceptPlug sends the verification link. Terms: https://resend.com/legal/terms-of-service — Privacy: https://resend.com/legal/privacy-policy

**Primary service provider:** ConceptPlug — https://conceptplug.com

**Terms of Service:** https://conceptplug.com/terms

**Privacy Policy:** https://conceptplug.com/privacy

== Privacy ==

ConceptPlug sends two types of data to our API:

* **Billing (required):** credit balance and usage per operation — needed to run the service.
* **Anonymous usage statistics (optional, off by default):** which features are used, counts, timings, success/error types, SEO scores (numbers only), plugin and WordPress versions. We do **not** collect product names, descriptions, images, prompts, or other store content in telemetry. Enable or disable anytime under ConceptPlug → Settings → Privacy.

== Changelog ==

= 1.1.0 =
* Added single-use email verification and installation-specific licenses.
* Added explicit module enable and disable controls.
* Preserved original Media Library files by optimizing a copy.
* Hardened image validation, request retries, and admin output escaping.

= 1.0.0 =
* Initial release: ConceptPlug core + ConWoo module
