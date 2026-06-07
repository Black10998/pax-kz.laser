# Naruto anime eye line models (type_102–type_111)

**Do not use procedural or hand-drawn substitutes.** Import only from your reference sheet artwork.

## Required source

Preferred source is the LightBurn project (embedded 2×5 sheet thumbnail):

```
import/naruto-eye-models/10 line.vector naroto aye.lbt
```

The import script will extract the embedded thumbnail into:

```
import/naruto-eye-models/reference-sheet.png
```

The sheet must contain these 10 models only (reading order left→right, top→bottom):

1. Sharingan (3 Tomoe)
2. Mangekyo Sharingan
3. Rinnegan
4. Mangekyo (Itachi)
5. Rinnegan (Tomoe)
6. Byakugan
7. Sage Mode
8. Tenseigan
9. Ketsuryugan
10. Eien no Mangekyo Sharingan

## Import

```bash
bash tools/import-naruto-eye-line-models.sh
```

This color-traces each grid cell with vtracer (labels/numbers stripped), preserves native colors, and writes:

`public/assets/lines/type_102.svg` … `type_111.svg` — 950×35, `preserve_colors` in catalog.

Labels are defined in `includes/bundled-line-labels.php`.

## Note for Cloud / CI builds

Reference images attached in Cursor chat are **not** stored on the build VM. Commit the `.lbt` (or `reference-sheet.png`) to this folder before import can run in CI or release builds.
