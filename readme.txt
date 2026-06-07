=== PCKZ Canonical Engine ===
Contributors: productcreatorkz
Tags: product customizer, woocommerce, laser, engraving, print, configurator
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 2.28.32
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Ledos-style WordPress product customizer: live preview, customer options, WooCommerce orders with production files for your workshop.

== Description ==

PCKZ Canonical Engine is a native WordPress plugin for building professional product configurators similar to industrial personalization and laser/engraving workflows (LightBurn-style coordinates).

**Features:**

* Live real-time canvas preview (Fabric.js)
* Text editing with fonts and colors
* Logo/image upload
* Drag, scale, rotate, and alignment tools
* Layer management
* Millimeter-accurate dimensions and print safe zones
* Bottom-left or top-left coordinate origin
* Print-ready PNG export at configurable DPI
* WooCommerce add to cart with saved design metadata
* Admin settings panel and creator product post type

**Shortcode:**

`[product_creator]` — auto-loads default product

`[product_creator id="123"]` — specific product

**Default preset:** License plate frame 525×145 mm with 520×112 mm print area (DSTU 4278).

== Installation ==

1. Upload the `pckz-canonical-engine` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **Product Creator → Products** and create a creator product
4. Embed `[product_creator id="YOUR_ID"]` on any page
5. (Optional) Install WooCommerce and link a product ID for cart checkout

== Frequently Asked Questions ==

= Does this require WooCommerce? =

No. The creator works standalone. WooCommerce is optional for e-commerce.

= Can I use custom canvas sizes? =

Yes. Each creator product has configurable canvas and safe zone dimensions in millimeters.

== Changelog ==

= 2.28.32 =

* Fix protected ZIP runtime asset loading: when source JS/CSS files are stripped, frontend now auto-falls back to min/protected assets even if the protection toggle is off.
* Restores creator page scripts/styles in protected installs (no blank/boot-stuck creator).
* Keeps Naruto eye lines (type_102–type_111) visible/selectable with preserved colors and bundled labels via manifest-based registration.

= 2.28.31 =

* Fix creator page failures and missing Naruto eye models in the Linien picker caused by heavy SVG geometry processing on large traced bundled lines.
* Register type_102–type_111 via bundled-line-manifest.php (labels + preserve_colors) without parsing multi-megabyte SVG bodies on every request.
* Serve pre-normalized 950×35 Naruto eye SVGs directly for picker thumbnails and live preview (same asset path as other bundled lines).
* Always re-enable bundled Naruto eye models on init when SVG assets exist (clears stale delete/disabled flags from older purge builds).

= 2.28.30 =

* Fix Naruto anime eye line models (type_102–111) not appearing in the customer Linien picker after upgrade from older purge builds.
* Re-enable bundled type_102–111 on plugin activation and on init when SVG assets exist but stale permanent-delete or disabled flags remain.
* Clear incorrect permanent-delete, disabled, inactive, and admin-hidden flags for official Naruto eye models when bundled SVGs are present.

= 2.28.29 =

* Add 10 bundled Naruto anime eye line models (type_102–type_111) traced from the customer reference sheet with native colors preserved.
* Models register in the Linien picker with exact reference names: Sharingan, Mangekyo Sharingan, Rinnegan, Byakugan, Sage Mode, Tenseigan, Ketsuryugan, and related variants.
* Reference import pipeline: `tools/import-naruto-eye-line-models.sh` (vtracer color trace, 950×35 artboard).

= 2.28.28 =

* Sendungen verfolgen: integrated the provided truck/shipping animation on the order tracking page when status is Versendet (shipped).
* Animation is responsive on desktop, tablet, and mobile; tracking logic unchanged.

= 2.28.27 =

* Release storage management page with full package inventory (type, version, build ID, validation/manifest/checksum/publish status, storage area, created date) plus search and filters.
* Detailed protected archive validation errors now include ZIP filename, release version, storage location, all detected forbidden/master-only files, and recommended action.
* Automatic quarantine for invalid packages containing master-only files; quarantined packages are excluded from publishing and client update distribution.
* Storage maintenance tools: clean invalid packages, remove legacy releases, remove master-file packages, remove duplicate releases, rebuild metadata.
* Improved monitoring alerts for release validation failures with filename, version, detection time, rule triggered, and recommended action.
* Master Build ZIP uploads to the client protected workflow are blocked immediately with a clear explanation.

= 2.28.24 =

* Master Control UX redesign: five clear sections (Dashboard, Customer fleet, Software updates, Licenses & delivery, Activity & logs).
* Removed duplicate stats, changelog fields, and license status cards; unified panel/table styling and responsive layouts.
* Protected download history now supports instant client-side search/filter; release workflow uses tabbed Generate/Upload/Publish panels.

= 2.28.23 =

* Master Control: generate publish-ready protected ZIP packages directly from the master installation with automatic version sync (plugin header, PCKZCE_VERSION, PCKZCE_BUILD, filename, manifest/checksums).
* Protected release validation now checks all version fields together and reports detailed mismatch diagnostics (expected release version, filename, plugin header, constants, manifest).
* Rejects invalid archive layouts (for example release-packages/ or repo-root folders instead of pckz-canonical-engine/).

= 2.28.22 =

* Reverted v2.28.21 icon refX shift; icon placement restored to v2.28.20 values (left 817.5, right 2748.5).
* Kept automatic default line loading on first configurator open (first line in customer picker).

= 2.28.21 =

* Default line on first load: first line in the customer Linien picker (top red library model) loads automatically with text and icons.
* Icon placement: ~0.5 cm (5 mm) inward from original Cloudlift refs (left refX ~850.5, right refX ~2715.5) for all icons in preview and export.

= 2.28.20 =

* Default customer configurator layout: text “Lumi-Plate”, Instagram left icon, TikTok right icon, first font in the picker, and no lines on first load.
* Existing creator products receive the new start defaults automatically on upgrade and at runtime when the configurator opens.
* Icon placement: left icon refX 817.5 (+1.5 inward), right icon refX 2748.5 (−1.5 inward); preview and export parity unchanged otherwise.

= 2.28.19 =

