# PCKZ Canonical Engine — Installation

## Clean install (required)

The v2.1.0 build uses a **new plugin slug** and must not coexist with old `product-creator-kz` copies.

1. Deactivate and **delete** any existing Product Creator / PCKZ plugin from WordPress admin.
2. Remove these folders from `wp-content/plugins/` if they still exist:
   - `product-creator-kz/`
3. Clear all caches (WordPress, CDN, minify plugins).
4. Hard-refresh the browser (Ctrl+Shift+R / Cmd+Shift+R).
5. Copy this repository folder to `wp-content/plugins/pckz-canonical-engine/` (or zip the folder and upload via **Plugins → Add New → Upload**).
6. Activate **PCKZ Canonical Engine** (version **2.9.4**).

The plugin auto-deactivates legacy `product-creator-kz/product-creator-kz.php` on activation.

## Verify correct assets loaded

Open DevTools → Network → filter `js`. Load order must be:

1. `bootstrap.js` — sets `window.PCKZCE_GLOBAL = window`
2. `vendor/fabric.min.js` (patched)
3. `fabric-patch.js`, `canvas-safe.js`
4. preview / canonical modules
5. inline `var pckzceConfig = {...}` (before creator.js)
6. `creator.js`

Console check: `window.PCKZCE_GLOBAL === window` must be `true`.

## AJAX endpoints (new slug)

| Action | Handler |
|--------|---------|
| `pckzce_save_design` | Save + canonical export |
| `pckzce_export_design` | PNG export |
| `pckzce_upload_image` | Customer image upload |
| `pckzce_add_to_cart` | WooCommerce cart |

Localized JS global: `pckzceConfig` (not `pckzCreator`).

## Line models (Typ 21–91)

Bundled divider ornaments are included as `public/assets/lines/type_21.svg` … `type_91.svg`.  
They appear in the customer **Linien** selector automatically (types **1–20** still use Cloudlift CDN).

To regenerate SVGs after editing LightBurn sources in `import/line-models/`:

```bash
python3 tools/convert-lightburn-ai-to-svg.py import/line-models -o public/assets/lines
```

## Requirements

- WordPress 6.0+
- PHP 7.4+ with DOM extension
- WooCommerce (optional, for cart integration)

## Troubleshooting

### CanvasTextBaseline 'alphabetical' warning

Caused by Fabric.js 5.3.1 CDN bug. This build ships a **patched local Fabric** — if you still see the warning, an old cached Fabric script is loading. Clear CDN/minify cache.

### requestRenderAll undefined

Caused by calling render before canvas init. Fixed in v2.1.0 via `PCKZCECanvas.safeRender()`. If it persists, confirm `canvas-safe.js` loads and no old `creator.js` is cached.

### admin-ajax.php HTTP 500 on save

v2.1.2 fixes a PHP fatal in `PCKZ_Production_Scene::attach_layer_bbox_mm()` (method was called but missing). Export errors now return JSON with `exception`, `file`, and `line` instead of a blank 500. A **422** response means validation/parity failed (expected); check `validation.errors` in the JSON body.

### HTTP 422 on save (validation failed)

This is expected when export validation fails (not a crash). Open DevTools → Network → `admin-ajax.php` → Response. Look for `data.validation.errors` or `data.errors` — each entry includes `object_id`, `role`, `code`, and `message`.
