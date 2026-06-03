# Customer matte-red line models (type_102–type_111)

Place the **exact** Illustrator/LightBurn source file here (no redesign in code):

```
import/vector-line-customer-red/svg for line vicor costumer.ai
```

From Windows Downloads:

```
c:\Users\43681\Downloads\svg for line vicor costumer.ai
```

## Import

```bash
bash tools/import-vector-line-customer-red.sh
```

This converts native vector paths from the `.ai` file into `public/assets/lines/type_102.svg` … `type_111.svg` (950×35, matte red `#B22222`, Cloudlift center runners).

Then set `PCKZ_Ledos_Preview::BUNDLED_LINE_TYPE_MAX` to `111`.

## Notes

- Uses `tools/convert-lightburn-ai-to-svg.py` (same pipeline as fire/batch-2 imports).
- Does **not** use procedural generators; geometry comes only from your artwork file.
- Catalog auto-sets `preserve_colors` for chromatic bundled SVGs.