* Configurator performance: batch asset resolver (`pckzce_resolve_option_assets`) replaces up to four separate AJAX round-trips with one request.
* Incremental preview rendering only rebuilds changed layers (line/icons/text) and cancels stale async renders to prevent overlapping objects.
* In-flight SVG/raster load deduplication, connected-line group cache, and selective cache invalidation on icon/line color changes.
* Text-only edits update the canvas in-place without reloading icons/lines; common fonts preload during init.
* Export validation debounce increased to reduce background work while typing.

= 2.28.18 =

* Fixed Roboto (and all Google fonts) export URL resolution: font asset resolver now returns same-origin `pckzce_font_file` proxy URLs that OpenType.js accepts for SVG/LBRN2 export.
* Extended export-safe font URL detection to accept signed `pckzce_secure_asset` endpoints as a fallback.
* Redesigned checkout flow: export package prepares silently in the background while the customer finishes the design; payment button stays disabled with an inline preparing state until ready, then shows “Weiter zur Zahlung” for immediate Stripe/PayPal redirect.
* Customer-facing export errors are now friendly; technical validation details are logged to the browser console and shown only to administrators.

= 2.28.10 =

* Release bump for delivery verification: published a new protected package version so client installations can confirm latest code deployment path end-to-end.
* Master Control tamper rendering upgrade: security event rows now show human-readable event labels (for example “Tamper signal reported”) instead of raw generic slugs only.
* Added detailed per-signal explanations in Master Control (why triggered, what was detected, and update impact: informational vs potentially blocking under strict integrity/signature policy).
* Installation history now includes expanded tamper signal detail cards with impact notes and clearer admin interpretation.

= 2.28.9 =

* Security hardening (phase 2): removed full runtime datasets from `pckzce_runtime_config`; endpoint now returns only minimal runtime defaults.
* Added on-demand option asset resolver (`pckzce_resolve_option_asset`) that returns short-lived signed token URLs for the currently selected icon/line/font only.
* Added secure token asset streaming endpoint (`pckzce_secure_asset`) for line/icon/font/background delivery without exposing raw mapping tables or direct asset URLs in localized JS config.
* Updated creator runtime to resolve and cache only currently selected option assets, instead of loading full line/icon/font mapping payloads into `pckzce-creator-js-extra`.
* Updated preview engine to consume per-selection resolved asset metadata (`resolved_assets`) and preserve production/export compatibility paths.
* Protected update reliability: fixed master release-meta auto-sync so it no longer overwrites manually published same-version package URLs or leaves stale checksum/manifest metadata after version changes.
* Protected package validation hardening: release validation now enforces WordPress-installable archive layout (`pckz-canonical-engine/pckz-canonical-engine.php`) and checks plugin header version parity with release metadata.
* Master visibility: Master Control now shows exact tamper signal slugs in fleet/security views and adds a safe per-installation “Acknowledge Signals” action that clears signals and re-baselines integrity on next check-in.
* Update failure telemetry: client update failures/success are now reported back to Master Control (`client/update-report`) so failed installs produce clear error visibility on both client and master dashboards.

= 2.28.8 =

* Security hardening: reduced public `pckzce-creator-js-extra` payload to minimum inline runtime fields (nonce/ajax endpoint + essential creator/checkout/UI values only).
* Moved heavy runtime mappings (`icons`, `ledosPreview`, `stdSpec`, font file maps) to a nonce-protected runtime AJAX endpoint (`pckzce_runtime_config`) fetched on demand.
* Removed non-essential public metadata from creator inline payload (build/version/plugin/debug fields, full settings maps, duplicated asset/config blocks).
* Updated preview runtime to consume lightweight font family-to-id mapping instead of full settings-font payload.
* Added regression assertion in `tests/public-protected-assets-smoke.php` to ensure forbidden heavy keys are not exposed in localized creator payload.

= 2.28.7 =

* Hardening-only release: added new `security_prefer_protected_assets` setting to serve production artifacts on public creator pages (`.protected.js`, `.min.js`, `.min.css`) via standard WordPress enqueue APIs.
* Added safe fallback behavior: if protected/minified assets are missing, frontend stays functional and admin receives a clear warning with required build targets.
* Added production asset resolver for scripts/styles and extended public tracking stylesheet enqueue to support minified assets.
* Added `tools/build-js-protection.php` to generate minified JS, protected JS for sensitive frontend files, minified CSS, and remove source maps from public build output.
* Reduced exposed frontend payload by removing unused payment diagnostics/hints from public JS config; sensitive checks stay server-side.
* Added regression smoke test `tests/public-protected-assets-smoke.php` to verify protected mode never enqueues readable development JS on public creator pages.

= 2.28.6 =

* Shipment tracking admin fix: tracking inputs are now always editable/savable in the order detail view (even when a WooCommerce order link is missing), and values persist in plugin order storage.
* Shipment data persistence: introduced dedicated shipment tracking JSON storage on commerce orders to preserve manual carrier/tracking/status/location/ETA/date/history data reliably.
* Carrier API integration: added automatic shipment synchronization via AfterShip (supports Austrian Post, DHL, DPD, GLS, UPS, FedEx), including carrier slug detection, status/event/location/ETA import, and scheduled refresh.
* Admin workflow upgrade: shipment form now includes carrier slug, auto-sync toggle, last-sync timestamp, and sync error visibility while keeping manual input as fallback.
* WooCommerce interoperability: synchronized shipment payload updates back into WooCommerce order meta when linked.
* Added shipment persistence smoke coverage (`tests/shipment-tracking-persistence-smoke.php`).

= 2.28.5 =

* Checkout responsiveness: export validation/preparation now runs progressively in the background and is no longer re-triggered by customer address/email input changes, reducing PayPal click latency.
* Checkout optimization: prepared export payloads are reused during save/checkout so the payment click path avoids rebuilding heavy preview/export artifacts whenever possible.
* Shipment tracking integration: added dedicated shipment tracking fields in the Orders admin detail (carrier, tracking number, tracking URL, shipment status, location, ETA, shipping date, and shipment event history).
* Customer tracking enhancements: tracking page now surfaces shipment status and shipment event history from WooCommerce tracking meta (including custom JSON event feeds).
* Tracking page refinement: simplified to a cleaner, minimal, Cloudflare-like layout with stronger readability and less visual weight while preserving responsive behavior.
* Added shipment tracking smoke coverage (`tests/customer-shipment-tracking-smoke.php`).

= 2.28.4 =

