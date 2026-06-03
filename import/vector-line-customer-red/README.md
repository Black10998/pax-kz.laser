# Customer matte-red line models (type_102–type_111)

**Do not use procedural or hand-drawn substitutes.** Import only from your source artwork.

## Required source (pick one)

### A) Reference sheet image (from your numbered PNG/JPG)

Export the sheet you sent in chat (designs **1–10** only) as a lossless PNG with numbers visible; the importer strips digits automatically.

```
import/vector-line-customer-red/reference-sheet.png
```

```bash
bash tools/import-vector-line-customer-reference-image.sh
```

### B) LightBurn native vectors (preferred if you have the project)

```
import/vector-line-customer-red/svg.lbrn2
```

```bash
bash tools/import-vector-line-customer-lbrn2.sh
```

### C) Illustrator AI export

```
import/vector-line-customer-red/svg for line vicor costumer.ai
```

```bash
bash tools/import-vector-line-customer-red.sh
```

## Output

`public/assets/lines/type_102.svg` … `type_111.svg` — 950×35, matte red `#B22222`, stretchable center runners like bundled types 1–20 / 21–40, `preserve_colors` in catalog.

Set `PCKZ_Ledos_Preview::BUNDLED_LINE_TYPE_MAX` to `111` after a successful import.

## Note for Cloud / CI builds

The reference image attached in Cursor chat is **not** stored on the build VM. You must commit `reference-sheet.png` or `svg.lbrn2` to this folder before import can run in CI or release builds.
