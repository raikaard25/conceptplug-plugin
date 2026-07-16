=== ConceptPlug ===
Contributors: conceptjames
Tags: woocommerce, ai, product, conwoo
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.3.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Modular WordPress enhancement platform. ConWoo module: AI-powered WooCommerce product publishing via ConceptPlug cloud credits.

== Description ==

ConceptPlug connects your WordPress site to the ConceptPlug API. ConWoo is the first module — create WooCommerce products with AI-generated SEO content and designed product images.

* Email verification → one free complete product trial (content, one AI photo, SEO on publish)
* Secure, account-bound credit checkout from $5 without exposing a license key in the URL
* All AI logic runs on ConceptPlug servers — your site is a thin client

== Installation ==

1. Upload the `conceptplug` folder to `/wp-content/plugins/`
2. Activate through the 'Plugins' menu
3. Open ConceptPlug → Dashboard, enter email to activate
4. Requires WooCommerce for ConWoo module

== Configuration ==

Advanced: define `CONCEPTPLUG_API_URL` in wp-config.php to point at a custom API endpoint. Default is `https://api.conceptplug.com`.

== External Services ==

This plugin connects to the ConceptPlug API to provide cloud-powered features.

**What the service is used for**

* Email activation and installation license rotation
* Credit balance and billing for AI operations
* AI content generation, image design, and SEO analysis (ConWoo module)
* Optional anonymous usage statistics (only if you opt in)

**What data is sent and when**

* **On activation:** your email address, site URL, and marketing preference (if you opt in).
* **During AI operations:** product briefs, images, and brand settings you submit in the admin — sent only when you run generate, design, or publish actions.
* **Billing:** operation type and credit usage on each API call (required to run the service).
* **Optional telemetry (opt-in only):** feature names, counts, timings, success/error types, SEO scores (numbers only), plugin and WordPress versions. We do **not** collect product names, descriptions, images, or prompts in telemetry.

**Service provider:** ConceptPlug — https://conceptplug.com

**Terms of Service:** https://conceptplug.com/terms

**Privacy Policy:** https://conceptplug.com/privacy

== Privacy ==

ConceptPlug sends two types of data to our API:

* **Billing (required):** credit balance and usage per operation — needed to run the service.
* **Anonymous usage statistics (optional, off by default):** which features are used, counts, timings, success/error types, SEO scores (numbers only), plugin and WordPress versions. We do **not** collect product names, descriptions, images, prompts, or other store content in telemetry. Enable or disable anytime under ConceptPlug → Settings → Privacy.

== Changelog ==

= 1.3.6 =
* Fix My Products table column collapse on tablet/desktop: remove cp-table-cards min-width bug, auto table layout with title min-width

= 1.3.5 =
* Fix tablet typography (601-1100px): scaled stat/billing fonts, readable card table labels, form-table stack at 782px

= 1.3.4 =
* Complete responsive pass: fixed wizard CTA on mobile, credits header sync, card tables on tablet, billing history cards, full-width forms/buttons

= 1.3.3 =
* Fix My Products title column on mobile: full-width card header with normal word wrap (no one-character-per-line)

= 1.3.2 =
* Responsive wp-admin UI for phones and tablets: scrollable nav, stacked forms, billing layout, My Products card table, ConWoo wizard sticky CTA

= 1.3.1 =
* Fix context nav highlighting all platform tabs on Home (remove faulty JS substring match)

= 1.3.0 =
* Thin-line admin UI: branded shell header, underline tabs, dashboard overview stat cards, polished module cards

= 1.2.2 =
* Fix "Sorry, you are not allowed to access this page" — keep submenu registrations, hide sidebar fly-out with CSS only

= 1.2.1 =
* Fix critical admin crash: restore missing CONCEPTPLUG_ACTIVATION_OPTION_KEY constant

= 1.2.0 =
* App Shell UX: hide WordPress submenu, in-app breadcrumbs, context navigation, and unified dashboard hub

= 1.1.13 =
* ConWoo: image design style moved out of Advanced — visible chips + clear optional/default copy on Create Product

= 1.1.12 =
* ConWoo demo presets: replace stock lifestyle photos with catalog-style product shots on white backgrounds

= 1.1.11 =
* ConWoo: category-based demo presets (10 product verticals) with matching sample photos
* Fill Demo now imports a sample image into the wizard so you can generate without uploading first

= 1.1.10 =
* ConWoo: AI-generated content aligns with SEO score checks (title 40-60 chars, 300+ word descriptions)
* ConWoo SEO preview checklist uses clearer title length guidance

= 1.1.9 =
* Activation emails and dashboard now show this site's URL before you confirm
* Two-step email confirmation on the API (review site, then confirm) to reduce mistaken activations
* ConWoo: clearer design failure messages, credits bar sync after AI steps, longer design timeout
* API client sanitizes HTML error pages (e.g. Cloudflare 502) for readable admin errors

= 1.1.8 =
* Force plugin update checks when opening Plugins/Updates screens
* Added "Check for updates" link on the Plugins list row

= 1.1.7 =
* ConWoo dashboard card links to Install/Activate WooCommerce when the store plugin is missing

= 1.1.6 =
* Clarified activation email instructions (inbox + Spam/Junk)
* Excluded release/ and public/downloads from plugin zip builds

= 1.1.5 =
* Fixed missing ConWoo_Image_Designer class reference on the create-product page

= 1.1.4 =
* Fixed fatal PHP parse error on plugin activation (missing brace in v1.1.3)

= 1.1.3 =
* Fixed activation AJAX returning HTTP 400 because handlers did not load during admin-ajax.php requests

= 1.1.2 =
* Fixed email activation returning HTTP 400 when WooCommerce is not active yet
* Moved core activation and billing AJAX handlers out of the ConWoo module bootstrap

= 1.1.1 =
* Added one-click updates from conceptplug.com with SHA256 integrity verification
* Synced activation flow with api.conceptplug.com email verification endpoints
* Added Stripe billing UI hooks (enabled when API Stripe config is live)

= 1.1.0 =
* Added email-link activation with installation UUID, expiring poll token, and license rotation
* Added durable idempotency keys for every charged operation retry
* Added secure one-time Buy Credits checkout sessions
* Removed credentials and customer content from telemetry and logs

= 1.0.1 =
* Signup trial: one free complete AI product (36 credits) instead of 100 starter credits

= 1.0.0 =
* Initial release: ConceptPlug core + ConWoo module