* Checkout UX/Performance: PayPal checkout click path is now non-blocking for prefilled customers; export validation continues in the background without noisy "please wait" toasts while preserving export/payment validation checks before order creation.
* Payment Redirects: successful PayPal and Stripe returns now resolve directly to the public order tracking page (with the obfuscated tracking order ID), with a safe creator-page fallback when a tracking shortcode page is not configured.
* Tracking Page redesign: upgraded to a premium black/white card layout with gradient surface, clearer hierarchy, progress bar, status cards (order/production/shipping), richer shipping facts (carrier, tracking no., location, ETA), and improved mobile responsiveness.
* Shipping data enrichment: customer tracking now reads optional location and estimated-delivery metadata from common WooCommerce tracking meta keys when available.
* Added smoke coverage for post-payment tracking redirects (`tests/post-payment-tracking-redirect-smoke.php`).

= 2.28.3 =

* Master Control permissions fix: resolved WordPress “Sorry, you are not allowed to access this page.” on `admin.php?page=pckz-license-server` for administrators.
* Admin menu registration now runs after parent Product Creator menu registration to prevent submenu capability/page-hook mismatch.
* Added robust fallback parent attachment (`options-general.php`) when the Product Creator parent menu is unavailable due external menu-order interference, while keeping capability as `manage_options`.
* Capability, architecture, licensing protections, update authorization, asset sync permissions, and master-host lock remain unchanged.

= 2.28.2 =

* Master Control routing: added legacy-path compatibility redirect so requests to `/wp-admin/pckz-license-server` are normalized to `wp-admin/admin.php?page=pckz-license-server` instead of falling through to frontend error templates.
* Settings hardening: normalize `licensing_master_url` to a base site URL (strip accidental `/wp-admin/...` and `/wp-json/...` endpoint paths) so client heartbeat/update/asset-sync calls always target the correct master root.
* Payments: fixed Stripe `payment_intent` array/object handling in `class-pckz-payments.php` (line 360 warning) to prevent "Array to string conversion" and preserve checkout/order finalization flow.
* Master Control DB resilience: added a wpdb connection-state normalizer before schema/dashboard queries to recover safely after intermittent mysqli “Commands out of sync” incidents.
* Architecture and protections unchanged: PaxDesign.at remains master; clients remain connected via licensed REST checks, update authorization, asset permissions, and domain restrictions.

= 2.28.1 =

* Master Control: fix blank page on paxdesign.at — the fleet partial called `$format_datetime()` before it was defined, producing a PHP 8 fatal that wiped the page. Shared formatter closures moved to the parent dashboard view; partials carry defensive fallbacks.
* Master Control: render path now wrapped in a fail-safe; any unexpected throwable falls back to a recovery panel with the error and next steps instead of a blank screen.
* Master Control: empty-state banner explains exactly what to do when no client installations have checked in yet.
* Master Control: schema auto-heal — license/installation/download/security-event tables are re-created on demand if they are missing, so an interrupted upgrade no longer leaves a broken dashboard.
* Master Control: `PCKZ_Master_Control::register_hooks()` is now wired up unconditionally on master so asset-catalog change notifications always bump the manifest revision.
* Licensing, asset synchronization, premium/security controls, domain restrictions, update authorization, and master-host lock are unchanged.

= 2.28.0 =

* Master Control: fleet overview dashboard with online/offline status, version, license health, update status, security alerts, search/filter/sort.
* Asset synchronization: custom lines, icons, and presets from paxdesign.at master to licensed installations (migration-safe, checksum verified).
* Licensing: plugin deactivation telemetry, integrity/security event log, admin menu update badge, critical update admin notices.
* Existing license protection, PayPal checkout, preview/export pipelines unchanged.

= 2.27.40 =

* Removed customer SVG download from the configurator preview (not requested).
* Checkout section “Lassen Sie uns Ihre Wünsche wissen”: optional customer artwork upload (SVG, PNG, JPG, JPEG, WEBP) attached to orders for production; admin download on order detail.

= 2.27.39 =

* Removed customer line color control (Linienfarbe); line models keep native/preserve colors in preview and export.
* Frontend: deter casual SVG copying (context menu/drag); picker URLs only in JS (no direct upload paths).
* Mobile: lazy-load line/icon picker thumbnails, debounced preview updates, magnifier off on small screens.
* Customer SVG download button (removed in 2.27.40); PayPal stays clickable while export validates in background.

= 2.27.38 =

* Line Library: fix SVG upload success with invisible lines — custom uploads at type_102+ no longer hidden by permanent-delete markers from the old purge.
* Upload and URL import only report success when the line is registered in admin and customer catalogs.

= 2.27.37 =

* Line Library: restore standard SVG file upload and URL import exactly as before (direct store, no conversion).
* Optional vector import (LBRN2, AI, EPS, etc.) moved to a separate admin form and does not affect normal SVG uploads.

= 2.27.36 =

* Line Library: fix fatal error when PHP exec() is disabled — import panel shows a warning instead of crashing; library and existing line models unchanged.
* Vector import (LBRN2, AI, EPS, DXF, PDF) remains optional; canonical 950×35 SVG upload still works without exec().

= 2.27.35 =

* Line Library: import vector files from admin (LBRN2, SVG, AI, EPS, DXF, PDF) — native paths converted to 950×35, auto-registered with preview and customer picker.
* Optional preserve-colors and connected L/R mirror on import; supports LightBurn → upload → full preview/export workflow.

= 2.27.34 =

* Removed incorrect generated `type_102`–`type_111` models (not from customer reference artwork).
* Added reference-sheet image import (`reference-sheet.png` → type_102–111 via vtracer; numbers stripped).
* LBRN2 / AI import paths unchanged; bundled max back to 101 until a successful import adds 102–111.

= 2.27.33 =

* (Reverted) Incorrect procedural red lines type_102–111 — removed in 2.27.34.

= 2.27.32 =

* Line library: permanent delete for built-in models (removes SVG + all catalog references).
* Purges incorrect red models type_102–type_121 on upgrade; LBRN2 import tool for 10 reference models (`svg.lbrn2` → type_102–type_111).

= 2.27.31 =

* Removed incorrect procedural red line models type_102–type_121.
* Added import pipeline for 10 customer red lines from `svg for line vicor costumer.ai` → type_102–type_111 (`tools/import-vector-line-customer-red.sh`).

