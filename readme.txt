=== ConceptPlug ===
Contributors: conceptjames
Tags: woocommerce, ai, product, ecommerce
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.8.19
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Free local WooCommerce product tools with optional, clearly priced ConceptPlug AI actions.

== Description ==

ConceptPlug provides local WooCommerce product publishing, Product Health, bulk editing, and image optimization without activation or credits. Optional AI content and image actions connect to the ConceptPlug API only when you intentionally start them.

* Create drafts, publish products, quick edit, and run Product Health locally for 0 credits
* Locale-aware Thai/English Product Health never sends product data to an AI API
* Optional AI content and image actions show their credit cost before they start
* Secure, account-bound credit checkout without exposing a license key in the URL

== Installation ==

1. Upload the `conceptplug` folder to `/wp-content/plugins/`
2. Activate through the 'Plugins' menu
3. Use local WooCommerce tools immediately
4. Enter your email on ConceptPlug → Dashboard only when you want AI or billing features
5. Requires WooCommerce 8.0 or newer; multisite is not supported in this release

== Configuration ==

Advanced: define `CONCEPTPLUG_API_URL` in wp-config.php to point at a custom API endpoint. Default is `https://api.conceptplug.com`.

== External Services ==

This plugin connects to the ConceptPlug API to provide cloud-powered features.

**What the service is used for**

* Email activation and installation license rotation
* Credit balance and billing for optional AI operations
* AI content generation and image design for WooCommerce
* Optional pseudonymous usage statistics tied to your ConceptPlug account (only if you opt in)

**What data is sent and when**

* **On activation:** your email address, site URL, and marketing preference (if you opt in).
* **During AI operations:** product briefs, images, and brand settings you submit in the admin — sent only when you deliberately press an AI action. Local draft, publish, Product Health, quick edit, and local image optimization do not send this content to ConceptPlug.
* **Billing:** operation type and credit usage on each API call (required to run the service).
* **Optional telemetry (opt-in only):** pseudonymous account-linked feature names, counts, timings, success/error types, SEO scores (numbers only), plugin and WordPress versions. We do **not** include product names, descriptions, images, or prompts in telemetry.

**Service provider:** ConceptPlug — https://conceptplug.com

**Terms of Service:** https://conceptplug.com/terms

**Privacy Policy:** https://conceptplug.com/privacy

== Privacy ==

When activated, ConceptPlug sends two types of data to our API:

* **Billing (required):** credit balance and usage per operation — needed to run the service.
* **Pseudonymous usage statistics (optional, off by default):** account-linked feature usage, counts, timings, success/error types, SEO scores (numbers only), plugin and WordPress versions. We do **not** include product names, descriptions, images, prompts, or other store content in telemetry. Enable or disable anytime under ConceptPlug → Settings → Privacy.

== Changelog ==

= 1.8.19 =
* Fix Enhance image step appearing stuck at 50% — show queue/generate/save status while the async image job runs
* Fix resume after reload merging content and image jobs so review shows both text and designed images
* Allow downloading designed images from Cloudflare R2 and retry saving when multiple polls race

= 1.8.18 =
* Enhance working progress bar creeps smoothly during each AI step instead of staying at 0% until a step completes

= 1.8.17 =
* Enhance version history cards show featured product thumbnail, preview title, and readable field chips

= 1.8.16 =
* Enhance working step shows step counter, determinate progress bar, and completion percentage per AI task

= 1.8.15 =
* Fix fatal JavaScript parse error in woocommerce-admin.js (Enhance and catalog helpers failed to load)
* Clearer message when ConceptPlug AI is disabled on the server (ai_mode)

= 1.8.14 =
* Fix Enhance modal still showing “Loading the current AI price” — warm catalog on My Products, ship catalog with enhance load, and show retry when pricing fails to load

= 1.8.13 =
* Fix Enhance modal stuck with “Loading the current AI price” — prefetch catalog on My Products and auto-load when the enhance dialog opens

= 1.8.12 =
* WooCommerce Enhance: local version history saved on every Apply (original, before/after snapshots)
* Restore any saved enhance version with automatic backup of the current state (0 credits)
* Versions modal and Enhance History tab with diff preview and JSON export for external backup
* Configurable version limit per product (default 15; uninstall keeps version data on products)

= 1.8.11 =
* Purchase history now includes monthly subscription charges alongside packs and top-ups
* Active subscribers can compare plans in-plugin and upgrade to a higher tier without leaving WordPress
* Upgrade grants additional monthly credits for the current billing period without removing existing credits

