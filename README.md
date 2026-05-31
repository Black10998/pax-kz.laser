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
- **Typ 21–71:** Bundled ornaments in `public/assets/lines/type_21.svg` … `type_71.svg` (included in this repository).

Source artwork from LightBurn `.ai` exports is archived under `import/line-models/`. To regenerate SVGs after editing sources:

```bash
python3 tools/convert-lightburn-ai-to-svg.py import/line-models -o public/assets/lines
```

## Architecture

See [readme.txt](readme.txt) changelog and the in-plugin export pipeline (`Fabric → canonical scene → production SVG → LBRN2`).

### Master-control release tooling

Protected distribution workflow helpers:

```bash
php tools/build-protected-release.php --version=2.22.0 --build=2.22.0.custom --output=dist
php tools/build-customer-protected-package.php --source=dist/pckz-canonical-engine-2.22.0-protected.zip --license=PCKZCE-XXXX --domain=client.example.com --output=dist/customers
php tools/verify-release-manifest.php dist/unpacked/RELEASE_MANIFEST.json
```

Set `PCKZCE_RELEASE_SIGNING_KEY` in your environment to sign and verify manifests.

## Development tests

```bash
php tests/scene-export-smoke.php
cd tests/node && npm install && npm test
```
