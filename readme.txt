=== PCKZ Canonical Engine ===
Contributors: productcreatorkz
Tags: product customizer, woocommerce, laser, engraving, print, configurator
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 2.23.1
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
