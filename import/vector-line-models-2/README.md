# Decorative line models batch 2 (types 82–91)

Place the LightBurn export here (10 models on one sheet, 2×5 layout), for example:

`vector line models 2.ai`

Then from the repo root:

```bash
chmod +x tools/import-vector-line-models-batch2.sh
./tools/import-vector-line-models-batch2.sh
```

After import, set `BUNDLED_LINE_TYPE_MAX` to `91` in `includes/class-pckz-ledos-preview.php` and run smoke tests.