= 1.8.10 =
* After Stripe subscription checkout, auto-sync monthly credits and refresh the billing page when credits arrive
* New subscription sync API fallback when Stripe webhooks are delayed or missed

= 1.8.9 =
* Clearer subscription checkout error messages when ConceptPlug cloud billing is misconfigured
* Fix billing plan and top-up cards: stacked layout so price and credits no longer overflow the button border

= 1.8.8 =
* Fix subscription Subscribe button: read data-plan-id correctly in billing.js (Stripe checkout redirect)
* Show billing status errors under Subscribe; auto-select first plan in subscription mode

= 1.8.7 =
* Billing UI prefers live billing-config over a stale credits_only account cache (subscription_plus_topup)
* Force-refresh billing config when opening Credits & Billing; fix subscription checkout consent enablement

= 1.8.6 =
* Replace generic plugin dashicon with ConceptPlug logo in the WordPress admin sidebar and in-app shell header

= 1.8.5 =
* Fix My Products table rendering: restore missing table/thead/tbody markup in list display (1.8.3–1.8.4 regressed layout)

= 1.8.4 =
* Fix My Products layout: move filters out of WordPress tablenav (30px height trap) so row actions no longer show through dropdowns
* Restore proper desktop table columns; improve mobile card stacking (including Source column)

= 1.8.3 =
* Fix My Products filter bar overlapping table column headers (source tabs, category/tag/status filters no longer stack on Image/Product/Source)

= 1.8.2 =
* Content format modes: Balanced (default), SEO long-form, and Compact for AI product copy
* Choose format in Settings, Create Product advanced options, and Enhance modal
* Product Health scoring follows the selected content format (no 300-word penalty in Balanced/Compact)
* Enhance working step shows animated progress while AI runs

= 1.8.1 =
* Subscription billing schema support (API-side; production may stay credits_only until configured)
* WooCommerce billing UI reads business_mode from API for packs vs subscription display

= 1.8.0 =
* Subscription and top-up billing foundation (API); plugin UI for subscription plans when enabled
* Console/web pricing alignment with business_mode

= 1.7.0 =
* Make local product draft/publish, Product Health, quick edit, demo presets, settings, and reviewed-field apply available without activation or credits
* Replace cloud SEO scoring with locale-aware local Thai/English Product Health at 0 credits
* Add durable publish intent locking and reset request keys for Create Another
* Make image optimization non-destructive, preserve the original, prevent double optimization, and record revert metadata
* Validate image capabilities, real MIME/dimensions/size, safe generated-image hosts, redirects, and response limits
* Add public v2 catalog fallback and show local-free, standard AI, and creative AI pricing
* Stop blocking admin rendering on account/catalog requests and improve Stripe initialization/poll timeout
* Build readable source and translations into deterministic ZIPs with safe 0644/0755 permissions
* Pin and verify SHA-256 plus detached Ed25519 signatures for self-hosted automatic updates

= 1.6.7 =
* Fix AI generate "Not found." when API URL in settings includes a trailing /v1 (double path)
* Prefer CONCEPTPLUG_API_URL from wp-config over stale DB api_url; clearer 404 error message

= 1.6.6 =
* Fix Enhance modal stuck on "Loading product…" (JS tried to set credits on product ID instead of admin state)

= 1.6.5 =
* Fix Enhance modal hanging on "Loading product…" (removed blocking remote credit refresh on open)

= 1.6.4 =
* Fix misleading "Buy Credits" on non-credit Enhance/API errors; show the real API message
* Prefer cached credits when opening Enhance (live refresh moved off the critical path in 1.6.5)

= 1.6.3 =
* My Products: AI Enhance for existing WooCommerce products (selective or Full Improve, credit-based)
* List all store products with source filters (Created / Enhanced / Store); simple-product Enhance
* Review before apply, SEO Fix with AI, bulk Enhance for simple products, Re-analyze All credit confirm
* Harden enhance AJAX timeouts/errors, category context, and suggested-category apply

= 1.6.2 =
* Replace dashboard stat cards with a compact status strip (credits stay in the header)

= 1.6.1 =
* Move Advanced options below Product Details on the Create Product wizard for clearer flow

= 1.6.0 =
* Full rename to WooCommerce naming across admin, API routes, meta keys, and assets
* Automatic database migration for sites still on pre-1.6.0 storage keys (options and product meta)

