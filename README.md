# pax-kz.laser — PCKZ Canonical Engine

WordPress plugin for Austrian license plate frame customization (Fabric.js preview) with **LightBurn-ready** production export (SVG + `.lbrn2`).

## Deploy (single package)

This repository **is** the plugin. Install it as:

`wp-content/plugins/pckz-canonical-engine/`

1. Clone or download this repository.
2. Copy the **repository root** (files `pckz-canonical-engine.php`, `includes/`, `public/`, etc.) into `wp-content/plugins/pckz-canonical-engine/`.
3. Activate **PCKZ Canonical Engine** in WordPress.
4. See [INSTALL.md](INSTALL.md) for asset load order and troubleshooting.

Do **not** use legacy zip-only layouts or old `product-creator-kz` copies.

## Line models (Typ 1–38)

- **Typ 1–20:** Cloudlift CDN SVGs (unchanged).
- **Typ 21–38:** Bundled ornaments in `public/assets/lines/type_21.svg` … `type_38.svg`.

To import your prepared artwork (from Illustrator or exported SVG):

1. Place files in `import/line-models/` (e.g. `model 23.svg` or `model 23.ai`).
2. Run: `bash tools/import-line-models.sh`
3. Confirm files exist under `public/assets/lines/`.

Only files present on disk are shown in the customer **Linien** selector.

## Architecture

See [readme.txt](readme.txt) changelog and the in-plugin export pipeline (`Fabric → canonical scene → production SVG → LBRN2`).

## Development tests

```bash
php tests/scene-export-smoke.php
cd tests/node && npm install && npm test
```
