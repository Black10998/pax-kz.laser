# Customer matte-red line models (type_102–type_111)

Ten bundled lines from the **reference sheet** (designs 1–10). Label digits are not part of the SVG artwork.

## Shipped mapping

| Reference | Slug      | Motif                         |
|-----------|-----------|-------------------------------|
| 1         | type_102  | Angular / shard               |
| 2         | type_103  | Tech / hexagonal              |
| 3         | type_104  | Ornate / filigree scroll      |
| 4         | type_105  | Futuristic bracket + dot      |
| 5         | type_106  | Flame / wisps                 |
| 6         | type_107  | Star / compass                |
| 7         | type_108  | Tribal / knotwork             |
| 8         | type_109  | Heavy arrowhead / chevrons    |
| 9         | type_110  | Molecular hex cluster         |
| 10        | type_111  | Crescent + sparkle            |

Output files: `public/assets/lines/type_102.svg` … `type_111.svg` (950×35, `#B22222`, stretchable center runners).

## Replace with native LightBurn vectors (recommended)

Place your exact source here:

```
import/vector-line-customer-red/svg.lbrn2
```

From Windows Downloads:

```
c:\Users\43681\Downloads\svg.lbrn2
```

Then run:

```bash
bash tools/import-vector-line-customer-lbrn2.sh
```

## Illustrator AI fallback

```
import/vector-line-customer-red/svg for line vicor costumer.ai
bash tools/import-vector-line-customer-red.sh
```

## Notes

- Uses `tools/convert-lightburn-ai-to-svg.py` (same pipeline as fire/batch imports).
- Catalog auto-sets `preserve_colors` for chromatic bundled SVGs (line_color does not recolor them).
- `PCKZ_Ledos_Preview::BUNDLED_LINE_TYPE_MAX` is `111`.
