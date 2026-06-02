# Vector fire line models (types 72–81)

Place the LightBurn export here:

`vector fire model svg.ai`

Then from the repo root:

```bash
chmod +x tools/import-vector-fire-models.sh
./tools/import-vector-fire-models.sh
```

The converter splits the sheet into 10 ornaments, removes label/number glyphs, and writes `public/assets/lines/type_72.svg` … `type_81.svg`.

After import, update `BUNDLED_LINE_TYPE_MAX` in `includes/class-pckz-ledos-preview.php` to `81` and run smoke tests.
