# Customer matte-red line models (type_102–type_111)

Place the **exact** LightBurn source here (no procedural redesign):

```
import/vector-line-customer-red/svg.lbrn2
```

From Windows Downloads:

```
c:\Users\43681\Downloads\svg.lbrn2
```

## Import (preferred — native LightBurn vectors)

```bash
bash tools/import-vector-line-customer-lbrn2.sh
```

## Import (Illustrator AI fallback)

```
import/vector-line-customer-red/svg for line vicor costumer.ai
bash tools/import-vector-line-customer-red.sh
```

Output: `public/assets/lines/type_102.svg` … `type_111.svg` (950×35, `#B22222`, Cloudlift center runners).

Then set `PCKZ_Ledos_Preview::BUNDLED_LINE_TYPE_MAX` to `111` and clear `pckz_line_permanently_deleted_slugs` entries for type_102–type_111 (import script does this via new files + upgrade purge).

## Notes

- Uses `tools/convert-lightburn-ai-to-svg.py` (same pipeline as fire/batch-2 imports).
- Does **not** use procedural generators; geometry comes only from your artwork file.
- Catalog auto-sets `preserve_colors` for chromatic bundled SVGs.