= 1.5.4 =
* Customer-friendly Settings and error messages; no API URL or raw server errors in admin UI

= 1.5.3 =
* Restore ConceptPlug hub dashboard as sidebar landing page (no redirect to Create Product)

= 1.5.2 =
* Fix sidebar ConceptPlug menu landing on Settings; activated users open WooCommerce Create Product

= 1.5.1 =
* Demo photos from CDN with sideload on Fill Demo

= 1.5.0 =
* Remove legacy checkout AJAX; leaner zip (no bundled demo JPG, no languages template)
* Demo photos load from CDN with sideload on Fill Demo
* Minified admin JS/CSS in release builds; complete uninstall cleanup; centralized SEO preview config

= 1.4.4 =
* My Products filters: hierarchical categories, WooCommerce-only terms, status filter, active filter chips with clear all

= 1.4.3 =
* My Products tags column: show a short preview with expand button to view all tags without stretching list rows

= 1.4.2 =
* Quick Edit tags: chip editor with add/remove, suggestions, and clearer help text (replaces confusing comma field)

= 1.4.1 =
* Simplify My Products UI: single Quick edit entry, display-only taxonomy columns, progressive bulk bar, fewer filters and bulk actions

= 1.4.0 =
* My Products Phase 2: multi-category quick edit, tag suggestions, mobile bulk checkboxes, virtual/downloadable flags for simple products

= 1.3.9 =
* My Products quick edit: category/tags/status columns, filters, per-row modal, and bulk actions (set category, add/remove tags, change status)

= 1.3.8 =
* Fix My Products mobile card: product image on the left beside title; hide IMAGE/PRODUCT row labels

= 1.3.7 =
* Fix My Products table squeezed beside search box (WP float:right) on tablet/desktop viewports

= 1.3.6 =
* Fix My Products table column collapse on tablet/desktop: remove cp-table-cards min-width bug, auto table layout with title min-width

= 1.3.5 =
* Fix tablet typography (601-1100px): scaled stat/billing fonts, readable card table labels, form-table stack at 782px

= 1.3.4 =
* Complete responsive pass: fixed wizard CTA on mobile, credits header sync, card tables on tablet, billing history cards, full-width forms/buttons

= 1.3.3 =
* Fix My Products title column on mobile: full-width card header with normal word wrap (no one-character-per-line)

= 1.3.2 =
* Responsive wp-admin UI for phones and tablets: scrollable nav, stacked forms, billing layout, My Products card table, WooCommerce wizard sticky CTA

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
* WooCommerce: image design style moved out of Advanced — visible chips + clear optional/default copy on Create Product

= 1.1.12 =
* WooCommerce demo presets: replace stock lifestyle photos with catalog-style product shots on white backgrounds

= 1.1.11 =
* WooCommerce: category-based demo presets (10 product verticals) with matching sample photos
* Fill Demo now imports a sample image into the wizard so you can generate without uploading first

= 1.1.10 =
* WooCommerce: AI-generated content aligns with SEO score checks (title 40-60 chars, 300+ word descriptions)
* WooCommerce SEO preview checklist uses clearer title length guidance

= 1.1.9 =
* Activation emails and dashboard now show this site's URL before you confirm
* Two-step email confirmation on the API (review site, then confirm) to reduce mistaken activations
* WooCommerce: clearer design failure messages, credits bar sync after AI steps, longer design timeout
* API client sanitizes HTML error pages (e.g. Cloudflare 502) for readable admin errors

= 1.1.8 =
* Force plugin update checks when opening Plugins/Updates screens
* Added "Check for updates" link on the Plugins list row

= 1.1.7 =
* WooCommerce dashboard card links to Install/Activate WooCommerce when the store plugin is missing

= 1.1.6 =
* Clarified activation email instructions (inbox + Spam/Junk)
* Excluded release/ and public/downloads from plugin zip builds

= 1.1.5 =
* Fixed missing WooCommerce_Image_Designer class reference on the create-product page

= 1.1.4 =
* Fixed fatal PHP parse error on plugin activation (missing brace in v1.1.3)

= 1.1.3 =
* Fixed activation AJAX returning HTTP 400 because handlers did not load during admin-ajax.php requests

= 1.1.2 =
* Fixed email activation returning HTTP 400 when WooCommerce is not active yet
* Moved core activation and billing AJAX handlers out of the WooCommerce module bootstrap

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
* Initial release: ConceptPlug core + WooCommerce module
