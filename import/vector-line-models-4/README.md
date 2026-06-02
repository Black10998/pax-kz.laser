# Vector line models batch 4 (type_102–type_121)

Matte-red decorative line models from the reference sheet (10 rows × 2 column variants).

## Generate

```bash
python3 tools/generate-line-models-102-121.py
```

Output: `public/assets/lines/type_102.svg` … `type_121.svg` (950×35, fill/stroke `#B22222`).

After adding SVGs, set `PCKZ_Ledos_Preview::BUNDLED_LINE_TYPE_MAX` to `121` in `includes/class-pckz-ledos-preview.php`.

## Mapping (reference row → slug)

| Row | Motif              | Left col | Right col |
|-----|--------------------|----------|-----------|
| 1   | Jagged / shard     | type_102 | type_103  |
| 2   | Tech / hex circuit | type_104 | type_105  |
| 3   | Filigree scroll    | type_106 | type_107  |
| 4   | Modern bracket+dot | type_108 | type_109  |
| 5   | Organic flame      | type_110 | type_111  |
| 6   | Star + diamonds    | type_112 | type_113  |
| 7   | Tribal loops       | type_114 | type_115  |
| 8   | Fletching          | type_116 | type_117  |
| 9   | Hex cluster        | type_118 | type_119  |
| 10  | Crescent + star    | type_120 | type_121  |

Center runners use the same span as CDN type_1–20: `M9.5 … L352` and `M598 … L940.5`.
