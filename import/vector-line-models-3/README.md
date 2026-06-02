# Vector line models batch 3 (type_92–type_101)

Reference sheet models **1–10** (decorative end caps + center runners).

## Generate

```bash
python3 tools/generate-line-models-92-101.py
```

Output: `public/assets/lines/type_92.svg` … `type_101.svg` (950×35, white fill).

## Register

After adding SVGs, set `PCKZ_Ledos_Preview::BUNDLED_LINE_TYPE_MAX` to `101` in `includes/class-pckz-ledos-preview.php`.

## Mapping

| Reference | Slug     | Motif              |
|-----------|----------|--------------------|
| 1         | type_92  | Fleur-de-lis       |
| 2         | type_93  | Leafy branch       |
| 3         | type_94  | Scroll + arrow tip |
| 4         | type_95  | Lotus + end dot  |
| 5         | type_96  | Filigree + diamond |
| 6         | type_97  | Tribal geometric   |
| 7         | type_98  | Fan + hub          |
| 8         | type_99  | Chevrons           |
| 9         | type_100 | Celtic loop        |
| 10        | type_101 | Fletching          |

Types **21–40** remain retired (hidden in admin/customer UI). Types **82–91** are an earlier batch from the same design family.