= 2.27.30 =

* (Superseded) Procedural red lines type_102–121 — removed in 2.27.31.

= 2.27.10 =

* Live preview UI: fix mobile black scroll area — canvas removed from document flow before Fabric init; stage size matches CSS box; preview layers clipped on mobile.
* Live preview UI: clean initial load — configurator hidden until canvas is ready; boot spinner shown instead of empty/partial layout flash.
* Magnifier, hover zoom, and desktop preview behavior unchanged.

= 2.27.9 =

* Live preview UI: reverted desktop sticky/fixed preview behavior to pre-v2.27.8 state (no preview stickiness on desktop).
* Live preview UI: removed preview-sticky.js entirely (fixed positioning caused extra mobile scroll height).
* Live preview UI: restored original layout CSS (container-type on wrapper, overflow-x hidden on product).
* Live preview UI: mobile scroll fix — contain preview paint overflow so the page ends where content ends.
* Magnifier, hover zoom, and all other preview UI improvements unchanged.

= 2.27.8 =

* Live preview UI: fix sticky preview on mobile and shortcode embeds (container-type moved off wrapper; minimal preview-sticky.js for page-scroll layouts).
* Live preview UI: fix extra black scroll area at page bottom caused by broken CSS sticky + overflow interaction.
* Desktop side-by-side layouts unchanged: options panel still scrolls internally; preview stays visible without sticky override.

= 2.27.7 =

* Live preview UI: sticky behavior scoped to the license plate preview panel (`.pckz-gallery`) only.
* Keeps magnifier, hover zoom, layout, and styling unchanged — CSS `position: sticky` only.
* Desktop and mobile: preview panel stays visible while scrolling configurator options.

= 2.27.6 =

* Live preview UI: sticky/floating license plate preview while scrolling the configurator (CSS-only).
* Desktop: preview column stays pinned while options scroll (page or panel scroll unchanged).
* Mobile: preview sticks to the top of the viewport with a subtle shadow while changing options.
* Shortcode embeds: container-query sticky rules for narrow and wide wrapper widths.

= 2.27.5 =

* Live preview UI: magnifier lens inside the license plate preview area (visual-only; follows pointer on desktop).
* Live preview UI: magnifier hint button (🔎) at the bottom of the preview stage; keeps existing smooth hover zoom.
* Live preview UI: respects prefers-reduced-motion (magnifier disabled when reduced motion is requested).

= 2.27.4 =

* Live preview UI (CSS-only): removed the gray bordered background box behind the license plate frame preview.
* Live preview UI (CSS-only): transparent preview stage for day and night modes — only the frame remains visible.
* Live preview UI (CSS-only): smooth hover zoom on desktop pointers (visual transform only; no Fabric coordinate or export changes).
* Live preview UI (CSS-only): subtle magnifier hint on hover; respects prefers-reduced-motion.

= 2.27.3 =

* Icon Library: fixed Customer (On) visibility not persisting after save on production sites (paxdesign.at).
* Icon Library: moved save script to enqueued admin/js/icon-library.js (inline scripts could be blocked by CSP).
* Icon Library: server-rendered JSON payload in textarea so save works even before JavaScript runs.
* Icon Library: uploaded icon visibility now stored per icon in pckz_icon_custom (customer_visible flag).
* Icon Library: save aborts with an admin error instead of silently disabling all icons when payload is missing.

= 2.27.2 =

* Icon Library: fixed Customer (On) visibility not persisting after save when many icons are uploaded (compact JSON save avoids PHP max_input_vars truncation).
* Icon Library: newly uploaded SVG icons are customer-visible by default and appear in the live symbol picker.
* Icon display: customer symbol picker loads icons from the live catalog instead of a stale per-product snapshot.
* Icon preview: consistent placement and centering for bundled and uploaded SVG icons in the configurator.

= 2.24.0 =

* Master Control UX overhaul from License Management downward: status guide, Active Installations X/Y counters, expandable license management panel, Reset License, Clear Installations, per-UUID removal, bulk license/installation tools, confirmation dialogs, copy-to-clipboard for keys/UUIDs, and clearer installation/download history tables.

= 2.23.3 =

* Shortcode embed layout: fixed horizontal clipping of the right configurator panel by using container-based responsive grid sizing (fluid `fr` columns, no rigid pixel minimums), removing embed overflow clipping, and constraining Cloudlift swatch rows to the panel width.

= 2.23.2 =

* Master release metadata now auto-syncs on paxdesign.at when the installed plugin version is newer than stored release metadata, so licensed client installs detect protected updates without a manual Master Control save.
* Shortcode embed preview sizing aligned with the Cloudlift configurator model (wrapper isolation, grid column balance, cloudlift stage max-height parity).
* LED-Beleuchtung is always enabled: “Ja (mit LED-Aufpreis)” is locked on with a green indicator; customers cannot order without LED.

= 2.23.1 =

* Fixed critical fatal error when `options-form.php` was included more than once during Gutenberg/REST page saves (`Cannot redeclare pckz_icon_choice_img()`).

= 2.23.0 =

* Final integration phase for master-control architecture:
  * Added master REST control endpoints (health, licenses, installations, download logs, release validation) secured by `X-PCKZ-Master-Key` and master mode checks.
  * Added protected package download telemetry table and admin visibility of download events.
  * Revocation propagation now blocks linked installations immediately on revoke/disable actions.
* Stripe payment integration (provider-aware checkout):
  * Implemented live Stripe Checkout Session creation flow (one-time payments) with secure provider abstraction.
  * Added Stripe webhook verification (signed payload/tolerance) and paid-session finalization into existing order pipeline.
  * Added Stripe return/cancel handling in frontend redirect flow with compatibility-safe fallback to existing behavior.
  * Added provider-aware checkout labels/messages while preserving existing customer flow.
* Remote export protection hardening:
  * Added strict-vs-fallback control for remote export mode (`licensing_export_remote_strict`).
  * When strict mode is enabled, remote export failures block export; otherwise local export fallback remains available.
* Protected customer distribution workflow:
  * Added `tools/build-customer-protected-package.php` to build domain/license-bound customer ZIPs with signed `LICENSE_BINDING.json`.
