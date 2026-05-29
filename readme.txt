=== PCKZ Canonical Engine ===
Contributors: productcreatorkz
Tags: product customizer, woocommerce, laser, engraving, print, configurator
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 2.10.0
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