* Testing and validation:
  * Added `tests/stripe-provider-smoke.php` (Stripe checkout contract + provider routing).
  * Added `tests/master-control-integration-smoke.php` (master key auth, release validation, revocation propagation).
* Compatibility: core configurator output and export parity remain unchanged under default settings; enforcement/protection modes remain opt-in until explicitly activated.

= 2.22.0 =

* Licensing/security hardening:
  * Master API now validates signed client requests with nonce + timestamp replay protection.
  * Added secure short-lived download tokens for protected update package delivery.
  * Added export authorization endpoint and permit tokens for protected export access.
* Master control/admin expansion:
  * License server UI now supports editing domains, permissions, expiry, max installs, and installation filtering.
  * Installation monitoring now tracks plugin build, WP/PHP version, heartbeat count, integrity hash, and tamper signals.
  * Added optional release policy flag to allow licensed remote export generation.
* Export protection strategy:
  * Added optional remote authorization gate before local export operations.
  * Added optional remote export mode endpoint scaffolding (`/client/export-generate`) for master-controlled generation paths.
* Payment architecture expansion:
  * Added non-breaking payment provider abstraction layer (`PayPal` adapter + `Stripe` scaffolding) for future card/Apple Pay/Google Pay/subscription support.
* Protected distribution workflow:
  * Added tooling for protected ZIP release builds with signed manifest support:
    * `tools/build-protected-release.php`
    * `tools/verify-release-manifest.php`
* Added security helper module for integrity fingerprints + anti-tamper telemetry (`includes/class-pckz-security.php`).
* Compatibility preserved by default: existing configurator preview/export/checkout behavior remains unchanged unless new licensing enforcement/protection toggles are explicitly enabled.

= 2.21.0 =

* Phase 1 licensing/control foundation added with non-breaking defaults:
  * master-control server mode with REST licensing endpoints,
  * license key issuance/revocation and installation monitoring admin panel,
  * client installation UUID + entitlement heartbeat state,
  * protected-feature licensing guard scaffolding for export endpoints,
  * gated update metadata/download architecture for licensed clients.
* Added licensing settings in admin (master URL, key, enforce mode, grace window, install UUID).
* No changes to configurator output, preview behavior, checkout flow, SVG/LBRN2 parity, icon/text rendering, or LightBurn export compatibility when licensing enforcement is disabled (default).

= 2.20.15 =

* Improved WordPress admin pricing/settings interface for clarity and professional UX.
* Added larger, clearer pricing inputs with explicit labels/descriptions for product price, shipping cost, and calculated total preview.
* Added live "Gesamtpreis" preview refresh in admin settings (UI-only helper, no pricing logic changes).
* Upgraded pricing section action buttons styling and visual hierarchy for faster admin recognition.

= 2.20.14 =

* Icon preview sizing/alignment hardening for reported oversized symbols: added post-placement real-bounds correction for left/right icons so actual rendered bounds are fit and centered in the icon reference box.
* Prevents metadata/viewBox mismatches from leaving oversized or asymmetrically positioned icons in preview; keeps left/right placement symmetrical per reference anchors.
* Export parity preserved by using the corrected live Fabric object transforms for production layout/export serialization.

= 2.20.13 =

* Targeted icon-size normalization for remaining oversized symbols reported in QA (including Symbol 1040248, 1087610, 1185226, 1294363, 1296647, 1297939, 154903, 1578289, 159681, 160752, 1911742, 1915356, 2022611, 2027245, 2884303, 2962084, 297607, 308943, 309386, 36417, 41646, 722073).
* Added role+symbol aware outlier profile so only the listed oversized symbols receive extra draw-bounds scaling; already-correct icons are left unchanged.
* Kept preview/export parity by applying the same targeted normalization metadata in preview layout and production geometry mapping.

= 2.20.12 =

* Icon normalization hardening: added coverage-based SVG draw-bounds normalization for symbol icons to prevent oversized outliers (e.g. large imported SVGs) while keeping left/right alignment consistent.
* Preview + export consistency: normalized draw-bounds metadata is now propagated from preview and respected by production geometry mapping so icon scale parity is maintained across configurator and generated files.

= 2.20.11 =

* Desktop icon alignment fix: left/right symbol placement now applies symmetric pair normalization in preview so both icons share the same visual vertical center baseline.
* Same-symbol consistency: when the same icon is selected on both sides, scale is harmonized to keep visual size parity.

= 2.20.10 =

* Mobile UX simplification: removed step wizard navigation and restored a single compact scrolling flow on phones.
* Compact mobile controls: reduced spacing, paddings, section sizing, and button heights for a cleaner premium mobile layout.
* Mobile collapsible selectors: large option groups (especially font selection) now use compact dropdown/collapsible behavior on phones while desktop remains unchanged.
* Keyboard + preview stability: mobile viewport resize handling now avoids keyboard-triggered preview/layout jumps during typing.

= 2.20.9 =

* Mobile configurator flow: added a compact 3-step phone experience (Design → Kundendaten → Zahlung) with clear Weiter/Zurück controls to avoid long mobile scrolling.
* Sticky preview + compact controls on phones: stabilized mobile preview behavior and reduced spacing/control sizes for a cleaner, premium mobile UI while preserving desktop layout.
* Mobile color picker compact mode: color fields now show a selected-color trigger and expandable palette on mobile to keep option sections short and easy to scan.
* Price summary clarity before payment: checkout summary now clearly separates Produktpreis, Versandkosten (from admin setup fee), and Gesamt.

= 2.20.8 =

* Customer tracking redesign: professional order-tracking layout with clear summary cards, status message, progress timeline, and shipping section when carrier/tracking data is available.
* Tracking IDs: customer-facing order IDs now use a non-sequential public format (`PAX-XXXX-XXXX`) while legacy `PCKZ-000123` IDs remain valid for lookup.
* Customer communication: confirmation and status-update emails now include the public tracking ID, direct tracking page URL, and clearer status guidance.
* Theme-safe tracking styles: improved readability and contrast across light/dark themes and custom site styling.

= 2.20.7 =

* Icon sizing normalization: SVG icon placement now uses drawable bounds metadata (not inconsistent outer canvas padding), so different icon files render with consistent visual scale in configurator preview and production export geometry.
* Tracking UX: customer tracking page now shows status badges, professional stage messages, and a clear progress timeline (Zahlung erhalten → In Bearbeitung → Produktion → Versandbereit → Versendet → Abgeschlossen / Storniert).
* Status colors + accessibility: introduced consistent status badge colors for customer and admin views with theme-aware contrast handling for light/dark environments.
* Automatic status emails: when admins change workflow status, customers now receive automatic status update emails (payment, production, shipping, completion, cancellation milestones).

= 2.20.6 =

* PayPal checkout unblock: export-font preload now treats the same-origin `pckzce_font_file` proxy URL as export-safe, so `exportReady` no longer fails prematurely and PayPal button enables after valid form + export validation.

= 2.20.5 =

* Text export hardening: guaranteed OpenType outline generation now falls back to manual glyph path assembly when `font.getPath()` returns empty/unsupported commands for complex scripts; keeps customer text vector outlines visible in final SVG/LBRN2 exports.
* Added persisted-file proof smoke (`tests/export-files-text-presence-smoke.php`) to verify the actual saved SVG/LBRN2 files contain `pckz-text-engrave` path geometry.

= 2.20.4 =

* Export hardening: embed customer text outline paths into `production_vector_svg` (redundant channel) and recover `text_plate_paths` from SVG when POST payload loses text fields; keeps customer text visible in final SVG + LBRN2 exports.

= 2.20.3 =

* Export: production SVG and LBRN2 now share `prepare_export_scene()` so customer text vector paths from `text_plate_paths` appear in both files; non-destructive merge preserves existing text when re-parse fails; save rejects exports missing text when custom text is set.

= 2.20.2 =

* LBRN2 text: non-destructive `text_plate_paths` merge (restore scene text when re-parse fails); resolve fragment from all package/meta keys; detect Fabric matrix vs baked svg-top-left coordinates; validate customer text shapes in LBRN2 before save.

= 2.20.1 =

* LBRN2: always merge `text_plate_paths` into the production scene before writing `.lbrn2` (fixes missing customer text when a cached `production_scene` snapshot had icons/lines only).

= 2.20.0 =

* LightBurn text: split OpenType paths per SVG subpath at parse/merge time; fix text-engrave layer roles in SVG walk.
* PayPal: return to configurator page with success message and public order number (PCKZ-000123); optional configurator page in settings.
* Order tracking shortcode `[pckz_order_tracking]` for customers.
* Admin orders: search by order ID/email, internal notes, design summary on order detail.

= 2.19.0 =

* LightBurn: vector text with many SVG subpaths (e.g. Playfair Display) is split into separate Path shapes so lettering appears in .lbrn2.
* Admin: grouped design detail (customer, order, design, production), font name/category/preview, Orders submenu with workflow statuses.
* Checkout: clear German success message after PayPal return (`pckz_paid=1`).

= 2.18.4 =
* Fix PayPal gate: read LBRN2 URL from design meta (`meta.production_lbrn2_url`), not missing top-level `production` key.
* export_validate runs full pipeline and verifies in-memory LBRN2 XML (`lbrn2_generated`, `lbrn2_length`, etc.).
* save_design fails if LBRN2 file persist fails; persist errors surfaced in export_debug.
* Close `</LightBurnProject>` in generated .lbrn2 XML.

= 2.18.3 =
* Fix LBRN2 text path parse: normalize text_plate_paths (UTF-8, entities, slashes); DOM path extraction; apply group transforms; drop broken PCRE /u mode.
* parse_svg_path_to_verts: comma-separated coordinates and collapsed whitespace.
* Export debug: path_entries, raw_path_verts, path_d_max.

= 2.18.2 =
* Fix production export path: prefer base64 text_plate_paths (avoids WAF/truncation of large script-font paths).
* PayPal blocked until server export_validate passes; PayPal order requires saved LBRN2 file.
* Export errors show live debug ([pckz=version], font, URLs, payload flags, parse probe).
* export_validate uses customer canvas mm from the konfigurator POST.

= 2.18.1 =
* Export preflight AJAX mirrors save/LBRN2 validation; PayPal stays disabled until server confirms OK.
* Rich export errors: [pckz=version], font, font URL, payload sizes, LBRN2 parse probe.
* text_plate_paths base64 POST fallback; SVG top-left path coords; stronger text path merge fallback.

= 2.18.0 =
* Export readiness: block PayPal/checkout until preview, Fabric production SVG, and text_plate_paths are ready.
* Fix merge order: text_plate_paths merge before empty-layer validation; always merge browser OpenType paths.
* Fallback parse for text_plate_paths fragments; escape SVG path attributes; await preview render before Fabric export.

= 2.17.9 =
* Fix vector_text_invalid on script fonts (e.g. Great Vibes): merge browser text_plate_paths even when Fabric SVG has a pckz-text path stub.
* Align text-engrave detection with LBRN2 validation; drop preview text path stubs before OpenType merge.
* Export fragments use pckz-text-engrave layer ids; E2E smoke: OpenType → SVG → LBRN2 for Great Vibes.

= 2.10.0 =
* Add bundled line ornaments Typ 21–71 (`public/assets/lines/type_21.svg` … `type_71.svg`, shipped in repo).
* Line selector lists all registered types from `PCKZ_Ledos_Preview::line_types()` (CDN 1–20 unchanged).
* Repository is the plugin root (single deployable package, no nested zip-only layout).

= 2.9.5 =
* Fix text export placement parity: OpenType paths scaled to Fabric getBoundingRect (no shift/size drift).
* Parity validation uses measured text path bbox; text-engrave layers map to canonical text placement.
* Restore text_plate_paths posting and vector path injection in production SVG export.

= 2.9.2 =
* Hotfix: repair preview-engine.js syntax error (extra closing brace broke preview module load).
* Restores live preview and fixes admin-ajax 422 when export payload could not be generated.

= 2.9.0 =
* Global preview/export parity: unified StaticCanvas toSVG export clones live Fabric objects in canvas z-order (no per-object rebuild).
* Removed export-only line knockout so lines (including type #17) match preview orientation exactly.
* Text LBRN2 paths use Fabric _getLeftOffset/_getTopOffset for OpenType alignment (no mirror/shift drift).
* Production SVG is the recommended LightBurn import (1:1 WYSIWYG); .lbrn2 derived from the same snapshot.
* scene-export-smoke.php regression for line types 1/5/10/17, icons, and text layers.

= 2.8.0 =
* Recalibrate export transforms: single canvas→LightBurn bottom-left mm matrix (no PHP Y double-flip).
* Fix fabric-toSVG coordinate-system handling; text paths use transform groups like icons (no baked drift).
* Text OpenType paths centered on Fabric origin (fixes mirrored/inverted export text).
* Line knockout + type #17 orientation preserved in canvas space.
* text_plate_paths parsed with lightburn-mm-bottom-left metadata.

= 2.7.0 =
* Geometry parity: export uses live Fabric toSVG coords (no clone drift, no double line transform).
* Text vector paths aligned via Fabric _getLeftOffset/_getTopOffset (matches preview centering).
* Line knockout uses canvas-space boolean ops on live Fabric mask SVG (icons/text/lines).
* placement-parity-smoke.php regression for icon/text center alignment.

= 2.6.0 =
* Restore live preview/UI: fix preview-engine.js syntax error that prevented the module from loading (text, icons, lines blank).
* Keep Fabric-first export architecture: canvas-space geometry + single canvas→mm transform (no double text transform).
* Production SVG text uses Fabric calcTransformMatrix in canvas space; LBRN2 text paths remain plate-mm accurate.

= 2.5.0 =
* Fabric canvas is the single source of truth for preview and export (fabric-toSVG pipeline).
* Layout objects use actual Fabric transforms (left/top/scaleX/scaleY/angle) — no separate ref-box placement for export.
* Production SVG: live Fabric objects → toSVG + one canvas→mm matrix (no double Y flip, no reconstructed geometry).
* Vector text paths positioned via Fabric calcTransformMatrix (OpenType paths, not layout mm boxes).

= 2.4.0 =
* Fix mirrored/flipped LightBurn export: all production geometry baked to lightburn-mm-bottom-left mm before LBRN2
* Text vector paths no longer use nested scale(1,-1); icons and knocked lines export as absolute paths (no group transforms)
* SVG parser reads coordinate-system metadata and avoids double Y inversion on browser WYSIWYG exports
* orientation-export-smoke.php regression test for transform/orientation parity

= 1.22.0 =
* Fix duplicate/inverted SVG text: production SVG uses a single layout-placed `<text>` (no Fabric path overlay)
* LBRN2 text: browser `text_plate_paths` vector engrave (ref-box fit); deduped against Text shape when paths exist
* Line knockout includes text mask — lines no longer cut through customer text
* Linien export: professional red (`#FF0000`) preserved from `line_color` selection through SVG and LBRN2

= 1.21.0 =
* Exact preview placement: export uses Cloudlift layer refs (placeInRef targets), not Fabric bounding boxes
* Lines export as separate `pckz-line-N` groups — individually selectable in LightBurn
* Text: SVG `<text>` + Fabric vector paths (`pckz-text-paths`) + guaranteed LBRN2 Text layer from layout
* Icon knockout masks use actual icon vector geometry
* LBRN2 shape names preserve layer ids (pckz-line-0, pckz-icon-left, …)

= 1.20.0 =
* Fix LBRN2: text always exported from saved layout (customer font + mm size), not lost in SVG parse
* LBRN2 icons/lines use layout mm + uniform path mapping (matches preview positions)
* Knocked decorative lines imported from browser `pckz-lines` group for correct icon cutouts
* SVG line knockout uses actual icon vector masks (not just rectangular layout boxes)
* LightBurn Text shapes keep the ordered font family (e.g. Russo One)

= 1.19.0 =
* Root WYSIWYG fix: export built from saved layout mm boxes + uniform center-fit (same as preview placeInRef)
* Removes Fabric toSVG / canvas-pixel matrix pipeline that misaligned icons, lines, text, and spacing in LightBurn
* Server always rebuilds production SVG/LBRN2 from layout when mm objects exist (authoritative, matches preview)
* Line knockout runs in plate mm space using icon mask boxes from layout
* Re-save designs after update to regenerate production files

= 1.18.0 =
* Fix LightBurn “outside plate” export: viewBox is always `0 0 plate_mm` (not canvas pixel offsets)
* Live Fabric `toSVG()` at exact preview positions, single `matrix()` maps fitted photo → mm plate
* Removes StaticCanvas/viewBox-offset export that broke LightBurn and misaligned server guide overlays
* LBRN2 parses same matrix; automated coordinate verification tests added

= 1.17.0 =
* Definitive WYSIWYG export: Fabric StaticCanvas + native toSVG with viewBox = fitted product photo (canvas px → mm)
* Removes plate-space offset / scale rebuild that caused spacing and alignment drift
* LBRN2 parser applies SVG viewBox → mm transform (fixes LightBurn position when viewBox has offset)
* Per-object Fabric ids preserved (`pckz-icon-left`, `pckz-main-text`, …) for separate LightBurn selection
* Save waits for render frame + fresh background bounds before export snapshot

= 1.16.0 =
* Fix WYSIWYG export: Fabric toSVG in plate space (fitted photo origin) with uniform mm scale — no clone-at-origin matrix rebuild
* Each icon, line set, and text exports as its own `pckz-*` SVG group (individually selectable in LightBurn)
* Line knockout uses icon geometry in the same plate coordinates as the preview (not layout mm boxes)
* Re-save designs after update to regenerate production_vector_svg

= 1.15.0 =
* Fix export position drift: per-object Fabric calcTransformMatrix mapped to mm (no global scale wrapper)
* Icon knockout uses layout mm boxes (same coordinates as saved production layout)
* Lines/icons/text export in native mm viewBox for 1:1 scene fidelity with configurator

= 1.14.0 =
* SVG export: boolean knockout subtracts icon regions from decorative lines (Clipper.js)
* Lines no longer penetrate through Instagram/TikTok icons in LightBurn — geometry matches preview masking
* Layered export: knocked-out lines, then icons, then text (correct paint order + baked holes)

= 1.13.0 =
* True Fabric WYSIWYG: production SVG is direct canvas toSVG (no asset rebuild or per-box rescale)
* Production SVG file passes through browser snapshot unchanged (guides only added server-side)
* LBRN2 parses the same WYSIWYG SVG 1:1 (ellipses as LightBurn Ellipse shapes)
* DXF and legacy .lbrn exports disabled until pipeline is stable
* `production_vector_svg` posted separately on save to avoid truncation

= 1.12.0 =
* Unified WYSIWYG export pipeline: one master vector SVG from the Fabric preview drives SVG, LBRN, LBRN2, and DXF
* Browser captures `production_vector_svg` on save (exact canvas artwork: thick lines, icons, text)
* New `PCKZ_Production_Scene` parses master SVG once; all exporters share the same flattened mm geometry
* Preserves filled shapes (line ellipses, compound paths) — no manual stripe reconstruction or centerline-only export

= 1.11.0 =
* Fix LightBurn .lbrn2 empty canvas: use native condensed VertList/PrimList strings (not invalid Vert/Prim XML children)
* Legacy .lbrn export uses direct V/P path elements for maximum LightBurn compatibility
* Fix production SVG/DXF/LBRN geometry: uniform aspect-ratio scaling into mm boxes (no stretched icons or line bands)
* Line overlays: embed source SVG in production SVG (matches preview); flattened paths use same uniform scale for LBRN/DXF
* Fix undefined selections variable in production SVG vector export

= 1.10.1 =
* Fix LightBurn export showing only guide zones: embed SVG vector data from browser (svg_source) on save
* Server fallback: rebuild text/icons/lines/backgrounds from Cloudlift layer refs when layout objects missing
* LightBurn engrave layer validation requires real artwork shapes (not guides-only)

= 1.10.0 =
* Production export final: unified vector pipeline for .lbrn, .lbrn2, SVG, and DXF
* Line overlays (Type 1–20) export ellipse-based Cloudlift artwork as real paths
* Icons: split multi-subpath SVGs; icon background layers included in layout
* SVG/DXF/LightBurn now share identical mm geometry (no broken embed/gradient gaps)

= 1.9.1 =
* Fix production export empty in LightBurn: correct SVG path mm mapping (was scaled off-canvas by viewBox)
* Parse cubic/quadratic SVG paths (icons, lines) for .lbrn, .lbrn2, and DXF
* LightBurn XML uses native V/P vertices; also writes .lbrn (legacy) for reliable open
* Production SVG: mm viewBox without invalid mm suffix; improved asset URL resolution
* Admin: primary download is .lbrn; smoke test in tests/export-smoke.py

= 1.9.0 =
* Full manufacturing package: LightBurn project (.lbrn2), production SVG, optional DXF (mm, bottom-left origin, safe/strip zones)
* Admin order and design pages: direct download buttons for .lbrn2, SVG, DXF, and technical JSON
* Production files auto-generated on every design save and order (regenerated when missing on admin view)

= 1.8.0 =
* Production SVG export for LightBurn (mm canvas, text, icons, lines, safe/strip guides)
* Download Production SVG button in admin orders and saved designs (JSON retained as technical data)

= 1.7.2 =
* Real SVG vector rendering on canvas (Fabric loadSVGFromURL) — original Cloudlift paths, no flat tint raster
* Icon/line thumbnails use CDN preview URLs; bundled symbols mapped to Cloudlift assets
* Six additional production fonts (Bebas Neue, Anton, Orbitron, Rajdhani, Exo 2, Audiowide, Black Ops One)

= 1.7.1 =
* Fix Linien / icon dropdown clipped inside options panel (overflow + fixed-position list)
* Fix icon thumbnails and canvas preview visibility (dark thumb background, black SVG base for light colors)
* Fix white icons invisible on white UI (invert filter for CDN SVGs, url_dark/url_light catalog)

= 1.7.0 =
* Full LightBurn production package in WordPress admin (orders + saved designs): exact mm/px coordinates, SVG refs, safe/strip zones, downloadable JSON
* Cloudlift-style visual dropdowns for symbols, lines, and icons (thumbnail + label per option)
* Order metabox links to full production detail page

= 1.6.0 =
* Fix fatal JS error (`global is not defined`) — preview engine loads correctly
* Manufacturing export: design px (3651×2132) + mm coordinates, STD safe zones, LightBurn-ready layout
* Stable compact UI (dropdown icons, wrapped color chips, visible frame preview)

= 1.5.1 =
* Fix product preview visibility (layout sizing, fallback image, canvas positioning)
* Compact options UI: icon/line dropdowns with preview, wrapped color chips (no horizontal overflow)
* Removed overflowing swatch rows and horizontal scroll sliders from options panel

= 1.5.0 =
* Full Ledos / Cloudlift configurator rebuild for WordPress (preview engine, options, Splide gallery, Tippy tooltips, Dawn swatch slider, LightBurn production export)

= 1.4.3 =
* Dawn-style horizontal slider for icon/color swatch rows (scroll-snap + prev/next on mobile)

= 1.4.2 =
* Cloudlift options panel CSS (`.cl-po--*`) with monochrome theme
* Tippy.js hover previews for icon/line swatches; mobile sticky preview classes

= 1.4.1 =
* Splide thumbnail carousel for day/night preview (Ledos gallery reference)
* Monochrome Splide overrides; syncs with LED preview option radios

= 1.4.0 =
* Cloudlift-compatible preview engine (3651×2132 design space → fitted product photo)
* LED on/off, separate day/night preview for LED orders, line types 1–20, left/right icon colors
* Conditional option fields (show_when), Shopify CDN overlays + bundled icon fallbacks
* Monochrome Ledos/Dawn product layout, mobile sticky preview, cart button loading state
* Production layout exports Cloudlift mm positions for LightBurn workflow

= 1.3.0 =
* Ledos strip layout: text + icons in lower black bar of frame
* Symbol links/rechts with social icon badges (Instagram, Telegram, etc.)
* German customer UI, Linien option, Symbolfarbe white/black
* Strip zone mm config + full order production data

= 1.2.0 =
* Premium monochrome UI aligned with Ledos product page layout
* Product preview images, gallery thumbs, on-product text clipping
* Production layout data (mm positions) saved for LightBurn workflow
* Cache-busting on CSS/JS via file modification time

= 1.1.1 =
* [product_creator] shortcode works without id (default product)
* INSTALL.md and security index files
* Settings: choose default creator product for embedding

= 1.1.0 =
* Shop-style Ledos layout (preview + options column)
* Configurable customer option fields
* Order production summary for admin / email

= 1.0.0 =
* Initial release
